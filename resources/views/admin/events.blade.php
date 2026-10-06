<?php
page_start('Events & approvals — MWCN ERP');
$canApprove = auth()->user()->hasPermission('events.approve');
$canSettle = auth()->user()->hasPermission('settlements.manage');
$dt = fn ($value) => $value?->format('Y-m-d\TH:i');
$reviewActions = [
    'approve' => ['Approve', 'Marks the event approved and reserves SoundTunnel if selected.'],
    'publish' => ['Publish', 'Makes the event visible and opens ticket sales. Requires approval first.'],
    'request_changes' => ['Request changes', 'Sends the event back to the organizer with your notes.'],
    'suspend' => ['Suspend', 'Hides a published event and pauses sales.'],
    'complete' => ['Complete', 'Closes the event and opens the organizer settlement.'],
    'reject' => ['Reject', 'Declines the event. The organizer is notified.'],
];
?>
<section class="section"><div class="wrap">
  <div class="a-page-head">
    <div>
      <p class="a-kicker">Operations</p>
      <h2>Events & approvals</h2>
      <p>Review organizer submissions, manage event details, publish to the public calendar and settle organizer payouts.</p>
    </div>
    <div class="a-page-actions">
      <a class="a-btn ghost" href="{{ route('events.live') }}" target="_blank" rel="noopener"><svg><use href="#ai-globe"></use></svg>Public events page</a>
      <a class="a-btn ghost" href="{{ route('admin.export', ['type' => 'events']) }}"><svg><use href="#ai-download"></use></svg>Export CSV</a>
    </div>
  </div>

  <div class="a-stats">
    <article class="a-stat"><small>Awaiting review</small><strong>{{ number_format($stats['pending']) }}</strong><span>Submitted by organizers</span></article>
    <article class="a-stat"><small>Live & published</small><strong>{{ number_format($stats['published']) }}</strong><span>Upcoming on the public site</span></article>
    <article class="a-stat"><small>Tickets sold</small><strong>{{ number_format($stats['tickets']) }}</strong><span>Across all events</span></article>
    <article class="a-stat"><small>Settlements open</small><strong>{{ number_format($stats['payouts']) }}</strong><span>Awaiting closeout or transfer</span></article>
  </div>

  <div class="a-chips">
    <a class="a-chip {{ !request('status') ? 'is-active' : '' }}" href="{{ route('admin.events', request()->except('status', 'page')) }}">All statuses</a>
    @foreach(['submitted', 'approved', 'published', 'changes_requested', 'draft', 'completed', 'suspended', 'rejected'] as $status)
      <a class="a-chip {{ request('status') === $status ? 'is-active' : '' }}" href="{{ route('admin.events', array_merge(request()->except('page'), ['status' => $status])) }}">{{ $statuses[$status] }}</a>
    @endforeach
  </div>

  <form method="get" class="a-toolbar">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <label class="a-field grow"><span>Search</span><input class="a-input" name="search" value="{{ request('search') }}" placeholder="Event title, reference or organizer"></label>
    <label class="a-field"><span>Location</span><select class="a-select" name="location"><option value="">Anywhere</option>@foreach($locations as $value => $label)<option value="{{ $value }}" @selected(request('location') === $value)>{{ $label }}</option>@endforeach</select></label>
    <label class="a-field"><span>When</span><select class="a-select" name="when"><option value="">All dates</option><option value="upcoming" @selected(request('when') === 'upcoming')>Upcoming</option><option value="past" @selected(request('when') === 'past')>Past</option></select></label>
    <div class="a-actions"><button class="a-btn primary" type="submit"><svg><use href="#ai-search"></use></svg>Filter</button><a class="a-btn ghost" href="{{ route('admin.events') }}">Reset</a></div>
  </form>

  <div class="a-table-wrap">
    <table class="a-table">
      <thead><tr><th>Event</th><th>Organizer</th><th>When</th><th>Location</th><th class="num">Capacity · sold</th><th>Status</th><th>Settlement</th><th class="actions">Actions</th></tr></thead>
      <tbody>
      @forelse($events as $event)
        <tr>
          <td><strong>{{ $event->title }}</strong><small><code class="a-code">{{ $event->reference }}</code>{{ $event->category ? ' · '.$event->category : '' }} · {{ $event->visibility }}</small></td>
          <td><strong>@if($event->organizer)<a class="a-link" style="color:inherit" href="{{ route('admin.users.show', $event->organizer) }}">{{ $event->organizer->name }}</a>@else — @endif</strong><small>{{ $event->organizer?->email }}</small></td>
          <td class="nowrap">{{ $event->starts_at->format('D, M j, Y') }}<small>{{ $event->starts_at->format('g:i A') }} – {{ $event->ends_at->format('g:i A') }}{{ $event->doors_at ? ' · doors '.$event->doors_at->format('g:i A') : '' }}</small></td>
          <td>{{ $locations[$event->location_type] ?? a_label($event->location_type) }}<small>{{ $event->location_type === 'soundtunnel' ? ($event->resource?->name ?? 'SoundTunnel') : ($event->venue_name ?: ($event->location_type === 'virtual' ? 'Online' : '—')) }}</small></td>
          <td class="num"><strong>{{ number_format($event->capacity) }}</strong><small>{{ (int) $event->tickets_sold }} sold · {{ $event->ticket_types_count }} ticket types</small></td>
          <td><x-admin.badge :status="$event->status" :label="$statuses[$event->status] ?? a_label($event->status)" />@if($event->status === 'submitted' && $event->submitted_at)<small>Submitted {{ $event->submitted_at->diffForHumans() }}</small>@endif</td>
          <td>@if($event->settlement)<x-admin.badge :status="$event->settlement->status" /><small>{{ a_money($event->settlement->organizer_proceeds_cents) }} to organizer</small>@else<span class="a-sub">Not started</span>@endif</td>
          <td class="actions"><div class="a-row-actions"><button class="a-btn ghost icon sm" type="button" data-modal-open="event-{{ $event->id }}" aria-label="Edit {{ $event->title }}" title="Manage event"><svg><use href="#ai-pencil"></use></svg></button></div></td>
        </tr>
      @empty
        <tr><td colspan="8"><div class="a-table-empty"><strong>No events match</strong>Try another status or clear the filters.</div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $events->links('admin.partials.pagination') }}
