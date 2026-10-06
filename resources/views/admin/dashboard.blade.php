<?php page_start('Dashboard — MWCN ERP'); $hour = (int) now()->format('G'); $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening'); ?>
<section class="section"><div class="wrap">
  <div class="a-page-head">
    <div><p class="a-kicker">Operations overview</p><h2>{{ $greeting }}, {{ str(auth()->user()->name)->before(' ') }}.</h2><p>Here is what is happening across MusiciansWorld today.</p></div>
    <div class="a-page-actions"><a class="a-btn ghost" href="{{ route('admin.settings') }}"><svg><use href="#ai-settings"></use></svg>Settings</a><a class="a-btn primary" href="{{ route('admin.reports') }}"><svg><use href="#ai-chart"></use></svg>Open reporting</a></div>
  </div>

  <div class="a-stats is-5">
    <article class="a-stat"><span class="a-stat-icon"><svg class="a-icon"><use href="#ai-users"></use></svg></span><small>Total users</small><strong>{{ number_format($metrics['customers']) }}</strong><span>+{{ $metrics['new_customers'] }} this month</span></article>
    <article class="a-stat"><span class="a-stat-icon"><svg class="a-icon"><use href="#ai-badge"></use></svg></span><small>Active memberships</small><strong>{{ number_format($metrics['active_memberships']) }}</strong><span>Across all plans</span></article>
    <article class="a-stat"><span class="a-stat-icon"><svg class="a-icon"><use href="#ai-calendar"></use></svg></span><small>Upcoming bookings</small><strong>{{ number_format($metrics['upcoming_bookings']) }}</strong><span>Confirmed reservations</span></article>
    <article class="a-stat"><span class="a-stat-icon"><svg class="a-icon"><use href="#ai-ticket"></use></svg></span><small>Pending approvals</small><strong>{{ number_format($metrics['pending_events']) }}</strong><span>Organizer events</span></article>
    <article class="a-stat"><span class="a-stat-icon"><svg class="a-icon"><use href="#ai-dollar"></use></svg></span><small>Revenue this month</small><strong>{{ a_money($metrics['revenue_cents']) }}</strong><span>Verified transactions</span></article>
  </div>

  <div class="admin-dashboard-grid">
    <div class="a-stack">
      <section class="a-card">
        <div class="a-card-head"><div><h3>Latest users</h3></div><a class="a-link" href="{{ route('admin.users') }}">Manage all users →</a></div>
        <div class="a-list">
          @forelse($recentUsers as $customer)
            <a class="a-list-row" href="{{ route('admin.users.show', $customer) }}"><span class="a-avatar {{ $customer->isStaffAccount() ? '' : 'neutral' }}">{{ $customer->initials() }}</span><span><strong>{{ $customer->name }}</strong><small>{{ $customer->email }} · {{ $customer->roles->pluck('label')->join(', ') ?: $customer->accountTypeLabel() }}</small></span><x-admin.badge :status="$customer->status" /></a>
          @empty <p class="a-empty">No users yet.</p> @endforelse
        </div>
      </section>
      <section class="a-card">
        <div class="a-card-head"><div><h3>Latest inquiries</h3></div><a class="a-link" href="{{ route('admin.submissions') }}">Open all submissions →</a></div>
        <div class="a-list">
          @forelse($recentSubmissions as $submission)
            <a class="a-list-row" href="{{ route('admin.submissions', ['type' => $submission->type]) }}"><span class="a-avatar neutral">{{ strtoupper(substr($submission->name, 0, 1)) }}</span><span><strong>{{ $submission->name }}</strong><small>{{ a_label($submission->type) }} · {{ $submission->email }}</small></span><span class="a-sub">{{ $submission->created_at->diffForHumans() }}</span></a>
          @empty <p class="a-empty">No form submissions yet.</p> @endforelse
        </div>
      </section>
      <section class="a-card">
        <div class="a-card-head"><div><h3>Recent bookings</h3></div><a class="a-link" href="{{ route('admin.bookings') }}">View booking desk →</a></div>
        <div class="a-list">
          @forelse($recentBookings as $booking)
            <a class="a-list-row" href="{{ route('admin.bookings', ['search' => $booking->reference]) }}"><span class="a-avatar neutral">{{ strtoupper(substr($booking->customer_name, 0, 1)) }}</span><span><strong>{{ $booking->customer_name }} · {{ $booking->resource->name }}</strong><small>{{ $booking->starts_at->setTimezone($booking->resource->timezone)->format('M j, Y · g:i A') }} · {{ a_money($booking->total_cents) }}</small></span><x-admin.badge :status="$booking->payment_status" /></a>
          @empty <p class="a-empty">No bookings yet.</p> @endforelse
        </div>
      </section>
    </div>

    <aside class="a-stack">
      <section class="a-card">
        <div class="a-card-head"><div><h3>Quick actions</h3></div></div>
        <div class="admin-quick-actions">
          <a class="admin-quick-action" href="{{ route('admin.staff') }}"><svg><use href="#ai-briefcase"></use></svg>Internal staff</a>
          <a class="admin-quick-action" href="{{ route('admin.roles') }}"><svg><use href="#ai-shield"></use></svg>Roles & permissions</a>
          <a class="admin-quick-action" href="{{ route('admin.events', ['status' => 'submitted']) }}"><svg><use href="#ai-ticket"></use></svg>Review events</a>
          <a class="admin-quick-action" href="{{ route('admin.memberships') }}"><svg><use href="#ai-badge"></use></svg>Memberships</a>
        </div>
      </section>
      <section class="a-card">
        <div class="a-card-head"><div><h3>System health</h3></div><a class="a-link" href="{{ route('admin.settings.integrations') }}">Integrations →</a></div>
        <div class="admin-health">@foreach($health as $service => $status)<div class="admin-health-row"><span>{{ $service }}</span><x-admin.badge :status="$status" /></div>@endforeach</div>
      </section>
      <section class="a-card">
        <div class="a-card-head"><div><h3>Approval queue</h3></div><a class="a-link" href="{{ route('admin.events', ['status' => 'submitted']) }}">Review all →</a></div>
        <div class="a-list">@forelse($pendingEventRows as $event)<a class="a-list-row" href="{{ route('admin.events', ['search' => $event->reference]) }}"><span class="a-avatar neutral">{{ strtoupper(substr($event->title, 0, 1)) }}</span><span><strong>{{ $event->title }}</strong><small>{{ $event->organizer->name }} · submitted {{ $event->submitted_at?->diffForHumans() }}</small></span><span class="a-badge warn">Review</span></a>@empty<p class="a-empty">No events waiting for review.</p>@endforelse</div>
      </section>
    </aside>
  </div>
</div></section>
<?php page_end(); ?>
