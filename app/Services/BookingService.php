<?php

namespace App\Services;

use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Event;
use App\Models\PricingRule;
use App\Models\PlatformSetting;
use App\Models\Resource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        if (!$rule) {
            throw new RuntimeException('Invalid duration');
        }
        return $rule->price_cents;
    }

    public function holdStudio(array $data): Booking
    {
        return DB::transaction(function () use ($data) {
            $resource = Resource::where('slug', 'studio-a')->lockForUpdate()->firstOrFail();
            $date = CarbonImmutable::parse($data['date'], $resource->timezone)->startOfDay();
            $start = $date->addMinutes((int) $data['start']);
            $end = $start->addHours((int) $data['durationHours']);

            $conflict = Booking::query()
                ->where('resource_id', $resource->id)
                ->where('starts_at', '<', $end->utc())
                ->where('ends_at', '>', $start->utc())
                ->where(function ($query) {
                    $query->whereIn('status', ['confirmed', 'paid', 'admin_hold'])
                        ->orWhere(fn ($q) => $q->whereIn('status', ['hold', 'pending_payment'])->where('hold_expires_at', '>', now()));
                })->lockForUpdate()->exists();
            $blocked = AvailabilityBlock::query()
                ->where('resource_id', $resource->id)
                ->where('starts_at', '<', $end->utc())
                ->where('ends_at', '>', $start->utc())
                ->lockForUpdate()->exists();
            if ($conflict || $blocked) {
                throw new RuntimeException('UNAVAILABLE');
            }

            $price = $this->price($resource, (int) $data['durationHours']);
            return Booking::create([
                'reference' => 'MW-'.strtoupper(base_convert((string) (int) (microtime(true) * 1000), 10, 36)),
                'user_id' => auth()->id(),
                'resource_id' => $resource->id,
                'service_type' => 'studio_booking',
                'starts_at' => $start->utc(),
                'ends_at' => $end->utc(),
                'hold_expires_at' => now()->addMinutes(PlatformSetting::valueOf('checkout_hold_minutes', 45)),
                'status' => 'pending_payment',
                'payment_status' => 'pending',
                'subtotal_cents' => $price,
                'total_cents' => $price,
                'customer_name' => $data['name'],
                'customer_email' => strtolower($data['email']),
                'customer_phone' => $data['phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'meta' => ['legacy_start_minutes' => (int) $data['start'], 'duration_hours' => (int) $data['durationHours']],
            ]);
        }, 3);
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
