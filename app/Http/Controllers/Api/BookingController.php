<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Resource;
use App\Services\BookingService;
use App\Services\ClientPricing;
use App\Services\CustomerAccountService;
use App\Services\NotificationService;
use App\Services\StripeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BookingController extends Controller
{
    public function availability(Request $request, BookingService $bookings, ClientPricing $pricing)
    {
        $data = $request->validate([
            'resource' => ['nullable', 'in:studio-a'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2020,2100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'duration' => ['nullable', 'integer', 'in:2,3,4,6,8'],
            'start' => ['nullable', 'integer'],
            'end' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'coupon' => ['nullable', 'string', 'max:40'],
        ]);
        $resource = Resource::where('slug', 'studio-a')->firstOrFail();
        if (isset($data['month'], $data['year'])) {
            $month = CarbonImmutable::create((int) $data['year'], (int) $data['month'], 1, 0, 0, 0, $resource->timezone);
            $days = [];
            for ($day = $month; $day->month === $month->month; $day = $day->addDay()) {
                $date = $day->format('Y-m-d');
                $busy = $bookings->busyRanges($resource, $date);
                $days[$date] = day_status($busy, $date, phoenix_date_iso());
            }

            return response()->json(['resource' => 'studio-a', 'year' => (int) $data['year'], 'month' => (int) $data['month'], 'days' => $days]);
        }

        $date = $data['date'] ?? phoenix_date_iso();
        $duration = (int) ($data['duration'] ?? 2);
        $busy = $bookings->busyRanges($resource, $date);
        $now = CarbonImmutable::now($resource->timezone)->startOfMinute();
        $slots = [];
        for ($time = OPEN_MIN; $time <= CLOSE_MIN; $time += SLOT_STEP) {
            $moment = CarbonImmutable::parse($date, $resource->timezone)->startOfDay()->addMinutes($time);
            $open = $moment->greaterThanOrEqualTo($now) && (
                $time === CLOSE_MIN
                    ? is_range_open($busy, $time - SLOT_STEP, $time)
                    : is_range_open($busy, $time, $time + SLOT_STEP)
            );
            $slots[] = [
                'start' => $time,
                'label' => format_clock($time),
                'available' => $open,
            ];
        }
        $price = intdiv($bookings->price($resource, $duration), 100);
        $quote = null;
        if (! empty($data['from']) && ! empty($data['to'])) {
            $rangeStart = CarbonImmutable::parse($data['from'], $resource->timezone);
            $rangeEnd = CarbonImmutable::parse($data['to'], $resource->timezone);
            $status = $bookings->rangeStatus($resource, $rangeStart, $rangeEnd);
            $minutes = (int) $rangeStart->diffInMinutes($rangeEnd);
            $priced = $status === 'invalid_range' ? null : $pricing->quote($minutes, $data['email'] ?? $request->user()?->email, $data['phone'] ?? $request->user()?->phone, $request->user()?->id, $data['coupon'] ?? null);
            $quote = [
                'available' => $status === 'ok',
                'status' => $status,
                'price' => $priced ? intdiv($priced['total_cents'], 100) : null,
                'from' => $rangeStart->format('m/d/Y H:i'),
                'to' => $rangeEnd->format('m/d/Y H:i'),
                'pricing' => $priced ? [
                    'firstTime' => $priced['first_time'],
                    'hourly' => intdiv($priced['hourly_cents'], 100),
                    'subtotalCents' => $priced['subtotal_cents'],
                    'discountCents' => $priced['discount_cents'],
                    'totalCents' => $priced['total_cents'],
                    'message' => $priced['message'],
                    'couponApplied' => $priced['coupon_applied'],
                    'couponError' => $priced['coupon_error'],
                ] : null,
            ];
            if ($quote['price'] !== null) {
                $price = $quote['price'];
            }
        } elseif (isset($data['start'], $data['end'])) {
            $minutes = (int) $data['end'] - (int) $data['start'];
            if ($minutes >= SLOT_STEP) {
                $price = intdiv($bookings->priceForMinutes($resource, $minutes), 100);
            }
        }

        return response()->json([
            'resource' => 'studio-a', 'date' => $date, 'duration' => $duration,
            'price' => $price,
            'dayStatus' => day_status($busy, $date, phoenix_date_iso()),
            'busy' => array_map(fn ($block) => $block + ['label' => format_clock($block['start']).' to '.format_clock($block['end'])], $busy),
            'slots' => $slots,
            'quote' => $quote,
        ]);
    }

    public function show(Request $request)
    {
        $request->validate(['id' => ['required', 'string']]);
        $booking = Booking::where('reference', $request->query('id'))->with('resource')->firstOrFail();

        return response()->json($this->legacyPayload($booking));
    }

    public function store(Request $request, BookingService $bookings, StripeService $stripe, CustomerAccountService $accounts, ClientPricing $pricing, NotificationService $notifications)
    {
        $data = $request->validate([
            'resourceId' => ['required', 'in:studio-a'],
            'from' => ['required_without:durationHours', 'date'],
            'to' => ['required_with:from', 'date', 'after:from'],
            'date' => ['required_without:from', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start' => ['required_without:from', 'integer', 'min:540', 'max:1560'],
            'durationHours' => ['required_without:from', 'integer', 'in:2,3,4,6,8'],
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'coupon' => ['nullable', 'string', 'max:40'],
            'price' => ['nullable', 'integer'],
        ]);

        try {
            $user = $accounts->resolve($request->user(), [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ]);
            $data['email'] = $user->email;
            if ($request->user()) {
                $data['name'] = $user->name;
                $data['phone'] = $user->phone ?? ($data['phone'] ?? null);
            }

            $booking = $bookings->holdStudio($data, $user);
            if (array_key_exists('price', $data) && $data['price'] !== null && $booking->total_cents !== $data['price'] * 100 && $data['price'] !== intdiv($booking->total_cents, 100)) {
                $booking->update(['status' => 'cancelled', 'payment_status' => 'cancelled']);
                $pricing->releaseForBooking($booking);
                throw ValidationException::withMessages(['price' => 'Price mismatch']);
            }
            $session = $stripe->bookingCheckout($booking->load('resource'));
            $booking->update(['stripe_checkout_session_id' => $session->id]);
            $notifications->bookingLeadAfterResponse($booking);

            $localStart = $booking->starts_at->copy()->timezone($booking->resource->timezone);
            $localEnd = $booking->ends_at->copy()->timezone($booking->resource->timezone);

            return response()->json([
                'id' => $booking->reference,
                'from' => $localStart->format('m/d/Y H:i'),
                'to' => $localEnd->format('m/d/Y H:i'),
                'date' => $localStart->format('Y-m-d'),
                'start' => (int) ($booking->meta['legacy_start_minutes'] ?? ($localStart->hour * 60 + $localStart->minute)),
                'durationHours' => $booking->meta['duration_hours'] ?? null,
                'price' => intdiv($booking->total_cents, 100),
                'url' => $session->url,
            ]);
        } catch (RuntimeException $exception) {
            $message = match (true) {
                $exception->getMessage() === 'UNAVAILABLE' => 'This time is unavailable.',
                $exception->getMessage() === 'CLOSED' => 'That range includes hours when Studio A is closed (02:00–09:00).',
                $exception->getMessage() === 'INVALID_RANGE' => 'Choose an end time after the start time.',
                $exception->getMessage() === 'PAST' => 'That time has already passed. Choose a later open slot.',
                str_starts_with($exception->getMessage(), 'COUPON:') => substr($exception->getMessage(), 7),
                default => null,
            };
            if ($message === null) {
                throw $exception;
            }

            return response()->json(['error' => $message, 'code' => str_starts_with($exception->getMessage(), 'COUPON:') ? 'COUPON' : $exception->getMessage()], $exception->getMessage() === 'UNAVAILABLE' ? 409 : 422);
        } catch (Throwable $exception) {
            if (isset($booking)) {
                $booking->update(['status' => 'cancelled', 'payment_status' => 'failed']);
            }
            report($exception);

            return response()->json(['error' => 'Could not start payment. Please try again shortly.'], 502);
        }
    }

    private function legacyPayload(Booking $booking): array
    {
        $local = $booking->starts_at->setTimezone($booking->resource->timezone);

        return [
            'id' => $booking->reference,
            'resourceId' => $booking->resource->slug,
            'date' => $local->format('Y-m-d'),
            'start' => (int) ($booking->meta['legacy_start_minutes'] ?? ($local->hour * 60 + $local->minute)),
            'durationHours' => (int) ($booking->meta['duration_hours'] ?? ($booking->starts_at->diffInMinutes($booking->ends_at) / 60)),
            'price' => intdiv($booking->total_cents, 100),
            'status' => $booking->status,
            'payment' => ['status' => $booking->payment_status],
            'customer' => ['name' => $booking->customer_name, 'email' => $booking->customer_email],
        ];
    }
}
