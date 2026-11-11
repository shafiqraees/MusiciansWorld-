<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Order;
use App\Models\Resource;
use App\Services\CustomerCalendarInvite;
use Tests\TestCase;

class CustomerCalendarInviteTest extends TestCase
{
    public function test_a_studio_booking_can_be_added_to_google_and_microsoft_calendars(): void
    {
        $resource = new Resource(['name' => 'Studio A', 'timezone' => 'America/Phoenix']);
        $booking = new Booking([
            'reference' => 'BK-100',
            'customer_name' => 'Ava Stone',
            'customer_email' => 'ava@example.com',
            'notes' => 'Bring the vocal mic',
            'starts_at' => '2026-12-15 18:00:00',
            'ends_at' => '2026-12-15 20:00:00',
        ]);
        $booking->setRelation('resource', $resource);
        $invite = app(CustomerCalendarInvite::class);

        $ics = $invite->bookingIcs($booking);
        $links = $invite->bookingLinks($booking);

        $this->assertStringContainsString('DTSTART;TZID=America/Phoenix:20261215T180000', $ics);
        $this->assertStringContainsString('DTEND;TZID=America/Phoenix:20261215T200000', $ics);
        $this->assertStringContainsString('Bring the vocal mic', $ics);
        $this->assertStringContainsString('https://calendar.google.com/calendar/render?action=TEMPLATE', $links['google']);
        $this->assertStringContainsString('dates=20261215T180000%2F20261215T200000', $links['google']);
        $this->assertStringContainsString('ctz=America%2FPhoenix', $links['google']);
        $this->assertStringContainsString('https://outlook.office.com/calendar/0/deeplink/compose', $links['outlook']);
        $this->assertStringContainsString('startdt=2026-12-15T18%3A00%3A00-07%3A00', $links['outlook']);
        $this->assertStringContainsString('/book/BK-100/calendar.ics', $links['download']);
    }

    public function test_a_ticket_order_keeps_the_event_title_and_time(): void
    {
        $event = new Event([
            'title' => 'Night Market',
            'slug' => 'night-market',
            'description' => 'Live sets on the floor',
            'location_type' => 'soundtunnel',
            'venue_address' => '10409 N 21st Ave',
            'starts_at' => '2026-12-15 18:00:00',
            'ends_at' => '2026-12-15 22:00:00',
        ]);
        $order = new Order([
            'reference' => 'ORD-100',
            'buyer_name' => 'Ava Stone',
            'buyer_email' => 'ava@example.com',
        ]);
        $order->setRelation('event', $event);

        $ics = app(CustomerCalendarInvite::class)->orderIcs($order);

        $this->assertStringContainsString('SUMMARY:Night Market', $ics);
        $this->assertStringContainsString('DTSTART;TZID=America/Phoenix:20261215T180000', $ics);
        $this->assertStringContainsString('Live sets on the floor', $ics);
        $this->assertStringContainsString('10409 N 21st Ave', $ics);
    }
}
