@extends('account.layout', ['title' => 'My Events', 'current' => 'events'])

@section('content')
<div class="acct-head">
  <div>
    <p class="kicker">My Events · Promoter workspace</p>
    <h1>Events you host</h1>
    <p>Create events, sell tickets through MWCN and follow the money on every event: gross sales, the MWCN platform fee, processing, refunds and what is paid out to you.</p>
  </div>
  <div class="acct-head-actions">
    @if($user->organizerProfile && !$user->organizerProfile->stripe_payouts_enabled)
      <a class="btn btn-ghost" href="{{ route('connect.start') }}">Connect payout account</a>
    @endif
    <a class="btn btn-mark" href="{{ route('organizer.events.create') }}">Host a new event</a>
  </div>
</div>

@if($user->organizerProfile)
  <div class="acct-note {{ $user->organizerProfile->stripe_payouts_enabled ? 'is-ok' : 'is-warn' }}">
    <span>Promoter profile <strong>{{ $user->organizerProfile->display_name }}</strong> · {{ a_label($user->organizerProfile->status) }} · payouts {{ $user->organizerProfile->stripe_payouts_enabled ? 'enabled' : 'not yet enabled' }}{{ $user->organizerProfile->stripe_account_id ? ' · '.$user->organizerProfile->stripe_account_id : '' }}</span>
    @unless($user->organizerProfile->stripe_payouts_enabled)<a class="acct-text-btn" href="{{ route('connect.start') }}">Finish payout setup</a>@endunless
  </div>
@endif

<div class="acct-stats">
  <div class="acct-stat is-mark"><small>Gross ticket sales</small><strong>{{ a_money($totals['gross']) }}</strong><span>{{ $totals['tickets'] }} tickets across {{ $user->events->count() }} event(s)</span></div>
  <div class="acct-stat"><small>MWCN platform fees</small><strong>{{ a_money($totals['platform_fee']) }}</strong><span>kept by MWCN per your fee tier</span></div>
  <div class="acct-stat is-ok"><small>Your proceeds</small><strong>{{ a_money($totals['organizer']) }}</strong><span>after fees, refunds and charges</span></div>
  <div class="acct-stat"><small>Paid out</small><strong>{{ a_money($totals['paid_out']) }}</strong><span>{{ a_money(max($totals['organizer'] - $totals['paid_out'], 0)) }} pending</span></div>
</div>

<div class="acct-panel">
  <div class="acct-panel-head">
    <h2>Events</h2>
    <div class="acct-filters">
      @foreach($groups as $key => $label)
        <a href="{{ route('account.events', $key ? ['status' => $key] : []) }}" class="{{ $filter === $key ? 'is-on' : '' }}">{{ $label }}</a>
      @endforeach
    </div>
  </div>

  @forelse($events as $event)
    <?php $s = $statements[$event->id]; $tz = $event->resource->timezone ?? 'America/Phoenix'; $cap = (int) $event->ticketTypes->sum('quantity'); ?>
    <div class="acct-item" style="margin-top:.85rem">
      <div class="acct-item-top">
        <div>
          <strong><a href="{{ route('account.events.show', $event) }}">{{ $event->title }}</a></strong>
          <span>{{ $event->reference }} · {{ $event->starts_at?->timezone($tz)->format('D, M j, Y · g:i A T') }} · {{ $event->venue_name ?: ($event->resource->name ?? a_label($event->location_type)) }}</span>
        </div>
        <div class="acct-pills">{!! acct_badge($event->status) !!} {!! acct_badge($s['payout_status'], 'Payout: '.a_label($s['payout_status'])) !!}</div>
      </div>
      <div class="acct-table-wrap">
        <table class="acct-table">
          <thead><tr><th>Tickets sold</th><th class="is-num">Gross</th><th class="is-num">MWCN fee ({{ \App\Support\Finance::percent($s['platform_fee_bps']) }})</th><th class="is-num">Processing</th><th class="is-num">Refunds</th><th class="is-num">Venue &amp; add-ons</th><th class="is-num">Your proceeds</th></tr></thead>
          <tbody>
            <tr>
              <td>{{ $s['tickets_sold'] }}{{ $cap ? ' / '.$cap : '' }} <small>{{ $s['orders'] }} order(s)</small></td>
              <td class="is-num">{{ a_money($s['gross']) }}</td>
              <td class="is-num acct-money is-neg">−{{ a_money($s['platform_fee']) }}</td>
              <td class="is-num acct-money is-neg">−{{ a_money($s['processing']) }}</td>
              <td class="is-num acct-money is-neg">−{{ a_money($s['refunds']) }}</td>
              <td class="is-num acct-money is-neg">−{{ a_money($s['venue'] + $s['addons']) }}</td>
              <td class="is-num acct-money is-pos"><strong>{{ a_money($s['organizer']) }}</strong></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="acct-actions">
        <div class="acct-pills">
          <a class="btn btn-ghost" href="{{ route('account.events.show', $event) }}">Sales &amp; transactions</a>
          @if(in_array($event->status, ['draft', 'changes_requested', 'rejected'], true))
            <a class="btn btn-ghost" href="{{ route('organizer.events.edit', $event) }}">Edit</a>
            <form method="post" action="{{ route('organizer.events.submit', $event) }}" style="display:inline">@csrf<button class="btn btn-mark" type="submit">Submit for review</button></form>
          @elseif(in_array($event->status, ['approved', 'published'], true))
            <a class="btn btn-ghost" href="{{ route('events.show', $event->slug) }}">Public page</a>
          @endif
        </div>
        @if($event->review_notes && in_array($event->status, ['changes_requested', 'rejected'], true))<span class="acct-meta">MWCN review: {{ $event->review_notes }}</span>@endif
      </div>
    </div>
  @empty
    <p class="acct-empty">{{ $filter ? 'No events in this group.' : 'You have not created an event yet.' }} <a href="{{ route('organizer.events.create') }}">Host an event</a>.</p>
  @endforelse
</div>

<div class="acct-panel">
  <h2>How event money flows</h2>
  <dl class="acct-dl">
    <div><dt>1 · Customer pays</dt><dd>Attendees buy tickets on the MWCN event page. The full ticket price is collected by MWCN.</dd></div>
    <div><dt>2 · MWCN platform fee</dt><dd>MWCN keeps a percentage of each paid ticket set by your membership tier (Founder {{ \App\Support\Finance::percent(300) }}, Community {{ \App\Support\Finance::percent(500) }}). The rate is locked when the event is created.</dd></div>
    <div><dt>3 · Processing, refunds &amp; charges</dt><dd>Card processing, any refunds, venue and add-on services you ordered are deducted at closeout.</dd></div>
    <div><dt>4 · Payout</dt><dd>After the event completes, MWCN finance reviews the settlement and transfers your proceeds to your connected payout account.</dd></div>
  </dl>
</div>
@endsection