</div></section>

@foreach($events as $event)
<x-admin.modal id="event-{{ $event->id }}" :title="$event->title" :kicker="($statuses[$event->status] ?? a_label($event->status)).' · '.$event->reference" :subtitle="$event->starts_at->format('l, F j, Y · g:i A').' – '.$event->ends_at->format('g:i A').' · '.($event->organizer?->name ?? 'Unknown organizer')" size="xl">
  <div class="a-modal-body">
    <div class="a-modal-summary" style="grid-template-columns:repeat(4,minmax(0,1fr))">
      <div><small>Capacity</small><strong>{{ number_format($event->capacity) }}</strong></div>
      <div><small>Tickets sold</small><strong>{{ (int) $event->tickets_sold }}</strong></div>
      <div><small>Platform fee</small><strong>{{ $event->platform_fee_bps / 100 }}%</strong></div>
      <div><small>Reviewed by</small><strong>{{ $event->reviewer?->name ?? 'Not yet' }}</strong></div>
    </div>
    <div class="a-modal-tabs" data-modal-tabs>
      <button type="button" class="is-active" data-pane="details">Event details</button>
      @if($canApprove)<button type="button" data-pane="review">Review & status</button>@endif
      <button type="button" data-pane="settlement">Settlement</button>
    </div>

    <div data-pane-panel="details">
      @if($canApprove)
      <form method="post" action="{{ route('admin.events.update', $event) }}">
        @csrf @method('PUT')
        <input type="hidden" name="_modal" value="event-{{ $event->id }}"><input type="hidden" name="_pane" value="details">
        <div class="a-form-grid cols-3">
          <label class="a-field span-2"><span>Title</span><input class="a-input" name="title" value="{{ old('title', $event->title) }}" required></label>
          <label class="a-field"><span>Category</span><input class="a-input" name="category" value="{{ old('category', $event->category) }}" placeholder="Showcase, club night…"></label>
          <label class="a-field span-3"><span>Description</span><textarea class="a-textarea" name="description" required>{{ old('description', $event->description) }}</textarea></label>
          <label class="a-field"><span>Doors open</span><input class="a-input" type="datetime-local" name="doors_at" value="{{ old('doors_at', $dt($event->doors_at)) }}"></label>
          <label class="a-field"><span>Starts</span><input class="a-input" type="datetime-local" name="starts_at" value="{{ old('starts_at', $dt($event->starts_at)) }}" required></label>
          <label class="a-field"><span>Ends</span><input class="a-input" type="datetime-local" name="ends_at" value="{{ old('ends_at', $dt($event->ends_at)) }}" required></label>
          <label class="a-field"><span>Location</span><select class="a-select" name="location_type" data-location-switch>@foreach($locations as $value => $label)<option value="{{ $value }}" @selected(old('location_type', $event->location_type) === $value)>{{ $label }}</option>@endforeach</select></label>
          <label class="a-field" data-location-only="external"><span>Venue name</span><input class="a-input" name="venue_name" value="{{ old('venue_name', $event->venue_name) }}"></label>
          <label class="a-field" data-location-only="external"><span>Venue address</span><input class="a-input" name="venue_address" value="{{ old('venue_address', $event->venue_address) }}"></label>
          <label class="a-field span-2" data-location-only="virtual"><span>Virtual access details</span><input class="a-input" name="virtual_access" value="{{ old('virtual_access', $event->virtual_access) }}" placeholder="Stream link or join instructions"></label>
          <label class="a-field"><span>Capacity</span><input class="a-input" type="number" min="1" name="capacity" value="{{ old('capacity', $event->capacity) }}" required></label>
          <label class="a-field"><span>Setup minutes</span><input class="a-input" type="number" min="0" name="setup_minutes" value="{{ old('setup_minutes', $event->setup_minutes) }}"></label>
          <label class="a-field"><span>Teardown minutes</span><input class="a-input" type="number" min="0" name="teardown_minutes" value="{{ old('teardown_minutes', $event->teardown_minutes) }}"></label>
          <label class="a-field"><span>Visibility</span><select class="a-select" name="visibility"><option value="public" @selected(old('visibility', $event->visibility) === 'public')>Public</option><option value="private" @selected(old('visibility', $event->visibility) === 'private')>Private (link only)</option></select></label>
          <label class="a-field"><span>Platform fee (bps)</span><input class="a-input" type="number" min="0" max="5000" name="platform_fee_bps" value="{{ old('platform_fee_bps', $event->platform_fee_bps) }}" required><small>100 bps = 1% of ticket revenue.</small></label>
        </div>
        <x-admin.reason />
        <div style="display:flex;justify-content:flex-end;margin-top:14px"><button class="a-btn primary" type="submit">Save event details</button></div>
      </form>
      @else
      <dl class="a-dl"><dt>Description</dt><dd style="white-space:pre-wrap">{{ $event->description }}</dd><dt>Location</dt><dd>{{ $locations[$event->location_type] }}{{ $event->venue_name ? ' · '.$event->venue_name : '' }}</dd><dt>Capacity</dt><dd>{{ $event->capacity }}</dd></dl>
      @endif
      @if($event->review_notes)<div class="a-alert info" style="margin:16px 0 0"><svg class="a-icon" viewBox="0 0 24 24"><use href="#ai-info"></use></svg><div><strong>Latest review notes:</strong> {{ $event->review_notes }}</div></div>@endif
    </div>

    @if($canApprove)
    <div data-pane-panel="review" hidden>
      <form method="post" action="{{ route('admin.events.review', $event) }}">
        @csrf @method('PATCH')
        <input type="hidden" name="_modal" value="event-{{ $event->id }}"><input type="hidden" name="_pane" value="review">
        <dl class="a-dl is-compact" style="margin-bottom:16px">
          <dt>Timeline</dt><dd>Created {{ $event->created_at->format('M j, Y') }}{{ $event->submitted_at ? ' · submitted '.$event->submitted_at->format('M j, Y g:i A') : '' }}{{ $event->approved_at ? ' · approved '.$event->approved_at->format('M j, Y') : '' }}{{ $event->published_at ? ' · published '.$event->published_at->format('M j, Y') : '' }}</dd>
          <dt>Current status</dt><dd><x-admin.badge :status="$event->status" :label="$statuses[$event->status] ?? a_label($event->status)" /></dd>
        </dl>
        <div class="a-check-grid">
          @foreach($reviewActions as $value => [$label, $hint])
            <label class="a-check"><input type="radio" name="action" value="{{ $value }}" @checked(old('action') === $value) required><span><strong>{{ $label }}</strong><small>{{ $hint }}</small></span></label>
          @endforeach
        </div>
        <label class="a-field" style="margin-top:14px"><span>Notes to organizer / internal record</span><textarea class="a-textarea" name="notes" placeholder="Explain the decision or list the changes needed">{{ old('notes', $event->review_notes) }}</textarea></label>
        <div style="display:flex;justify-content:flex-end;margin-top:14px"><button class="a-btn primary" type="submit">Apply review action</button></div>
      </form>
    </div>
    @endif

    <div data-pane-panel="settlement" hidden>
      @if($event->settlement)
        <div class="a-kv" style="margin-bottom:16px">
          <div><small>Status</small><strong><x-admin.badge :status="$event->settlement->status" /></strong></div>
          <div><small>Gross ticket revenue</small><strong>{{ a_money($event->settlement->gross_ticket_revenue_cents) }}</strong></div>
          <div><small>Refunds</small><strong>{{ a_money($event->settlement->refunds_cents) }}</strong></div>
          <div><small>Processing fees</small><strong>{{ a_money($event->settlement->processing_fees_cents) }}</strong></div>
          <div><small>MWCN platform fees</small><strong>{{ a_money($event->settlement->platform_fees_cents) }}</strong></div>
          <div><small>Venue charges</small><strong>{{ a_money($event->settlement->venue_charges_cents) }}</strong></div>
          <div><small>Add-on charges</small><strong>{{ a_money($event->settlement->addon_charges_cents) }}</strong></div>
          <div><small>Adjustments</small><strong>{{ a_money($event->settlement->adjustments_cents) }}</strong></div>
          <div><small>Organizer proceeds</small><strong style="color:var(--a-success)">{{ a_money($event->settlement->organizer_proceeds_cents) }}</strong></div>
          <div><small>Stripe transfer</small><strong>{{ $event->settlement->stripe_transfer_id ?: '—' }}</strong></div>
          <div><small>Approved</small><strong>{{ $event->settlement->approved_at?->format('M j, Y') ?? '—' }}</strong></div>
          <div><small>Paid</small><strong>{{ $event->settlement->paid_at?->format('M j, Y') ?? '—' }}</strong></div>
        </div>
        @if($event->settlement->hold_reason)<div class="a-alert error"><svg class="a-icon" viewBox="0 0 24 24"><use href="#ai-alert"></use></svg><div><strong>On hold:</strong> {{ $event->settlement->hold_reason }}</div></div>@endif
      @else
        <div class="a-empty" style="padding:20px"><strong>No settlement yet</strong>Settlements are opened automatically when an event is completed, or you can calculate one now from ledger records.</div>
      @endif
      @if($canSettle)
      <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end">
        <form method="post" action="{{ route('admin.settlements.calculate', $event) }}">@csrf<button class="a-btn ghost" type="submit"><svg><use href="#ai-refresh"></use></svg>Recalculate from ledger</button></form>
        @if($event->settlement && !$event->settlement->paid_at)
          <form method="post" action="{{ route('admin.settlements.transfer', $event->settlement) }}" data-confirm="Submit the organizer transfer to Stripe for {{ a_money($event->settlement->organizer_proceeds_cents) }}? This cannot be undone.">@csrf<input type="hidden" name="confirm" value="1"><button class="a-btn success" type="submit"><svg><use href="#ai-dollar"></use></svg>Approve Stripe transfer</button></form>
        @endif
      </div>
      @endif
    </div>
  </div>
  <div class="a-modal-foot"><span class="left">Slug: <code class="a-code">{{ $event->slug }}</code></span>@if($event->status === 'published')<a class="a-btn ghost" href="{{ route('events.show', $event->slug) }}" target="_blank" rel="noopener">View public page</a>@endif<button class="a-btn ghost" type="button" data-modal-close>Close</button></div>
</x-admin.modal>
@endforeach
<?php page_end(); ?>
