<?php page_start('Dashboard — MWCN ERP'); ?>
<section class="section"><div class="wrap">
  <div class="admin-page-head">
    <div><p class="kicker">Operations overview</p><h2>Good evening, {{ str(auth()->user()->name)->before(' ') }}.</h2><p>Here is what is happening across MusiciansWorld today.</p></div>
    <a class="btn btn-mark" href="{{ route('admin.reports') }}">Open reporting</a>
  </div>

  <div class="admin-metric-grid">
    <article class="admin-metric-card"><span class="admin-metric-icon"><svg><use href="#ai-users"></use></svg></span><small>Total users</small><strong>{{ number_format($metrics['customers']) }}</strong><small>+{{ $metrics['new_customers'] }} this month</small></article>
    <article class="admin-metric-card"><span class="admin-metric-icon"><svg><use href="#ai-shield"></use></svg></span><small>Active memberships</small><strong>{{ number_format($metrics['active_memberships']) }}</strong><small>Across all plans</small></article>
    <article class="admin-metric-card"><span class="admin-metric-icon"><svg><use href="#ai-calendar"></use></svg></span><small>Upcoming bookings</small><strong>{{ number_format($metrics['upcoming_bookings']) }}</strong><small>Confirmed reservations</small></article>
    <article class="admin-metric-card"><span class="admin-metric-icon"><svg><use href="#ai-ticket"></use></svg></span><small>Pending approvals</small><strong>{{ number_format($metrics['pending_events']) }}</strong><small>Organizer events</small></article>
    <article class="admin-metric-card"><span class="admin-metric-icon"><svg><use href="#ai-chart"></use></svg></span><small>Revenue this month</small><strong>${{ number_format($metrics['revenue_cents']/100,2) }}</strong><small>Verified transactions</small></article>
  </div>

  <div class="admin-dashboard-grid">
    <div class="grid gap-[18px]">
      <section class="admin-panel">
        <div class="admin-panel-head"><h3>Latest users</h3><a href="{{ route('admin.customers') }}">Manage all users →</a></div>
        <div class="admin-activity-list">
          @forelse($recentUsers as $customer)
            <div class="admin-activity-row"><span class="admin-avatar">{{ strtoupper(substr($customer->name,0,1)) }}</span><span><strong>{{ $customer->name }}</strong><small>{{ $customer->email }} · {{ $customer->roles->pluck('label')->join(', ') ?: 'Customer' }}</small></span><span class="admin-status">{{ $customer->status }}</span></div>
          @empty <p class="p-5 text-sm text-dim">No users yet.</p> @endforelse
        </div>
      </section>
      <section class="admin-panel">
        <div class="admin-panel-head"><h3>Latest inquiries</h3><a href="{{ route('admin.submissions') }}">Open all submissions →</a></div>
        <div class="admin-activity-list">
          @forelse($recentSubmissions as $submission)
            <a class="admin-activity-row" href="{{ route('admin.submissions', ['type' => $submission->type]) }}">
              <span class="admin-avatar">{{ strtoupper(substr($submission->name, 0, 1)) }}</span>
              <span><strong>{{ $submission->name }}</strong><small>{{ str_replace('_', ' ', $submission->type) }} · {{ $submission->email }}</small></span>
              <span class="admin-status">{{ $submission->created_at->diffForHumans() }}</span>
            </a>
          @empty
            <p class="p-5 text-sm text-dim">No form submissions yet.</p>
          @endforelse
        </div>
      </section>
      <section class="admin-panel">
        <div class="admin-panel-head"><h3>Recent bookings</h3><a href="{{ route('admin.bookings') }}">View booking desk →</a></div>
        <div class="admin-activity-list">
          @forelse($recentBookings as $booking)
            <div class="admin-activity-row"><span class="admin-avatar">{{ strtoupper(substr($booking->customer_name,0,1)) }}</span><span><strong>{{ $booking->customer_name }} · {{ $booking->resource->name }}</strong><small>{{ $booking->starts_at->setTimezone($booking->resource->timezone)->format('M j, Y · g:i A') }} · ${{ number_format($booking->total_cents/100,2) }}</small></span><span class="admin-status {{ in_array($booking->payment_status,['failed','cancelled']) ? 'bad' : ($booking->payment_status === 'pending' ? 'warn' : '') }}">{{ str_replace('_',' ',$booking->payment_status) }}</span></div>
          @empty <p class="p-5 text-sm text-dim">No bookings yet.</p> @endforelse
        </div>
      </section>
    </div>

    <aside class="grid content-start gap-[18px]">
      <section class="admin-panel">
        <div class="admin-panel-head"><h3>Quick actions</h3></div>
        <div class="admin-quick-actions">
          <a class="admin-quick-action" href="{{ route('admin.customers') }}"><svg><use href="#ai-users"></use></svg>Manage users</a>
          <a class="admin-quick-action" href="{{ route('admin.roles') }}"><svg><use href="#ai-shield"></use></svg>Roles & permissions</a>
          <a class="admin-quick-action" href="{{ route('admin.events') }}"><svg><use href="#ai-ticket"></use></svg>Review events</a>
          <a class="admin-quick-action" href="{{ route('admin.reports') }}"><svg><use href="#ai-chart"></use></svg>Run reports</a>
        </div>
      </section>
      <section class="admin-panel">
        <div class="admin-panel-head"><h3>System health</h3><a href="{{ route('admin.failures') }}">Automation →</a></div>
        <div class="admin-health">@foreach($health as $service => $status)<div class="admin-health-row"><span>{{ $service }}</span><span class="admin-status {{ str_contains($status,'not') || str_contains($status,'attention') ? 'warn' : '' }}">{{ $status }}</span></div>@endforeach</div>
      </section>
      <section class="admin-panel">
        <div class="admin-panel-head"><h3>Approval queue</h3><a href="{{ route('admin.events') }}">Review all →</a></div>
        <div class="admin-activity-list">@forelse($pendingEventRows as $event)<div class="admin-activity-row"><span class="admin-avatar">{{ strtoupper(substr($event->title,0,1)) }}</span><span><strong>{{ $event->title }}</strong><small>{{ $event->organizer->name }} · submitted {{ $event->submitted_at?->diffForHumans() }}</small></span><span class="admin-status warn">Review</span></div>@empty<p class="p-5 text-sm text-dim">No events waiting for review.</p>@endforelse</div>
      </section>
    </aside>
  </div>
</div></section>
<?php page_end(); ?>
