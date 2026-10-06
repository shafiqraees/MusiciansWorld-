<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RecordsAudit;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MembershipPlan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    use RecordsAudit;

    /** Listing segments exposed in the navigation. */
    public const SEGMENTS = [
        'all' => ['title' => 'All users', 'kicker' => 'People & access', 'intro' => 'Every account on the platform: customers, organizers, internal admins and staff.'],
        'customers' => ['title' => 'Customers', 'kicker' => 'People & access', 'intro' => 'Public customer and organizer accounts with memberships, bookings, tickets and events.'],
        'admins' => ['title' => 'Internal admins', 'kicker' => 'Internal team', 'intro' => 'Administrator accounts with elevated access to finance, settings and access control.'],
        'staff' => ['title' => 'Internal staff', 'kicker' => 'Internal team', 'intro' => 'Operations, instructor and check-in team members who work inside the portal.'],
    ];

    public function index(Request $request, string $segment = 'all')
    {
        abort_unless(array_key_exists($segment, self::SEGMENTS), 404);

        $query = User::query()
            ->with(['roles', 'memberships.plan'])
            ->withCount(['bookings', 'events', 'tickets', 'orders'])
            ->latest();

        $this->applySegment($query, $segment);

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(fn ($row) => $row->where('name', 'like', $search)->orWhere('email', 'like', $search)->orWhere('phone', 'like', $search)->orWhere('id', (int) trim($search, '%')));
        }
        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($row) => $row->where('name', $request->string('role')));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('verified')) {
            $request->string('verified')->toString() === 'yes' ? $query->whereNotNull('email_verified_at') : $query->whereNull('email_verified_at');
        }
        if ($request->filled('plan')) {
            $query->whereHas('memberships', fn ($row) => $row->where('membership_plan_id', (int) $request->input('plan'))->whereIn('status', ['active', 'trialing', 'grace']));
        }
        if (in_array($request->input('sort'), ['name', 'email', 'created_at', 'last_login_at', 'bookings_count'], true)) {
            $query->reorder($request->input('sort'), $request->input('dir') === 'asc' ? 'asc' : 'desc');
        }

        $stats = [
            'total' => (clone $query)->reorder()->count(),
            'active' => (clone $query)->reorder()->where('status', 'active')->count(),
            'unverified' => (clone $query)->reorder()->whereNull('email_verified_at')->count(),
            'new_30d' => (clone $query)->reorder()->where('created_at', '>=', now()->subDays(30))->count(),
        ];

        $roles = Role::orderBy('label')->get();
        $segmentRoles = match ($segment) {
            'customers' => $roles->whereIn('name', Role::CUSTOMER_ROLES)->values(),
            'admins' => $roles->whereIn('name', Role::ADMIN_ROLES)->values(),
            'staff' => $roles->whereIn('name', Role::STAFF_ROLES)->values(),
            default => $roles,
        };

        return view('admin.users.index', [
            'segment' => $segment,
            'meta' => self::SEGMENTS[$segment],
            'users' => $query->paginate(25)->withQueryString(),
            'roles' => $roles,
            'segmentRoles' => $segmentRoles,
            'staffRoles' => $roles->whereIn('name', Role::STAFF_ROLES)->values(),
            'plans' => MembershipPlan::orderBy('sort_order')->get(),
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, User $user)
    {
        $user->load([
            'roles.permissionRecords',
            'directPermissions',
            'memberships.plan',
            'bookings.resource',
            'registrations.program',
            'tickets.event',
            'tickets.ticketType',
            'orders.event',
            'events.settlement',
            'events.resource',
            'organizerProfile',
            'transactions',
            'submissions',
        ]);
        $history = AuditLog::with('actor')
            ->where(fn ($q) => $q->where(fn ($inner) => $inner->where('auditable_type', User::class)->where('auditable_id', $user->id))->orWhere('actor_id', $user->id))
            ->latest()->limit(40)->get();
        $sessions = DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->limit(5)->get();

        return view('admin.users.show', [
            'user' => $user,
            'roles' => Role::with('permissionRecords')->orderBy('label')->get(),
            'permissions' => Permission::orderBy('group')->orderBy('label')->get()->groupBy('group'),
            'effective' => $user->effectivePermissions(),
            'plans' => MembershipPlan::orderBy('sort_order')->get(),
            'history' => $history,
            'sessions' => $sessions,
            'segment' => $user->isAdminAccount() ? 'admins' : ($user->isStaffAccount() ? 'staff' : 'customers'),
            'totals' => [
                'spent' => (int) $user->transactions->where('status', 'succeeded')->where('amount_cents', '>', 0)->sum('amount_cents'),
                'bookings' => $user->bookings->count(),
                'tickets' => $user->tickets->count(),
                'events' => $user->events->count(),
            ],
        ]);
    }

    /** Create an internal admin or staff account. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'account_type' => ['required', Rule::in([User::TYPE_ADMIN, User::TYPE_STAFF])],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'internal_notes' => ['nullable', 'string', 'max:3000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($data['account_type'] === User::TYPE_ADMIN && !$request->user()->isAdminAccount()) {
            return back()->withErrors(['account_type' => 'Only an administrator can create another administrator account.'])->withInput();
        }
        $superAdminId = Role::where('name', 'super_admin')->value('id');
        if (in_array((int) $superAdminId, array_map('intval', $data['roles']), true) && !$request->user()->hasRole('super_admin')) {
            return back()->withErrors(['roles' => 'Only a Super Admin can grant the Super Admin role.'])->withInput();
        }

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'account_type' => $data['account_type'],
                'status' => 'active',
                'email_verified_at' => now(),
                'password' => Hash::make($data['password']),
            ]);
            $user->roles()->sync($data['roles']);
            return $user;
        });
        $this->audit($request, 'user.created', $user, null, $data['reason']);

        return redirect()->route('admin.users.show', $user)->with('success', $user->name.' was added to the internal team.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'account_type' => ['required', Rule::in(array_keys(User::TYPES))],
            'internal_notes' => ['nullable', 'string', 'max:3000'],
            'marketing' => ['nullable', 'boolean'],
            'email_verified' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($user->is($request->user()) && $data['account_type'] === User::TYPE_CUSTOMER) {
            return back()->withErrors(['account_type' => 'You cannot convert your own account to a customer account.'])->withInput()->with('modal', 'edit-profile');
        }
        if ($data['account_type'] === User::TYPE_ADMIN && $user->account_type !== User::TYPE_ADMIN && !$request->user()->isAdminAccount()) {
            return back()->withErrors(['account_type' => 'Only an administrator can promote an account to Internal Admin.'])->withInput()->with('modal', 'edit-profile');
        }

        $before = $user->toArray();
        $user->update([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'account_type' => $data['account_type'],
            'internal_notes' => $data['internal_notes'] ?? null,
            'communication_preferences' => array_merge($user->communication_preferences ?? [], ['marketing' => $request->boolean('marketing')]),
            'email_verified_at' => $request->boolean('email_verified') ? ($user->email_verified_at ?? now()) : null,
        ]);
        $this->audit($request, 'user.updated', $user, $before, $data['reason']);

        return back()->with('success', 'Profile details saved.');
    }

    public function updateStatus(Request $request, User $user)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(User::STATUSES))], 'reason' => ['required', 'string', 'max:500']]);
        if ($user->is($request->user()) && $data['status'] !== 'active') {
            return back()->withErrors(['status' => 'You cannot deactivate the account you are signed in with.'])->with('modal', 'account-status');
        }
        $before = $user->toArray();
        $user->update(['status' => $data['status']]);
        if ($data['status'] !== 'active') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $this->audit($request, 'user.status_changed', $user, $before, $data['reason']);
        return back()->with('success', 'Account status set to '.User::STATUSES[$data['status']].'.');
    }

    public function updatePassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'sign_out' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => str()->random(60)])->save();
        if ($request->boolean('sign_out')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $this->audit($request, 'user.password_reset', $user, null, $data['reason'], ['password' => '[changed]']);
        return back()->with('success', 'Password updated.');
    }

    public function updateRoles(Request $request, User $user)
    {
        $data = $request->validate(['roles' => ['nullable', 'array'], 'roles.*' => ['integer', 'exists:roles,id'], 'reason' => ['required', 'string', 'max:500']]);
        $roleIds = array_map('intval', $data['roles'] ?? []);
        $superAdminRoleId = (int) Role::where('name', 'super_admin')->value('id');
        $keepsSuperAdmin = in_array($superAdminRoleId, $roleIds, true);

        if ($keepsSuperAdmin && !$user->hasRole('super_admin') && !$request->user()->hasRole('super_admin')) {
            return back()->withErrors(['roles' => 'Only a Super Admin can grant the Super Admin role.'])->with('modal', 'edit-roles');
        }
        if ($user->hasRole('super_admin') && !$keepsSuperAdmin) {
            $otherSuperAdmins = User::whereKeyNot($user->id)->whereHas('roles', fn ($query) => $query->where('name', 'super_admin'))->exists();
            if ($user->is($request->user()) || !$otherSuperAdmins) {
                return back()->withErrors(['roles' => 'You cannot remove the active or final Super Admin role.'])->with('modal', 'edit-roles');
            }
        }

        $before = $user->roles()->pluck('roles.id')->all();
        $user->roles()->sync($roleIds);
        $user->load('roles');
        // Keep the account type in step with the roles that were granted.
        if ($user->account_type !== User::TYPE_ADMIN && $user->hasAnyRole(Role::ADMIN_ROLES)) {
            $user->update(['account_type' => User::TYPE_ADMIN]);
        } elseif ($user->account_type === User::TYPE_CUSTOMER && $user->hasAnyRole(Role::STAFF_ROLES)) {
            $user->update(['account_type' => User::TYPE_STAFF]);
        }
        $this->audit($request, 'user.roles_updated', $user, ['role_ids' => $before], $data['reason'], ['role_ids' => $roleIds]);

        return back()->with('success', 'Roles updated for '.$user->name.'.');
    }

    public function updatePermissions(Request $request, User $user)
    {
        $data = $request->validate(['permissions' => ['nullable', 'array'], 'permissions.*' => ['integer', 'exists:permissions,id'], 'reason' => ['required', 'string', 'max:500']]);
        $before = $user->directPermissions()->pluck('permissions.id')->all();
        $after = array_map('intval', $data['permissions'] ?? []);
        $user->directPermissions()->sync($after);
        $this->audit($request, 'user.permissions_updated', $user, ['permission_ids' => $before], $data['reason'], ['permission_ids' => $after]);
        return back()->with('success', 'Direct permissions updated for '.$user->name.'.');
    }

    private function applySegment($query, string $segment): void
    {
        $staffRoleIds = Role::whereIn('name', Role::STAFF_ROLES)->pluck('id');
        $adminRoleIds = Role::whereIn('name', Role::ADMIN_ROLES)->pluck('id');
        $hasRole = fn ($ids) => fn ($q) => $q->whereIn('roles.id', $ids);

        match ($segment) {
            'customers' => $query->where('account_type', User::TYPE_CUSTOMER)->whereDoesntHave('roles', $hasRole($staffRoleIds)),
            'admins' => $query->where(fn ($q) => $q->where('account_type', User::TYPE_ADMIN)->orWhereHas('roles', $hasRole($adminRoleIds))),
            'staff' => $query->where(fn ($q) => $q->where('account_type', User::TYPE_STAFF)->orWhereHas('roles', $hasRole($staffRoleIds)))
                ->where('account_type', '!=', User::TYPE_ADMIN)->whereDoesntHave('roles', $hasRole($adminRoleIds)),
            default => null,
        };
    }
}
