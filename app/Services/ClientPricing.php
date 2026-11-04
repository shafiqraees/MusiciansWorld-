<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\FormSubmission;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\DB;

class ClientPricing
{
    public const SERVICES = [
        'studio_a' => 'Studio A',
        'soundtunnel' => 'SoundTunnel',
        'dj_school' => 'DJ School',
    ];

    public const PERCENTS = [10, 15, 20, 30];

    public function standardHourlyCents(): int
    {
        return max(0, (int) PlatformSetting::valueOf('studio_standard_hourly_cents', 4000));
    }

    public function firstTimeHourlyCents(): int
    {
        return max(0, (int) PlatformSetting::valueOf('studio_first_time_hourly_cents', 3000));
    }

    public function normalizeEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
    }

    public function isReturning(?string $email, ?string $phone, ?int $userId, ?int $ignoreBookingId = null): bool
    {
        $email = $this->normalizeEmail($email);
        $phone = $this->normalizePhone($phone);
        if ($email === '' && $phone === null && $userId === null) {
            return false;
        }

        return Booking::query()
            ->when($ignoreBookingId, fn ($query) => $query->whereKeyNot($ignoreBookingId))
            ->where(function ($query) use ($email, $phone, $userId) {
                if ($email !== '') {
                    $query->orWhereRaw('LOWER(customer_email) = ?', [$email]);
                }
                if ($phone) {
                    $query->orWhere('customer_phone_digits', $phone);
                }
                if ($userId) {
                    $query->orWhere('user_id', $userId);
                }
            })
            ->where(function ($query) {
                $query->where('payment_status', 'paid')
                    ->orWhereIn('status', ['confirmed', 'paid', 'completed', 'refunded'])
                    ->orWhere(fn ($pending) => $pending->whereIn('status', ['hold', 'pending_payment'])->where('hold_expires_at', '>', now()));
            })
            ->exists();
    }

    /**
     * @return array{
     *     first_time: bool,
     *     hourly_cents: int,
     *     subtotal_cents: int,
     *     discount_cents: int,
     *     total_cents: int,
     *     coupon: ?Coupon,
     *     coupon_applied: bool,
     *     message: ?string,
     *     coupon_error: bool
     * }
     */
    public function quote(int $minutes, ?string $email, ?string $phone, ?int $userId, ?string $code): array
    {
        $known = $this->normalizeEmail($email) !== '' || $this->normalizePhone($phone) !== null || $userId !== null;
        $firstTime = $known && ! $this->isReturning($email, $phone, $userId);
        $hourly = $firstTime ? $this->firstTimeHourlyCents() : $this->standardHourlyCents();
        $subtotal = (int) round($minutes / 60 * $hourly);
        $result = [
            'first_time' => $firstTime,
            'hourly_cents' => $hourly,
            'subtotal_cents' => $subtotal,
            'discount_cents' => 0,
            'total_cents' => $subtotal,
            'coupon' => null,
            'coupon_applied' => false,
            'message' => $firstTime
                ? 'First-time client rate applied.'
                : ($known ? 'Standard rate applied.' : 'Enter your email to confirm the first-time rate of $'.intdiv($this->firstTimeHourlyCents(), 100).' an hour.'),
            'coupon_error' => false,
        ];

        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return $result;
        }

        $check = $this->inspect($code, 'studio_a', $email, $phone, $userId);
        $result['coupon'] = $check['coupon'];
        if (! $check['ok']) {
            $result['message'] = $check['message'];
            $result['coupon_error'] = true;

            return $result;
        }
        if ($firstTime) {
            $result['message'] = 'This code cannot be combined with the first-time client rate.';

            return $result;
        }

        $discount = (int) round($subtotal * $check['coupon']->percent / 100);
        $result['discount_cents'] = $discount;
        $result['total_cents'] = max(0, $subtotal - $discount);
        $result['coupon_applied'] = true;
        $result['message'] = 'Code applied.';

        return $result;
    }

    /**
     * @return array{ok: bool, message: string, coupon: ?Coupon}
     */
    public function inspect(string $code, string $service, ?string $email, ?string $phone, ?int $userId): array
    {
        $coupon = Coupon::query()->where('code', strtoupper(trim($code)))->first();
        if (! $coupon) {
            return ['ok' => false, 'message' => 'This code is not valid.', 'coupon' => null];
        }
        if (! $coupon->is_active) {
            return ['ok' => false, 'message' => 'This code is turned off.', 'coupon' => $coupon];
        }
        if ($coupon->expires_at && $coupon->expires_at->endOfDay()->isPast()) {
            return ['ok' => false, 'message' => 'This code has expired.', 'coupon' => $coupon];
        }
        if (! in_array($service, $coupon->services ?? [], true)) {
            return ['ok' => false, 'message' => 'This code does not apply to '.(self::SERVICES[$service] ?? 'this service').'.', 'coupon' => $coupon];
        }

        $email = $this->normalizeEmail($email);
        $phone = $this->normalizePhone($phone);
        if (! $this->assignmentAllows($coupon, $email, $userId)) {
            return ['ok' => false, 'message' => 'This code is not valid.', 'coupon' => $coupon];
        }

        if ($coupon->isSingleUse() && $this->customerAlreadyUsed($coupon, $email, $phone, $userId)) {
            return ['ok' => false, 'message' => 'You have already used this code.', 'coupon' => $coupon];
        }

        $uses = $coupon->redemptions()->whereIn('status', ['reserved', 'used'])->count();
        if ($uses >= $coupon->max_uses) {
            $message = $coupon->max_uses === 1
                ? 'This code has already been used.'
                : 'This code has reached its use limit.';

            return ['ok' => false, 'message' => $message, 'coupon' => $coupon];
        }

        return ['ok' => true, 'message' => 'Code applied.', 'coupon' => $coupon];
    }

    public function reserve(Coupon $coupon, Booking $booking, int $subtotal, int $discount): CouponRedemption
    {
        return CouponRedemption::query()->create([
            'coupon_id' => $coupon->id,
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'email' => $this->normalizeEmail($booking->customer_email),
            'phone_digits' => $booking->customer_phone_digits,
            'service' => 'studio_a',
            'percent' => $coupon->percent,
            'subtotal_cents' => $subtotal,
            'discount_cents' => $discount,
            'status' => 'reserved',
        ]);
    }

    public function markUsedForBooking(Booking $booking): void
    {
        CouponRedemption::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'reserved')
            ->update(['status' => 'used']);
    }

    public function releaseForBooking(Booking $booking): void
    {
        CouponRedemption::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'reserved')
            ->update(['status' => 'released']);
    }

    /**
     * @return array{ok: bool, message: string, coupon: ?Coupon}
     */
    public function redeemInquiry(string $code, string $service, FormSubmission $submission): array
    {
        return DB::transaction(function () use ($code, $service, $submission) {
            $coupon = Coupon::query()->where('code', strtoupper(trim($code)))->lockForUpdate()->first();
            $check = $this->inspect($coupon?->code ?? $code, $service, $submission->email, $submission->phone, $submission->user_id);
            if (! $check['ok'] || ! $check['coupon']) {
                return $check;
            }

            CouponRedemption::query()->create([
                'coupon_id' => $check['coupon']->id,
                'form_submission_id' => $submission->id,
                'user_id' => $submission->user_id,
                'email' => $this->normalizeEmail($submission->email),
                'phone_digits' => $this->normalizePhone($submission->phone),
                'service' => $service,
                'percent' => $check['coupon']->percent,
                'subtotal_cents' => 0,
                'discount_cents' => 0,
                'status' => 'used',
            ]);

            return ['ok' => true, 'message' => 'Code applied.', 'coupon' => $check['coupon']];
        });
    }

    private function assignmentAllows(Coupon $coupon, string $email, ?int $userId): bool
    {
        if ($coupon->kind === 'family' && $coupon->assigned_email === null && $coupon->assigned_user_id === null) {
            return false;
        }
        if ($coupon->assigned_email === null && $coupon->assigned_user_id === null) {
            return true;
        }

        $emailMatches = $coupon->assigned_email !== null && $email === $this->normalizeEmail($coupon->assigned_email);
        $userMatches = $coupon->assigned_user_id !== null && $userId === (int) $coupon->assigned_user_id;
        if ($coupon->assigned_user_id !== null && $userId !== null && ! $userMatches) {
            return false;
        }
        if ($coupon->assigned_email !== null && $email !== '' && ! $emailMatches) {
            return false;
        }

        return $emailMatches || $userMatches;
    }

    private function customerAlreadyUsed(Coupon $coupon, string $email, ?string $phone, ?int $userId): bool
    {
        if ($email === '' && $phone === null && $userId === null) {
            return false;
        }

        return $coupon->redemptions()
            ->whereIn('status', ['reserved', 'used'])
            ->where(function ($query) use ($email, $phone, $userId) {
                if ($email !== '') {
                    $query->orWhere('email', $email);
                }
                if ($phone) {
                    $query->orWhere('phone_digits', $phone);
                }
                if ($userId) {
                    $query->orWhere('user_id', $userId);
                }
            })
            ->exists();
    }
}
