<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FormSubmission;
use App\Models\Permission;
use App\Models\Resource;
use App\Models\Role;
use App\Models\TicketType;
use App\Models\User;
use App\Services\TicketService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
    }

    public function test_preserved_public_pages_render(): void
    {
        foreach (['/', '/spaces', '/spaces/studio-a', '/book', '/soundtunnel', '/dj-school', '/membership', '/events', '/about', '/contact'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/soundtunnel/night')->assertRedirect('/dj-school');
        $this->get('/dj-school')->assertSee('Start your DJ journey')->assertSee('Parent/Legal guardian full name');
    }

    public function test_registration_creates_one_account_and_free_membership(): void
    {
        $this->post('/register', [
            'name' => 'Connected Customer',
            'email' => 'connected@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect('/account');

        $user = User::where('email', 'connected@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('customer'));
        $this->assertSame('community', $user->activeMembership()->plan->slug);
    }

    public function test_laravel_booking_availability_routes_return_calendar_data(): void
    {
        $this->getJson(route('booking.availability', ['resource' => 'studio-a', 'year' => 2026, 'month' => 10]))
            ->assertOk()->assertJsonPath('resource', 'studio-a')->assertJsonPath('year', 2026)->assertJsonStructure(['days']);
        $this->getJson(route('booking.availability', ['resource' => 'studio-a', 'date' => '2026-10-05', 'duration' => 2, 'start' => 600]))
            ->assertOk()->assertJsonPath('price', 80)->assertJsonStructure(['dayStatus', 'busy', 'slots', 'requested']);
        $this->getJson(route('membership.status'))
            ->assertOk()->assertJsonStructure(['max', 'taken', 'remaining'])->assertJsonPath('max', 50);
    }

    public function test_ticket_capacity_and_replay_safe_check_in(): void
    {
        $user = User::create(['name' => 'Buyer', 'email' => 'buyer@example.com', 'password' => Hash::make('Password123'), 'email_verified_at' => now()]);
        $user->roles()->attach(Role::where('name', 'customer')->value('id'));
        $event = Event::create([
            'reference' => 'EVT-TEST', 'organizer_id' => User::where('email', 'admin@musiciansworld.com')->value('id'),
            'resource_id' => Resource::where('slug', 'soundtunnel')->value('id'), 'slug' => 'test-event',
            'title' => 'Test Event', 'description' => 'Test', 'location_type' => 'soundtunnel',
            'starts_at' => now()->addMonth(), 'ends_at' => now()->addMonth()->addHours(3),
            'capacity' => 2, 'status' => 'published', 'platform_fee_bps' => 500,
        ]);
        $type = TicketType::create(['event_id' => $event->id, 'name' => 'Free RSVP', 'price_cents' => 0, 'quantity' => 2, 'per_order_limit' => 2]);
        $this->actingAs($user);
        $service = app(TicketService::class);
        $order = $service->createOrder($event, [$type->id => 2], ['name' => $user->name, 'email' => $user->email]);
        $service->fulfill($order);
        $ticket = $order->tickets()->firstOrFail();
        $token = hash_hmac('sha256', $ticket->uuid, config('app.key'));
        $this->assertSame('CHECKED_IN', $service->checkIn($token, $user->id)['result']);
        $this->assertSame('ALREADY_USED', $service->checkIn($token, $user->id)['result']);
    }

    public function test_admin_requires_privileged_role(): void
    {
        $customer = User::create(['name' => 'Customer', 'email' => 'customer@example.com', 'password' => Hash::make('Password123')]);
        $customer->roles()->attach(Role::where('name', 'customer')->value('id'));
        $this->actingAs($customer)->get('/admin')->assertForbidden();
        $this->actingAs(User::where('email', 'admin@musiciansworld.com')->firstOrFail())->get('/admin')->assertOk();
    }

    public function test_admin_permissions_are_enforced_and_super_admin_keeps_full_access(): void
    {
        $operator = User::create(['name' => 'Operator', 'email' => 'operator@example.com', 'password' => Hash::make('Password123')]);
        $operator->roles()->attach(Role::where('name', 'operations')->value('id'));

        $this->actingAs($operator)->get('/admin/bookings')->assertOk();
        $this->actingAs($operator)->get('/admin/reports')->assertForbidden();

        $admin = User::where('email', 'admin@musiciansworld.com')->firstOrFail();
        Role::where('name', 'super_admin')->firstOrFail()->permissionRecords()->detach();
        $this->actingAs($admin)->get('/admin/roles')->assertOk();
    }

    public function test_super_admin_can_synchronize_role_permissions(): void
    {
        $admin = User::where('email', 'admin@musiciansworld.com')->firstOrFail();
        $role = Role::where('name', 'operations')->firstOrFail();
        $permissionIds = Permission::whereIn('name', ['dashboard.view', 'reports.view'])->pluck('id')->all();

        $this->actingAs($admin)->patch(route('admin.roles.permissions', $role), [
            'label' => 'Operations Team',
            'permissions' => $permissionIds,
            'reason' => 'Align access with the reporting workflow.',
        ])->assertRedirect();

        $this->assertSame('Operations Team', $role->fresh()->label);
        $this->assertEqualsCanonicalizing(
            ['dashboard.view', 'reports.view'],
            $role->permissionRecords()->pluck('name')->all(),
        );
    }

    public function test_soundtunnel_multistep_application_is_saved(): void
    {
        $response = $this->postJson(route('forms.soundtunnel'), [
            'name' => 'Alex Promoter',
            'email' => 'alex@example.com',
            'phone' => '602-555-0199',
            'organization' => 'Night Shift Collective',
            'eventName' => 'Phoenix Night Shift',
            'eventType' => 'DJ Event / Club Night',
            'date' => now()->addMonth()->toDateString(),
            'alternateDate' => now()->addMonth()->addDay()->toDateString(),
            'startTime' => '20:00',
            'duration' => '4 hours',
            'headcount' => 180,
            'audience' => '21+',
            'ticketed' => 'Yes',
            'ticketPrice' => '$20 advance',
            'services' => ['Sound engineer', 'Lighting upgrade'],
            'alcohol' => 'Yes',
            'budget' => '$2,500–$5,000',
            'productionNotes' => 'Two DJs and one live vocalist.',
            'message' => 'We are planning an immersive electronic music showcase for local artists.',
            'referral' => 'Instagram / social media',
            'terms' => '1',
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $submission = FormSubmission::where('email', 'alex@example.com')->firstOrFail();
        $this->assertSame('soundtunnel_rental', $submission->type);
        $this->assertSame(['Sound engineer', 'Lighting upgrade'], $submission->data['services']);
        $this->assertSame(180, $submission->data['headcount']);
        $this->assertSame(User::where('email', 'alex@example.com')->value('id'), $submission->user_id);
    }

    public function test_dj_school_inquiry_stores_conditional_answers_and_notifies(): void
    {
        $minor = $this->postJson(route('forms.dj-school'), [
            'name' => 'Casey Minor',
            'email' => 'casey.minor@example.com',
            'phone' => '602-555-0144',
            'city' => 'Phoenix',
            'state' => 'Arizona',
            'zip' => '85004',
            'age' => 'Under 18',
            'parentName' => 'Pat Minor',
            'parentEmail' => 'pat.minor@example.com',
            'parentPhone' => '602-555-0145',
            'program' => 'DJ Fundamentals - Beginner',
            'experience' => "Brand New - I've never used DJ equipment",
            'hasEquipment' => 'Yes',
            'equipment' => 'DDJ-400',
            'software' => 'Serato',
            'skills' => ['Beatmatching & tempo control', 'Mixing & transitions'],
            'bestTimes' => ['Evening', 'Weekends'],
            'bestDays' => ['Saturdays'],
            'preparingEvent' => 'Yes',
            'eventDetails' => 'School talent show in November.',
            'goals' => 'Learn to mix a 20 minute set for a school event.',
            'referral' => 'Instagram',
            'acknowledgment' => '1',
        ]);
        $minor->assertOk()->assertJsonPath('ok', true);

        $submission = FormSubmission::where('email', 'casey.minor@example.com')->firstOrFail();
        $this->assertSame('dj_school_inquiry', $submission->type);
        $this->assertSame('Pat Minor', $submission->data['parentName']);
        $this->assertSame(['Beatmatching & tempo control', 'Mixing & transitions'], $submission->data['skills']);
        $this->assertDatabaseHas('program_registrations', ['email' => 'casey.minor@example.com', 'status' => 'received']);
        $this->assertSame(User::where('email', 'casey.minor@example.com')->value('id'), $submission->user_id);

        $this->postJson(route('forms.dj-school'), [
            'name' => 'Adult Student',
            'email' => 'adult.student@example.com',
            'phone' => '602-555-0166',
            'city' => 'Tempe',
            'state' => 'Arizona',
            'zip' => '85281',
            'age' => '25-34',
            'program' => 'Not Sure',
            'experience' => 'Developing - I understand the basics',
            'hasEquipment' => 'No',
            'skills' => ['Crowd reading'],
            'bestTimes' => ['Flexible / Open Availability'],
            'bestDays' => ['Monday'],
            'preparingEvent' => 'No',
            'goals' => 'Get comfortable playing a full hour for friends.',
            'referral' => 'Our Website',
            'acknowledgment' => '1',
        ])->assertOk();

        $adult = FormSubmission::where('email', 'adult.student@example.com')->firstOrFail();
        $this->assertArrayNotHasKey('parentName', array_filter($adult->data ?? []));
        $this->assertSame('No', $adult->data['hasEquipment']);
        $this->assertDatabaseMissing('program_registrations', ['email' => 'adult.student@example.com']);

        $this->postJson(route('forms.dj-school'), [
            'name' => 'Missing Parent',
            'email' => 'missing.parent@example.com',
            'phone' => '602-555-0170',
            'city' => 'Mesa',
            'state' => 'Arizona',
            'zip' => '85201',
            'age' => 'Under 18',
            'program' => 'Not Sure',
            'experience' => "Beginner - I've practiced a little",
            'hasEquipment' => 'No',
            'skills' => ['Phrasing'],
            'bestTimes' => ['Afternoon'],
            'bestDays' => ['Sunday'],
            'preparingEvent' => 'No',
            'goals' => 'Learn the basics with a parent nearby.',
            'referral' => 'Family/Friends',
            'acknowledgment' => '1',
        ])->assertUnprocessable();
    }
}
