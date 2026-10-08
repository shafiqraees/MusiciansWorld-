<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingService;
use App\Services\MicrosoftCalendarService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Throwable;

class BookingPageController extends Controller
{
    public function index()
    {
        return view('pages.book');
    }

    public function confirm(string $id)
    {
        $booking = Booking::where('reference', $id)->with('resource')->firstOrFail();

        return view('book.confirm', compact('booking'));
    }

    public function success(Request $request, StripeService $stripe, BookingService $bookings, MicrosoftCalendarService $calendar)
    {
        $booking = null;
        $verified = false;
        if ($request->filled('session_id')) {
            try {
                $session = $stripe->client()->checkout->sessions->retrieve($request->string('session_id')->toString());
                if (($session->payment_status ?? '') === 'paid' && ($session->metadata->purpose ?? '') === 'studio_booking') {
                    $booking = Booking::whereKey($session->metadata->booking_id)->with('resource')->first();
                    if ($booking && $booking->payment_status !== 'paid') {
                        $bookings->confirm($booking, $session->payment_intent ?? null);
                        $calendar->syncBooking($booking->fresh('resource'));
                    }
                    $verified = (bool) $booking;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return view('book.success', compact('booking', 'verified'));
    }

    public function cancelled(Request $request)
    {
        $booking = Booking::where('reference', $request->query('id'))->first();
        if ($booking && $booking->payment_status === 'pending') {
            $booking->update(['status' => 'cancelled', 'payment_status' => 'cancelled', 'hold_expires_at' => null]);
        }

        return view('book.cancelled', compact('booking'));
    }
}
