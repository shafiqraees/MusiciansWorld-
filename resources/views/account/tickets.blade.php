@extends('account.layout', ['title' => 'My Tickets', 'current' => 'tickets'])

@section('content')
<div class="acct-head">
  <div>
    <p class="kicker">My Tickets</p>
    <h1>Tickets &amp; orders</h1>
    <p>Show the QR code at the door — it's unique to each ticket and is marked used when scanned. Every order below links to its receipt.</p>
  </div>
  <a class="btn btn-mark" href="{{ route('events.live') }}">Find events</a>
</div>

<div class="acct-stats">
  <div class="acct-stat is-mark"><small>Upcoming</small><strong>{{ $upcoming->count() }}</strong><span>tickets ready to scan</span></div>
  <div class="acct-stat"><small>Attended</small><strong>{{ $past->whereIn('status', ['checked_in', 'used'])->count() }}</strong><span>checked in</span></div>
  <div class="acct-stat"><small>Orders</small><strong>{{ $orders->count() }}</strong><span>ticket purchases</span></div>
  <div class="acct-stat is-ok"><small>Spent on tickets</small><strong>{{ a_money($orders->where('payment_status', 'paid')->sum('total_cents')) }}</strong><span>paid orders</span></div>
</div>

<div class="acct-panel">
  <h2>Upcoming events</h2>
  @forelse($upcoming as $ticket)
    <?php $tz = $ticket->event->resource->timezone ?? 'America/Phoenix'; ?>
    <div class="acct-item acct-ticket" style="margin-top:.85rem">
      <img src="{{ route('tickets.qr', $ticket) }}" alt="QR code for ticket #{{ $ticket->id }}" loading="lazy">
      <div>
        <div class="acct-item-top">
          <div>
            <strong><a href="{{ route('events.show', $ticket->event->slug) }}">{{ $ticket->event->title }}</a></strong>
            <span>{{ $ticket->event->starts_at?->timezone($tz)->format('D, M j, Y · g:i A T') }}{{ $ticket->event->doors_at ? ' · doors '.$ticket->event->doors_at->timezone($tz)->format('g:i A') : '' }}</span>
            <span>{{ $ticket->event->venue_name ?: ($ticket->event->resource->name ?? 'MWCN') }}</span>
          </div>
          {!! acct_badge($ticket->status) !!}
        </div>
        <dl class="acct-dl">
          <div><dt>Ticket</dt><dd>#{{ $ticket->id }} · {{ $ticket->ticketType?->name ?? 'General admission' }} · {{ a_money($ticket->ticketType?->price_cents ?? 0) }}</dd></div>
          <div><dt>Attendee</dt><dd>{{ $ticket->attendee_name ?: $user->name }}</dd></div>
          <div><dt>Order</dt><dd>{{ $ticket->order?->reference ?? '—' }} · {{ $ticket->order ? a_money($ticket->order->total_cents) : '' }} {!! $ticket->order ? acct_badge($ticket->order->payment_status) : '' !!}</dd></div>
        </dl>
      </div>
    </div>
  @empty
    <p class="acct-empty">No upcoming tickets. <a href="{{ route('events.live') }}">Browse live events</a>.</p>
  @endforelse
</div>

<div class="acct-panel">
  <h2>Order history</h2>
  @if($orders->isEmpty())
    <p class="acct-empty">No ticket orders yet.</p>
  @else
    <div class="acct-stack">
      @foreach($orders as $order)
        <?php $flow = \App\Support\Finance::orderBreakdown($order); $receipt = $order->transactions->where('type', 'gross_ticket_revenue')->first(); ?>
        <div class="acct-item">
          <div class="acct-item-top">
            <div>
              <strong>{{ $order->reference }} · {{ $order->event?->title ?? 'Event' }}</strong>
              <span>{{ $order->created_at->format('M j, Y · g:i A') }} · {{ $order->items->sum('quantity') }} ticket(s)</span>
            </div>
            <div class="acct-pills">{!! acct_badge($order->payment_status) !!} @if($receipt)<a class="acct-status is-mark" href="{{ route('account.receipts.show', $receipt) }}">Receipt</a>@endif</div>
          </div>
          <div class="acct-table-wrap">
            <table class="acct-table">
              <thead><tr><th>Ticket type</th><th class="is-num">Qty</th><th class="is-num">Unit</th><th class="is-num">Line</th></tr></thead>
              <tbody>
                @foreach($order->items as $item)
                  <tr><td>{{ $item->ticketType?->name ?? 'Ticket' }}</td><td class="is-num">{{ $item->quantity }}</td><td class="is-num">{{ a_money($item->unit_price_cents) }}</td><td class="is-num">{{ a_money($item->total_cents) }}</td></tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr><td colspan="3">Tickets subtotal</td><td class="is-num">{{ a_money($flow['tickets']) }}</td></tr>
                @if($order->processing_fee_cents)<tr><td colspan="3">Processing fee</td><td class="is-num">{{ a_money($order->processing_fee_cents) }}</td></tr>@endif
                <tr><td colspan="3">You paid</td><td class="is-num" style="color:var(--mark)">{{ a_money($flow['customer_paid']) }}</td></tr>
                @if($flow['refunds'])<tr><td colspan="3">Refunded</td><td class="is-num acct-money is-neg">−{{ a_money($flow['refunds']) }}</td></tr>@endif
              </tfoot>
            </table>
          </div>
          <p class="acct-meta" style="margin:.6rem 0 0;font-size:.82rem">Where it goes: MWCN platform fee {{ \App\Support\Finance::percent($flow['platform_fee_bps']) }} ({{ a_money($flow['platform_fee']) }}) · organizer receives {{ a_money($flow['organizer']) }}.</p>
        </div>
      @endforeach
    </div>
  @endif
</div>

@if($past->isNotEmpty())
<div class="acct-panel">
  <h2>Past tickets</h2>
  <div class="acct-table-wrap">
    <table class="acct-table">
      <thead><tr><th>Event</th><th>Date</th><th>Type</th><th>Status</th><th>Order</th></tr></thead>
      <tbody>
        @foreach($past as $ticket)
          <tr>
            <td>{{ $ticket->event?->title ?? '—' }}</td>
            <td>{{ $ticket->event?->starts_at?->timezone($ticket->event->resource->timezone ?? 'America/Phoenix')->format('M j, Y') ?? '—' }}</td>
            <td>{{ $ticket->ticketType?->name ?? '—' }}</td>
            <td>{!! acct_badge($ticket->status) !!}</td>
            <td>{{ $ticket->order?->reference ?? '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif
@endsection
