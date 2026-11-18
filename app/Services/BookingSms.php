<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\IntegrationJob;
use Throwable;

class BookingSms
{
    public function __construct(private NextivaSms $nextiva) {}

    public function confirmationAfterResponse(Booking $booking): void
    {
        if (! $booking->sms_consent) {
            return;
        }

        $bookingId = $booking->id;
        app()->terminating(function () use ($bookingId): void {
            $fresh = Booking::with('resource')->find($bookingId);
            if ($fresh) {
                $this->sendConfirmation($fresh);
            }
        });
    }

    public function sendConfirmation(Booking $booking, bool $recordFailure = true): bool
    {
        return $this->deliver($booking, 'sms_confirmation', 'sms_confirmation_sent_at', $this->confirmationText($booking), $recordFailure);
    }

    public function sendReminder(Booking $booking, bool $recordFailure = true): bool
    {
        return $this->deliver($booking, 'sms_reminder', 'sms_reminder_sent_at', $this->reminderText($booking), $recordFailure);
    }

    private function deliver(Booking $booking, string $action, string $sentColumn, string $message, bool $recordFailure): bool
    {
        if ($booking->{$sentColumn}) {
            return true;
        }
        if (! $booking->sms_consent || ! $this->nextiva->configured()) {
            return false;
        }

        $to = $this->nextiva->toE164($booking->customer_phone);
        if ($to === null) {
            return false;
        }

        try {
            $this->nextiva->send($to, $message);
            $booking->update([$sentColumn => now()]);

            return true;
        } catch (Throwable $exception) {
            report($exception);
            if ($recordFailure) {
                IntegrationJob::updateOrCreate(
                    ['integration' => 'nextiva', 'action' => $action, 'subject_type' => Booking::class, 'subject_id' => $booking->id],
                    ['status' => 'failed', 'attempts' => 1, 'next_attempt_at' => now()->addMinutes(10), 'last_error' => $exception->getMessage()]
                );
            }

            return false;
        }
    }

    private function confirmationText(Booking $booking): string
    {
        $booking->loadMissing('resource');
        $meta = $booking->meta ?? [];
        $place = $booking->resource?->name ?: 'Studio A';

        return 'MusiciansWorld: '.$place.' is held for '
            .($meta['from'] ?? $booking->starts_at?->format('m/d/Y H:i'))
            .' to '.($meta['to'] ?? $booking->ends_at?->format('m/d/Y H:i'))
            .'. Total '.a_money($booking->total_cents).'. Reply STOP to opt out. Msg & data rates may apply.';
    }

    private function reminderText(Booking $booking): string
    {
        $booking->loadMissing('resource');
        $meta = $booking->meta ?? [];
        $place = $booking->resource?->name ?: 'Studio A';

        return 'MusiciansWorld reminder: '.$place.' is coming up, '
            .($meta['from'] ?? $booking->starts_at?->format('m/d/Y H:i'))
            .' to '.($meta['to'] ?? $booking->ends_at?->format('m/d/Y H:i'))
            .'. Reply STOP to opt out.';
    }
}
