@extends('account.layout', ['title' => 'Overview', 'current' => 'overview'])

@section('content')
<div class="acct-head">
  <div>
    <p class="kicker">Connected customer platform</p>
    <h1>Welcome, {{ $user->name }}</h1>
    <p>Account #{{ $user->id }} · member since {{ $user->created_at->format('M j, Y') }}. Everything you do with MWCN — studio time, DJ School, tickets, events and payments — is managed from the left menu.</p>
  </div>
  <div class="acct-head-actions">
    <a class="btn btn-ghost" href="/book">Book Studio A</a>
    <a class="btn btn-mark" href="{{ route('events.live') }}">Find events</a>
  </div>
</div>

<div class="acct-stats">
  <a class="acct-stat is-mark" href="{{ route('account.membership') }}"><small>Membership</small><strong>{{ $membership?->plan?->name ?? 'None' }}</strong><span>{{ $membership?->cancelled_at ? 'Ends '.$membership->current_period_end?->format('M j') : ($membership?->current_period_end ? 'Renews '.$membership->current_period_end->format('M j, Y') : 'No renewal date') }}</span></a>
  <a class="acct-stat" href="{{ route('account.bookings') }}"><small>Upcoming bookings</small><strong>{{ $upcomingBookings->count() }}</strong><span>{{ $upcomingBookings->first()?->starts_at?->timezone($upcomingBookings->first()?->resource?->timezone ?? 'America/Phoenix')->format('D, M j · g:i A') ?? 'Nothing scheduled' }}</span></a>
  <a class="acct-stat" href="{{ route('account.tickets') }}"><small>Upcoming tickets</small><strong>{{ $upcomingTickets->count() }}</strong><span>{{ $upcomingTickets->first()?->event?->title ?? 'No upcoming events' }}</span></a>
  <a class="acct-stat is-ok" href="{{ route('account.payments') }}"><small>Total paid</small><strong>{{ a_money($spent) }}</strong><span>{{ $recentReceipts->count() }} recent receipt{{ $recentReceipts->count() === 1 ? '' : 's' }}</span></a>
</div>

<div class="acct-grid-2">
  <div class="acct-panel">
    <div class="acct-panel-head"><h2>Next up</h2><a class="btn btn-ghost" href="{{ route('account.bookings') }}">All bookings</a></div>
    @forelse($upcomingBookings->take(3) as $booking)
      <div class="acct-item" style="margin-top:.75rem">
        <div class="acct-item-top">
          <div><strong>{{ $booking->resource->name ?? 'Studio A' }}</strong><span>{{ $booking->starts_at->timezone($booking->resource->timezone ?? 'America/Phoenix')->format('D, M j · g:i A') }} – {{ $booking->ends_at->timezone($booking->resource->timezone ?? 'America/Phoenix')->format('g:i A') }}</span></div>
          {!! acct_badge($booking->status) !!}
        </div>
      </div>
    @empty
      <p class="acct-empty">No upcoming studio bookings. <a href="/book">Reserve time in Studio A</a>.</p>
    @endforelse
  </div>

  <div class="acct-panel">
    <div class="acct-panel-head"><h2>Your tickets</h2><a class="btn btn-ghost" href="{{ route('account.tickets') }}">All tickets</a></div>
    @forelse($upcomingTickets->take(3) as $ticket)
      <div class="acct-item" style="margin-top:.75rem">
        <div class="acct-item-top">
          <div><strong>{{ $ticket->event->title }}</strong><span>{{ $ticket->event->starts_at?->timezone($ticket->event->resource->timezone ?? 'America/Phoenix')->format('D, M j · g:i A') }} · {{ $ticket->ticketType?->name ?? 'General admission' }}</span></div>
          {!! acct_badge($ticket->status) !!}
        </div>
      </div>
    @empty
      <p class="acct-empty">No upcoming tickets. <a href="{{ route('events.live') }}">Browse live events</a>.</p>
    @endforelse
  </div>
</div>

<div class="acct-grid-2">
  <div class="acct-panel">
    <div class="acct-panel-head"><h2>Recent payments</h2><a class="btn btn-ghost" href="{{ route('account.payments') }}">Payments &amp; receipts</a></div>
    @if($recentReceipts->isEmpty())
      <p class="acct-empty">No payments recorded yet.</p>
    @else
      <div class="acct-table-wrap">
        <table class="acct-table">
          <thead><tr><th>Date</th><th>Description</th><th>Status</th><th class="is-num">Amount</th></tr></thead>
          <tbody>
            @foreach($recentReceipts as $tx)
              <tr>
                <td>{{ $tx->created_at->format('M j, Y') }}</td>
                <td><a href="{{ route('account.receipts.show', $tx) }}">{{ \App\Support\Finance::label($tx->type) }}</a></td>
                <td>{!! acct_badge($tx->status) !!}</td>
                <td class="is-num acct-money {{ $tx->amount_cents < 0 ? 'is-neg' : '' }}">{{ a_money($tx->amount_cents) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <div class="acct-panel">
    <div class="acct-panel-head"><h2>Promoter workspace</h2><a class="btn btn-ghost" href="{{ route('account.events') }}">My events</a></div>
    @if($user->events->isEmpty())
      <p class="acct-empty">Host your own night at MWCN. Create an event, sell tickets through the platform, and track every dollar — gross sales, the MWCN platform fee, and your proceeds — from the My Events page.</p>
      <div class="acct-actions"><a class="btn btn-mark" href="{{ route('organizer.events.create') }}">Host an event</a></div>
    @else
      <dl class="acct-dl">
        <div><dt>Events</dt><dd>{{ $user->events->count() }} total · {{ $activeEvents }} active</dd></div>
        <div><dt>Payout account</dt><dd>{{ $user->organizerProfile?->stripe_account_id ? 'Connected' : 'Not connected' }}</dd></div>
        <div><dt>Tickets sold</dt><dd>{{ $user->events->sum(fn ($e) => $e->ticketTypes->sum('sold_count')) }}</dd></div>
        <div><dt>Awaiting payout</dt><dd>{{ $user->events->filter(fn ($e) => in_array($e->settlement?->status, ['pending_closeout', 'ready_for_approval', 'approved', 'admin_hold'], true))->count() }} event(s)</dd></div>
      </dl>
    @endif
  </div>
</div>
@endsection
