<?php page_start('Bookings — MWCN ERP'); $canManage = auth()->user()->hasPermission('bookings.manage'); ?>
<section class="section"><div class="wrap">
  <div class="a-page-head">
    <div>
      <p class="a-kicker">Operations</p>
      <h2>Bookings & payments</h2>
      <p>Studio A and SoundTunnel reservations with payment state, calendar sync and customer details.</p>
    </div>
    <div class="a-page-actions">
      <a class="a-btn ghost" href="{{ route('admin.settings.availability') }}"><svg><use href="#ai-calendar"></use></svg>Availability holds</a>
      <a class="a-btn ghost" href="{{ route('admin.export', ['type' => 'bookings']) }}"><svg><use href="#ai-download"></use></svg>Export CSV</a>
    </div>
  </div>

  <div class="a-stats">
    <article class="a-stat"><small>Upcoming confirmed</small><strong>{{ number_format($stats['upcoming']) }}</strong><span>Reservations still to happen</span></article>
    <article class="a-stat"><small>Awaiting payment</small><strong>{{ number_format($stats['pending']) }}</strong><span>Pending checkout or invoice</span></article>
    <article class="a-stat"><small>Paid this month</small><strong>{{ a_money($stats['revenue_cents']) }}</strong><span>Sessions starting this month</span></article>
    <article class="a-stat"><small>Calendar sync issues</small><strong>{{ number_format($stats['sync_issues']) }}</strong><span>Need a retry or manual fix</span></article>
  </div>

  <form method="get" class="a-toolbar">
    <label class="a-field grow"><span>Search</span><input class="a-input" name="search" value="{{ request('search') }}" placeholder="Reference, customer name, email or phone"></label>
    <label class="a-field"><span>Space</span><select class="a-select" name="resource"><option value="">All spaces</option>@foreach($resources as $resource)<option value="{{ $resource->id }}" @selected((string) request('resource') === (string) $resource->id)>{{ $resource->name }}</option>@endforeach</select></label>
    <label class="a-field"><span>Booking status</span><select class="a-select" name="status"><option value="">Any</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label>
    <label class="a-field"><span>Payment</span><select class="a-select" name="payment"><option value="">Any</option>@foreach($paymentStatuses as $value => $label)<option value="{{ $value }}" @selected(request('payment') === $value)>{{ $label }}</option>@endforeach</select></label>
    <label class="a-field"><span>When</span><select class="a-select" name="when"><option value="">All dates</option><option value="upcoming" @selected(request('when') === 'upcoming')>Upcoming</option><option value="past" @selected(request('when') === 'past')>Past</option></select></label>
    <label class="a-field"><span>From</span><input class="a-input" type="date" name="from" value="{{ request('from') }}"></label>
    <label class="a-field"><span>To</span><input class="a-input" type="date" name="to" value="{{ request('to') }}"></label>
    <div class="a-actions"><button class="a-btn primary" type="submit"><svg><use href="#ai-search"></use></svg>Filter</button><a class="a-btn ghost" href="{{ route('admin.bookings') }}">Reset</a></div>
  </form>

  <div class="a-table-wrap">
    <table class="a-table">
      <thead><tr><th>Reference</th><th>Space</th><th>When</th><th>Customer</th><th class="num">Total</th><th>Status</th><th>Payment</th><th>Calendar</th><th>Created</th><th class="actions">Actions</th></tr></thead>
      <tbody>
      @forelse($bookings as $booking)
        @php($tz = $booking->resource?->timezone ?? 'America/Phoenix')
        <tr>
          <td><code class="a-code">{{ $booking->reference }}</code>@if($booking->hold_expires_at && $booking->status === 'pending_payment')<small>Hold expires {{ $booking->hold_expires_at->diffForHumans() }}</small>@endif</td>
          <td><strong>{{ $booking->resource?->name }}</strong><small>{{ a_label($booking->service_type) }}</small></td>
          <td class="nowrap">{{ $booking->starts_at->setTimezone($tz)->format('D, M j, Y') }}<small>{{ $booking->starts_at->setTimezone($tz)->format('g:i A') }} – {{ $booking->ends_at->setTimezone($tz)->format('g:i A') }} · {{ rtrim(rtrim(number_format($booking->starts_at->diffInMinutes($booking->ends_at) / 60, 1), '0'), '.') }}h</small></td>
          <td><strong>@if($booking->user)<a class="a-link" style="color:inherit" href="{{ route('admin.users.show', $booking->user) }}">{{ $booking->customer_name }}</a>@else{{ $booking->customer_name }}@endif</strong><small>{{ $booking->customer_email }}{{ $booking->customer_phone ? ' · '.$booking->customer_phone : '' }}</small></td>
          <td class="num"><strong>{{ a_money($booking->total_cents) }}</strong>@if($booking->discount_cents)<small>−{{ a_money($booking->discount_cents) }} discount</small>@endif</td>
          <td><x-admin.badge :status="$booking->status" :label="$statuses[$booking->status] ?? a_label($booking->status)" /></td>
          <td><x-admin.badge :status="$booking->payment_status" /></td>
          <td><x-admin.badge :status="$booking->calendar_sync_status" /></td>
          <td class="nowrap">{{ $booking->created_at->format('M j, Y') }}<small>{{ $booking->created_at->format('g:i A') }}</small></td>
          <td class="actions"><div class="a-row-actions"><button class="a-btn ghost icon sm" type="button" data-modal-open="booking-{{ $booking->id }}" aria-label="{{ $canManage ? 'Edit' : 'View' }} booking {{ $booking->reference }}" title="{{ $canManage ? 'Edit booking' : 'View booking' }}"><svg><use href="#ai-{{ $canManage ? 'pencil' : 'eye' }}"></use></svg></button></div></td>
        </tr>
      @empty
        <tr><td colspan="10"><div class="a-table-empty"><strong>No bookings found</strong>Try a different date range or clear the filters.</div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $bookings->links('admin.partials.pagination') }}
