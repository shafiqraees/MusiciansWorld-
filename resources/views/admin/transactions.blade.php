<?php
page_start('Transactions — MWCN ERP');
$period = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
$tone = fn (string $type) => in_array($type, ['mwcn_platform_fee', 'venue_charge', 'addon_charge'], true) ? 'color:var(--a-accent)' : (in_array($type, ['organizer_proceeds', 'organizer_transfer', 'organizer_payout'], true) ? 'color:var(--a-success)' : ($type === 'refund' || $type === 'payment_processing' ? 'color:var(--a-danger)' : ''));
$relatedLabel = function ($tx) {
    $t = $tx->transactable;
    return match (true) {
        $t instanceof \App\Models\Order => ['Order '.$t->reference, $t->event?->title],
        $t instanceof \App\Models\Booking => ['Booking '.$t->reference, $t->resource?->name],
        $t instanceof \App\Models\CustomerMembership => ['Membership', $t->plan?->name],
        $t instanceof \App\Models\OrganizerSettlement => ['Settlement #'.$t->id, $t->event?->title],
        default => [class_basename((string) $tx->transactable_type) ?: '—', null],
    };
};
?>
<section class="section"><div class="wrap">
  <div class="a-page-head">
    <div><p class="a-kicker">Finance</p><h2>Transactions &amp; money flow</h2><p>Every dollar that moves through the platform: what customers pay, what MWCN keeps, what processors take and what organizers are owed and paid.</p></div>
    <div class="a-page-actions">
      <a class="a-btn ghost" href="{{ route('admin.reports', $period) }}"><svg><use href="#ai-chart"></use></svg>Reports</a>
      <a class="a-btn primary" href="{{ route('admin.transactions', array_merge(request()->query(), ['format' => 'csv'])) }}"><svg><use href="#ai-download"></use></svg>Export CSV</a>
    </div>
  </div>

  <form method="get" class="a-toolbar">
    <label class="a-field"><span>From</span><input class="a-input is-sm" type="date" name="from" value="{{ $from->toDateString() }}"></label>
    <label class="a-field"><span>To</span><input class="a-input is-sm" type="date" name="to" value="{{ $to->toDateString() }}"></label>
    <label class="a-field"><span>Party</span>
      <select class="a-select is-sm" name="party">
        <option value="">All parties</option>
        @foreach(['customer' => 'Customer payments', 'mwcn' => 'MWCN revenue', 'organizer' => 'Organizer proceeds & payouts', 'processor' => 'Processing fees'] as $k => $v)<option value="{{ $k }}" @selected($filters['party'] === $k)>{{ $v }}</option>@endforeach
      </select>
    </label>
    <label class="a-field"><span>Type</span>
      <select class="a-select is-sm" name="type">
        <option value="">All types</option>
        @foreach($types as $k => $v)<option value="{{ $k }}" @selected($filters['type'] === $k)>{{ $v }}</option>@endforeach
      </select>
    </label>
    <label class="a-field"><span>Status</span>
      <select class="a-select is-sm" name="status">
        <option value="">Any</option>
        @foreach(['succeeded', 'pending', 'processing', 'failed', 'refunded'] as $s)<option value="{{ $s }}" @selected($filters['status'] === $s)>{{ a_label($s) }}</option>@endforeach
      </select>
    </label>
    <label class="a-field"><span>Event</span>
      <select class="a-select is-sm" name="event">
        <option value="">Any event</option>
        @foreach($eventOptions as $id => $title)<option value="{{ $id }}" @selected($filters['event'] === (string) $id)>{{ $title }}</option>@endforeach
      </select>
    </label>
    <label class="a-field grow"><span>Search</span><input class="a-input is-sm" name="q" value="{{ $filters['q'] }}" placeholder="Customer, email, order/booking reference, Stripe id"></label>
    <div class="a-actions"><button class="a-btn primary" type="submit">Apply</button><a class="a-btn ghost" href="{{ route('admin.transactions') }}">Reset</a></div>
  </form>

  <div class="a-stats is-5">
    <article class="a-stat"><small>Gross ticket sales</small><strong>{{ a_money($totals['gross']) }}</strong><span>Paid by customers</span></article>
    <article class="a-stat"><small>MWCN platform fees</small><strong>{{ a_money($totals['platform']) }}</strong><span>Kept from ticket sales</span></article>
    <article class="a-stat"><small>Organizer proceeds</small><strong>{{ a_money($totals['organizer']) }}</strong><span>{{ a_money($totals['paid_out']) }} transferred</span></article>
    <article class="a-stat"><small>Processing &amp; refunds</small><strong>{{ a_money($totals['processing'] + $totals['refunds']) }}</strong><span>{{ a_money($totals['processing']) }} processing · {{ a_money($totals['refunds']) }} refunds</span></article>
    <article class="a-stat"><small>MWCN net revenue</small><strong>{{ a_money($totals['mwcn_net']) }}</strong><span>Fees + bookings {{ a_money($totals['bookings']) }} + memberships {{ a_money($totals['memberships']) }}</span></article>
  </div>

  <div class="a-tabs" data-tabs="finance">
    <button class="a-tab is-active" type="button" data-tab="ledger">Ledger <b>{{ $transactions->total() }}</b></button>
    <button class="a-tab" type="button" data-tab="events">Event money flow <b>{{ $events->count() }}</b></button>
    <button class="a-tab" type="button" data-tab="summary">Summary by type</button>
  </div>

  <section class="a-card" data-tab-panel="ledger" data-tab-group="finance">
    <div class="a-card-head"><div><h3>Transaction ledger</h3><p>{{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }}{{ $totals['pending'] ? ' · '.$totals['pending'].' pending' : '' }}{{ $totals['failed'] ? ' · '.$totals['failed'].' failed' : '' }}</p></div></div>
    <div class="a-table-wrap"><table class="a-table"><thead><tr><th>Transaction</th><th>Type</th><th>Direction</th><th>Account</th><th>Related</th><th>Reference</th><th>Status</th><th class="num">Amount</th></tr></thead><tbody>
      @forelse($transactions as $tx)
        <?php [$rel, $relSub] = $relatedLabel($tx); ?>
        <tr>
          <td><strong>MWCN-{{ str_pad($tx->id, 6, '0', STR_PAD_LEFT) }}</strong><small>{{ $tx->created_at->format('M j, Y · g:i A') }}</small></td>
          <td>{{ \App\Support\Finance::label($tx->type) }}</td>
          <td><small style="font-size:12px">{{ \App\Support\Finance::party($tx->type) }}</small></td>
          <td>@if($tx->user)<a href="{{ route('admin.users.show', $tx->user) }}">{{ $tx->user->name }}</a><small>{{ $tx->user->email }}</small>@else<span class="a-sub">—</span>@endif</td>
          <td>{{ $rel }}@if($relSub)<small>{{ $relSub }}</small>@endif</td>
          <td><small style="font-size:12px">{{ $tx->stripe_id ?: '—' }}</small></td>
          <td><x-admin.badge :status="$tx->status" /></td>
          <td class="num" style="{{ $tone($tx->type) }}"><strong>{{ a_money($tx->amount_cents) }}</strong></td>
        </tr>
      @empty
        <tr><td colspan="8"><div class="a-table-empty"><strong>No transactions match</strong>Adjust the period or filters.</div></td></tr>
      @endforelse
    </tbody></table></div>
    @include('admin.partials.pagination', ['paginator' => $transactions])
  </section>

  <section class="a-card" data-tab-panel="events" data-tab-group="finance" hidden>
    <div class="a-card-head"><div><h3>Per-event money flow</h3><p>Customer paid → MWCN platform fee → processing / refunds / charges → organizer proceeds → payout.</p></div></div>
    <div class="a-table-wrap"><table class="a-table"><thead><tr><th>Event</th><th>Organizer</th><th class="num">Sold</th><th class="num">Gross</th><th class="num">MWCN fee</th><th class="num">Processing</th><th class="num">Refunds</th><th class="num">Venue / add-ons</th><th class="num">Organizer</th><th>Payout</th><th class="actions"></th></tr></thead><tbody>
      @forelse($events as $row)
        <?php $event = $row['event']; $s = $row['statement']; ?>
        <tr>
          <td><strong>{{ $event->title }}</strong><small>{{ $event->reference }} · {{ $event->starts_at?->format('M j, Y') }} · <x-admin.badge :status="$event->status" /></small></td>
          <td>@if($event->organizer)<a href="{{ route('admin.users.show', $event->organizer) }}">{{ $event->organizer->name }}</a>@else —@endif</td>
          <td class="num">{{ $s['tickets_sold'] }}<small>{{ $s['orders'] }} orders</small></td>
          <td class="num"><strong>{{ a_money($s['gross']) }}</strong></td>
          <td class="num" style="color:var(--a-accent)">{{ a_money($s['platform_fee']) }}<small>{{ \App\Support\Finance::percent($s['platform_fee_bps']) }}</small></td>
          <td class="num">{{ a_money($s['processing']) }}</td>
          <td class="num">{{ a_money($s['refunds']) }}</td>
          <td class="num">{{ a_money($s['venue'] + $s['addons']) }}</td>
          <td class="num" style="color:var(--a-success)"><strong>{{ a_money($s['organizer']) }}</strong></td>
          <td><x-admin.badge :status="$s['payout_status']" />@if($s['transfer_id'])<small>{{ $s['transfer_id'] }}</small>@endif</td>
          <td class="actions"><div class="a-row-actions"><a class="a-btn ghost sm" href="{{ route('admin.transactions', array_merge($period, ['event' => $event->id])) }}">Ledger</a><a class="a-btn ghost sm" href="{{ route('admin.events', ['search' => $event->reference]) }}">Manage</a></div></td>
        </tr>
      @empty
        <tr><td colspan="11"><div class="a-table-empty"><strong>No ticketed events yet</strong>Event money flow appears once an organizer sells tickets.</div></td></tr>
      @endforelse
    </tbody></table></div>
  </section>

  <section class="a-card" data-tab-panel="summary" data-tab-group="finance" hidden>
    <div class="a-card-head"><div><h3>Summary by type</h3><p>Grouped totals for the selected period, including pending and failed rows.</p></div></div>
    <div class="a-table-wrap"><table class="a-table"><thead><tr><th>Type</th><th>Direction</th><th>Status</th><th class="num">Count</th><th class="num">Total</th></tr></thead><tbody>
      @forelse($byType->sortBy([['type', 'asc'], ['status', 'asc']]) as $row)
        <tr>
          <td>{{ \App\Support\Finance::label($row->type) }}</td>
          <td><small style="font-size:12px">{{ \App\Support\Finance::party($row->type) }}</small></td>
          <td><x-admin.badge :status="$row->status" /></td>
          <td class="num">{{ $row->total }}</td>
          <td class="num" style="{{ $tone($row->type) }}"><strong>{{ a_money($row->amount_cents) }}</strong></td>
        </tr>
      @empty
        <tr><td colspan="5"><div class="a-table-empty"><strong>No transactions in this period</strong></div></td></tr>
      @endforelse
    </tbody></table></div>
  </section>
</div></section>
<?php page_end(); ?>
