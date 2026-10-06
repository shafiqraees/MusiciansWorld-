<?php page_start($meta['title'].' — MWCN ERP'); $canManage = auth()->user()->hasPermission('users.manage'); $isTeam = in_array($segment, ['admins', 'staff', 'all'], true); ?>
<section class="section"><div class="wrap">
  <div class="a-page-head">
    <div>
      <p class="a-kicker">{{ $meta['kicker'] }}</p>
      <h2>{{ $meta['title'] }}</h2>
      <p>{{ $meta['intro'] }}</p>
    </div>
    <div class="a-page-actions">
      <a class="a-btn ghost" href="{{ route('admin.export', ['type' => 'members']) }}"><svg><use href="#ai-download"></use></svg>Export CSV</a>
      @if($canManage && $isTeam)
        <button class="a-btn primary" type="button" data-modal-open="create-user"><svg><use href="#ai-plus"></use></svg>Add team member</button>
      @endif
    </div>
  </div>

  <div class="a-tabs" role="tablist" aria-label="User segments">
    <a class="a-tab {{ $segment === 'all' ? 'is-active' : '' }}" href="{{ route('admin.users') }}">All users</a>
    <a class="a-tab {{ $segment === 'customers' ? 'is-active' : '' }}" href="{{ route('admin.customers') }}">Customers</a>
    <a class="a-tab {{ $segment === 'admins' ? 'is-active' : '' }}" href="{{ route('admin.admins') }}">Internal admins</a>
    <a class="a-tab {{ $segment === 'staff' ? 'is-active' : '' }}" href="{{ route('admin.staff') }}">Internal staff</a>
  </div>

  <div class="a-stats">
    <article class="a-stat"><small>In this view</small><strong>{{ number_format($stats['total']) }}</strong><span>Matching the current filters</span></article>
    <article class="a-stat"><small>Active accounts</small><strong>{{ number_format($stats['active']) }}</strong><span>Able to sign in</span></article>
    <article class="a-stat"><small>Unverified email</small><strong>{{ number_format($stats['unverified']) }}</strong><span>Verification pending</span></article>
    <article class="a-stat"><small>New in 30 days</small><strong>{{ number_format($stats['new_30d']) }}</strong><span>Recently created</span></article>
  </div>

  <form method="get" class="a-toolbar">
    <label class="a-field grow"><span>Search</span><input class="a-input" name="search" value="{{ request('search') }}" placeholder="Name, email, phone or ID"></label>
    <label class="a-field"><span>Role</span><select class="a-select" name="role"><option value="">All roles</option>@foreach($segmentRoles as $role)<option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ $role->label }}</option>@endforeach</select></label>
    <label class="a-field"><span>Status</span><select class="a-select" name="status"><option value="">Any status</option>@foreach(\App\Models\User::STATUSES as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label>
    <label class="a-field"><span>Email</span><select class="a-select" name="verified"><option value="">Any</option><option value="yes" @selected(request('verified') === 'yes')>Verified</option><option value="no" @selected(request('verified') === 'no')>Unverified</option></select></label>
    @if($segment === 'customers' || $segment === 'all')
      <label class="a-field"><span>Membership</span><select class="a-select" name="plan"><option value="">Any plan</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((string) request('plan') === (string) $plan->id)>{{ $plan->name }}</option>@endforeach</select></label>
    @endif
    <label class="a-field"><span>Sort</span><select class="a-select" name="sort"><option value="">Newest first</option><option value="name" @selected(request('sort') === 'name')>Name</option><option value="last_login_at" @selected(request('sort') === 'last_login_at')>Last sign-in</option><option value="bookings_count" @selected(request('sort') === 'bookings_count')>Most bookings</option></select></label>
    <input type="hidden" name="dir" value="{{ request('sort') === 'name' ? 'asc' : 'desc' }}">
    <div class="a-actions"><button class="a-btn primary" type="submit"><svg><use href="#ai-search"></use></svg>Filter</button><a class="a-btn ghost" href="{{ url()->current() }}">Reset</a></div>
  </form>

  <div class="a-table-wrap">
    <table class="a-table">
      <thead><tr>
        <th>User</th><th>Contact</th><th>Type</th><th>Roles</th>
        @if($segment !== 'admins' && $segment !== 'staff')<th>Membership</th>@endif
        @if($segment === 'admins' || $segment === 'staff')<th>Title</th>@endif
        <th>Activity</th><th>Status</th><th>Joined · Last sign-in</th><th class="actions">Actions</th>
      </tr></thead>
      <tbody>
      @forelse($users as $row)
        @php($membership = $row->memberships->whereIn('status', ['active', 'trialing', 'grace'])->sortByDesc('created_at')->first())
        <tr>
          <td><div class="a-user-cell"><span class="a-avatar {{ $row->isStaffAccount() ? '' : 'neutral' }}">{{ $row->initials() }}</span><span><strong><a class="a-link" style="color:inherit" href="{{ route('admin.users.show', $row) }}">{{ $row->name }}</a></strong><small>ID #{{ $row->id }}</small></span></div></td>
          <td>{{ $row->email }}<small>{{ $row->phone ?: 'No phone on file' }}</small></td>
          <td><span class="a-badge plain {{ $row->account_type === 'admin' ? 'purple' : ($row->account_type === 'staff' ? 'info' : 'neutral') }}">{{ $row->accountTypeLabel() }}</span></td>
          <td><div class="a-badges">@forelse($row->roles as $role)<span class="a-pill">{{ $role->label }}</span>@empty<span class="a-sub">No role</span>@endforelse</div></td>
          @if($segment !== 'admins' && $segment !== 'staff')
            <td>@if($membership)<x-admin.badge :status="$membership->status" :label="$membership->plan?->name" /><small>{{ a_label($membership->status) }}@if($membership->current_period_end) · renews {{ $membership->current_period_end->format('M j') }}@endif</small>@else<span class="a-sub">No membership</span>@endif</td>
          @endif
          @if($segment === 'admins' || $segment === 'staff')
            <td>{{ $row->job_title ?: '—' }}</td>
          @endif
          <td class="nowrap"><span title="Bookings">{{ $row->bookings_count }} bookings</span><small>{{ $row->events_count }} events · {{ $row->tickets_count }} tickets</small></td>
          <td><x-admin.badge :status="$row->status" /><small>{{ $row->email_verified_at ? 'Email verified' : 'Unverified email' }}</small></td>
          <td class="nowrap">{{ $row->created_at->format('M j, Y') }}<small>{{ $row->last_login_at ? 'Signed in '.$row->last_login_at->diffForHumans() : 'Never signed in' }}</small></td>
          <td class="actions"><div class="a-row-actions"><a class="a-btn ghost sm" href="{{ route('admin.users.show', $row) }}"><svg><use href="#ai-eye"></use></svg>View</a></div></td>
        </tr>
      @empty
        <tr><td colspan="10"><div class="a-table-empty"><strong>No users match these filters</strong>Try widening your search or clearing the filters.</div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $users->links('admin.partials.pagination') }}
