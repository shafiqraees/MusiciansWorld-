<?php page_start('Reports — MWCN ERP'); ?>
<section class="section"><div class="wrap">
  <div class="admin-page-head"><div><p class="kicker">Business intelligence</p><h2>Reporting</h2><p>Revenue, operations, memberships and organizer settlement in one view.</p></div><a class="btn btn-mark" href="{{ route('admin.reports',['format'=>'csv','from'=>$from->toDateString(),'to'=>$to->toDateString()]) }}">Export financial CSV</a></div>
  <form method="get" class="admin-panel mb-5 flex flex-wrap items-end gap-3 p-4">
    <label class="text-[10px] uppercase tracking-wider text-dim">From<input class="input mt-2 block" type="date" name="from" value="{{ $from->toDateString() }}"></label>
    <label class="text-[10px] uppercase tracking-wider text-dim">To<input class="input mt-2 block" type="date" name="to" value="{{ $to->toDateString() }}"></label>
    <button class="btn btn-mark">Apply period</button><a class="btn btn-ghost" href="{{ route('admin.reports') }}">This month</a>
  </form>
  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <article class="admin-metric-card"><small>Gross ticket revenue</small><strong>${{ number_format($totals['gross']/100,2) }}</strong><small>Before deductions</small></article>
    <article class="admin-metric-card"><small>MWCN platform fees</small><strong>${{ number_format($totals['platform']/100,2) }}</strong><small>Platform revenue</small></article>
    <article class="admin-metric-card"><small>Organizer proceeds</small><strong>${{ number_format($totals['organizer']/100,2) }}</strong><small>Before approved payout</small></article>
    <article class="admin-metric-card"><small>Refunds</small><strong>${{ number_format($totals['refunds']/100,2) }}</strong><small>Recorded adjustments</small></article>
  </div>
  <div class="mt-5 grid gap-5 lg:grid-cols-3">
    <article class="admin-panel p-6"><h3 class="display text-3xl">Financial ledger</h3>@forelse($revenue as $row)<p class="mt-3 flex justify-between gap-4"><span>{{ ucwords(str_replace('_',' ',$row->type)) }}</span><strong>${{ number_format($row->amount_cents/100,2) }}</strong></p>@empty<p class="mt-3 text-dim">No completed transactions in this period.</p>@endforelse</article>
    <article class="admin-panel p-6"><h3 class="display text-3xl">Bookings</h3>@forelse($bookings as $row)<p class="mt-3 flex justify-between"><span>{{ ucfirst(str_replace('_',' ',$row->status)) }}</span><strong>{{ $row->total }}</strong></p>@empty<p class="mt-3 text-dim">No bookings in this period.</p>@endforelse</article>
    <article class="admin-panel p-6"><h3 class="display text-3xl">Memberships</h3>@forelse($memberships as $row)<p class="mt-3">{{ $row->name }} · {{ ucfirst($row->status) }} <strong class="float-right">{{ $row->total }}</strong></p>@empty<p class="mt-3 text-dim">No memberships.</p>@endforelse</article>
  </div>
  <div class="admin-page-head mt-10"><div><h2>Event sales & settlement</h2><p>{{ $from->format('M j, Y') }} through {{ $to->format('M j, Y') }}</p></div></div>
  <div class="admin-panel overflow-x-auto"><table class="w-full min-w-[850px] text-left text-sm"><thead><tr><th>Event</th><th>Status</th><th>Orders</th><th>Gross</th><th>Organizer proceeds</th><th>Payout</th></tr></thead><tbody>@forelse($events as $event)<tr><td>{{ $event->title }}</td><td><span class="admin-status">{{ $event->status }}</span></td><td>{{ $event->orders_count }}</td><td>${{ number_format(($event->settlement?->gross_ticket_revenue_cents ?? 0)/100,2) }}</td><td>${{ number_format(($event->settlement?->organizer_proceeds_cents ?? 0)/100,2) }}</td><td>{{ $event->settlement?->status ?? 'Not eligible' }}</td></tr>@empty<tr><td colspan="6" class="py-12 text-center text-dim">No events in this reporting period.</td></tr>@endforelse</tbody></table></div>
</div></section>
<?php page_end(); ?>
