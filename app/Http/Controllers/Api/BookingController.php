<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Resource;
use App\Services\BookingService;
use App\Services\CustomerAccountService;
use App\Services\StripeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BookingController extends Controller
{
    public function availability(Request $request, BookingService $bookings)
    {
        $data = $request->validate([
            'resource' => ['nullable', 'in:studio-a'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2020,2100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'duration' => ['nullable', 'integer', 'in:2,3,4,6,8'],
            'start' => ['nullable', 'integer'],
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
        $slots = array_map(fn ($start) => [
            'start' => $start,
            'label' => format_clock($start),
            'available' => $date >= phoenix_date_iso() && is_available($busy, $start, $duration),
        ], iter_starts($duration));
        $requested = null;
        if (isset($data['start'])) {
            $available = $date >= phoenix_date_iso() && is_available($busy, (int) $data['start'], $duration);
            $requested = [
                'available' => $available,
                'alternatives' => $available ? [] : array_map(fn ($time) => ['start' => $time, 'label' => format_clock($time)], nearby_times($busy, (int) $data['start'], $duration)),
            ];
        }

        return response()->json([
            'resource' => 'studio-a', 'date' => $date, 'duration' => $duration,
            'price' => intdiv($bookings->price($resource, $duration), 100),
            'dayStatus' => day_status($busy, $date, phoenix_date_iso()),
            'busy' => array_map(fn ($block) => $block + ['label' => format_clock($block['start']).' to '.format_clock($block['end'])], $busy),
            'slots' => $slots, 'requested' => $requested,
        ]);
    }

    public function show(Request $request)
    {
        $request->validate(['id' => ['required', 'string']]);
        $booking = Booking::where('reference', $request->query('id'))->with('resource')->firstOrFail();

        return response()->json($this->legacyPayload($booking));
    }

    public function store(Request $request, BookingService $bookings, StripeService $stripe, CustomerAccountService $accounts)
    {
        $data = $request->validate([
            'resourceId' => ['required', 'in:studio-a'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start' => ['required', 'integer', 'min:540', 'max:1560'],
            'durationHours' => ['required', 'integer', 'in:2,3,4,6,8'],
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:3000'],
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
                throw ValidationException::withMessages(['price' => 'Price mismatch']);
            }
            $session = $stripe->bookingCheckout($booking->load('resource'));
            $booking->update(['stripe_checkout_session_id' => $session->id]);

            return response()->json([
                'id' => $booking->reference,
                'date' => $data['date'],
                'start' => (int) $data['start'],
                'durationHours' => (int) $data['durationHours'],
                'price' => intdiv($booking->total_cents, 100),
                'url' => $session->url,
            ]);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'UNAVAILABLE') {
                return response()->json(['error' => 'This time is unavailable.', 'code' => 'UNAVAILABLE'], 409);
            }
            throw $exception;
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
