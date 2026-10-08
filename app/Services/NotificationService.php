<?php

namespace App\Services;

use App\Models\IntegrationJob;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationService
{
    private static ?float $lastSendAt = null;

    public function welcomeAfterResponse(string $email, string $name, string $type, string $detail = ''): void
    {
        app()->terminating(fn () => $this->welcome($email, $name, $type, $detail));
    }

    public function adminAfterResponse(string $subject, array $submission): void
    {
        app()->terminating(fn () => $this->admin($subject, $submission));
    }

    public function customerAccountAfterResponse(User $user, string $temporaryPassword): void
    {
        app()->terminating(fn () => $this->customerAccount($user, $temporaryPassword));
    }

    public function customerAccount(User $user, string $temporaryPassword, bool $recordFailure = true): bool
    {
        $loginUrl = route('login');

        try {
            $this->throttle();
            Mail::send('emails.customer-account-created', compact('user', 'temporaryPassword', 'loginUrl'), function ($message) use ($user) {
                $message->to($user->email, $user->name)->subject('Your MusiciansWorld customer account');
            });
            $this->markSent();

            return true;
        } catch (Throwable $exception) {
            report($exception);
            if ($recordFailure) {
                IntegrationJob::create([
                    'integration' => 'email',
                    'action' => 'customer_account_created',
                    'status' => 'failed',
                    'attempts' => 1,
                    'next_attempt_at' => now()->addMinutes(10),
                    'last_error' => $exception->getMessage(),
                    'payload' => ['email' => $user->email, 'name' => $user->name],
                ]);
            }

            return false;
        }
    }

    public function welcome(string $email, string $name, string $type, string $detail = '', bool $recordFailure = true): bool
    {
        $isDj = $type === 'dj';
        $subject = $isDj ? 'Welcome to MusiciansWorld DJ School' : 'Welcome to SoundTunnel';
        $formUrl = $isDj ? config('services.mwcn.dj_form_url') : config('services.mwcn.soundtunnel_form_url');
        try {
            $this->throttle();
            Mail::send('emails.welcome', compact('name', 'detail', 'formUrl', 'isDj'), function ($message) use ($email, $name, $subject) {
                $message->to($email, $name)->subject($subject);
            });
            $this->markSent();

            return true;
        } catch (Throwable $exception) {
            report($exception);
            if ($recordFailure) {
                IntegrationJob::create([
                    'integration' => 'email',
                    'action' => $type.'_welcome',
                    'status' => 'failed',
                    'attempts' => 1,
                    'next_attempt_at' => now()->addMinutes(10),
                    'last_error' => $exception->getMessage(),
                    'payload' => ['email' => $email, 'name' => $name, 'detail' => $detail],
                ]);
            }

            return false;
        }
    }

    /**
     * The Mailtrap sandbox rejects a submission's second notification with
     * "550 Too many emails per second" unless the sends are spaced out. Every
     * caller is either a terminating callback or the retry command, so the wait
     * never lands on a request the visitor is waiting for.
     */
    private function throttle(): void
    {
        $gap = (float) config('mail.min_seconds_between_sends', 0);
        if ($gap <= 0 || self::$lastSendAt === null) {
            return;
        }
        $wait = $gap - (microtime(true) - self::$lastSendAt);
        if ($wait > 0) {
            usleep((int) round($wait * 1_000_000));
        }
    }

    private function markSent(): void
    {
        self::$lastSendAt = microtime(true);
    }

    public function admin(string $subject, array $submission): bool
    {
        try {
            $this->throttle();
            Mail::send('emails.admin-notification', compact('submission'), function ($message) use ($subject, $submission) {
                $message->to(config('mail.from.address'))->replyTo($submission['email'], $submission['name'])->subject($subject);
            });
            $this->markSent();

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
