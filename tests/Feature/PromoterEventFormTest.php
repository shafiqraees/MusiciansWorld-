<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PromoterEventFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function promoter(): User
    {
        $user = User::create([
            'name' => 'Pat Promoter',
            'email' => 'pat.promoter@example.com',
            'password' => Hash::make('Password123'),
        ]);
        $user->roles()->attach(Role::where('name', 'customer')->value('id'));

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Test Night',
            'category' => 'dj_night',
            'description' => 'A night of testing.',
            'location_type' => 'soundtunnel',
            'visibility' => 'public',
            'starts_at' => '2030-05-10T19:00',
            'ends_at' => '2030-05-10T23:00',
            'doors_at' => '2030-05-10T18:00',
            'capacity' => 120,
            'setup_minutes' => 0,
            'teardown_minutes' => 0,
            'tickets' => [
                ['id' => '', 'name' => 'General admission', 'price' => '20', 'quantity' => '80', 'per_order_limit' => '10'],
            ],
        ], $overrides);
    }

    public function test_host_an_event_page_renders_inside_the_customer_portal(): void
    {
        $this->actingAs($this->promoter())->get(route('organizer.events.create'))
            ->assertOk()
            ->assertSee('Create your event')
            ->assertSee('type="datetime-local"', false)
            ->assertSee('Payments &amp; Receipts', false);
    }

    public function test_times_are_stored_and_shown_in_arizona_local_time(): void
    {
        $user = $this->promoter();

        $response = $this->actingAs($user)->post(route('organizer.events.store'), $this->payload());

        $event = Event::where('organizer_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('organizer.events.edit', $event));

        $this->assertSame('2030-05-10 19:00', $event->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2030-05-10 23:00', $event->ends_at->format('Y-m-d H:i'));
        $this->assertSame('2030-05-10 18:00', $event->doors_at->format('Y-m-d H:i'));
        $this->assertSame('draft', $event->status);
        $this->assertSame(1, $event->ticketTypes()->count());
        $this->assertSame(2000, $event->ticketTypes()->first()->price_cents);

        $this->actingAs($user)->get(route('organizer.events.edit', $event))
            ->assertOk()
            ->assertSee('7:00 PM MST')
            ->assertSee('value="2030-05-10T19:00"', false)
            ->assertSee('value="2030-05-10T23:00"', false);
    }

    public function test_schedule_validation_errors_are_plain_language(): void
    {
        $user = $this->promoter();

        $this->actingAs($user)->from(route('organizer.events.create'))
            ->post(route('organizer.events.store'), $this->payload(['ends_at' => '2030-05-10T18:00']))
            ->assertRedirect(route('organizer.events.create'))
            ->assertSessionHasErrors(['ends_at' => 'The end time must be after the start time.']);

        $this->actingAs($user)->from(route('organizer.events.create'))
            ->post(route('organizer.events.store'), $this->payload(['doors_at' => '2030-05-10T20:00']))
            ->assertSessionHasErrors(['doors_at' => 'Doors must open before the event starts.']);

        $this->actingAs($user)->from(route('organizer.events.create'))
            ->post(route('organizer.events.store'), $this->payload(['starts_at' => '2020-01-01T19:00', 'ends_at' => '2020-01-01T22:00', 'doors_at' => null]))
            ->assertSessionHasErrors(['starts_at' => 'The start time must be in the future.']);

        $this->actingAs($user)->from(route('organizer.events.create'))
            ->post(route('organizer.events.store'), $this->payload(['location_type' => 'external', 'venue_name' => '']))
            ->assertSessionHasErrors(['venue_name' => 'Enter the venue name for an event held at another venue.']);

        $this->assertSame(0, Event::where('organizer_id', $user->id)->count());
    }

    public function test_submit_requires_a_ticket_type_and_locks_the_draft(): void
    {
        $user = $this->promoter();

        $this->actingAs($user)->post(route('organizer.events.store'), $this->payload(['tickets' => []]));
        $event = Event::where('organizer_id', $user->id)->firstOrFail();

        $this->actingAs($user)->from(route('organizer.events.edit', $event))
            ->post(route('organizer.events.submit', $event))
            ->assertRedirect(route('organizer.events.edit', $event))
            ->assertSessionHasErrors('tickets');
        $this->assertSame('draft', $event->fresh()->status);

        $this->actingAs($user)->put(route('organizer.events.update', $event), $this->payload())
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('organizer.events.submit', $event))->assertSessionHasNoErrors();
        $this->assertSame('submitted', $event->fresh()->status);

        // Locked once submitted: edits are refused with a clear message and the form is read-only.
        $this->actingAs($user)->put(route('organizer.events.update', $event), $this->payload(['title' => 'Changed']))
            ->assertSessionHasErrors('event');
        $this->assertSame('Test Night', $event->fresh()->title);
        $this->actingAs($user)->get(route('organizer.events.edit', $event))->assertOk()->assertSee('disabled', false);

        // Another customer cannot touch it.
        $other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => Hash::make('Password123')]);
        $this->actingAs($other)->get(route('organizer.events.edit', $event))->assertForbidden();
        $this->actingAs($other)->put(route('organizer.events.update', $event), $this->payload())->assertForbidden();
    }
}
