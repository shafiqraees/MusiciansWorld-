<?php

namespace App\Support;

use App\Models\Event;
use App\Models\FinancialTransaction;
use App\Models\Order;

/**
 * Shared money-flow helpers so the customer portal, promoter workspace and
 * admin ledger all describe the same transaction the same way.
 */
class Finance
{
    /** Ledger rows that describe where money goes, not what a customer paid. */
    public const INTERNAL_TYPES = [
        'mwcn_platform_fee',
        'organizer_proceeds',
        'payment_processing',
        'venue_charge',
        'addon_charge',
        'adjustment',
        'organizer_transfer',
        'organizer_payout',
    ];

    /** Human labels for every ledger type the platform writes. */
    public const TYPE_LABELS = [
        'gross_ticket_revenue' => 'Ticket sale',
        'mwcn_platform_fee' => 'MWCN platform fee',
        'payment_processing' => 'Payment processing',
        'organizer_proceeds' => 'Organizer proceeds',
        'organizer_transfer' => 'Organizer transfer',
        'organizer_payout' => 'Organizer payout',
        'refund' => 'Refund',
        'studio_booking' => 'Studio A booking',
        'booking_payment' => 'Booking payment',
        'soundtunnel_booking' => 'SoundTunnel rental',
        'membership' => 'Membership',
        'membership_payment' => 'Membership payment',
        'program_registration' => 'DJ School payment',
        'venue_charge' => 'Venue charge',
        'addon_charge' => 'Add-on / service charge',
        'adjustment' => 'Adjustment',
    ];

    /** Who the money belongs to for a given ledger type. */
    public const TYPE_PARTY = [
        'gross_ticket_revenue' => 'Customer → MWCN platform',
        'mwcn_platform_fee' => 'Kept by MWCN',
        'payment_processing' => 'Card processor',
        'organizer_proceeds' => 'Owed to organizer',
        'organizer_transfer' => 'MWCN → organizer',
        'organizer_payout' => 'MWCN → organizer',
        'refund' => 'MWCN → customer',
        'studio_booking' => 'Customer → MWCN',
        'booking_payment' => 'Customer → MWCN',
        'soundtunnel_booking' => 'Customer → MWCN',
        'membership' => 'Customer → MWCN',
        'membership_payment' => 'Customer → MWCN',
        'program_registration' => 'Customer → MWCN',
        'venue_charge' => 'Organizer → MWCN',
        'addon_charge' => 'Organizer → MWCN',
        'adjustment' => 'Authorized adjustment',
    ];

    public static function label(?string $type): string
    {
        return self::TYPE_LABELS[$type] ?? a_label($type);
    }

    public static function party(?string $type): string
    {
        return self::TYPE_PARTY[$type] ?? '—';
    }

    public static function isCustomerFacing(?string $type): bool
    {
        return !in_array($type, self::INTERNAL_TYPES, true);
    }

    public static function percent(int $bps): string
    {
        return rtrim(rtrim(number_format($bps / 100, 2), '0'), '.').'%';
    }

    /**
     * Money split for a single ticket order: what the buyer paid, what MWCN
     * keeps, what the processor takes and what the organizer is owed.
     */
    public static function orderBreakdown(Order $order): array
    {
        $ledger = $order->relationLoaded('transactions') ? $order->transactions : $order->transactions()->get();
        $sum = fn (string $type) => (int) $ledger->where('type', $type)->where('status', 'succeeded')->sum('amount_cents');
        $feeBps = (int) ($order->event?->platform_fee_bps ?? 0);
        $platformFee = $sum('mwcn_platform_fee') ?: (int) round($order->subtotal_cents * $feeBps / 10000);
        $processing = $sum('payment_processing') ?: (int) $order->processing_fee_cents;
        $refunds = abs($sum('refund'));
        $proceeds = $ledger->where('type', 'organizer_proceeds')->where('status', 'succeeded')->isNotEmpty()
            ? $sum('organizer_proceeds')
            : $order->subtotal_cents - $platformFee - $processing;

        return [
            'tickets' => (int) $order->subtotal_cents,
            'customer_paid' => (int) $order->total_cents,
            'platform_fee' => $platformFee,
            'platform_fee_bps' => $feeBps,
            'processing' => $processing,
            'refunds' => $refunds,
            'organizer' => $proceeds - $refunds,
        ];
    }

    /**
     * Full statement for an event, built from recorded transactions so every
     * party sees the same numbers.
     */
    public static function eventStatement(Event $event): array
    {
        $orderIds = $event->relationLoaded('orders') ? $event->orders->pluck('id') : $event->orders()->pluck('id');
        $ledger = FinancialTransaction::where('transactable_type', Order::class)
            ->whereIn('transactable_id', $orderIds)
            ->where('status', 'succeeded')
            ->get();
        $sum = fn (string $type) => (int) $ledger->where('type', $type)->sum('amount_cents');
        $settlement = $event->settlement;

        $gross = $settlement?->gross_ticket_revenue_cents ?: $sum('gross_ticket_revenue');
        $refunds = abs($settlement?->refunds_cents ?: $sum('refund'));
        $processing = $settlement?->processing_fees_cents ?: $sum('payment_processing');
        $platform = $settlement?->platform_fees_cents ?: $sum('mwcn_platform_fee');
        $venue = (int) ($settlement?->venue_charges_cents ?? 0);
        $addons = (int) ($settlement?->addon_charges_cents ?? 0);
        $adjustments = (int) ($settlement?->adjustments_cents ?? 0);
        $proceeds = $settlement
            ? (int) $settlement->organizer_proceeds_cents
            : $gross - $refunds - $processing - $platform - $venue - $addons + $adjustments;

        $ticketTypes = $event->relationLoaded('ticketTypes') ? $event->ticketTypes : $event->ticketTypes()->get();
        $paidTickets = $orderIds->isNotEmpty()
            ? (int) \App\Models\OrderItem::whereIn('order_id', Order::whereIn('id', $orderIds)->where('payment_status', 'paid')->select('id'))->sum('quantity')
            : 0;

        return [
            'gross' => $gross,
            'refunds' => $refunds,
            'processing' => $processing,
            'platform_fee' => $platform,
            'platform_fee_bps' => (int) $event->platform_fee_bps,
            'venue' => $venue,
            'addons' => $addons,
            'adjustments' => $adjustments,
            'organizer' => $proceeds,
            'tickets_sold' => $paidTickets ?: (int) $ticketTypes->sum('sold_count'),
            'orders' => $orderIds->count(),
            'payout_status' => $settlement?->status ?? 'not_eligible',
            'payout_final' => in_array($settlement?->status, ['paid', 'processing', 'transferred'], true),
            'hold_reason' => $settlement?->hold_reason,
            'transfer_id' => $settlement?->stripe_transfer_id,
            'paid_at' => $settlement?->paid_at,
            'settlement' => $settlement,
        ];
    }
}
