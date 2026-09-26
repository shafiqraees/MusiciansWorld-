<?php page_start('Roles & permissions — MWCN ERP'); ?>
<section class="section"><div class="wrap">
  <div class="admin-page-head">
    <div><p class="kicker">Users & access</p><h2>Roles & permissions</h2><p>Synchronize staff access across the MWCN operations portal.</p></div>
    <div class="admin-status">{{ $roles->count() }} roles · {{ $permissions->flatten()->count() }} permissions</div>
  </div>
  @if(session('success'))<p class="mb-5 rounded-lg border border-green-400/20 bg-green-400/5 p-4 text-green-300">{{ session('success') }}</p>@endif
  @if($errors->any())<p class="mb-5 rounded-lg border border-red-400/20 bg-red-400/5 p-4 text-red-300">{{ $errors->first() }}</p>@endif
  <div class="grid gap-5 xl:grid-cols-2">
    @foreach($roles as $role)
      <form method="post" action="{{ route('admin.roles.permissions',$role) }}" class="admin-panel overflow-hidden">
        @csrf @method('PATCH')
        <div class="admin-panel-head">
          <div><h3>{{ $role->label }}</h3><p class="mt-1 text-xs text-dim">{{ $role->users_count }} assigned users · <code>{{ $role->name }}</code></p></div>
          <span class="admin-status {{ $role->name === 'super_admin' ? 'warn' : '' }}">{{ $role->permissionRecords->count() }} enabled</span>
        </div>
        <div class="grid gap-5 p-5">
          <label class="text-xs text-dim">Display name<input class="input mt-2 w-full" name="label" value="{{ $role->label }}" required></label>
          @foreach($permissions as $group => $groupPermissions)
            <fieldset>
              <legend class="mb-3 text-[10px] font-bold uppercase tracking-[.16em] text-dim">{{ $group }}</legend>
              <div class="grid gap-2 sm:grid-cols-2">
                @foreach($groupPermissions as $permission)
                  <label class="flex cursor-pointer gap-3 rounded-lg border border-white/5 bg-white/[.015] p-3 transition hover:border-cyan-300/20">
                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissionRecords->contains($permission))>
                    <span><strong class="block text-xs">{{ $permission->label }}</strong><small class="text-[10px] text-dim">{{ $permission->name }}</small></span>
                  </label>
                @endforeach
              </div>
            </fieldset>
          @endforeach
          <label class="text-xs text-dim">Reason for permission change<input class="input mt-2 w-full" name="reason" placeholder="Required for the audit trail" required></label>
          <button class="btn btn-mark" type="submit">Synchronize permissions</button>
        </div>
      </form>
    @endforeach
  </div>
</div></section>
<?php page_end(); ?>
