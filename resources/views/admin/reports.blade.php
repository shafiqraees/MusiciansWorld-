<?php page_start('Reports — MWCN ERP'); ?>
<section class="section"><div class="wrap">
  <div class="a-page-head">
    <div><p class="a-kicker">Business intelligence</p><h2>Reporting</h2><p>Revenue, operations, memberships and organizer settlement for the selected period.</p></div>
    <div class="a-page-actions"><a class="a-btn primary" href="{{ route('admin.reports', ['format' => 'csv', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"><svg><use href="#ai-download"></use></svg>Export financial CSV</a></div>
  </div>
  <form method="get" class="a-toolbar">
    <label class="a-field"><span>From</span><input class="a-input" type="date" name="from" value="{{ $from->toDateString() }}"></label>
    <label class="a-field"><span>To</span><input class="a-input" type="date" name="to" value="{{ $to->toDateString() }}"></label>
    <div class="a-actions"><button class="a-btn primary" type="submit">Apply period</button><a class="a-btn ghost" href="{{ route('admin.reports') }}">This month</a><a class="a-btn ghost" href="{{ route('admin.reports', ['from' => now()->subDays(90)->toDateString(), 'to' => now()->toDateString()]) }}">Last 90 days</a></div>
  </form>
  <div class="a-stats">
    <article class="a-stat"><small>Gross ticket revenue</small><strong>{{ a_money($totals['gross']) }}</strong><span>Before deductions</span></article>
    <article class="a-stat"><small>MWCN platform fees</small><strong>{{ a_money($totals['platform']) }}</strong><span>Platform revenue</span></article>
    <article class="a-stat"><small>Organizer proceeds</small><strong>{{ a_money($totals['organizer']) }}</strong><span>Before approved payout</span></article>
    <article class="a-stat"><small>Refunds</small><strong>{{ a_money($totals['refunds']) }}</strong><span>Recorded adjustments</span></article>
  </div>
  <div class="a-grid cols-3">
    <section class="a-card" style="margin:0"><div class="a-card-head"><div><h3>Financial ledger</h3></div></div><div class="a-card-body">@forelse($revenue as $row)<p style="display:flex;justify-content:space-between;gap:12px;margin:0 0 10px"><span>{{ a_label($row->type) }}</span><strong>{{ a_money($row->amount_cents) }}</strong></p>@empty<p class="a-sub" style="margin:0">No completed transactions in this period.</p>@endforelse</div></section>
    <section class="a-card" style="margin:0"><div class="a-card-head"><div><h3>Bookings</h3></div></div><div class="a-card-body">@forelse($bookings as $row)<p style="display:flex;justify-content:space-between;gap:12px;margin:0 0 10px"><x-admin.badge :status="$row->status" /><strong>{{ $row->total }}</strong></p>@empty<p class="a-sub" style="margin:0">No bookings in this period.</p>@endforelse</div></section>
    <section class="a-card" style="margin:0"><div class="a-card-head"><div><h3>Memberships</h3></div></div><div class="a-card-body">@forelse($memberships as $row)<p style="display:flex;justify-content:space-between;gap:12px;margin:0 0 10px"><span>{{ $row->name }} <small class="a-sub" style="display:inline">· {{ a_label($row->status) }}</small></span><strong>{{ $row->total }}</strong></p>@empty<p class="a-sub" style="margin:0">No memberships.</p>@endforelse</div></section>
  </div>
  <section class="a-card" style="margin-top:20px">
    <div class="a-card-head"><div><h3>Event sales & settlement</h3><p>{{ $from->format('M j, Y') }} through {{ $to->format('M j, Y') }}</p></div></div>
    <div class="a-table-wrap"><table class="a-table"><thead><tr><th>Event</th><th>Status</th><th class="num">Orders</th><th class="num">Gross</th><th class="num">Organizer proceeds</th><th>Payout</th></tr></thead><tbody>
      @forelse($events as $event)<tr><td><strong>{{ $event->title }}</strong><small>{{ $event->starts_at->format('M j, Y') }}</small></td><td><x-admin.badge :status="$event->status" /></td><td class="num">{{ $event->orders_count }}</td><td class="num">{{ a_money($event->settlement?->gross_ticket_revenue_cents ?? 0) }}</td><td class="num">{{ a_money($event->settlement?->organizer_proceeds_cents ?? 0) }}</td><td>@if($event->settlement)<x-admin.badge :status="$event->settlement->status" />@else<span class="a-sub">Not eligible</span>@endif</td></tr>
      @empty<tr><td colspan="6"><div class="a-table-empty"><strong>No events in this reporting period</strong>Adjust the dates to see event sales.</div></td></tr>@endforelse
    </tbody></table></div>
  </section>
</div></section>
<?php page_end(); ?>
