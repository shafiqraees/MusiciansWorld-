<?php
page_start($user->name.' — MWCN ERP');
$me = auth()->user();
$canManage = $me->hasPermission('users.manage');
$canRoles = $me->hasPermission('roles.manage');
$segmentLabel = ['customers' => 'Customers', 'admins' => 'Internal admins', 'staff' => 'Internal staff'][$segment];
$segmentRoute = ['customers' => 'admin.customers', 'admins' => 'admin.admins', 'staff' => 'admin.staff'][$segment];
$activeMembership = $user->memberships->whereIn('status', ['active', 'trialing', 'grace'])->sortByDesc('created_at')->first();
$effectiveIds = $effective->pluck('id')->all();
$directIds = $user->directPermissions->pluck('id')->all();
$prefs = $user->communication_preferences ?? [];
?>
<section class="section"><div class="wrap">
  <nav class="a-breadcrumb"><a href="{{ route('admin.users') }}">Users</a><span><a href="{{ route($segmentRoute) }}">{{ $segmentLabel }}</a></span><span>{{ $user->name }}</span></nav>

  <div class="a-profile">
    <span class="a-avatar lg {{ $user->isStaffAccount() ? '' : 'neutral' }}">{{ $user->initials() }}</span>
    <div class="a-profile-copy">
      <p class="a-kicker">{{ $user->accountTypeLabel() }}{{ $user->job_title ? ' · '.$user->job_title : '' }}</p>
      <h2>{{ $user->name }}</h2>
      <p>{{ $user->email }}{{ $user->phone ? ' · '.$user->phone : '' }} · ID #{{ $user->id }}</p>
      <div class="a-profile-meta">
        <x-admin.badge :status="$user->status" />
        <span class="a-badge plain {{ $user->email_verified_at ? 'success' : 'warn' }}">{{ $user->email_verified_at ? 'Email verified' : 'Email unverified' }}</span>
        @foreach($user->roles as $role)<span class="a-badge plain {{ $role->name === 'super_admin' ? 'purple' : 'info' }}">{{ $role->label }}</span>@endforeach
        @if($activeMembership)<span class="a-badge plain neutral">{{ $activeMembership->plan?->name }}</span>@endif
      </div>
    </div>
    <div class="a-profile-actions">
      @if($canManage)
        <button class="a-btn primary" type="button" data-modal-open="edit-profile"><svg><use href="#ai-pencil"></use></svg>Edit profile</button>
        <button class="a-btn ghost" type="button" data-modal-open="account-status"><svg><use href="#ai-lock"></use></svg>Account status</button>
        <button class="a-btn ghost" type="button" data-modal-open="reset-password"><svg><use href="#ai-key"></use></svg>Reset password</button>
      @endif
      @if($canRoles)
        <button class="a-btn ghost" type="button" data-modal-open="edit-roles"><svg><use href="#ai-shield"></use></svg>Roles</button>
      @endif
    </div>
  </div>

  <div class="a-stats" style="margin-top:18px">
    <article class="a-stat"><small>Lifetime payments</small><strong>{{ a_money($totals['spent']) }}</strong><span>{{ $user->transactions->count() }} ledger entries</span></article>
    <article class="a-stat"><small>Bookings</small><strong>{{ $totals['bookings'] }}</strong><span>{{ $user->bookings->where('starts_at', '>=', now())->count() }} upcoming</span></article>
    <article class="a-stat"><small>Tickets</small><strong>{{ $totals['tickets'] }}</strong><span>{{ $user->orders->count() }} orders</span></article>
    <article class="a-stat"><small>Hosted events</small><strong>{{ $totals['events'] }}</strong><span>{{ $user->events->whereIn('status', ['approved', 'published'])->count() }} live or approved</span></article>
  </div>

  <div class="a-grid detail">
    <div class="a-stack">
      <div class="a-tabs" data-tabs="user">
        <button class="a-tab is-active" type="button" data-tab="overview">Overview</button>
        <button class="a-tab" type="button" data-tab="memberships">Memberships <b>{{ $user->memberships->count() }}</b></button>
        <button class="a-tab" type="button" data-tab="bookings">Bookings <b>{{ $user->bookings->count() }}</b></button>
        <button class="a-tab" type="button" data-tab="events">Events & tickets <b>{{ $user->events->count() + $user->tickets->count() }}</b></button>
        <button class="a-tab" type="button" data-tab="payments">Payments <b>{{ $user->transactions->count() }}</b></button>
        <button class="a-tab" type="button" data-tab="requests">Requests <b>{{ $user->submissions->count() + $user->registrations->count() }}</b></button>
        <button class="a-tab" type="button" data-tab="activity">Activity <b>{{ $history->count() }}</b></button>
      </div>

      {{-- Overview --}}
      <div data-tab-panel="overview" data-tab-group="user" class="a-stack">
        <section class="a-card">
          <div class="a-card-head"><div><h3>Profile</h3><p>Identity and contact details stored on the account.</p></div>@if($canManage)<button class="a-btn ghost sm" type="button" data-modal-open="edit-profile"><svg><use href="#ai-pencil"></use></svg>Edit</button>@endif</div>
          <div class="a-card-body">
            <dl class="a-dl">
              <dt>Full name</dt><dd>{{ $user->name }}</dd>
              <dt>Email</dt><dd>{{ $user->email }} <small class="a-sub" style="display:inline">{{ $user->email_verified_at ? '· verified '.$user->email_verified_at->format('M j, Y g:i A') : '· not verified' }}</small></dd>
              <dt>Phone</dt><dd>{{ $user->phone ?: '—' }}</dd>
              <dt>Location</dt><dd>{{ collect([$user->city, $user->state, $user->postal_code])->filter()->join(', ') ?: '—' }}</dd>
              <dt>Account type</dt><dd>{{ $user->accountTypeLabel() }}</dd>
              <dt>Job title</dt><dd>{{ $user->job_title ?: '—' }}</dd>
              <dt>Status</dt><dd><x-admin.badge :status="$user->status" /></dd>
              <dt>Marketing emails</dt><dd>{{ !empty($prefs['marketing']) ? 'Opted in' : 'Not opted in' }}</dd>
              <dt>Other preferences</dt><dd>@php($other = collect($prefs)->except('marketing'))@if($other->isEmpty())—@else{{ $other->map(fn ($v, $k) => a_label($k).': '.(is_bool($v) ? ($v ? 'yes' : 'no') : (is_array($v) ? json_encode($v) : $v)))->join(' · ') }}@endif</dd>
            </dl>
          </div>
        </section>
        <section class="a-card">
          <div class="a-card-head"><div><h3>Account & security</h3><p>Sign-in history and lifecycle timestamps.</p></div></div>
          <div class="a-card-body">
            <div class="a-kv">
              <div><small>Created</small><strong>{{ $user->created_at->format('M j, Y g:i A') }}</strong></div>
              <div><small>Last updated</small><strong>{{ $user->updated_at->format('M j, Y g:i A') }}</strong></div>
              <div><small>Last sign-in</small><strong>{{ $user->last_login_at ? $user->last_login_at->format('M j, Y g:i A') : 'Never' }}</strong></div>
              <div><small>Last sign-in IP</small><strong>{{ $user->last_login_ip ?: '—' }}</strong></div>
              <div><small>Active sessions</small><strong>{{ $sessions->count() }}</strong></div>
              <div><small>Remember-me token</small><strong>{{ $user->remember_token ? 'Issued' : 'None' }}</strong></div>
            </div>
            @if($sessions->isNotEmpty())
            <div class="a-table-wrap" style="margin-top:16px"><table class="a-table"><thead><tr><th>Session</th><th>IP address</th><th>Device</th><th>Last activity</th></tr></thead><tbody>
              @foreach($sessions as $session)<tr><td><code class="a-code">{{ substr($session->id, 0, 12) }}…</code></td><td>{{ $session->ip_address ?: '—' }}</td><td><small style="display:block;max-width:420px;white-space:normal">{{ str($session->user_agent)->limit(110) }}</small></td><td class="nowrap">{{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}</td></tr>@endforeach
            </tbody></table></div>
            @endif
          </div>
        </section>
        @if($user->organizerProfile)
        <section class="a-card">
          <div class="a-card-head"><div><h3>Organizer profile</h3><p>Promoter account and Stripe Connect payout readiness.</p></div></div>
          <div class="a-card-body"><dl class="a-dl">
            <dt>Display name</dt><dd>{{ $user->organizerProfile->display_name }}</dd>
            <dt>Status</dt><dd><x-admin.badge :status="$user->organizerProfile->status" /> {!! $user->organizerProfile->is_verified ? '<span class="a-badge plain success">Verified</span>' : '' !!}</dd>
            <dt>Stripe account</dt><dd>{{ $user->organizerProfile->stripe_account_id ?: 'Not connected' }}</dd>
            <dt>Charges / payouts</dt><dd>{{ $user->organizerProfile->stripe_charges_enabled ? 'Charges enabled' : 'Charges disabled' }} · {{ $user->organizerProfile->stripe_payouts_enabled ? 'Payouts enabled' : 'Payouts disabled' }}</dd>
            @if($user->organizerProfile->requirements)<dt>Outstanding requirements</dt><dd><code class="a-code">{{ json_encode($user->organizerProfile->requirements) }}</code></dd>@endif
          </dl></div>
        </section>
        @endif
        <section class="a-card">
          <div class="a-card-head"><div><h3>Internal notes</h3><p>Only visible to the internal team.</p></div></div>
          <div class="a-card-body">@if($user->internal_notes)<p style="margin:0;white-space:pre-wrap">{{ $user->internal_notes }}</p>@else<p class="a-sub" style="margin:0">No internal notes yet.@if($canManage) Use <strong>Edit profile</strong> to add context about this account.@endif</p>@endif</div>
        </section>
      </div>

      {{-- Memberships --}}
      <div data-tab-panel="memberships" data-tab-group="user" hidden>
        <section class="a-card">
          <div class="a-card-head"><div><h3>Memberships</h3><p>Every plan this customer has held, including Stripe billing identifiers.</p></div><a class="a-btn ghost sm" href="{{ route('admin.memberships', ['tab' => 'members', 'search' => $user->email]) }}">Manage in Memberships</a></div>
          <div class="a-table-wrap"><table class="a-table"><thead><tr><th>Plan</th><th>Status</th><th>Current period</th><th>Grace / cancelled</th><th>Stripe</th><th>Since</th></tr></thead><tbody>
            @forelse($user->memberships->sortByDesc('created_at') as $membership)
              <tr><td><strong>{{ $membership->plan?->name }}</strong><small>{{ a_money($membership->plan?->price_cents) }} / {{ a_label($membership->plan?->billing_interval) }}</small></td><td><x-admin.badge :status="$membership->status" /></td><td class="nowrap">{{ $membership->current_period_start?->format('M j, Y') ?? '—' }} → {{ $membership->current_period_end?->format('M j, Y') ?? '—' }}</td><td class="nowrap">{{ $membership->grace_ends_at ? 'Grace until '.$membership->grace_ends_at->format('M j') : '' }}{{ $membership->cancelled_at ? 'Cancelled '.$membership->cancelled_at->format('M j, Y') : ($membership->grace_ends_at ? '' : '—') }}</td><td><code class="a-code">{{ $membership->stripe_customer_id ?: '—' }}</code><small>{{ $membership->stripe_subscription_id ?: 'No subscription' }}</small></td><td class="nowrap">{{ $membership->created_at->format('M j, Y') }}</td></tr>
            @empty<tr><td colspan="6"><div class="a-table-empty"><strong>No memberships</strong>This account has never held a membership plan.</div></td></tr>@endforelse
          </tbody></table></div>
        </section>
      </div>

      {{-- Bookings --}}
      <div data-tab-panel="bookings" data-tab-group="user" hidden>
        <section class="a-card">
          <div class="a-card-head"><div><h3>Bookings</h3><p>Studio and venue reservations placed by this account.</p></div><a class="a-btn ghost sm" href="{{ route('admin.bookings', ['search' => $user->email]) }}">Open booking desk</a></div>
          <div class="a-table-wrap"><table class="a-table"><thead><tr><th>Reference</th><th>Space</th><th>When</th><th>Total</th><th>Status</th><th>Payment</th><th>Calendar</th></tr></thead><tbody>
            @forelse($user->bookings->sortByDesc('starts_at') as $booking)
              <tr><td><code class="a-code">{{ $booking->reference }}</code></td><td>{{ $booking->resource?->name }}<small>{{ a_label($booking->service_type) }}</small></td><td class="nowrap">{{ $booking->starts_at->setTimezone($booking->resource?->timezone ?? 'America/Phoenix')->format('M j, Y g:i A') }}<small>{{ $booking->starts_at->diffInMinutes($booking->ends_at) / 60 }} hours</small></td><td class="num">{{ a_money($booking->total_cents) }}</td><td><x-admin.badge :status="$booking->status" /></td><td><x-admin.badge :status="$booking->payment_status" /></td><td><x-admin.badge :status="$booking->calendar_sync_status" /></td></tr>
            @empty<tr><td colspan="7"><div class="a-table-empty"><strong>No bookings</strong>This account has not reserved a space yet.</div></td></tr>@endforelse
          </tbody></table></div>
        </section>
      </div>

      {{-- Events & tickets --}}
      <div data-tab-panel="events" data-tab-group="user" hidden class="a-stack">
        <section class="a-card">
          <div class="a-card-head"><div><h3>Hosted events</h3><p>Events this account organizes, with settlement status.</p></div><a class="a-btn ghost sm" href="{{ route('admin.events', ['search' => $user->email]) }}">Open events</a></div>
          <div class="a-table-wrap"><table class="a-table"><thead><tr><th>Event</th><th>When</th><th>Location</th><th>Capacity</th><th>Status</th><th>Settlement</th></tr></thead><tbody>
            @forelse($user->events->sortByDesc('starts_at') as $event)
              <tr><td><strong>{{ $event->title }}</strong><small>{{ $event->reference }}</small></td><td class="nowrap">{{ $event->starts_at->format('M j, Y g:i A') }}</td><td>{{ a_label($event->location_type) }}{{ $event->venue_name ? ' · '.$event->venue_name : '' }}</td><td class="num">{{ number_format($event->capacity) }}</td><td><x-admin.badge :status="$event->status" /></td><td>@if($event->settlement)<x-admin.badge :status="$event->settlement->status" /><small>{{ a_money($event->settlement->organizer_proceeds_cents) }} to organizer</small>@else<span class="a-sub">—</span>@endif</td></tr>
            @empty<tr><td colspan="6"><div class="a-table-empty"><strong>No hosted events</strong>This account has not created any events.</div></td></tr>@endforelse
          </tbody></table></div>
        </section>
        <section class="a-card">
          <div class="a-card-head"><div><h3>Tickets & orders</h3><p>Tickets purchased or claimed by this account.</p></div></div>
          <div class="a-table-wrap"><table class="a-table"><thead><tr><th>Ticket</th><th>Event</th><th>Type</th><th>Attendee</th><th>Status</th><th>Checked in</th></tr></thead><tbody>
            @forelse($user->tickets->sortByDesc('created_at') as $ticket)
              <tr><td><code class="a-code">{{ substr($ticket->uuid, 0, 8) }}</code><small>Order #{{ $ticket->order_id }}</small></td><td>{{ $ticket->event?->title }}</td><td>{{ $ticket->ticketType?->name }}<small>{{ a_money($ticket->ticketType?->price_cents) }}</small></td><td>{{ $ticket->attendee_name ?: $user->name }}<small>{{ $ticket->attendee_email ?: $user->email }}</small></td><td><x-admin.badge :status="$ticket->status" /></td><td class="nowrap">{{ $ticket->checked_in_at?->format('M j, g:i A') ?? '—' }}</td></tr>
            @empty<tr><td colspan="6"><div class="a-table-empty"><strong>No tickets</strong>This account has not purchased tickets.</div></td></tr>@endforelse
          </tbody></table></div>
          @if($user->orders->isNotEmpty())
          <div class="a-card-foot"><strong>Orders:</strong> @foreach($user->orders->sortByDesc('created_at') as $order)<span class="a-pill" title="{{ $order->event?->title }}">{{ $order->reference }} · {{ a_money($order->total_cents) }} · {{ a_label($order->payment_status) }}</span> @endforeach</div>
          @endif
        </section>
      </div>

      {{-- Payments --}}
      <div data-tab-panel="payments" data-tab-group="user" hidden>
        <section class="a-card">
          <div class="a-card-head"><div><h3>Payments & ledger</h3><p>Financial transactions recorded against this account.</p></div></div>
          <div class="a-table-wrap"><table class="a-table"><thead><tr><th>Date</th><th>Type</th><th>Related to</th><th>Amount</th><th>Status</th><th>Stripe reference</th></tr></thead><tbody>
            @forelse($user->transactions->sortByDesc('created_at') as $transaction)
              <tr><td class="nowrap">{{ $transaction->created_at->format('M j, Y g:i A') }}</td><td>{{ a_label($transaction->type) }}</td><td>{{ $transaction->transactable_type ? class_basename($transaction->transactable_type).' #'.$transaction->transactable_id : '—' }}</td><td class="num" style="color:{{ $transaction->amount_cents < 0 ? 'var(--a-danger)' : 'inherit' }}">{{ a_money($transaction->amount_cents) }}</td><td><x-admin.badge :status="$transaction->status" /></td><td><code class="a-code">{{ $transaction->stripe_id ?: '—' }}</code></td></tr>
            @empty<tr><td colspan="6"><div class="a-table-empty"><strong>No payment records</strong>Nothing has been charged or refunded for this account.</div></td></tr>@endforelse
          </tbody></table></div>
        </section>
      </div>

      {{-- Requests --}}
      <div data-tab-panel="requests" data-tab-group="user" hidden class="a-stack">
        <section class="a-card">
          <div class="a-card-head"><div><h3>Form submissions</h3><p>Leads and inquiries linked to this account.</p></div><a class="a-btn ghost sm" href="{{ route('admin.submissions') }}">All submissions</a></div>
          <div class="a-table-wrap"><table class="a-table"><thead><tr><th>Type</th><th>Status</th><th>Message</th><th>Submitted</th></tr></thead><tbody>
            @forelse($user->submissions->sortByDesc('created_at') as $submission)
              <tr><td>{{ a_label($submission->type) }}</td><td><x-admin.badge :status="$submission->status" /></td><td><small style="white-space:normal;max-width:460px">{{ str($submission->message ?: collect($submission->data ?? [])->filter(fn ($v) => is_scalar($v))->take(4)->map(fn ($v, $k) => a_label($k).': '.$v)->join(' · '))->limit(160) }}</small></td><td class="nowrap">{{ $submission->created_at->format('M j, Y g:i A') }}</td></tr>
            @empty<tr><td colspan="4"><div class="a-table-empty"><strong>No submissions</strong>No forms are linked to this account.</div></td></tr>@endforelse
          </tbody></table></div>
        </section>
        <section class="a-card">
          <div class="a-card-head"><div><h3>DJ School registrations</h3><p>Program enrolments and assigned instructors.</p></div></div>
          <div class="a-table-wrap"><table class="a-table"><thead><tr><th>Program</th><th>Status</th><th>Payment</th><th>Instructor</th><th>Registered</th></tr></thead><tbody>
            @forelse($user->registrations->sortByDesc('created_at') as $registration)
              <tr><td><strong>{{ $registration->program?->name ?? 'Unassigned program' }}</strong></td><td><x-admin.badge :status="$registration->status" /></td><td>{{ a_label($registration->payment_status) }}</td><td>{{ $registration->instructor?->name ?? '—' }}</td><td class="nowrap">{{ $registration->created_at->format('M j, Y') }}</td></tr>
            @empty<tr><td colspan="5"><div class="a-table-empty"><strong>No registrations</strong>This account has not enrolled in a program.</div></td></tr>@endforelse
          </tbody></table></div>
        </section>
      </div>

      {{-- Activity --}}
      <div data-tab-panel="activity" data-tab-group="user" hidden>
        <section class="a-card">
          <div class="a-card-head"><div><h3>Activity & audit history</h3><p>Changes made to this account and actions this user performed in the portal.</p></div></div>
          <div class="a-card-body">
            @if($history->isEmpty())<div class="a-empty"><strong>No recorded activity</strong>Audit entries will appear here as changes are made.</div>@else
            <div class="a-timeline">
              @foreach($history as $log)
                <div class="a-timeline-item"><i></i><div>
                  <strong>{{ a_label($log->action) }}</strong>
                  <small>{{ $log->created_at->format('M j, Y g:i A') }} · {{ $log->actor_id === $user->id ? 'performed by this user' : 'by '.($log->actor?->name ?? 'System') }} · {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}{{ $log->ip_address ? ' · '.$log->ip_address : '' }}</small>
                  @if($log->reason)<small>Reason: {{ $log->reason }}</small>@endif
                  @if($log->before || $log->after)<details><summary>Before / after</summary><pre>{{ json_encode(['before' => $log->before, 'after' => collect($log->after)->except(['password', 'remember_token'])], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
                </div></div>
              @endforeach
            </div>
            @endif
          </div>
        </section>
      </div>
    </div>

    {{-- Right column: roles & permissions --}}
    <aside class="a-stack">
      <section class="a-card">
        <div class="a-card-head"><div><h3>Roles</h3><p>Roles bundle permissions. Assign or remove them here.</p></div>@if($canRoles)<button class="a-btn ghost sm" type="button" data-modal-open="edit-roles"><svg><use href="#ai-pencil"></use></svg>Edit</button>@endif</div>
        <div class="a-card-body">
          @forelse($user->roles as $role)
            <div class="a-perm-item is-granted" style="margin-bottom:6px"><span><strong>{{ $role->label }}</strong><small style="display:block"><code class="a-code">{{ $role->name }}</code> · {{ $role->permissionRecords->count() }} permissions</small></span><svg class="a-icon" style="color:var(--a-success)"><use href="#ai-check"></use></svg></div>
          @empty
            <div class="a-empty" style="padding:16px"><strong>No roles assigned</strong>This account only has basic access.</div>
          @endforelse
        </div>
      </section>

      <section class="a-card">
        <div class="a-card-head"><div><h3>Direct permissions</h3><p>Grants that apply to this user on top of their roles.</p></div>@if($canRoles)<button class="a-btn ghost sm" type="button" data-modal-open="edit-permissions"><svg><use href="#ai-pencil"></use></svg>Edit</button>@endif</div>
        <div class="a-card-body">
          @if($user->directPermissions->isEmpty())<p class="a-sub" style="margin:0">None. All access comes from the assigned roles.</p>@else
            <div class="a-badges">@foreach($user->directPermissions as $permission)<span class="a-pill" title="{{ $permission->name }}">{{ $permission->label }}</span>@endforeach</div>
          @endif
        </div>
      </section>

      <section class="a-card">
        <div class="a-card-head"><div><h3>Effective permissions</h3><p>{{ $user->hasRole('super_admin') ? 'Super Admin — every permission is granted.' : count($effectiveIds).' of '.$permissions->flatten()->count().' permissions granted.' }}</p></div></div>
        <div class="a-card-body" style="padding-top:6px">
          @foreach($permissions as $group => $groupPermissions)
            <div class="a-perm-group">
              <h5>{{ $group }}</h5>
              <div class="a-perm-list" style="grid-template-columns:1fr">
                @foreach($groupPermissions as $permission)
                  @php($granted = in_array($permission->id, $effectiveIds, true))
                  <div class="a-perm-item {{ $granted ? 'is-granted' : 'is-missing' }}"><span>{{ $permission->label }}<small style="display:block">{{ $permission->name }}{{ in_array($permission->id, $directIds, true) ? ' · direct grant' : '' }}</small></span>@if($granted)<svg class="a-icon" style="color:var(--a-success)"><use href="#ai-check"></use></svg>@else<svg class="a-icon" style="color:var(--a-muted)"><use href="#ai-x"></use></svg>@endif</div>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>
      </section>
    </aside>
  </div>
</div></section>

@if($canManage)
<x-admin.modal id="edit-profile" title="Edit profile" kicker="{{ $user->name }}" subtitle="Update identity, contact details and account classification." size="lg">
  <form method="post" action="{{ route('admin.users.update', $user) }}">
    @csrf @method('PATCH')
    <input type="hidden" name="_modal" value="edit-profile">
    <div class="a-modal-body">
      <div class="a-form-grid">
        <label class="a-field"><span>Full name</span><input class="a-input" name="name" value="{{ old('name', $user->name) }}" required></label>
        <label class="a-field"><span>Email</span><input class="a-input" type="email" name="email" value="{{ old('email', $user->email) }}" required></label>
        <label class="a-field"><span>Phone</span><input class="a-input" name="phone" value="{{ old('phone', $user->phone) }}"></label>
        <label class="a-field"><span>Job title</span><input class="a-input" name="job_title" value="{{ old('job_title', $user->job_title) }}" placeholder="Internal team only"></label>
        <label class="a-field"><span>City</span><input class="a-input" name="city" value="{{ old('city', $user->city) }}"></label>
        <label class="a-field"><span>State</span><input class="a-input" name="state" value="{{ old('state', $user->state) }}"></label>
        <label class="a-field"><span>ZIP / postal code</span><input class="a-input" name="postal_code" value="{{ old('postal_code', $user->postal_code) }}"></label>
        <label class="a-field"><span>Account type</span><select class="a-select" name="account_type">@foreach(\App\Models\User::TYPES as $value => $label)<option value="{{ $value }}" @selected(old('account_type', $user->account_type) === $value) @disabled($value === 'admin' && !$me->isAdminAccount())>{{ $label }}</option>@endforeach</select></label>
      </div>
      <div class="a-form-section">
        <div class="a-check-grid">
          <label class="a-check"><input type="checkbox" name="email_verified" value="1" @checked(old('email_verified', (bool) $user->email_verified_at))><span><strong>Email verified</strong><small>Uncheck to require verification again.</small></span></label>
          <label class="a-check"><input type="checkbox" name="marketing" value="1" @checked(old('marketing', !empty($prefs['marketing'])))><span><strong>Marketing emails</strong><small>Receives MWCN updates and opportunities.</small></span></label>
        </div>
      </div>
      <div class="a-form-section"><label class="a-field"><span>Internal notes</span><textarea class="a-textarea" name="internal_notes">{{ old('internal_notes', $user->internal_notes) }}</textarea></label></div>
      <x-admin.reason />
    </div>
    <div class="a-modal-foot"><button class="a-btn ghost" type="button" data-modal-close>Cancel</button><button class="a-btn primary" type="submit">Save profile</button></div>
  </form>
</x-admin.modal>

<x-admin.modal id="account-status" title="Account status" kicker="{{ $user->name }}" subtitle="Inactive and suspended accounts cannot sign in. Active sessions are ended immediately." size="sm">
  <form method="post" action="{{ route('admin.users.status', $user) }}">
    @csrf @method('PATCH')
    <input type="hidden" name="_modal" value="account-status">
    <div class="a-modal-body">
      <div class="a-stack" style="gap:8px">
        @foreach(\App\Models\User::STATUSES as $value => $label)
          <label class="a-check"><input type="radio" name="status" value="{{ $value }}" @checked(old('status', $user->status) === $value)><span><strong>{{ $label }}</strong><small>{{ ['active' => 'Normal access to the website and portal.', 'inactive' => 'Account is parked; the user cannot sign in.', 'suspended' => 'Blocked for policy or billing reasons.'][$value] }}</small></span></label>
        @endforeach
      </div>
      <x-admin.reason />
    </div>
    <div class="a-modal-foot"><button class="a-btn ghost" type="button" data-modal-close>Cancel</button><button class="a-btn primary" type="submit">Update status</button></div>
  </form>
</x-admin.modal>

<x-admin.modal id="reset-password" title="Reset password" kicker="{{ $user->name }}" subtitle="Set a new password on behalf of this user." size="sm">
  <form method="post" action="{{ route('admin.users.password', $user) }}">
    @csrf @method('PATCH')
    <input type="hidden" name="_modal" value="reset-password">
    <div class="a-modal-body">
      <div class="a-stack" style="gap:12px">
        <label class="a-field"><span>New password</span><input class="a-input" type="password" name="password" autocomplete="new-password" required minlength="10"></label>
        <label class="a-field"><span>Confirm password</span><input class="a-input" type="password" name="password_confirmation" autocomplete="new-password" required minlength="10"></label>
        <div style="display:flex;gap:8px"><button class="a-btn subtle sm" type="button" data-generate-password><svg><use href="#ai-key"></use></svg>Generate</button><button class="a-btn subtle sm" type="button" data-toggle-password><svg><use href="#ai-eye"></use></svg>Show / hide</button></div>
        <label class="a-check"><input type="checkbox" name="sign_out" value="1" checked><span><strong>Sign out all devices</strong><small>Ends every active session for this user.</small></span></label>
      </div>
      <x-admin.reason />
    </div>
    <div class="a-modal-foot"><button class="a-btn ghost" type="button" data-modal-close>Cancel</button><button class="a-btn primary" type="submit">Set password</button></div>
  </form>
</x-admin.modal>
@endif

@if($canRoles)
<x-admin.modal id="edit-roles" title="Assign roles" kicker="{{ $user->name }}" subtitle="Roles are the primary way access is granted. Internal roles automatically mark the account as team." size="lg">
  <form method="post" action="{{ route('admin.users.roles', $user) }}">
    @csrf @method('PATCH')
    <input type="hidden" name="_modal" value="edit-roles">
    <div class="a-modal-body">
      <div class="a-check-grid">
        @foreach($roles as $role)
          <label class="a-check"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', $user->roles->pluck('id')->all()))) @disabled($role->name === 'super_admin' && !$me->hasRole('super_admin'))><span><strong>{{ $role->label }}</strong><small><code class="a-code">{{ $role->name }}</code> · {{ in_array($role->name, \App\Models\Role::STAFF_ROLES, true) ? 'Internal' : 'Customer' }} · {{ $role->permissionRecords->count() }} permissions</small></span></label>
        @endforeach
      </div>
      <x-admin.reason label="Reason for changing roles" />
    </div>
    <div class="a-modal-foot"><button class="a-btn ghost" type="button" data-modal-close>Cancel</button><button class="a-btn primary" type="submit">Save roles</button></div>
  </form>
</x-admin.modal>

<x-admin.modal id="edit-permissions" title="Direct permissions" kicker="{{ $user->name }}" subtitle="Grant individual permissions beyond what the assigned roles provide. Permissions inherited from roles are shown for reference." size="xl">
  <form method="post" action="{{ route('admin.users.permissions', $user) }}">
    @csrf @method('PATCH')
    <input type="hidden" name="_modal" value="edit-permissions">
    <div class="a-modal-body">
      @php($roleGranted = $user->roles->flatMap->permissionRecords->pluck('id')->unique()->all())
      <div style="display:flex;justify-content:flex-end;margin-bottom:6px"><button class="a-btn subtle sm" type="button" data-select-all="direct-permission-list">Select / clear all</button></div>
      <div id="direct-permission-list">
      @foreach($permissions as $group => $groupPermissions)
        <div class="a-perm-group">
          <h5>{{ $group }}</h5>
          <div class="a-check-grid">
            @foreach($groupPermissions as $permission)
              @php($viaRole = in_array($permission->id, $roleGranted, true))
              <label class="a-check"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, old('permissions', $directIds)))><span><strong>{{ $permission->label }}</strong><small><code class="a-code">{{ $permission->name }}</code>{{ $viaRole ? ' · already granted by a role' : '' }}</small></span></label>
            @endforeach
          </div>
        </div>
      @endforeach
      </div>
      <x-admin.reason label="Reason for changing permissions" />
    </div>
    <div class="a-modal-foot"><button class="a-btn ghost" type="button" data-modal-close>Cancel</button><button class="a-btn primary" type="submit">Save permissions</button></div>
  </form>
</x-admin.modal>
@endif
<?php page_end(); ?>
