<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Resource;
use App\Services\StripeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Stripe\Checkout\Session;
use Tests\TestCase;

class BookingSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_a_checked_sms_box_texts_the_booking_details_from_nextiva(): void
    {
        Mail::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.nextiva.com/provider/token-with-authorities' => Http::response([
                'token' => 'jwt-token',
                'location' => 'https://api.nextiva.com',
            ]),
            'https://api.nextiva.com/users/api/sms' => Http::response(['status' => 'sent']),
        ]);
        config([
            'services.nextiva.username' => 'studio-agent',
            'services.nextiva.password' => 'secret',
            'services.nextiva.from' => '480-565-6708',
            'services.nextiva.base_url' => 'https://api.nextiva.com',
        ]);
        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('bookingCheckout')->once()->andReturn(Session::constructFrom([
                'id' => 'cs_test_sms',
                'url' => 'https://checkout.stripe.test/sms',
            ]));
        });

        $this->postJson(route('booking.store'), [
            'resourceId' => 'studio-a',
            'from' => '2027-03-15T18:00',
            'to' => '2027-03-15T20:00',
            'name' => 'Rachel Hester',
            'email' => 'rachel.sms@example.com',
            'phone' => '925-336-5274',
            'notes' => 'Vocal booth',
            'smsConsent' => true,
        ])->assertOk();

        $booking = Booking::query()->where('customer_email', 'rachel.sms@example.com')->firstOrFail();
        $this->assertTrue($booking->sms_consent);
        $this->assertNotNull($booking->sms_consent_at);
        $this->assertNotNull($booking->sms_confirmation_sent_at);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.nextiva.com/users/api/sms'
                && $request['to'] === '+19253365274'
                && $request['from'] === '+14805656708'
                && str_contains($request['message'], '03/15/2027 18:00')
                && str_contains($request['message'], 'STOP');
        });
    }

    public function test_an_unchecked_sms_box_does_not_text_and_a_missing_number_is_rejected(): void
    {
        Mail::fake();
        Http::preventStrayRequests();
        Http::fake();
        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('bookingCheckout')->once()->andReturn(Session::constructFrom([
                'id' => 'cs_test_quiet',
                'url' => 'https://checkout.stripe.test/quiet',
            ]));
        });

        $this->postJson(route('booking.store'), [
            'resourceId' => 'studio-a',
            'from' => '2027-03-16T18:00',
            'to' => '2027-03-16T20:00',
            'name' => 'Rachel Hester',
            'email' => 'rachel.quiet@example.com',
            'phone' => '925-336-5274',
            'smsConsent' => false,
        ])->assertOk();

        $this->assertFalse((bool) Booking::query()->where('customer_email', 'rachel.quiet@example.com')->value('sms_consent'));
        Http::assertNothingSent();

        $this->postJson(route('booking.store'), [
            'resourceId' => 'studio-a',
            'from' => '2027-03-17T18:00',
            'to' => '2027-03-17T20:00',
            'name' => 'Rachel Hester',
            'email' => 'rachel.nophone@example.com',
            'smsConsent' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_a_paid_booking_gets_one_reminder_about_twenty_four_hours_ahead(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.nextiva.com/provider/token-with-authorities' => Http::response([
                'token' => 'jwt-token',
                'location' => 'https://api.nextiva.com',
            ]),
            'https://api.nextiva.com/users/api/sms' => Http::response(['status' => 'sent']),
        ]);
        config([
            'services.nextiva.username' => 'studio-agent',
            'services.nextiva.password' => 'secret',
            'services.nextiva.from' => '+14805656708',
            'services.nextiva.base_url' => 'https://api.nextiva.com',
        ]);
        $this->travelTo('2027-03-15 12:00:00');
        $resource = Resource::query()->where('slug', 'studio-a')->firstOrFail();
        $due = $this->booking($resource, 'MW-DUE', now()->addHours(24), true);
        $this->booking($resource, 'MW-LATER', now()->addHours(48), true);
        $this->booking($resource, 'MW-QUIET', now()->addHours(24), false);

        $this->artisan('mwcn:sms-reminders')->assertSuccessful();
        $this->artisan('mwcn:sms-reminders')->assertSuccessful();

        $this->assertNotNull($due->fresh()->sms_reminder_sent_at);
        $this->assertNull(Booking::query()->where('reference', 'MW-LATER')->value('sms_reminder_sent_at'));
        $this->assertNull(Booking::query()->where('reference', 'MW-QUIET')->value('sms_reminder_sent_at'));
        Http::assertSentCount(2);
    }

    private function booking(Resource $resource, string $reference, mixed $start, bool $consent): Booking
    {
        return Booking::query()->create([
            'reference' => $reference,
            'resource_id' => $resource->id,
            'service_type' => 'studio_booking',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'subtotal_cents' => 6000,
            'total_cents' => 6000,
            'currency' => 'usd',
            'customer_name' => 'Rachel Hester',
            'customer_email' => strtolower($reference).'@example.com',
            'customer_phone' => '925-336-5274',
            'sms_consent' => $consent,
            'meta' => ['from' => '03/16/2027 12:00', 'to' => '03/16/2027 14:00'],
        ]);
    }
}
