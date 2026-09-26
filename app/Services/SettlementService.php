<?php

namespace App\Services;

use App\Models\Event;
use App\Models\FinancialTransaction;
use App\Models\OrganizerSettlement;
use App\Models\Order;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SettlementService
{
    public function calculate(Event $event): OrganizerSettlement
    {
        $transactions = FinancialTransaction::where('transactable_type', Order::class)
            ->whereIn('transactable_id', $event->orders()->pluck('id'))
            ->where('status', 'succeeded')->get()->groupBy('type');
        $sum = fn (string $type) => (int) ($transactions->get($type)?->sum('amount_cents') ?? 0);
        $venue = $event->resource_id ? (int) ($event->resource->settings['event_base_price_cents'] ?? 0) : 0;
        $addons = (int) $event->services->sum(fn ($service) => $service->pivot->quantity * $service->pivot->unit_price_cents);
        return OrganizerSettlement::updateOrCreate(
            ['event_id' => $event->id],
            [
                'status' => $event->status === 'completed' ? 'pending_closeout' : 'not_eligible',
                'gross_ticket_revenue_cents' => $sum('gross_ticket_revenue'),
                'refunds_cents' => $sum('refund'),
                'processing_fees_cents' => $sum('payment_processing'),
                'platform_fees_cents' => $sum('mwcn_platform_fee'),
                'venue_charges_cents' => $venue,
                'addon_charges_cents' => $addons,
                'organizer_proceeds_cents' => $sum('gross_ticket_revenue') - $sum('refund') - $sum('payment_processing') - $sum('mwcn_platform_fee') - $venue - $addons,
            ]
        );
    }

    public function transfer(OrganizerSettlement $settlement, int $approverId, StripeService $stripe): OrganizerSettlement
    {
        return DB::transaction(function () use ($settlement, $approverId, $stripe) {
            $settlement = OrganizerSettlement::whereKey($settlement->id)->lockForUpdate()->with('event.organizer.organizerProfile')->firstOrFail();
            abort_unless(PlatformSetting::valueOf('stripe.transfers.enabled', false), 503, 'Stripe transfers are awaiting MWCN approval.');
            $profile = $settlement->event->organizer->organizerProfile;
            if (!$profile?->stripe_account_id || !$profile->stripe_payouts_enabled) {
                throw new RuntimeException('Organizer Stripe onboarding is incomplete.');
            }
            if ($settlement->event->status !== 'completed' || $settlement->organizer_proceeds_cents <= 0) {
                throw new RuntimeException('Settlement is not eligible for payout.');
            }
            $transfer = $stripe->client()->transfers->create([
                'amount' => $settlement->organizer_proceeds_cents,
                'currency' => 'usd',
                'destination' => $profile->stripe_account_id,
                'transfer_group' => 'event_'.$settlement->event_id,
                'metadata' => ['event_id' => $settlement->event_id, 'settlement_id' => $settlement->id],
            ], ['idempotency_key' => 'mwcn-settlement-'.$settlement->id]);
            $settlement->update([
                'status' => 'processing', 'stripe_transfer_id' => $transfer->id,
                'approved_by' => $approverId, 'approved_at' => now(),
            ]);
            FinancialTransaction::firstOrCreate(
                ['idempotency_key' => 'organizer-transfer-'.$settlement->id],
                ['transactable_type' => OrganizerSettlement::class, 'transactable_id' => $settlement->id, 'type' => 'organizer_transfer', 'status' => 'processing', 'amount_cents' => $settlement->organizer_proceeds_cents, 'stripe_id' => $transfer->id]
            );
            return $settlement;
        });
    }
}
