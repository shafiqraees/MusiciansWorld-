<?php

namespace App\Services;

use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Event;
use App\Models\PlatformSetting;
use App\Models\PricingRule;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BookingService
{
    public function busyRanges(Resource $resource, string $date): array
    {
        $timezone = $resource->timezone;
        $dayStart = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $dayEnd = $dayStart->addDay()->addHours(3);

        $bookings = Booking::query()
            ->where('resource_id', $resource->id)
            ->where('starts_at', '<', $dayEnd->utc())
            ->where('ends_at', '>', $dayStart->utc())
            ->where(function ($query) {
                $query->whereIn('status', ['confirmed', 'paid', 'admin_hold'])
                    ->orWhere(function ($pending) {
                        $pending->whereIn('status', ['hold', 'pending_payment'])
                            ->where('hold_expires_at', '>', now());
                    });
            })
            ->get(['starts_at', 'ends_at']);

        $blocks = AvailabilityBlock::query()
            ->where('resource_id', $resource->id)
            ->where('starts_at', '<', $dayEnd->utc())
            ->where('ends_at', '>', $dayStart->utc())
            ->get(['starts_at', 'ends_at']);

        $events = Event::query()
            ->where('resource_id', $resource->id)
            ->whereIn('status', ['approved', 'published', 'completed', 'closeout'])
            ->where('starts_at', '<', $dayEnd->utc())
            ->where('ends_at', '>', $dayStart->utc())
            ->get(['starts_at', 'ends_at', 'setup_minutes', 'teardown_minutes'])
            ->map(function ($event) {
                $event->starts_at = $event->starts_at->copy()->subMinutes($event->setup_minutes);
                $event->ends_at = $event->ends_at->copy()->addMinutes($event->teardown_minutes);

                return $event;
            });

        return $bookings->concat($blocks)->concat($events)->map(function ($item) use ($dayStart, $timezone) {
            $start = CarbonImmutable::parse($item->starts_at)->setTimezone($timezone);
            $end = CarbonImmutable::parse($item->ends_at)->setTimezone($timezone);

            return [
                'start' => $dayStart->diffInMinutes($start, false),
                'end' => $dayStart->diffInMinutes($end, false),
            ];
        })->values()->all();
    }

    public function price(Resource $resource, int $durationHours): int
    {
        $rule = PricingRule::query()
            ->where('resource_id', $resource->id)
            ->where('service_type', 'studio_booking')
            ->where('duration_minutes', $durationHours * 60)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->first();
        if (! $rule) {
            throw new RuntimeException('Invalid duration');
        }

        return $rule->price_cents;
    }

    public function priceForMinutes(Resource $resource, int $minutes): int
    {
        if ($minutes < SLOT_STEP) {
            throw new RuntimeException('INVALID_RANGE');
        }

        if ($minutes % 60 === 0) {
            $rule = PricingRule::query()
                ->where('resource_id', $resource->id)
                ->where('service_type', 'studio_booking')
                ->where('duration_minutes', $minutes)
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', now()))
                ->first();
            if ($rule) {
                return $rule->price_cents;
            }
        }

        return (int) round($minutes / 60 * ($this->price($resource, 2) / 2));
    }

    public function rangeStatus(Resource $resource, CarbonImmutable $start, CarbonImmutable $end): string
    {
        if ($end->lessThanOrEqualTo($start) || $start->diffInMinutes($end) < SLOT_STEP) {
            return 'invalid_range';
        }
        if ($start->lessThan(CarbonImmutable::now($resource->timezone)->startOfMinute())) {
            return 'invalid_range';
        }

        for ($cursor = $start; $cursor->lessThan($end); $cursor = $cursor->addMinutes(SLOT_STEP)) {
            $minutes = $cursor->hour * 60 + $cursor->minute;
            $duringOpenHours = $minutes >= OPEN_MIN || $minutes < (CLOSE_MIN % (24 * 60));
            if (! $duringOpenHours) {
                return 'closed';
            }
        }

        return $this->rangeIsFree($resource, $start, $end) ? 'ok' : 'unavailable';
    }

    public function holdStudio(array $data, User $user): Booking
    {
        return DB::transaction(function () use ($data, $user) {
            $resource = Resource::where('slug', 'studio-a')->lockForUpdate()->firstOrFail();
            if (! empty($data['from']) && ! empty($data['to'])) {
                $start = CarbonImmutable::parse($data['from'], $resource->timezone);
                $end = CarbonImmutable::parse($data['to'], $resource->timezone);
                $status = $this->rangeStatus($resource, $start, $end);
                if ($status !== 'ok') {
                    throw new RuntimeException(strtoupper($status));
                }
                $minutes = (int) $start->diffInMinutes($end);
                $price = $this->priceForMinutes($resource, $minutes);
                $legacyStart = $start->hour * 60 + $start->minute;
                $durationHours = round($minutes / 60, 2);
            } else {
                $date = CarbonImmutable::parse($data['date'], $resource->timezone)->startOfDay();
                $start = $date->addMinutes((int) $data['start']);
                $end = $start->addHours((int) $data['durationHours']);
                $price = $this->price($resource, (int) $data['durationHours']);
                $legacyStart = (int) $data['start'];
                $durationHours = (int) $data['durationHours'];
            }

            if (! $this->rangeIsFree($resource, $start, $end, lock: true)) {
                throw new RuntimeException('UNAVAILABLE');
            }

            return Booking::create([
                'reference' => 'MW-'.strtoupper(base_convert((string) (int) (microtime(true) * 1000), 10, 36)),
                'user_id' => $user->id,
                'resource_id' => $resource->id,
                'service_type' => 'studio_booking',
                'starts_at' => $start->utc(),
                'ends_at' => $end->utc(),
                'hold_expires_at' => now()->addMinutes(PlatformSetting::valueOf('checkout_hold_minutes', 45)),
                'status' => 'pending_payment',
                'payment_status' => 'pending',
                'subtotal_cents' => $price,
                'total_cents' => $price,
                'currency' => 'usd',
                'customer_name' => $data['name'],
                'customer_email' => strtolower($data['email']),
                'customer_phone' => $data['phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'meta' => [
                    'legacy_start_minutes' => $legacyStart,
                    'duration_hours' => $durationHours,
                    'from' => $start->format('m/d/Y g:i A'),
                    'to' => $end->format('m/d/Y g:i A'),
                ],
            ]);
        }, 3);
    }

    private function rangeIsFree(Resource $resource, CarbonImmutable $start, CarbonImmutable $end, bool $lock = false): bool
    {
        $bookingQuery = Booking::query()
            ->where('resource_id', $resource->id)
            ->where('starts_at', '<', $end->utc())
            ->where('ends_at', '>', $start->utc())
            ->where(function ($query) {
                $query->whereIn('status', ['confirmed', 'paid', 'admin_hold'])
                    ->orWhere(fn ($pending) => $pending->whereIn('status', ['hold', 'pending_payment'])->where('hold_expires_at', '>', now()));
            });
        $blockQuery = AvailabilityBlock::query()
            ->where('resource_id', $resource->id)
            ->where('starts_at', '<', $end->utc())
            ->where('ends_at', '>', $start->utc());
        $eventQuery = Event::query()
            ->where('resource_id', $resource->id)
            ->whereIn('status', ['approved', 'published', 'completed', 'closeout'])
            ->where('starts_at', '<', $end->utc())
            ->where('ends_at', '>', $start->utc());

        if ($lock) {
            $bookingQuery->lockForUpdate();
            $blockQuery->lockForUpdate();
            $eventQuery->lockForUpdate();
        }

        return ! $bookingQuery->exists() && ! $blockQuery->exists() && ! $eventQuery->exists();
    }

    public function confirm(Booking $booking, ?string $paymentIntent = null): void
    {
        $booking->update([
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'hold_expires_at' => null,
            'stripe_payment_intent_id' => $paymentIntent ?: $booking->stripe_payment_intent_id,
            'calendar_sync_status' => 'pending',
        ]);
    }
}
