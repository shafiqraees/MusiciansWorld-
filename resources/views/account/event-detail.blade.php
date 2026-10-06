@extends('account.layout', ['title' => $event->title, 'current' => 'events'])

<?php $tz = $event->resource->timezone ?? 'America/Phoenix'; $s = $statement; ?>

@section('content')
<div class="acct-head">
  <div>
    <p class="kicker"><a href="{{ route('account.events') }}" style="color:inherit">My Events</a> · {{ $event->reference }}</p>
    <h1>{{ $event->title }}</h1>
    <p>{{ $event->starts_at?->timezone($tz)->format('l, F j, Y · g:i A T') }}{{ $event->ends_at ? ' – '.$event->ends_at->timezone($tz)->format('g:i A') : '' }} · {{ $event->venue_name ?: ($event->resource->name ?? a_label($event->location_type)) }}</p>
  </div>
  <div class="acct-head-actions">
    {!! acct_badge($event->status) !!}
    @if(in_array($event->status, ['draft', 'changes_requested', 'rejected'], true))<a class="btn btn-ghost" href="{{ route('organizer.events.edit', $event) }}">Edit event</a>@endif
    @if(in_array($event->status, ['approved', 'published'], true))<a class="btn btn-ghost" href="{{ route('events.show', $event->slug) }}">Public page</a>@endif
  </div>
</div>

<div class="acct-stats">
  <div class="acct-stat is-mark"><small>Tickets sold</small><strong>{{ $s['tickets_sold'] }}</strong><span>{{ $issued }} issued · {{ $checkedIn }} checked in</span></div>
  <div class="acct-stat"><small>Gross sales</small><strong>{{ a_money($s['gross']) }}</strong><span>{{ $s['orders'] }} order(s)</span></div>
  <div class="acct-stat"><small>MWCN fee</small><strong>{{ a_money($s['platform_fee']) }}</strong><span>{{ \App\Support\Finance::percent($s['platform_fee_bps']) }} of paid tickets</span></div>
  <div class="acct-stat is-ok"><small>Your proceeds</small><strong>{{ a_money($s['organizer']) }}</strong><span>payout {{ a_label($s['payout_status']) }}</span></div>
</div>

<div class="acct-grid-2">
  <div class="acct-panel">
    <h2>Settlement statement</h2>
    <p class="acct-lead">{{ $s['settlement'] ? 'Calculated by MWCN finance on '.$s['settlement']->updated_at->format('M j, Y').'.' : 'Live estimate from recorded ticket sales. MWCN finance finalizes the statement after the event completes.' }}</p>
    <div class="acct-flow">
      <div class="acct-flow-row"><div>Gross ticket revenue<span>Collected from attendees</span></div><strong>{{ a_money($s['gross']) }}</strong></div>
      <div class="acct-flow-row is-minus"><div>MWCN platform fee<span>{{ \App\Support\Finance::percent($s['platform_fee_bps']) }} — kept by MWCN</span></div><strong>−{{ a_money($s['platform_fee']) }}</strong></div>
      <div class="acct-flow-row is-minus"><div>Payment processing<span>{{ $s['processing'] ? 'Card processor fees' : 'Reconciled at closeout' }}</span></div><strong>−{{ a_money($s['processing']) }}</strong></div>
      <div class="acct-flow-row is-minus"><div>Refunds<span>Returned to attendees</span></div><strong>−{{ a_money($s['refunds']) }}</strong></div>
      <div class="acct-flow-row is-minus"><div>Venue charges<span>Space and staffing</span></div><strong>−{{ a_money($s['venue']) }}</strong></div>
      <div class="acct-flow-row is-minus"><div>Add-on services<span>{{ $event->services->isNotEmpty() ? $event->services->pluck('name')->join(', ') : 'None ordered' }}</span></div><strong>−{{ a_money($s['addons']) }}</strong></div>
      @if($s['adjustments'])<div class="acct-flow-row"><div>Adjustments<span>Authorized by MWCN finance</span></div><strong>{{ $s['adjustments'] < 0 ? '−' : '+' }}{{ a_money(abs($s['adjustments'])) }}</strong></div>@endif
      <div class="acct-flow-row is-total"><div>Organizer proceeds<span>Paid to your connected account</span></div><strong>{{ a_money($s['organizer']) }}</strong></div>
    </div>
    <dl class="acct-dl">
      <div><dt>Payout status</dt><dd>{!! acct_badge($s['payout_status']) !!}</dd></div>
      <div><dt>Transfer reference</dt><dd>{{ $s['transfer_id'] ?: '—' }}</dd></div>
      @if($s['hold_reason'])<div><dt>Hold reason</dt><dd>{{ $s['hold_reason'] }}</dd></div>@endif
      @if($s['paid_at'])<div><dt>Paid on</dt><dd>{{ $s['paid_at']->format('M j, Y') }}</dd></div>@endif
    </dl>
  </div>

  <div class="acct-panel">
    <h2>Ticket types</h2>
    <div class="acct-table-wrap">
      <table class="acct-table">
        <thead><tr><th>Type</th><th class="is-num">Price</th><th class="is-num">Sold</th><th class="is-num">Available</th><th class="is-num">Revenue</th><th>Status</th></tr></thead>
        <tbody>
          @forelse($event->ticketTypes as $type)
            <tr>
              <td>{{ $type->name }}</td>
              <td class="is-num">{{ a_money($type->price_cents) }}</td>
              <td class="is-num">{{ $type->sold_count }}</td>
              <td class="is-num">{{ $type->quantity !== null ? max($type->quantity - $type->sold_count, 0) : '∞' }}</td>
              <td class="is-num">{{ a_money($type->sold_count * $type->price_cents) }}</td>
              <td>{!! acct_badge($type->status) !!}</td>
            </tr>
          @empty
            <tr><td colspan="6">No ticket types yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <h3>Event details</h3>
    <dl class="acct-dl">
      <div><dt>Category</dt><dd>{{ a_label($event->category) }}</dd></div>
      <div><dt>Capacity</dt><dd>{{ $event->capacity ?: '—' }}</dd></div>
      <div><dt>Doors</dt><dd>{{ $event->doors_at?->timezone($tz)->format('g:i A') ?? '—' }}</dd></div>
      <div><dt>Visibility</dt><dd>{{ a_label($event->visibility) }}</dd></div>
      @if($event->review_notes)<div><dt>MWCN review notes</dt><dd>{{ $event->review_notes }}</dd></div>@endif
    </dl>
  </div>