</div></section>

@foreach($bookings as $booking)
@php($tz = $booking->resource?->timezone ?? 'America/Phoenix')
<x-admin.modal id="booking-{{ $booking->id }}" :title="'Booking '.$booking->reference" :kicker="$booking->resource?->name.' · '.a_label($booking->service_type)" :subtitle="$booking->starts_at->setTimezone($tz)->format('l, F j, Y · g:i A').' – '.$booking->ends_at->setTimezone($tz)->format('g:i A').' ('.$tz.')'" size="lg">
  <form method="post" action="{{ route('admin.bookings.update', $booking) }}">
    @csrf @method('PATCH')
    <input type="hidden" name="_modal" value="booking-{{ $booking->id }}">
    <div class="a-modal-body">
      <div class="a-modal-summary">
        <div><small>Subtotal</small><strong>{{ a_money($booking->subtotal_cents) }}</strong></div>
        <div><small>Discount</small><strong>{{ a_money($booking->discount_cents) }}</strong></div>
        <div><small>Total charged</small><strong>{{ a_money($booking->total_cents) }} {{ strtoupper($booking->currency) }}</strong></div>
      </div>
      <dl class="a-dl is-compact" style="margin-bottom:18px">
        <dt>Current state</dt><dd><x-admin.badge :status="$booking->status" :label="$statuses[$booking->status] ?? a_label($booking->status)" /> <x-admin.badge :status="$booking->payment_status" :label="'Payment '.a_label($booking->payment_status)" /> <x-admin.badge :status="$booking->calendar_sync_status" :label="'Calendar '.a_label($booking->calendar_sync_status)" /></dd>
        <dt>Linked account</dt><dd>@if($booking->user)<a class="a-link" href="{{ route('admin.users.show', $booking->user) }}">{{ $booking->user->name }}</a> · {{ $booking->user->email }}@else Guest checkout (no account) @endif</dd>
        <dt>Stripe</dt><dd><code class="a-code">{{ $booking->stripe_payment_intent_id ?: ($booking->stripe_checkout_session_id ?: 'No Stripe reference') }}</code></dd>
        <dt>Calendar event</dt><dd>{{ $booking->calendar_event_id ?: 'Not created' }}@if($booking->calendar_sync_error)<small class="a-sub" style="color:var(--a-danger)">{{ $booking->calendar_sync_error }}</small>@endif</dd>
        <dt>Created</dt><dd>{{ $booking->created_at->format('M j, Y g:i A') }} · last update {{ $booking->updated_at->diffForHumans() }}</dd>
        @if($booking->meta)<dt>Metadata</dt><dd><code class="a-code">{{ json_encode($booking->meta) }}</code></dd>@endif
      </dl>
      @if($canManage)
      <div class="a-form-section">
        <h4>Payment & status</h4>
        <div class="a-form-grid">
          <label class="a-field"><span>Payment status</span><select class="a-select" name="payment_status">@foreach($paymentStatuses as $value => $label)<option value="{{ $value }}" @selected(old('payment_status', $booking->payment_status) === $value)>{{ $label }}</option>@endforeach</select><small>Marking as paid confirms the booking and syncs the calendar; cancelling removes the calendar event.</small></label>
          <label class="a-field"><span>Booking status</span><select class="a-select" name="status"><option value="">Follow payment status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status') === $value)>{{ $label }}</option>@endforeach</select><small>Leave on "Follow payment status" unless you need an override.</small></label>
        </div>
      </div>
      <div class="a-form-section">
        <h4>Customer details</h4>
        <div class="a-form-grid cols-3">
          <label class="a-field"><span>Name</span><input class="a-input" name="customer_name" value="{{ old('customer_name', $booking->customer_name) }}" required></label>
          <label class="a-field"><span>Email</span><input class="a-input" type="email" name="customer_email" value="{{ old('customer_email', $booking->customer_email) }}" required></label>
          <label class="a-field"><span>Phone</span><input class="a-input" name="customer_phone" value="{{ old('customer_phone', $booking->customer_phone) }}"></label>
          <label class="a-field span-3"><span>Notes</span><textarea class="a-textarea" name="notes" style="min-height:72px" placeholder="Customer requests, setup needs, internal notes">{{ old('notes', $booking->notes) }}</textarea></label>
        </div>
      </div>
      <x-admin.reason />
      @else
        @if($booking->notes)<div class="a-form-section"><h4>Notes</h4><p style="margin:0;white-space:pre-wrap">{{ $booking->notes }}</p></div>@endif
      @endif
    </div>
    <div class="a-modal-foot">
      <span class="left">{{ $booking->reference }}</span>
      <button class="a-btn ghost" type="button" data-modal-close>Close</button>
      @if($canManage)<button class="a-btn primary" type="submit">Save booking</button>@endif
    </div>
  </form>
</x-admin.modal>
@endforeach
<?php page_end(); ?>
