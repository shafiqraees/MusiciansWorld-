<?php page_start('Roles & permissions — MWCN ERP'); $isSuper = auth()->user()->hasRole('super_admin'); $permissionTotal = $permissions->flatten()->count(); ?>
<section class="section"><div class="wrap">
  <div class="a-page-head">
    <div>
      <p class="a-kicker">People & access</p>
      <h2>Roles & permissions</h2>
      <p>Roles bundle permissions and are assigned to users from their detail page. Edit a role to change what every member of that role can do.</p>
    </div>
    <div class="a-page-actions"><a class="a-btn ghost" href="{{ route('admin.staff') }}"><svg><use href="#ai-briefcase"></use></svg>Internal staff</a></div>
  </div>

  <div class="a-stats">
    <article class="a-stat"><small>Roles</small><strong>{{ $roles->count() }}</strong><span>{{ $roles->whereIn('name', \App\Models\Role::STAFF_ROLES)->count() }} internal · {{ $roles->whereIn('name', \App\Models\Role::CUSTOMER_ROLES)->count() }} customer</span></article>
    <article class="a-stat"><small>Permissions</small><strong>{{ $permissionTotal }}</strong><span>Across {{ $permissions->count() }} groups</span></article>
    <article class="a-stat"><small>Users with roles</small><strong>{{ $roles->sum('users_count') }}</strong><span>Role assignments</span></article>
    <article class="a-stat"><small>Super admins</small><strong>{{ $roles->firstWhere('name', 'super_admin')?->users_count ?? 0 }}</strong><span>Unrestricted access</span></article>
  </div>

  <div class="a-table-wrap">
    <table class="a-table">
      <thead><tr><th>Role</th><th>Scope</th><th>Permissions</th><th class="num">Users</th><th class="actions">Actions</th></tr></thead>
      <tbody>
      @foreach($roles as $role)
        <tr>
          <td><strong>{{ $role->label }}</strong><small><code class="a-code">{{ $role->name }}</code></small></td>
          <td><span class="a-badge plain {{ $role->name === 'super_admin' ? 'purple' : ($role->isStaffRole() ? 'info' : 'neutral') }}">{{ $role->name === 'super_admin' ? 'Full access' : ($role->isStaffRole() ? 'Internal' : 'Customer') }}</span></td>
          <td>@if($role->name === 'super_admin')<span class="a-sub">All {{ $permissionTotal }} permissions, always.</span>@else<div class="a-badges">@forelse($role->permissionRecords->take(6) as $permission)<span class="a-pill" title="{{ $permission->name }}">{{ $permission->label }}</span>@empty<span class="a-sub">No portal permissions</span>@endforelse @if($role->permissionRecords->count() > 6)<span class="a-pill">+{{ $role->permissionRecords->count() - 6 }} more</span>@endif</div>@endif</td>
          <td class="num"><a class="a-link" href="{{ route('admin.users', ['role' => $role->name]) }}">{{ $role->users_count }}</a></td>
          <td class="actions"><div class="a-row-actions"><button class="a-btn ghost icon sm" type="button" data-modal-open="role-{{ $role->id }}" aria-label="Edit {{ $role->label }}" title="Edit role" @disabled($role->name === 'super_admin' && !$isSuper)><svg><use href="#ai-pencil"></use></svg></button></div></td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>

  <section class="a-card" style="margin-top:20px">
    <div class="a-card-head"><div><h3>Permission matrix</h3><p>Which roles hold each permission. Super Admin always has everything.</p></div></div>
    <div class="a-table-wrap"><table class="a-table">
      <thead><tr><th>Permission</th>@foreach($roles as $role)<th style="text-align:center">{{ $role->label }}</th>@endforeach</tr></thead>
      <tbody>
      @foreach($permissions as $group => $groupPermissions)
        <tr><td colspan="{{ $roles->count() + 1 }}" style="background:var(--a-surface-2);padding:8px 14px"><span class="a-kicker is-muted">{{ $group }}</span></td></tr>
        @foreach($groupPermissions as $permission)
          <tr><td>{{ $permission->label }}<small>{{ $permission->name }}</small></td>@foreach($roles as $role)<td style="text-align:center">@if($role->name === 'super_admin' || $role->permissionRecords->contains($permission))<svg class="a-icon" style="color:var(--a-success);display:inline"><use href="#ai-check"></use></svg>@else<span style="color:var(--a-border-strong)">—</span>@endif</td>@endforeach</tr>
        @endforeach
      @endforeach
      </tbody>
    </table></div>
  </section>
</div></section>

@foreach($roles as $role)
<x-admin.modal id="role-{{ $role->id }}" title="Edit role" :kicker="$role->label" :subtitle="$role->users_count.' users hold this role. Changes apply to all of them immediately.'" size="lg">
  <form method="post" action="{{ route('admin.roles.permissions', $role) }}">
    @csrf @method('PATCH')
    <input type="hidden" name="_modal" value="role-{{ $role->id }}">
    <div class="a-modal-body">
      <div class="a-form-grid">
        <label class="a-field"><span>Display name</span><input class="a-input" name="label" value="{{ old('label', $role->label) }}" required></label>
        <label class="a-field"><span>System name</span><input class="a-input" value="{{ $role->name }}" readonly></label>
      </div>
      @if($role->name === 'super_admin')
        <div class="a-alert info" style="margin:16px 0 0"><svg class="a-icon" viewBox="0 0 24 24"><use href="#ai-info"></use></svg><div>Super Admin bypasses permission checks. The selections below are kept for documentation only.</div></div>
      @endif
      <div class="a-form-section">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><h4 style="margin:0">Permissions</h4><button class="a-btn subtle sm" type="button" data-select-all="role-perms-{{ $role->id }}">Select / clear all</button></div>
        <div id="role-perms-{{ $role->id }}">
        @foreach($permissions as $group => $groupPermissions)
          <div class="a-perm-group"><h5>{{ $group }}</h5><div class="a-check-grid">
            @foreach($groupPermissions as $permission)
              <label class="a-check"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, old('permissions', $role->permissionRecords->pluck('id')->all())))><span><strong>{{ $permission->label }}</strong><small><code class="a-code">{{ $permission->name }}</code></small></span></label>
            @endforeach
          </div></div>
        @endforeach
        </div>
      </div>
      <x-admin.reason label="Reason for changing this role" />
    </div>
    <div class="a-modal-foot"><button class="a-btn ghost" type="button" data-modal-close>Cancel</button><button class="a-btn primary" type="submit">Save role</button></div>
  </form>
</x-admin.modal>
@endforeach
<?php page_end(); ?>
