<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CustomerMembership;
use App\Models\FinancialTransaction;
use App\Models\MembershipPlan;
use App\Models\Resource;
use App\Models\User;
use App\Models\WebhookEvent;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        config(['services.stripe.webhook_secret' => $this->secret]);
    }

    public function test_missing_signature_returns_400(): void
    {
        $existing = WebhookEvent::count();

        $this->call('POST', '/api/stripe-webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"id":"evt_bad"}')
            ->assertBadRequest()
            ->assertJson(['error' => 'Invalid webhook signature.']);

        $this->assertSame($existing, WebhookEvent::count());
    }

    public function test_unconfigured_signing_secret_returns_503(): void
    {
        config(['services.stripe.webhook_secret' => '']);

        $this->call('POST', '/api/stripe-webhook', [], [], [], $this->signatureHeaders('{}'), '{}')
            ->assertStatus(503);
    }

    public function test_paid_studio_checkout_confirms_booking_once(): void
    {
        $booking = $this->pendingBooking();
        $payload = $this->event('evt_booking_1', 'checkout.session.completed', [
            'id' => 'cs_booking_1',
            'object' => 'checkout.session',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_booking_1',
            'metadata' => ['purpose' => 'studio_booking', 'booking_id' => (string) $booking->id],
        ]);

        $this->postSigned($payload)->assertOk()->assertJson(['received' => true]);
        $this->postSigned($payload)->assertOk()->assertJson(['received' => true, 'duplicate' => true]);

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('pi_booking_1', $booking->stripe_payment_intent_id);
        $this->assertSame(1, FinancialTransaction::where('idempotency_key', 'booking-payment-'.$booking->id)->count());
        $this->assertSame('processed', WebhookEvent::where('external_id', 'evt_booking_1')->value('status'));
    }

    public function test_paid_membership_checkout_activates_founder_membership(): void
    {
        $user = User::where('email', 'customer@musiciansworld.test')->firstOrFail();
        $plan = MembershipPlan::where('slug', 'founder')->firstOrFail();
        $payload = $this->event('evt_member_1', 'checkout.session.completed', [
            'id' => 'cs_member_1',
            'object' => 'checkout.session',
            'payment_status' => 'paid',
            'customer' => 'cus_member_1',
            'subscription' => 'sub_member_1',
            'metadata' => ['purpose' => 'membership', 'plan_id' => (string) $plan->id, 'user_id' => (string) $user->id],
        ]);

        $this->postSigned($payload)->assertOk();

        $this->assertDatabaseHas('customer_memberships', [
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'stripe_subscription_id' => 'sub_member_1',
            'status' => 'active',
        ]);
        $this->assertSame(1, CustomerMembership::where('stripe_subscription_id', 'sub_member_1')->count());
    }

    private function pendingBooking(): Booking
    {
        return Booking::create([
            'reference' => 'MW-WEBHOOK',
            'user_id' => User::where('email', 'customer@musiciansworld.test')->value('id'),
            'resource_id' => Resource::where('slug', 'studio-a')->value('id'),
            'service_type' => 'studio_booking',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'subtotal_cents' => 8000,
            'total_cents' => 8000,
            'customer_name' => 'Jordan Customer',
            'customer_email' => 'customer@musiciansworld.test',
        ]);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function event(string $id, string $type, array $object): string
    {
        return json_encode([
            'id' => $id,
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $object],
        ], JSON_THROW_ON_ERROR);
    }

    private function postSigned(string $payload)
    {
        return $this->call('POST', '/api/stripe-webhook', [], [], [], $this->signatureHeaders($payload), $payload);
    }

    /**
     * @return array<string, string>
     */
    private function signatureHeaders(string $payload): array
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $this->secret);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ];
    }
}
