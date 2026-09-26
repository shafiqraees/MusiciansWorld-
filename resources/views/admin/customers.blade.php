<?php page_start('Users — MWCN ERP'); ?>
<section class="section"><div class="wrap">
  <div class="admin-page-head">
    <div><p class="kicker">People & access</p><h2>Users</h2><p>Manage connected customer identities, memberships, staff roles and access.</p></div>
    <div class="flex gap-2"><a class="btn btn-ghost" href="{{ route('admin.roles') }}">Roles & permissions</a><a class="btn btn-mark" href="{{ route('admin.export',['type'=>'members']) }}">Export users</a></div>
  </div>
  @if(session('success'))<p class="mb-5 rounded-lg border border-green-400/20 bg-green-400/5 p-4 text-green-300">{{ session('success') }}</p>@endif
  <form method="get" class="admin-panel mb-5 flex flex-wrap items-end gap-3 p-4">
    <label class="min-w-64 flex-1 text-[10px] uppercase tracking-wider text-dim">Search users<input class="input mt-2 w-full" name="search" value="{{ request('search') }}" placeholder="Name, email or phone"></label>
    <label class="min-w-48 text-[10px] uppercase tracking-wider text-dim">Role<select class="input mt-2 w-full" name="role"><option value="">All roles</option>@foreach($roles as $role)<option value="{{ $role->name }}" @selected(request('role')===$role->name)>{{ $role->label }}</option>@endforeach</select></label>
    <button class="btn btn-mark">Filter</button><a class="btn btn-ghost" href="{{ route('admin.customers') }}">Reset</a>
  </form>
  <div class="admin-panel overflow-x-auto"><table class="w-full min-w-[1050px] text-left text-sm"><thead><tr><th>User</th><th>Contact</th><th>Membership</th><th>Assigned roles</th><th>Account</th><th>Access management</th></tr></thead><tbody>
  @forelse($customers as $customer)<tr>
    <td><div class="flex items-center gap-3"><span class="admin-avatar">{{ strtoupper(substr($customer->name,0,1)) }}</span><span><strong class="block">{{ $customer->name }}</strong><small class="text-dim">ID #{{ $customer->id }} · joined {{ $customer->created_at->format('M j, Y') }}</small></span></div></td>
    <td>{{ $customer->email }}<br><small class="text-dim">{{ $customer->phone ?: 'No phone' }}</small></td>
    <td><span class="admin-status">{{ $customer->activeMembership()?->plan?->name ?? 'Free Community' }}</span></td>
    <td><div class="flex max-w-xs flex-wrap gap-1">@forelse($customer->roles as $role)<span class="rounded-full border border-cyan-300/15 bg-cyan-300/5 px-2 py-1 text-[10px] text-cyan-200">{{ $role->label }}</span>@empty<span class="text-dim">No role</span>@endforelse</div></td>
    <td><span class="admin-status {{ $customer->status !== 'active' ? 'bad' : '' }}">{{ $customer->status }}</span>@if($customer->email_verified_at)<small class="mt-2 block text-dim">Email verified</small>@else<small class="mt-2 block text-yellow-300">Unverified email</small>@endif</td>
    <td><details><summary class="cursor-pointer text-xs text-mark">Edit roles</summary><form method="post" action="{{ route('admin.customers.roles',$customer) }}" class="mt-3 grid min-w-72 gap-2">@csrf @method('PATCH')<div class="grid grid-cols-2 gap-2">@foreach($roles as $role)<label class="text-xs"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($customer->roles->contains($role))> {{ $role->label }}</label>@endforeach</div><input class="input" name="reason" placeholder="Reason for role change" required><button class="btn btn-ghost">Synchronize user roles</button></form></details></td>
  </tr>@empty<tr><td colspan="6" class="py-12 text-center text-dim">No users match these filters.</td></tr>@endforelse
  </tbody></table></div>
  <div class="mt-5">{{ $customers->links() }}</div>
</div></section>
<?php page_end(); ?>
