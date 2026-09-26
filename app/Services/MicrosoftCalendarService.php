<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\IntegrationJob;
use Illuminate\Support\Facades\Http;
use Throwable;

class MicrosoftCalendarService
{
    public function syncBooking(Booking $booking): void
    {
        $calendarId = $booking->resource->calendar_id
            ?: config('services.microsoft.'.($booking->resource->slug === 'studio-a' ? 'studio_calendar' : 'soundtunnel_calendar'));

        if (!$this->configured() || !$calendarId) {
            $this->recordFailure($booking, 'Microsoft 365 credentials or resource calendar are not configured.');
            return;
        }

        try {
            $token = Http::asForm()->post(
                'https://login.microsoftonline.com/'.config('services.microsoft.tenant_id').'/oauth2/v2.0/token',
                [
                    'client_id' => config('services.microsoft.client_id'),
                    'client_secret' => config('services.microsoft.client_secret'),
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ]
            )->throw()->json('access_token');

            $payload = [
                'subject' => $booking->resource->name.' — '.$booking->customer_name.' ['.$booking->reference.']',
                'body' => ['contentType' => 'text', 'content' => 'MWCN booking '.$booking->reference],
                'start' => ['dateTime' => $booking->starts_at->setTimezone($booking->resource->timezone)->format('Y-m-d\TH:i:s'), 'timeZone' => $booking->resource->timezone],
                'end' => ['dateTime' => $booking->ends_at->setTimezone($booking->resource->timezone)->format('Y-m-d\TH:i:s'), 'timeZone' => $booking->resource->timezone],
                'location' => ['displayName' => $booking->resource->name],
            ];
            $base = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($calendarId).'/calendar/events';
            $response = $booking->calendar_event_id
                ? Http::withToken($token)->patch($base.'/'.$booking->calendar_event_id, $payload)
                : Http::withToken($token)->post($base, $payload);
            $response->throw();
            $booking->update(['calendar_event_id' => $response->json('id') ?: $booking->calendar_event_id, 'calendar_sync_status' => 'synced', 'calendar_sync_error' => null]);
        } catch (Throwable $exception) {
            $this->recordFailure($booking, $exception->getMessage());
        }
    }

    public function cancelBooking(Booking $booking): void
    {
        if (!$booking->calendar_event_id || !$this->configured()) {
            return;
        }
        $calendarId = $booking->resource->calendar_id
            ?: config('services.microsoft.'.($booking->resource->slug === 'studio-a' ? 'studio_calendar' : 'soundtunnel_calendar'));
        if (!$calendarId) {
            return;
        }
        try {
            $token = Http::asForm()->post(
                'https://login.microsoftonline.com/'.config('services.microsoft.tenant_id').'/oauth2/v2.0/token',
                ['client_id' => config('services.microsoft.client_id'), 'client_secret' => config('services.microsoft.client_secret'), 'scope' => 'https://graph.microsoft.com/.default', 'grant_type' => 'client_credentials']
            )->throw()->json('access_token');
            Http::withToken($token)->delete(
                'https://graph.microsoft.com/v1.0/users/'.rawurlencode($calendarId).'/calendar/events/'.$booking->calendar_event_id
            )->throw();
            $booking->update(['calendar_sync_status' => 'cancelled', 'calendar_sync_error' => null]);
        } catch (Throwable $exception) {
            $this->recordFailure($booking, $exception->getMessage(), 'cancel_booking');
        }
    }

    private function configured(): bool
    {
        return (bool) config('services.microsoft.tenant_id')
            && (bool) config('services.microsoft.client_id')
            && (bool) config('services.microsoft.client_secret');
    }

    private function recordFailure(Booking $booking, string $message, string $action = 'sync_booking'): void
    {
        $booking->update(['calendar_sync_status' => 'failed', 'calendar_sync_error' => $message]);
        IntegrationJob::updateOrCreate(
            ['integration' => 'microsoft_graph', 'action' => $action, 'subject_type' => Booking::class, 'subject_id' => $booking->id],
            ['status' => 'failed', 'attempts' => 1, 'next_attempt_at' => now()->addMinutes(10), 'last_error' => $message]
        );
    }
}
