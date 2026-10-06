@extends('account.layout', ['title' => 'Payments & Receipts', 'current' => 'payments'])

<?php
$filters = ['' => 'All', 'tickets' => 'Tickets', 'bookings' => 'Bookings', 'memberships' => 'Memberships', 'refunds' => 'Refunds'];
$describe = function ($tx) {
    $t = $tx->transactable;
    return match (true) {
        $t instanceof \App\Models\Order => $t->event?->title ?? 'Event tickets',
        $t instanceof \App\Models\Booking => ($t->resource?->name ?? 'Studio').($t->starts_at ? ' · '.$t->starts_at->timezone($t->resource?->timezone ?? 'America/Phoenix')->format('M j, Y') : ' booking'),
        $t instanceof \App\Models\CustomerMembership => ($t->plan?->name ?? 'Membership').' membership',
        default => \App\Support\Finance::label($tx->type),
    };
};
?>

@section('content')
<div class="acct-head">
  <div>
    <p class="kicker">Payments &amp; Receipts</p>
    <h1>Transaction history</h1>
    <p>Every charge and refund on your account with a printable receipt. Ticket receipts also show how the money is split between MWCN and the event organizer.</p>
  </div>
</div>

<div class="acct-stats">
  <div class="acct-stat is-mark"><small>Total paid</small><strong>{{ a_money($totals['paid']) }}</strong><span>succeeded payments</span></div>
  <div class="acct-stat"><small>Refunded</small><strong>{{ a_money($totals['refunded']) }}</strong><span>returned to you</span></div>
  <div class="acct-stat"><small>Transactions</small><strong>{{ $totals['count'] }}</strong><span>on record</span></div>
  <div class="acct-stat"><small>Last payment</small><strong>{{ $totals['last']?->format('M j') ?? '—' }}</strong><span>{{ $totals['last']?->format('Y') ?? 'none yet' }}</span></div>
</div>

<div class="acct-panel">
  <div class="acct-panel-head">
    <h2>Receipts</h2>
    <div class="acct-filters">
      @foreach($filters as $key => $label)
        <a href="{{ route('account.payments', $key ? ['type' => $key] : []) }}" class="{{ $filter === $key ? 'is-on' : '' }}">{{ $label }}</a>
      @endforeach
    </div>
  </div>
  @if($transactions->isEmpty())
    <p class="acct-empty">No transactions in this view.</p>
  @else
    <div class="acct-table-wrap">
      <table class="acct-table">
        <thead><tr><th>Date</th><th>Receipt</th><th>Description</th><th>Type</th><th>Reference</th><th>Status</th><th class="is-num">Amount</th></tr></thead>
        <tbody>
          @foreach($transactions as $tx)
            <tr>
              <td class="is-nowrap">{{ $tx->created_at->format('M j, Y') }}<small>{{ $tx->created_at->format('g:i A') }}</small></td>
              <td class="is-nowrap"><a href="{{ route('account.receipts.show', $tx) }}">MWCN-{{ str_pad($tx->id, 6, '0', STR_PAD_LEFT) }}</a></td>
              <td>{{ $describe($tx) }}
                @if($tx->transactable instanceof \App\Models\Order)
                  <?php $flow = \App\Support\Finance::orderBreakdown($tx->transactable); ?>
                  <small>Tickets {{ a_money($flow['tickets']) }} · MWCN fee {{ a_money($flow['platform_fee']) }} · organizer {{ a_money($flow['organizer']) }}</small>
                @endif
              </td>
              <td>{{ \App\Support\Finance::label($tx->type) }}</td>
              <td class="is-nowrap">{{ $tx->transactable->reference ?? '—' }}</td>
              <td>{!! acct_badge($tx->status) !!}</td>
              <td class="is-num acct-money {{ $tx->amount_cents < 0 || $tx->type === 'refund' ? 'is-neg' : '' }}">{{ $tx->type === 'refund' && $tx->amount_cents > 0 ? '−' : '' }}{{ a_money($tx->amount_cents) }} <small>{{ strtoupper($tx->currency ?? 'usd') }}</small></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

<div class="acct-panel">
  <h2>Need help with a charge?</h2>
  <p class="acct-lead">Open the receipt and quote its MWCN number when you <a href="/contact">contact us</a>. Refunds for refundable tickets and bookings are returned to the original payment method and appear here once processed.</p>
</div>
@endsection
