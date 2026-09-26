<?php

namespace Database\Seeders;

use App\Models\Benefit;
use App\Models\EventService;
use App\Models\MembershipPlan;
use App\Models\PlatformSetting;
use App\Models\Permission;
use App\Models\PricingRule;
use App\Models\Program;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'customer' => ['label' => 'Customer', 'permissions' => ['own.*']],
            'organizer' => ['label' => 'Promoter / Organizer', 'permissions' => ['events.own', 'attendees.own', 'settlements.own']],
            'instructor' => ['label' => 'Instructor', 'permissions' => ['programs.assigned']],
            'operations' => ['label' => 'Booking / Operations', 'permissions' => ['bookings.*', 'calendar.*', 'customers.read']],
            'check_in' => ['label' => 'Event Check-In', 'permissions' => ['tickets.check_in']],
            'finance' => ['label' => 'Finance / Admin', 'permissions' => ['finance.*', 'refunds.*', 'settlements.*']],
            'super_admin' => ['label' => 'Super Admin', 'permissions' => ['*']],
        ] as $name => $data) {
            Role::updateOrCreate(['name' => $name], $data);
        }

        $permissionGroups = [
            'Dashboard' => ['dashboard.view' => 'View operations dashboard'],
            'Users & Access' => [
                'users.view' => 'View users and customer profiles',
                'users.manage' => 'Manage users and account status',
                'roles.manage' => 'Assign roles and synchronize permissions',
            ],
            'Memberships' => [
                'memberships.view' => 'View membership records',
                'memberships.manage' => 'Configure plans and benefits',
            ],
            'Bookings' => [
                'bookings.view' => 'View bookings and availability',
                'bookings.manage' => 'Update bookings, payments and holds',
            ],
            'Events & Tickets' => [
                'events.view' => 'View events and organizer details',
                'events.approve' => 'Approve, publish, suspend and complete events',
                'tickets.check_in' => 'Scan and check in event tickets',
                'settlements.manage' => 'Calculate and approve organizer settlements',
            ],
            'Customer Requests' => [
                'submissions.view' => 'View leads and submitted forms',
                'submissions.manage' => 'Update lead and submission status',
            ],
            'Finance & Reporting' => [
                'reports.view' => 'View operational and financial reporting',
                'reports.export' => 'Export reports and accounting data',
            ],
            'Platform' => [
                'settings.manage' => 'Manage prices, fees and platform settings',
                'integrations.manage' => 'Review and retry failed integrations',
                'audit.view' => 'View security and change audit history',
            ],
        ];
        foreach ($permissionGroups as $group => $permissions) {
            foreach ($permissions as $name => $label) {
                Permission::updateOrCreate(['name' => $name], ['label' => $label, 'group' => $group]);
            }
        }
        $permissionIds = Permission::pluck('id', 'name');
        $allPermissionNames = collect($permissionGroups)->flatMap(fn ($items) => array_keys($items))->all();
        foreach ([
            'customer' => [],
            'organizer' => [],
            'instructor' => ['dashboard.view'],
            'operations' => ['dashboard.view', 'users.view', 'memberships.view', 'bookings.view', 'bookings.manage', 'events.view', 'events.approve', 'tickets.check_in', 'submissions.view', 'submissions.manage', 'integrations.manage'],
            'check_in' => ['dashboard.view', 'events.view', 'tickets.check_in'],
            'finance' => ['dashboard.view', 'users.view', 'memberships.view', 'bookings.view', 'events.view', 'settlements.manage', 'reports.view', 'reports.export', 'audit.view'],
            'super_admin' => $allPermissionNames,
        ] as $roleName => $permissionNames) {
            Role::where('name', $roleName)->firstOrFail()->permissionRecords()->sync(
                collect($permissionNames)->map(fn ($name) => $permissionIds[$name])->filter()->values()->all()
            );
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@musiciansworld.com'],
            ['name' => 'Administrator', 'password' => Hash::make('Admin1234'), 'email_verified_at' => now()]
        );
        $admin->roles()->syncWithoutDetaching([Role::where('name', 'super_admin')->value('id')]);

        foreach ([
            ['slug' => 'community', 'name' => 'Free Community', 'price_cents' => 0, 'sort_order' => 1],
            ['slug' => 'founder', 'name' => 'Founder Membership', 'price_cents' => 12000, 'stripe_price_id' => config('services.stripe.founding_price'), 'sort_order' => 2],
            ['slug' => 'creative-network', 'name' => 'Creative Network Membership', 'price_cents' => 0, 'is_active' => false, 'sort_order' => 3],
            ['slug' => 'pro', 'name' => 'Pro Membership', 'price_cents' => 0, 'is_active' => false, 'sort_order' => 4],
        ] as $plan) {
            MembershipPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }

        foreach ([
            'promoter_fee_bps' => ['name' => 'Promoter platform fee', 'type' => 'integer'],
            'studio_discount_percent' => ['name' => 'Studio discount percent', 'type' => 'integer'],
            'included_studio_minutes' => ['name' => 'Included Studio A minutes per month', 'type' => 'integer'],
        ] as $key => $benefit) {
            Benefit::updateOrCreate(['key' => $key], $benefit);
        }
        $community = MembershipPlan::where('slug', 'community')->firstOrFail();
        $founder = MembershipPlan::where('slug', 'founder')->firstOrFail();
        $fee = Benefit::where('key', 'promoter_fee_bps')->firstOrFail();
        $community->benefits()->syncWithoutDetaching([$fee->id => ['value' => '500']]);
        $founder->benefits()->syncWithoutDetaching([$fee->id => ['value' => '300']]);

        $studio = Resource::updateOrCreate(
            ['slug' => 'studio-a'],
            ['name' => 'Studio A', 'type' => 'studio', 'timezone' => 'America/Phoenix', 'is_active' => true]
        );
        Resource::updateOrCreate(
            ['slug' => 'soundtunnel'],
            ['name' => 'SoundTunnel', 'type' => 'venue', 'timezone' => 'America/Phoenix', 'capacity' => 250, 'is_active' => true]
        );
        foreach ([120 => 8000, 180 => 10500, 240 => 14000, 360 => 20000, 480 => 26000] as $minutes => $cents) {
            PricingRule::updateOrCreate(
                ['resource_id' => $studio->id, 'service_type' => 'studio_booking', 'duration_minutes' => $minutes],
                ['name' => intdiv($minutes, 60).' Hour Studio A Session', 'price_cents' => $cents, 'is_active' => true]
            );
        }

        foreach ([
            ['slug' => 'dj-fundamentals', 'name' => 'DJ Fundamentals'],
            ['slug' => 'dj-performance-lab', 'name' => 'DJ Performance Lab'],
            ['slug' => 'private-dj-lessons', 'name' => 'Private DJ Lessons'],
        ] as $program) {
            Program::updateOrCreate(['slug' => $program['slug']], $program + ['type' => 'dj_school', 'is_active' => true]);
        }

        foreach ([
            ['slug' => 'sound-engineer', 'name' => 'Sound engineer', 'unit' => 'hour'],
            ['slug' => 'dj-services', 'name' => 'DJ services', 'unit' => 'package'],
            ['slug' => 'wireless-microphone', 'name' => 'Wireless microphone', 'unit' => 'item'],
            ['slug' => 'lighting-upgrade', 'name' => 'Lighting upgrade', 'unit' => 'package'],
            ['slug' => 'video-wall', 'name' => 'Video wall / projector', 'unit' => 'package'],
            ['slug' => 'haze-fog', 'name' => 'Haze / fog', 'unit' => 'flat'],
            ['slug' => 'cleanup', 'name' => 'Cleanup', 'unit' => 'flat'],
            ['slug' => 'security', 'name' => 'Staffing / security', 'unit' => 'hour'],
        ] as $service) {
            EventService::updateOrCreate(['slug' => $service['slug']], $service + ['price_cents' => 0, 'is_active' => true]);
        }

        foreach ([
            ['group' => 'fees', 'key' => 'promoter.community.bps', 'value' => '500', 'type' => 'integer'],
            ['group' => 'fees', 'key' => 'promoter.founder.bps', 'value' => '300', 'type' => 'integer'],
            ['group' => 'booking', 'key' => 'checkout_hold_minutes', 'value' => '45', 'type' => 'integer'],
            ['group' => 'stripe', 'key' => 'stripe.connect.enabled', 'value' => 'false', 'type' => 'boolean'],
            ['group' => 'stripe', 'key' => 'stripe.transfers.enabled', 'value' => 'false', 'type' => 'boolean'],
        ] as $setting) {
            PlatformSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }

        if (Schema::hasTable('members')) {
            DB::table('members')->orderBy('id')->each(function ($member) use ($community, $founder) {
                $user = User::firstOrCreate(
                    ['email' => strtolower($member->email)],
                    ['name' => $member->name, 'phone' => $member->phone, 'password' => Hash::make(str()->random(40))]
                );
                $user->roles()->syncWithoutDetaching([Role::where('name', 'customer')->value('id')]);
                $plan = $member->tier === 'founding' ? $founder : $community;
                $user->memberships()->firstOrCreate(
                    ['membership_plan_id' => $plan->id],
                    ['status' => $member->status, 'stripe_customer_id' => $member->stripe_customer_id, 'stripe_subscription_id' => $member->stripe_subscription_id]
                );
            });
        }

        $this->call(DemoDataSeeder::class);
    }
}