</div>

<div class="acct-panel">
  <h2>Ticket orders</h2>
  @if($orders->isEmpty())
    <p class="acct-empty">No orders yet.</p>
  @else
    <div class="acct-table-wrap">
      <table class="acct-table">
        <thead><tr><th>Order</th><th>Buyer</th><th>Tickets</th><th>Payment</th><th class="is-num">Customer paid</th><th class="is-num">MWCN fee</th><th class="is-num">Processing</th><th class="is-num">Refunded</th><th class="is-num">To you</th></tr></thead>
        <tbody>
          @foreach($orders as $order)
            <?php $flow = \App\Support\Finance::orderBreakdown($order); ?>
            <tr>
              <td>{{ $order->reference }}<small>{{ $order->created_at->format('M j, Y · g:i A') }}</small></td>
              <td>{{ $order->buyer_name }}<small>{{ $order->buyer_email }}</small></td>
              <td>{{ $order->items->sum('quantity') }}<small>{{ $order->items->map(fn ($i) => $i->quantity.'× '.($i->ticketType?->name ?? 'Ticket'))->join(', ') }}</small></td>
              <td>{!! acct_badge($order->payment_status) !!}</td>
              <td class="is-num">{{ a_money($flow['customer_paid']) }}</td>
              <td class="is-num acct-money is-neg">−{{ a_money($flow['platform_fee']) }}</td>
              <td class="is-num acct-money is-neg">−{{ a_money($flow['processing']) }}</td>
              <td class="is-num acct-money is-neg">−{{ a_money($flow['refunds']) }}</td>
              <td class="is-num acct-money is-pos">{{ a_money($flow['organizer']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

<div class="acct-panel">
  <h2>Transaction ledger</h2>
  <p class="acct-lead">Every money movement recorded for this event, in the same ledger MWCN finance uses.</p>
  @if($ledger->isEmpty())
    <p class="acct-empty">No transactions recorded yet.</p>
  @else
    <div class="acct-table-wrap">
      <table class="acct-table">
        <thead><tr><th>Date</th><th>Transaction</th><th>Direction</th><th>Reference</th><th>Status</th><th class="is-num">Amount</th></tr></thead>
        <tbody>
          @foreach($ledger as $tx)
            <tr>
              <td>{{ $tx->created_at->format('M j, Y · g:i A') }}</td>
              <td>{{ \App\Support\Finance::label($tx->type) }}<small>MWCN-{{ str_pad($tx->id, 6, '0', STR_PAD_LEFT) }}</small></td>
              <td><small style="font-size:.85rem">{{ \App\Support\Finance::party($tx->type) }}</small></td>
              <td>{{ $tx->transactable->reference ?? ($orders->firstWhere('id', $tx->transactable_id)?->reference ?? '—') }}</td>
              <td>{!! acct_badge($tx->status) !!}</td>
              <td class="is-num acct-money {{ in_array($tx->type, ['mwcn_platform_fee', 'payment_processing', 'refund', 'venue_charge', 'addon_charge'], true) ? 'is-neg' : (in_array($tx->type, ['organizer_proceeds', 'organizer_transfer', 'organizer_payout'], true) ? 'is-pos' : '') }}">{{ a_money($tx->amount_cents) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