</div></section>

@if($canManage && $isTeam)
<x-admin.modal id="create-user" title="Add a team member" kicker="Internal team" subtitle="Create an internal admin or staff account, assign roles and set the initial password." size="lg">
  <form method="post" action="{{ route('admin.users.store') }}">
    @csrf
    <input type="hidden" name="_modal" value="create-user">
    <div class="a-modal-body">
      <div class="a-form-grid">
        <label class="a-field"><span>Full name</span><input class="a-input" name="name" value="{{ old('name') }}" required></label>
        <label class="a-field"><span>Work email</span><input class="a-input" type="email" name="email" value="{{ old('email') }}" required></label>
        <label class="a-field"><span>Phone</span><input class="a-input" name="phone" value="{{ old('phone') }}"></label>
        <label class="a-field"><span>Job title</span><input class="a-input" name="job_title" value="{{ old('job_title') }}" placeholder="e.g. Studio Coordinator"></label>
        <label class="a-field span-2"><span>Account type</span>
          <select class="a-select" name="account_type" required>
            <option value="staff" @selected(old('account_type', $segment === 'admins' ? 'admin' : 'staff') === 'staff')>Internal staff — operations, instructors, check-in</option>
            <option value="admin" @selected(old('account_type', $segment === 'admins' ? 'admin' : 'staff') === 'admin')>Internal admin — finance, settings, access control</option>
          </select>
        </label>
      </div>
      <div class="a-form-section">
        <h4>Roles</h4>
        <div class="a-check-grid">
          @foreach($staffRoles as $role)
            <label class="a-check"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', []))) @disabled($role->name === 'super_admin' && !auth()->user()->hasRole('super_admin'))><span><strong>{{ $role->label }}</strong><small>{{ $role->permissionRecords()->count() }} permissions · <code class="a-code">{{ $role->name }}</code></small></span></label>
          @endforeach
        </div>
      </div>
      <div class="a-form-section">
        <h4>Initial password</h4>
        <div class="a-form-grid">
          <label class="a-field"><span>Password</span><input class="a-input" type="password" name="password" autocomplete="new-password" required minlength="10"></label>
          <label class="a-field"><span>Confirm password</span><input class="a-input" type="password" name="password_confirmation" autocomplete="new-password" required minlength="10"></label>
        </div>
        <div style="display:flex;gap:8px;margin-top:10px"><button class="a-btn subtle sm" type="button" data-generate-password><svg><use href="#ai-key"></use></svg>Generate strong password</button><button class="a-btn subtle sm" type="button" data-toggle-password><svg><use href="#ai-eye"></use></svg>Show / hide</button></div>
        <p class="a-hint" style="margin:8px 0 0">At least 10 characters with letters and numbers. Share it securely — the team member can change it after signing in.</p>
      </div>
      <div class="a-form-section">
        <label class="a-field"><span>Internal notes</span><textarea class="a-textarea" name="internal_notes" placeholder="Visible to admins only">{{ old('internal_notes') }}</textarea></label>
      </div>
      <x-admin.reason label="Reason for creating this account" />
    </div>
    <div class="a-modal-foot">
      <span class="left">The account is created verified and active.</span>
      <button class="a-btn ghost" type="button" data-modal-close>Cancel</button>
      <button class="a-btn primary" type="submit">Create account</button>
    </div>
  </form>
</x-admin.modal>
@endif
<?php page_end(); ?>
