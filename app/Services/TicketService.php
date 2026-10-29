<?php

namespace App\Services;

use App\Models\Event;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TicketService
{
    public function createOrder(Event $event, array $selections, array $buyer): Order
    {
        return DB::transaction(function () use ($event, $selections, $buyer) {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($event->status !== 'published' || $event->starts_at->isPast()) {
                throw new RuntimeException('Tickets are not available for this event.');
            }
            $alreadyIssued = Ticket::where('event_id', $event->id)->whereNotIn('status', ['cancelled', 'voided', 'refunded'])->count();
            $requested = array_sum($selections);
            if ($alreadyIssued + $requested > $event->capacity) {
                throw new RuntimeException('Event capacity has been reached.');
            }

            $order = Order::create([
                'reference' => 'ORD-'.strtoupper(Str::random(10)),
                'user_id' => auth()->id(),
                'event_id' => $event->id,
                'buyer_name' => $buyer['name'],
                'buyer_email' => strtolower($buyer['email']),
                'status' => 'pending',
                'payment_status' => 'pending',
                'subtotal_cents' => 0,
                'total_cents' => 0,
                'currency' => 'usd',
            ]);
            $subtotal = 0;
            foreach ($selections as $ticketTypeId => $quantity) {
                $quantity = (int) $quantity;
                if ($quantity < 1) {
                    continue;
                }
                $type = TicketType::where('event_id', $event->id)->whereKey($ticketTypeId)->lockForUpdate()->firstOrFail();
                if ($quantity > $type->per_order_limit || $type->sold_count + $quantity > $type->quantity) {
                    throw new RuntimeException($type->name.' does not have that many tickets available.');
                }
                OrderItem::create([
                    'order_id' => $order->id, 'ticket_type_id' => $type->id, 'quantity' => $quantity,
                    'unit_price_cents' => $type->price_cents, 'total_cents' => $type->price_cents * $quantity,
                ]);
                $subtotal += $type->price_cents * $quantity;
            }
            if (! $order->items()->exists()) {
                throw new RuntimeException('Choose at least one ticket.');
            }
            $order->update(['subtotal_cents' => $subtotal, 'total_cents' => $subtotal]);

            return $order->fresh(['items.ticketType', 'event']);
        }, 3);
    }

    public function fulfill(Order $order, ?string $paymentIntent = null): void
    {
        DB::transaction(function () use ($order, $paymentIntent) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status === 'completed') {
                return;
            }
            foreach ($order->items()->with('ticketType')->get() as $item) {
                $type = TicketType::whereKey($item->ticket_type_id)->lockForUpdate()->firstOrFail();
                if ($type->sold_count + $item->quantity > $type->quantity) {
                    throw new RuntimeException('Ticket inventory changed before payment confirmation.');
                }
                for ($i = 0; $i < $item->quantity; $i++) {
                    $uuid = (string) Str::uuid();
                    $token = hash_hmac('sha256', $uuid, config('app.key'));
                    Ticket::create([
                        'uuid' => $uuid, 'order_id' => $order->id, 'event_id' => $order->event_id,
                        'ticket_type_id' => $type->id, 'user_id' => $order->user_id,
                        'attendee_name' => $order->buyer_name, 'attendee_email' => $order->buyer_email,
                        'qr_token_hash' => hash('sha256', $token), 'status' => 'valid',
                    ]);
                }
                $type->increment('sold_count', $item->quantity);
            }
            $platformFee = (int) round($order->subtotal_cents * $order->event->platform_fee_bps / 10000);
            $order->update(['status' => 'completed', 'payment_status' => 'paid', 'stripe_payment_intent_id' => $paymentIntent, 'platform_fee_cents' => $platformFee]);
            foreach ([
                'gross_ticket_revenue' => $order->subtotal_cents,
                'mwcn_platform_fee' => $platformFee,
                'payment_processing' => 0,
                'organizer_proceeds' => $order->subtotal_cents - $platformFee,
            ] as $type => $amount) {
                FinancialTransaction::firstOrCreate(
                    ['idempotency_key' => $type.'-'.$order->id],
                    [
                        'user_id' => $order->user_id, 'transactable_type' => Order::class,
                        'transactable_id' => $order->id, 'type' => $type, 'status' => 'succeeded',
                        'amount_cents' => $amount, 'stripe_id' => $paymentIntent,
                        'meta' => $type === 'payment_processing' ? ['pending_reconciliation' => true] : null,
                    ]
                );
            }
        }, 3);
    }

    public function checkIn(string $token, int $staffId): array
    {
        return DB::transaction(function () use ($token, $staffId) {
            $ticket = Ticket::where('qr_token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $ticket || $ticket->status !== 'valid') {
                return ['result' => $ticket?->status === 'checked_in' ? 'ALREADY_USED' : 'INVALID', 'ticket' => $ticket];
            }
            $ticket->update(['status' => 'checked_in', 'checked_in_at' => now(), 'checked_in_by' => $staffId]);

            return ['result' => 'CHECKED_IN', 'ticket' => $ticket];
        });
    }
}
