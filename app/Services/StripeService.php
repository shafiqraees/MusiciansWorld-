<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\MembershipPlan;
use App\Models\Order;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

class StripeService
{
    public function client(): StripeClient
    {
        return new StripeClient(config('services.stripe.secret'));
    }

    public function bookingCheckout(Booking $booking): Session
    {
        $local = $booking->starts_at->setTimezone($booking->resource->timezone);
        return $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $booking->customer_email,
            'client_reference_id' => $booking->reference,
            'success_url' => route('booking.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('booking.cancelled', ['id' => $booking->reference]),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $booking->currency,
                    'unit_amount' => $booking->total_cents,
                    'product_data' => [
                        'name' => 'Studio A — '.$local->format('Y-m-d · g:i A'),
                        'description' => 'MusiciansWorld Studio A booking',
                    ],
                ],
            ]],
            'metadata' => ['purpose' => 'studio_booking', 'booking_id' => $booking->id, 'booking_reference' => $booking->reference],
            'payment_intent_data' => ['metadata' => ['purpose' => 'studio_booking', 'booking_id' => $booking->id]],
        ]);
    }

    public function membershipCheckout(MembershipPlan $plan, string $email, ?int $userId): Session
    {
        return $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer_email' => $email,
            'success_url' => route('membership.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => url('/membership'),
            'line_items' => [['price' => $plan->stripe_price_id, 'quantity' => 1]],
            'metadata' => ['purpose' => 'membership', 'plan_id' => $plan->id, 'user_id' => $userId ?: ''],
            'subscription_data' => ['metadata' => ['purpose' => 'membership', 'plan_id' => $plan->id, 'user_id' => $userId ?: '']],
        ]);
    }

    public function ticketCheckout(Order $order): Session
    {
        $lineItems = $order->items->map(fn ($item) => [
            'quantity' => $item->quantity,
            'price_data' => [
                'currency' => $order->currency,
                'unit_amount' => $item->unit_price_cents,
                'product_data' => ['name' => $item->ticketType->name.' — '.$order->event->title],
            ],
        ])->all();
        return $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $order->buyer_email,
            'success_url' => route('tickets.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('events.show', $order->event->slug),
            'line_items' => $lineItems,
            'metadata' => ['purpose' => 'ticket_order', 'order_id' => $order->id, 'order_reference' => $order->reference],
            'payment_intent_data' => ['metadata' => ['purpose' => 'ticket_order', 'order_id' => $order->id]],
        ]);
    }
}
