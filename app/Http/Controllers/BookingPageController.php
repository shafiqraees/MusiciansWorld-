<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingService;
use App\Services\CustomerCalendarInvite;
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

    public function success(Request $request, StripeService $stripe, BookingService $bookings, MicrosoftCalendarService $calendar, CustomerCalendarInvite $invite)
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

        $calendarLinks = $booking && $booking->payment_status === 'paid'
            ? $invite->bookingLinks($booking)
            : null;

        return view('book.success', compact('booking', 'verified', 'calendarLinks'));
    }

    public function calendar(string $reference, CustomerCalendarInvite $invite)
    {
        $booking = Booking::where('reference', $reference)->with('resource')->firstOrFail();
        $heldForPayment = $booking->status === 'pending_payment' && $booking->hold_expires_at?->isFuture();
        abort_unless($booking->payment_status === 'paid' || $heldForPayment, 404);

        return response($invite->bookingIcs($booking), 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$booking->reference.'.ics"',
        ]);
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
