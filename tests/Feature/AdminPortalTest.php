<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@musiciansworld.com')->firstOrFail();
    }

    private function customer(): User
    {
        $customer = User::create(['name' => 'Casey Customer', 'email' => 'casey@example.com', 'password' => Hash::make('Password123')]);
        $customer->roles()->attach(Role::where('name', 'customer')->value('id'));

        return $customer;
    }

    public function test_admin_and_customer_logins_are_separate(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('Admin login');
        $this->get('/login')->assertOk()->assertSee('Use the Admin Login');

        // Guests hitting the portal are sent to the admin login, not the customer one.
        $this->get('/admin')->assertRedirect(route('admin.login'));

        $customer = $this->customer();
        $this->post('/admin/login', ['email' => $customer->email, 'password' => 'Password123'])
            ->assertRedirect()->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'admin@musiciansworld.com', 'password' => 'Admin1234'])
            ->assertRedirect()->assertSessionHasErrors('email')->assertSessionHas('admin_login_hint');
        $this->assertGuest();

        $this->post('/admin/login', ['email' => 'admin@musiciansworld.com', 'password' => 'Admin1234'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->admin());
        $this->assertNotNull($this->admin()->last_login_at);
    }

    public function test_user_directories_and_detail_page_render(): void
    {
        $customer = $this->customer();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee($customer->name)->assertSee($admin->name);
        $this->actingAs($admin)->get('/admin/customers')->assertOk()->assertSee($customer->name)->assertDontSee($admin->email);
        $this->actingAs($admin)->get('/admin/admins')->assertOk()->assertSee($admin->email)->assertDontSee($customer->email);
        $this->actingAs($admin)->get('/admin/staff')->assertOk();
        $this->actingAs($admin)->get(route('admin.users.show', $customer))->assertOk()->assertSee('Direct permissions')->assertSee('Effective permissions')->assertSee($customer->email);

        foreach (['/admin/memberships', '/admin/bookings', '/admin/events', '/admin/settings', '/admin/settings/platform', '/admin/settings/pricing', '/admin/settings/availability', '/admin/settings/integrations', '/admin/roles'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }

        $this->actingAs($admin)->get('/admin')->assertOk()->assertDontSee('/admin/failures')->assertDontSee('/admin/audit');
    }

    public function test_admin_can_create_staff_and_manage_roles_and_direct_permissions(): void
    {
        $admin = $this->admin();
        $operations = Role::where('name', 'operations')->firstOrFail();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Sam Staff',
            'email' => 'sam@musiciansworld.com',
            'account_type' => User::TYPE_STAFF,
            'job_title' => 'Front Desk',
            'roles' => [$operations->id],
            'password' => 'StaffPass123',
            'password_confirmation' => 'StaffPass123',
            'reason' => 'Onboarding new front desk hire.',
        ])->assertRedirect();

        $staff = User::where('email', 'sam@musiciansworld.com')->firstOrFail();
        $this->assertSame(User::TYPE_STAFF, $staff->account_type);
        $this->assertTrue($staff->hasRole('operations'));
        $this->assertTrue($staff->isStaffAccount());

        // Staff can use the admin portal but only within their permissions.
        $this->actingAs($staff)->get('/admin/bookings')->assertOk();
        $this->actingAs($staff)->get('/admin/reports')->assertForbidden();

        // Grant a direct permission from the user detail page.
        $reports = Permission::where('name', 'reports.view')->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.users.permissions', $staff), [
            'permissions' => [$reports->id],
            'reason' => 'Temporary reporting access.',
        ])->assertRedirect();
        $this->assertTrue($staff->fresh()->hasPermission('reports.view'));
        $this->actingAs($staff->fresh())->get('/admin/reports')->assertOk();

        // Reassign roles and confirm account type follows.
        $finance = Role::where('name', 'finance')->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.users.roles', $staff), [
            'roles' => [$finance->id],
            'reason' => 'Moving to finance team.',
        ])->assertRedirect();
        $staff->refresh();
        $this->assertTrue($staff->hasRole('finance'));
        $this->assertFalse($staff->hasRole('operations'));
        $this->assertSame(User::TYPE_ADMIN, $staff->account_type);

        // Suspended staff lose portal access.
        $this->actingAs($admin)->patch(route('admin.users.status', $staff), ['status' => 'suspended', 'reason' => 'Offboarding.'])->assertRedirect();
        $this->actingAs($staff->fresh())->get('/admin')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $staff->id, 'action' => 'user.roles_updated']);
    }

    public function test_admin_cannot_remove_own_super_admin_role(): void
    {
        $admin = $this->admin();
        $operations = Role::where('name', 'operations')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.users.roles', $admin), [
            'roles' => [$operations->id],
            'reason' => 'Trying to demote myself.',
        ])->assertRedirect()->assertSessionHasErrors();

        $this->assertTrue($admin->fresh()->hasRole('super_admin'));
    }
}
