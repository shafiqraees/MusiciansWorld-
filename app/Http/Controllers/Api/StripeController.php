<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CustomerMembership;
use App\Models\FinancialTransaction;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\OrganizerProfile;
use App\Models\OrganizerSettlement;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\BookingService;
use App\Services\CustomerAccountService;
use App\Services\MicrosoftCalendarService;
use App\Services\StripeService;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Webhook;
use Throwable;

class StripeController extends Controller
{
    public function checkout(Request $request, StripeService $stripe, CustomerAccountService $accounts)
    {
        $data = $request->validate(['name' => ['required'], 'email' => ['required', 'email'], 'phone' => ['nullable']]);
        $plan = MembershipPlan::where('slug', 'founder')->where('is_active', true)->firstOrFail();
        if (! $plan->stripe_price_id) {
            return response()->json(['error' => 'Membership checkout is not configured.'], 503);
        }
        $user = $accounts->resolve($request->user(), $data);
        $data['email'] = $user->email;
        if ($user?->memberships()->where('membership_plan_id', $plan->id)->whereIn('status', ['active', 'trialing', 'grace'])->exists()) {
            return response()->json(['error' => 'That email is already a Founding Member.'], 409);
        }
        try {
            $session = $stripe->membershipCheckout($plan, $data['email'], $user?->id);

            return response()->json(['ok' => true, 'url' => $session->url]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['error' => 'Could not start checkout. Please try again shortly.'], 502);
        }
    }

