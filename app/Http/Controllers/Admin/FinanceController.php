<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CustomerMembership;
use App\Models\Event;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\OrganizerSettlement;
use App\Support\Finance;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Platform-wide money flow: every ledger row, who it belongs to, and the
 * per-event split between customers, MWCN and organizers.
 */
class FinanceController extends Controller
{
    public function transactions(Request $request)
    {
        $from = CarbonImmutable::parse($request->query('from', now()->subDays(90)->toDateString()))->startOfDay();
        $to = CarbonImmutable::parse($request->query('to', now()->toDateString()))->endOfDay();

        $base = FinancialTransaction::query()->whereBetween('financial_transactions.created_at', [$from, $to]);

        $query = (clone $base)->with('user')->latest('financial_transactions.created_at');
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('party')) {
            $query->whereIn('type', match ($request->string('party')->toString()) {
                'customer' => ['gross_ticket_revenue', 'studio_booking', 'booking_payment', 'soundtunnel_booking', 'membership', 'membership_payment', 'program_registration', 'refund'],
                'mwcn' => ['mwcn_platform_fee', 'venue_charge', 'addon_charge', 'adjustment'],
                'organizer' => ['organizer_proceeds', 'organizer_transfer', 'organizer_payout'],
                'processor' => ['payment_processing'],
                default => array_keys(Finance::TYPE_LABELS),
            });
        }
        if ($request->filled('event')) {
            $orderIds = Order::where('event_id', $request->integer('event'))->pluck('id');
            $settlementIds = OrganizerSettlement::where('event_id', $request->integer('event'))->pluck('id');
            $query->where(fn ($q) => $q
                ->where(fn ($o) => $o->where('transactable_type', Order::class)->whereIn('transactable_id', $orderIds))
                ->orWhere(fn ($s) => $s->where('transactable_type', OrganizerSettlement::class)->whereIn('transactable_id', $settlementIds)));
        }
        if ($request->filled('q')) {
            $search = '%'.$request->string('q')->trim().'%';
            $query->where(fn ($q) => $q
                ->where('stripe_id', 'like', $search)
                ->orWhere('idempotency_key', 'like', $search)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $search)->orWhere('email', 'like', $search))
                ->orWhere(fn ($o) => $o->where('transactable_type', Order::class)->whereIn('transactable_id', Order::where('reference', 'like', $search)->select('id')))
                ->orWhere(fn ($b) => $b->where('transactable_type', Booking::class)->whereIn('transactable_id', Booking::where('reference', 'like', $search)->select('id'))));
        }

        if ($request->query('format') === 'csv') {
            return $this->csv((clone $query)->get(), $from, $to);
        }

        $transactions = $query->paginate(50)->withQueryString();
        $transactions->getCollection()->loadMorph('transactable', [
            Order::class => ['event'],
            Booking::class => ['resource'],
            CustomerMembership::class => ['plan'],
            OrganizerSettlement::class => ['event'],
        ]);

        $byType = (clone $base)->selectRaw('type, status, COUNT(*) total, SUM(amount_cents) amount_cents')->groupBy('type', 'status')->get();
        $sum = fn (string $type) => (int) $byType->where('type', $type)->where('status', 'succeeded')->sum('amount_cents');
        $totals = [
            'gross' => $sum('gross_ticket_revenue'),
            'platform' => $sum('mwcn_platform_fee'),
            'processing' => $sum('payment_processing'),
            'refunds' => abs($sum('refund')),
            'organizer' => $sum('organizer_proceeds'),
            'paid_out' => (int) $byType->whereIn('type', ['organizer_transfer', 'organizer_payout'])->whereIn('status', ['succeeded', 'processing'])->sum('amount_cents'),
            'bookings' => (int) $byType->whereIn('type', ['studio_booking', 'booking_payment', 'soundtunnel_booking'])->where('status', 'succeeded')->sum('amount_cents'),
            'memberships' => (int) $byType->whereIn('type', ['membership', 'membership_payment'])->where('status', 'succeeded')->sum('amount_cents'),
            'pending' => (int) $byType->whereIn('status', ['pending', 'processing'])->sum('total'),
            'failed' => (int) $byType->where('status', 'failed')->sum('total'),
        ];
        $totals['mwcn_net'] = $totals['platform'] + $totals['bookings'] + $totals['memberships'] - $totals['refunds'];

        $events = Event::with('organizer', 'settlement', 'ticketTypes', 'orders')
            ->whereHas('orders')
            ->orWhereHas('settlement')
            ->latest('starts_at')
            ->get()
            ->map(fn (Event $event) => ['event' => $event, 'statement' => Finance::eventStatement($event)]);

        return view('admin.transactions', [
            'transactions' => $transactions,
            'totals' => $totals,
            'byType' => $byType,
            'events' => $events,
            'eventOptions' => Event::orderBy('title')->pluck('title', 'id'),
            'types' => Finance::TYPE_LABELS,
            'from' => $from,
            'to' => $to,
            'filters' => [
                'type' => (string) $request->query('type', ''),
                'status' => (string) $request->query('status', ''),
                'party' => (string) $request->query('party', ''),
                'event' => (string) $request->query('event', ''),
                'q' => (string) $request->query('q', ''),
            ],
        ]);
    }

    private function csv($rows, CarbonImmutable $from, CarbonImmutable $to)
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Date', 'Type', 'Direction', 'Status', 'Amount USD', 'Currency', 'Customer', 'Email', 'Related', 'Related ID', 'Stripe reference']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    'MWCN-'.str_pad($row->id, 6, '0', STR_PAD_LEFT),
                    $row->created_at->toDateTimeString(),
                    Finance::label($row->type),
                    Finance::party($row->type),
                    $row->status,
                    number_format($row->amount_cents / 100, 2, '.', ''),
                    strtoupper($row->currency ?? 'usd'),
                    $row->user?->name,
                    $row->user?->email,
                    class_basename((string) $row->transactable_type),
                    $row->transactable_id,
                    $row->stripe_id,
                ]);
            }
            fclose($out);
        }, 'mwcn-transactions-'.$from->toDateString().'-to-'.$to->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }
}
