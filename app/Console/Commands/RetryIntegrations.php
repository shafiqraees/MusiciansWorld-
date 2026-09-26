<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\IntegrationJob;
use App\Services\MicrosoftCalendarService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Throwable;

class RetryIntegrations extends Command
{
    protected $signature = 'mwcn:retry-integrations';
    protected $description = 'Retry failed MWCN calendar and email integrations safely';

    public function handle(MicrosoftCalendarService $calendar, NotificationService $notifications): int
    {
        IntegrationJob::whereIn('status', ['pending', 'failed'])
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->where('attempts', '<', 10)->orderBy('id')->limit(100)->get()
            ->each(function (IntegrationJob $job) use ($calendar, $notifications) {
                $job->update(['status' => 'processing', 'attempts' => $job->attempts + 1]);
                try {
                    if ($job->integration === 'microsoft_graph' && $job->action === 'sync_booking') {
                        $booking = Booking::with('resource')->findOrFail($job->subject_id);
                        $calendar->syncBooking($booking);
                        if ($booking->fresh()->calendar_sync_status !== 'synced') {
                            throw new \RuntimeException($booking->fresh()->calendar_sync_error ?: 'Calendar sync failed.');
                        }
                    } elseif ($job->integration === 'microsoft_graph' && $job->action === 'cancel_booking') {
                        $booking = Booking::with('resource')->findOrFail($job->subject_id);
                        $calendar->cancelBooking($booking);
                        if ($booking->fresh()->calendar_sync_status !== 'cancelled') {
                            throw new \RuntimeException($booking->fresh()->calendar_sync_error ?: 'Calendar cancellation failed.');
                        }
                    } elseif ($job->integration === 'email' && str_ends_with($job->action, '_welcome')) {
                        $type = str_starts_with($job->action, 'dj_') ? 'dj' : 'soundtunnel';
                        $payload = $job->payload;
                        if (!$notifications->welcome($payload['email'], $payload['name'], $type, $payload['detail'] ?? '', false)) {
                            throw new \RuntimeException('Email delivery failed.');
                        }
                    } else {
                        throw new \RuntimeException('No retry handler is registered.');
                    }
                    $job->update(['status' => 'completed', 'last_error' => null, 'next_attempt_at' => null]);
                } catch (Throwable $exception) {
                    $job->update(['status' => 'failed', 'last_error' => $exception->getMessage(), 'next_attempt_at' => now()->addMinutes(min(60, 5 * $job->attempts))]);
                }
            });
        return self::SUCCESS;
    }
}
