<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Resource;
use App\Models\TicketType;
use App\Models\User;
use App\Services\MicrosoftCalendarService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MicrosoftCalendarEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_submitting_an_event_adds_it_to_the_microsoft_calendar(): void
    {
        config([
            'services.microsoft.tenant_id' => 'tenant-test',
            'services.microsoft.client_id' => 'client-test',
            'services.microsoft.client_secret' => 'secret-test',
            'services.microsoft.events_calendar' => 'events@example.com',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response(['access_token' => 'test-token']),
            'https://graph.microsoft.com/*' => Http::response(['id' => 'AAMk-event'], 201),
        ]);
        $admin = User::where('email', 'admin@musiciansworld.com')->firstOrFail();
        $event = Event::create([
            'reference' => 'EVT-CAL',
            'organizer_id' => $admin->id,
            'resource_id' => Resource::where('slug', 'soundtunnel')->value('id'),
            'slug' => 'night-market',
            'title' => 'Night Market',
            'category' => 'Showcase',
            'description' => 'Live sets on the floor',
            'location_type' => 'soundtunnel',
            'venue_address' => '10409 N 21st Ave, Phoenix, AZ 85021',
            'starts_at' => '2026-12-15 18:00:00',
            'ends_at' => '2026-12-15 22:00:00',
            'doors_at' => '2026-12-15 17:00:00',
            'capacity' => 80,
            'status' => 'draft',
            'platform_fee_bps' => 500,
        ]);
        TicketType::create(['event_id' => $event->id, 'name' => 'General', 'price_cents' => 0, 'quantity' => 80]);

        $this->actingAs($admin)
            ->from('/account/events')
            ->post(route('organizer.events.submit', $event))
            ->assertRedirect('/account/events')
            ->assertSessionHas('success', 'Event submitted to MWCN for review. It is on the Microsoft calendar.');

        $event->refresh();
        $this->assertSame('submitted', $event->status);
        $this->assertSame('synced', $event->calendar_sync_status);
        $this->assertSame('AAMk-event', $event->calendar_event_id);
        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->method() === 'POST'
                && str_contains($request->url(), 'https://graph.microsoft.com/v1.0/users/events%40example.com/calendar/events')
                && ($body['subject'] ?? null) === 'Night Market [EVT-CAL]'
                && ($body['start']['dateTime'] ?? null) === '2026-12-15T18:00:00'
                && ($body['end']['dateTime'] ?? null) === '2026-12-15T22:00:00'
                && ($body['start']['timeZone'] ?? null) === 'America/Phoenix'
                && ($body['location']['displayName'] ?? null) === 'SoundTunnel'
                && str_contains($body['body']['content'] ?? '', 'Live sets on the floor')
                && str_contains($body['body']['content'] ?? '', 'Doors: 12/15/2026 17:00')
                && str_contains($body['body']['content'] ?? '', 'Capacity: 80')
                && str_contains($body['body']['content'] ?? '', '10409 N 21st Ave, Phoenix, AZ 85021');
        });
    }

    public function test_an_event_stays_submitted_when_microsoft_calendar_is_not_configured(): void
    {
        config([
            'services.microsoft.tenant_id' => null,
            'services.microsoft.client_id' => null,
            'services.microsoft.client_secret' => null,
        ]);
        Http::preventStrayRequests();
        $admin = User::where('email', 'admin@musiciansworld.com')->firstOrFail();
        $event = Event::create([
            'reference' => 'EVT-OFF',
            'organizer_id' => $admin->id,
            'slug' => 'unmapped-night',
            'title' => 'Unmapped Night',
            'description' => 'No mailbox yet',
            'location_type' => 'external',
            'venue_name' => 'Other Room',
            'starts_at' => '2026-12-20 19:00:00',
            'ends_at' => '2026-12-20 22:00:00',
            'capacity' => 40,
            'status' => 'submitted',
            'platform_fee_bps' => 500,
        ]);

        app(MicrosoftCalendarService::class)->syncEvent($event);

        $this->assertSame('failed', $event->fresh()->calendar_sync_status);
        $this->assertNull($event->fresh()->calendar_event_id);
        Http::assertNothingSent();
    }
}