    public function webhook(Request $request, BookingService $bookings, MicrosoftCalendarService $calendar, TicketService $tickets)
    {
        try {
            $event = Webhook::constructEvent($request->getContent(), $request->header('Stripe-Signature', ''), config('services.stripe.webhook_secret'));
        } catch (Throwable $exception) {
            return response()->json(['error' => 'Invalid webhook signature.'], 400);
        }

        $record = WebhookEvent::firstOrCreate(
            ['external_id' => $event->id],
            ['provider' => 'stripe', 'type' => $event->type, 'status' => 'received', 'payload' => json_decode($request->getContent(), true)]
        );
        if (! $record->wasRecentlyCreated && $record->status === 'processed') {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        try {
            DB::transaction(function () use ($event, $bookings, $calendar, $tickets) {
                $object = $event->data->object;
                if (in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true) && ($object->payment_status ?? null) === 'paid') {
                    $purpose = $object->metadata->purpose ?? null;
                    if ($purpose === 'studio_booking') {
                        $booking = Booking::whereKey($object->metadata->booking_id)->lockForUpdate()->firstOrFail();
                        $bookings->confirm($booking, $object->payment_intent ?? null);
                        FinancialTransaction::firstOrCreate(
                            ['idempotency_key' => 'booking-payment-'.$booking->id],
                            ['user_id' => $booking->user_id, 'transactable_type' => Booking::class, 'transactable_id' => $booking->id, 'type' => 'booking_payment', 'status' => 'succeeded', 'amount_cents' => $booking->total_cents, 'stripe_id' => $object->payment_intent ?? null]
                        );
                        $calendar->syncBooking($booking->fresh('resource'));
                    } elseif ($purpose === 'ticket_order') {
                        $tickets->fulfill(Order::findOrFail($object->metadata->order_id), $object->payment_intent ?? null);
                    }
                }
                if ($event->type === 'customer.subscription.created' || $event->type === 'customer.subscription.updated') {
                    $this->syncSubscription($object);
                }
                if ($event->type === 'customer.subscription.deleted') {
                    CustomerMembership::where('stripe_subscription_id', $object->id)->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                }
                if ($event->type === 'invoice.payment_succeeded' && ! empty($object->subscription)) {
                    CustomerMembership::where('stripe_subscription_id', $object->subscription)->update(['status' => 'active', 'grace_ends_at' => null]);
                }
                if ($event->type === 'invoice.payment_failed' && ! empty($object->subscription)) {
                    CustomerMembership::where('stripe_subscription_id', $object->subscription)->update(['status' => 'grace', 'grace_ends_at' => now()->addDays(7)]);
                }
                if ($event->type === 'checkout.session.expired') {
                    Booking::where('stripe_checkout_session_id', $object->id)->where('status', 'pending_payment')->update(['status' => 'expired', 'payment_status' => 'expired']);
                    Order::where('stripe_checkout_session_id', $object->id)->where('status', 'pending')->update(['status' => 'expired', 'payment_status' => 'expired']);
                }
                if ($event->type === 'checkout.session.async_payment_failed') {
                    Booking::where('stripe_checkout_session_id', $object->id)->where('status', 'pending_payment')->update(['status' => 'failed', 'payment_status' => 'failed', 'hold_expires_at' => null]);
                    Order::where('stripe_checkout_session_id', $object->id)->where('status', 'pending')->update(['status' => 'failed', 'payment_status' => 'failed']);
                }
                if ($event->type === 'payment_intent.payment_failed') {
                    Booking::where('stripe_payment_intent_id', $object->id)->update(['payment_status' => 'failed']);
                    Order::where('stripe_payment_intent_id', $object->id)->update(['payment_status' => 'failed']);
                }
                if ($event->type === 'charge.refunded' && ! empty($object->payment_intent)) {
                    Booking::where('stripe_payment_intent_id', $object->payment_intent)->update(['payment_status' => 'refunded', 'status' => 'cancelled']);
                    $orders = Order::where('stripe_payment_intent_id', $object->payment_intent)->with('tickets')->get();
                    foreach ($orders as $order) {
                        foreach ($order->tickets->whereNotIn('status', ['refunded', 'cancelled', 'voided']) as $ticket) {
                            $ticket->ticketType()->decrement('sold_count');
                            $ticket->update(['status' => 'refunded']);
                        }
                        $order->update(['payment_status' => 'refunded', 'status' => 'refunded']);
                    }
                }
                if (str_starts_with($event->type, 'charge.dispute.') && ! empty($object->payment_intent)) {
                    FinancialTransaction::where('stripe_id', $object->payment_intent)->update(['status' => 'disputed']);
                }
                if ($event->type === 'transfer.created') {
                    OrganizerSettlement::where('stripe_transfer_id', $object->id)->update(['status' => 'paid', 'paid_at' => now()]);
                    FinancialTransaction::where('stripe_id', $object->id)->update(['status' => 'paid']);
                }
                if ($event->type === 'account.updated') {
                    OrganizerProfile::where('stripe_account_id', $object->id)->update([
                        'stripe_charges_enabled' => (bool) $object->charges_enabled,
                        'stripe_payouts_enabled' => (bool) $object->payouts_enabled,
                        'requirements' => $object->requirements?->toArray(),
                    ]);
                }
            }, 3);
            $record->update(['status' => 'processed', 'attempts' => $record->attempts + 1, 'last_error' => null]);
        } catch (Throwable $exception) {
            $record->update(['status' => 'failed', 'attempts' => $record->attempts + 1, 'last_error' => $exception->getMessage()]);
            report($exception);

            return response()->json(['error' => 'Webhook processing failed.'], 500);
        }

        return response()->json(['received' => true]);
    }

    private function syncSubscription(object $subscription): void
    {
        $planId = (int) ($subscription->metadata->plan_id ?? 0);
        $userId = (int) ($subscription->metadata->user_id ?? 0);
        if (! $planId) {
            return;
        }
        $user = $userId ? User::find($userId) : User::where('email', $subscription->customer_email ?? '')->first();
        if (! $user) {
            return;
        }
        $status = in_array($subscription->status, ['active', 'trialing'], true) ? $subscription->status : ($subscription->status === 'past_due' ? 'grace' : $subscription->status);
        CustomerMembership::updateOrCreate(
            ['stripe_subscription_id' => $subscription->id],
            [
                'user_id' => $user->id, 'membership_plan_id' => $planId, 'status' => $status,
                'stripe_customer_id' => $subscription->customer,
                'current_period_start' => isset($subscription->current_period_start) ? now()->setTimestamp($subscription->current_period_start) : null,
                'current_period_end' => isset($subscription->current_period_end) ? now()->setTimestamp($subscription->current_period_end) : null,
            ]
        );
    }
}
