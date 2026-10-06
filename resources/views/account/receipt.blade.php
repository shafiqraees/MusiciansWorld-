@extends('account.layout', ['title' => 'Receipt', 'current' => 'payments'])

<?php
$related = $transaction->transactable;
$reference = $related->reference ?? null;
$subject = match (true) {
    $related instanceof \App\Models\Booking => ($related->resource?->name ?? 'Booking').' · '.$related->starts_at?->timezone($related->resource?->timezone ?? 'America/Phoenix')->format('M j, Y g:i A'),
    $related instanceof \App\Models\Order => 'Tickets'.($related->event ? ' · '.$related->event->title : ''),
    $related instanceof \App\Models\CustomerMembership => ($related->plan?->name ?? 'Membership').' membership',
    default => \App\Support\Finance::label($transaction->type),
};
?>

@section('content')
<div class="acct-head">
  <div>
    <p class="kicker"><a href="{{ route('account.payments') }}" style="color:inherit">Payments &amp; Receipts</a></p>
    <h1>Receipt MWCN-{{ str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT) }}</h1>
    <p>MusiciansWorld Creative Network · {{ $transaction->created_at->format('M j, Y g:i A') }}</p>
  </div>
  <div class="acct-head-actions acct-actions" style="margin:0">
    <a class="btn btn-ghost" href="{{ route('account.payments') }}">Back to payments</a>
    <button class="btn btn-mark" type="button" onclick="window.print()">Print receipt</button>
  </div>
</div>

<article class="acct-panel acct-receipt">
  <dl class="acct-dl">
    <div><dt>Receipt</dt><dd>MWCN-{{ str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT) }}</dd></div>
    <div><dt>Account</dt><dd>{{ auth()->user()->name }} · {{ auth()->user()->email }}</dd></div>
    <div><dt>Description</dt><dd>{{ $subject }}</dd></div>
    @if($reference)<div><dt>Reference</dt><dd>{{ $reference }}</dd></div>@endif
    <div><dt>Type</dt><dd>{{ \App\Support\Finance::label($transaction->type) }}</dd></div>
    <div><dt>Status</dt><dd>{!! acct_badge($transaction->status) !!}</dd></div>
    <div><dt>Amount</dt><dd><strong style="font-size:1.2rem">{{ a_money($transaction->amount_cents) }}</strong> {{ strtoupper($transaction->currency ?: 'usd') }}</dd></div>
    <div><dt>Paid with</dt><dd>{{ $transaction->stripe_id ? 'Card, processed by Stripe' : 'MWCN desk' }}</dd></div>
  </dl>

  @if($related instanceof \App\Models\Order)
    <h3>Items</h3>
    <div class="acct-table-wrap">
      <table class="acct-table">
        <thead><tr><th>Ticket type</th><th class="is-num">Qty</th><th class="is-num">Unit</th><th class="is-num">Line</th></tr></thead>
        <tbody>
          @foreach($related->items as $item)
            <tr><td>{{ $item->ticketType?->name ?? 'Ticket' }}</td><td class="is-num">{{ $item->quantity }}</td><td class="is-num">{{ a_money($item->unit_price_cents) }}</td><td class="is-num">{{ a_money($item->total_cents) }}</td></tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr><td colspan="3">Subtotal</td><td class="is-num">{{ a_money($related->subtotal_cents) }}</td></tr>
          @if($related->processing_fee_cents)<tr><td colspan="3">Processing fee</td><td class="is-num">{{ a_money($related->processing_fee_cents) }}</td></tr>@endif
          <tr><td colspan="3">Total charged</td><td class="is-num" style="color:var(--mark)">{{ a_money($related->total_cents) }}</td></tr>
        </tfoot>
      </table>
    </div>
    @if($breakdown)
      <h3>Where your payment goes</h3>
      <div class="acct-flow">
        <div class="acct-flow-row"><div>You paid<span>Collected by MWCN</span></div><strong>{{ a_money($breakdown['customer_paid']) }}</strong></div>
        <div class="acct-flow-row is-minus"><div>MWCN platform fee<span>{{ \App\Support\Finance::percent($breakdown['platform_fee_bps']) }} of ticket price</span></div><strong>−{{ a_money($breakdown['platform_fee']) }}</strong></div>
        <div class="acct-flow-row is-minus"><div>Payment processing<span>{{ $breakdown['processing'] ? 'Card processor' : 'Reconciled at event closeout' }}</span></div><strong>−{{ a_money($breakdown['processing']) }}</strong></div>
        @if($breakdown['refunds'])<div class="acct-flow-row is-minus"><div>Refunded to you</div><strong>−{{ a_money($breakdown['refunds']) }}</strong></div>@endif
        <div class="acct-flow-row is-total"><div>Event organizer receives<span>{{ $related->event?->title }}</span></div><strong>{{ a_money($breakdown['organizer']) }}</strong></div>
      </div>
    @endif
  @elseif($related instanceof \App\Models\Booking)
    <h3>Booking</h3>
    <dl class="acct-dl">
      <div><dt>Space</dt><dd>{{ $related->resource?->name ?? '—' }}</dd></div>
      <div><dt>When</dt><dd>{{ $related->starts_at?->timezone($related->resource?->timezone ?? 'America/Phoenix')->format('D, M j, Y · g:i A') }} – {{ $related->ends_at?->timezone($related->resource?->timezone ?? 'America/Phoenix')->format('g:i A T') }}</dd></div>
      <div><dt>Subtotal</dt><dd>{{ a_money($related->subtotal_cents) }}</dd></div>
      @if($related->discount_cents)<div><dt>Member discount</dt><dd>−{{ a_money($related->discount_cents) }}</dd></div>@endif
      <div><dt>Total</dt><dd>{{ a_money($related->total_cents) }}</dd></div>
      <div><dt>Status</dt><dd>{!! acct_badge($related->status) !!} {!! acct_badge($related->payment_status) !!}</dd></div>
    </dl>
  @endif
</article>
@endsection
