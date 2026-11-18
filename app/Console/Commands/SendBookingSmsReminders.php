<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingSms;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mwcn:sms-reminders')]
#[Description('Text a Nextiva reminder about 24 hours before a paid Studio A booking')]
class SendBookingSmsReminders extends Command
{
    public function handle(BookingSms $sms): int
    {
        Booking::query()
            ->with('resource')
            ->where('sms_consent', true)
            ->whereNull('sms_reminder_sent_at')
            ->where('payment_status', 'paid')
            ->where('starts_at', '>', now()->addHours(23))
            ->where('starts_at', '<=', now()->addHours(25))
            ->orderBy('id')
            ->each(function (Booking $booking) use ($sms): void {
                $sms->sendReminder($booking);
            });

        return self::SUCCESS;
    }
}
