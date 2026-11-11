<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Event;
use App\Models\IntegrationJob;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class MicrosoftCalendarService
{
    public function syncBooking(Booking $booking): void
    {
        $calendarId = $booking->resource->calendar_id
            ?: config('services.microsoft.'.($booking->resource->slug === 'studio-a' ? 'studio_calendar' : 'soundtunnel_calendar'));

        if (! $this->configured() || ! $calendarId) {
            $this->recordFailure($booking, 'Microsoft 365 credentials or resource calendar are not configured.', 'sync_booking');

            return;
        }

        try {
            $payload = [
                'subject' => $booking->resource->name.' — '.$booking->customer_name.' ['.$booking->reference.']',
                'body' => ['contentType' => 'text', 'content' => $this->bookingDetails($booking)],
                'start' => ['dateTime' => $booking->starts_at->setTimezone($booking->resource->timezone)->format('Y-m-d\TH:i:s'), 'timeZone' => $booking->resource->timezone],
                'end' => ['dateTime' => $booking->ends_at->setTimezone($booking->resource->timezone)->format('Y-m-d\TH:i:s'), 'timeZone' => $booking->resource->timezone],
                'location' => ['displayName' => $booking->resource->name],
            ];
            $response = $this->save($calendarId, $booking->calendar_event_id, $payload);
            $booking->update(['calendar_event_id' => $response->json('id') ?: $booking->calendar_event_id, 'calendar_sync_status' => 'synced', 'calendar_sync_error' => null]);
        } catch (Throwable $exception) {
            $this->recordFailure($booking, $exception->getMessage(), 'sync_booking');
        }
    }

    public function cancelBooking(Booking $booking): void
    {
        if (! $booking->calendar_event_id || ! $this->configured()) {
            return;
        }
        $calendarId = $booking->resource->calendar_id
            ?: config('services.microsoft.'.($booking->resource->slug === 'studio-a' ? 'studio_calendar' : 'soundtunnel_calendar'));
        if (! $calendarId) {
            return;
        }
        try {
            $this->delete($calendarId, $booking->calendar_event_id);
            $booking->update(['calendar_sync_status' => 'cancelled', 'calendar_sync_error' => null]);
        } catch (Throwable $exception) {
            $this->recordFailure($booking, $exception->getMessage(), 'cancel_booking');
        }
    }

    public function syncEvent(Event $event): void
    {
        $event->loadMissing('organizer', 'resource');
        $calendarId = $this->eventCalendarId($event);

        if (! $this->configured() || ! $calendarId) {
            $this->recordFailure($event, 'Microsoft 365 credentials or an events calendar are not configured.', 'sync_event');

            return;
        }

        try {
            $timezone = $event->resource?->timezone ?: 'America/Phoenix';
            $payload = [
                'subject' => $event->title.' ['.$event->reference.']',
                'body' => ['contentType' => 'text', 'content' => $this->eventDetails($event)],
                'start' => ['dateTime' => $event->starts_at->format('Y-m-d\TH:i:s'), 'timeZone' => $timezone],
                'end' => ['dateTime' => $event->ends_at->format('Y-m-d\TH:i:s'), 'timeZone' => $timezone],
                'location' => ['displayName' => $this->eventLocation($event)],
            ];
            $response = $this->save($calendarId, $event->calendar_event_id, $payload);
            $event->update(['calendar_event_id' => $response->json('id') ?: $event->calendar_event_id, 'calendar_sync_status' => 'synced', 'calendar_sync_error' => null]);
        } catch (Throwable $exception) {
            $this->recordFailure($event, $exception->getMessage(), 'sync_event');
        }
    }

    public function cancelEvent(Event $event): void
    {
        if (! $event->calendar_event_id || ! $this->configured()) {
            return;
        }
        $calendarId = $this->eventCalendarId($event);
        if (! $calendarId) {
            return;
        }
        try {
            $this->delete($calendarId, $event->calendar_event_id);
            $event->update(['calendar_sync_status' => 'cancelled', 'calendar_sync_error' => null]);
        } catch (Throwable $exception) {
            $this->recordFailure($event, $exception->getMessage(), 'cancel_event');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function save(string $calendarId, ?string $calendarEventId, array $payload): Response
    {
        $base = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($calendarId).'/calendar/events';
        $request = Http::withToken($this->accessToken())->connectTimeout(5)->timeout(15);

        $response = $calendarEventId
            ? $request->patch($base.'/'.$calendarEventId, $payload)
            : $request->post($base, $payload);

        return $response->throw();
    }

    private function delete(string $calendarId, string $calendarEventId): void
    {
        Http::withToken($this->accessToken())
            ->connectTimeout(5)
            ->timeout(15)
            ->delete('https://graph.microsoft.com/v1.0/users/'.rawurlencode($calendarId).'/calendar/events/'.$calendarEventId)
            ->throw();
    }

    private function accessToken(): string
    {
        $token = Http::asForm()
            ->connectTimeout(5)
            ->timeout(15)
            ->post(
                'https://login.microsoftonline.com/'.config('services.microsoft.tenant_id').'/oauth2/v2.0/token',
                [
                    'client_id' => config('services.microsoft.client_id'),
                    'client_secret' => config('services.microsoft.client_secret'),
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ]
            )
            ->throw()
            ->json('access_token');

        return (string) $token;
    }

    private function bookingDetails(Booking $booking): string
    {
        return implode("\n", array_filter([
            'MWCN booking '.$booking->reference,
            $booking->customer_name,
            $booking->customer_email,
            $booking->customer_phone,
            $booking->notes,
        ], fn ($line) => $line !== null && $line !== ''));
    }

    private function eventDetails(Event $event): string
    {
        $lines = array_filter([
            $event->description,
            'Reference: '.$event->reference,
            'Category: '.($event->category ?: 'General'),
            'Starts: '.$event->starts_at->format('m/d/Y H:i'),
            'Ends: '.$event->ends_at->format('m/d/Y H:i'),
            $event->doors_at ? 'Doors: '.$event->doors_at->format('m/d/Y H:i') : null,
            'Location: '.$this->eventLocation($event),
            $event->venue_address ? 'Address: '.$event->venue_address : null,
            $event->virtual_access ? 'Access: '.$event->virtual_access : null,
            'Capacity: '.$event->capacity,
            'Organizer: '.($event->organizer?->name ?: 'MWCN'),
            $event->organizer?->email ? 'Organizer email: '.$event->organizer->email : null,
            $event->slug ? 'Page: '.url('/events/'.$event->slug) : null,
        ], fn ($line) => $line !== null && $line !== '');

        return mb_substr(implode("\n", $lines), 0, 8000);
    }

    private function eventLocation(Event $event): string
    {
        return match ($event->location_type) {
            'virtual' => 'Online'.($event->venue_name ? ' · '.$event->venue_name : ''),
            'external' => $event->venue_name ?: 'External venue',
            default => 'SoundTunnel'.($event->venue_name ? ' · '.$event->venue_name : ''),
        };
    }

    private function eventCalendarId(Event $event): ?string
    {
        $event->loadMissing('resource');

        return $event->resource?->calendar_id
            ?: config('services.microsoft.events_calendar')
            ?: ($event->location_type === 'soundtunnel'
                ? config('services.microsoft.soundtunnel_calendar')
                : config('services.microsoft.studio_calendar'));
    }

    private function configured(): bool
    {
        return (bool) config('services.microsoft.tenant_id')
            && (bool) config('services.microsoft.client_id')
            && (bool) config('services.microsoft.client_secret');
    }

    private function recordFailure(Booking|Event $subject, string $message, string $action): void
    {
        $subject->update(['calendar_sync_status' => 'failed', 'calendar_sync_error' => $message]);
        IntegrationJob::updateOrCreate(
            ['integration' => 'microsoft_graph', 'action' => $action, 'subject_type' => $subject::class, 'subject_id' => $subject->id],
            ['status' => 'failed', 'attempts' => 1, 'next_attempt_at' => now()->addMinutes(10), 'last_error' => $message]
        );
    }
}
