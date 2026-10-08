<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\StripeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Stripe\Checkout\Session;
use Tests\TestCase;

class CustomerFormAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
    }

    public function test_guest_forms_create_one_customer_and_link_every_submission_by_email(): void
    {
        $this->postJson(route('forms.contact'), [
            'name' => 'Connected Guest',
            'email' => '  CONNECTED.GUEST@example.com ',
            'phone' => '602-555-0101',
            'topic' => 'DJ School',
            'message' => 'I would like to learn more about the beginner program.',
        ])->assertOk();

        $this->postJson(route('forms.dj-lesson'), [
            'studentName' => 'Connected Guest',
            'email' => 'connected.guest@example.com',
            'phone' => '602-555-0101',
            'experienceLevel' => 'beginner',
            'lessonInterest' => 'Private lesson',
        ])->assertOk();

        $user = User::query()->where('email', 'connected.guest@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('customer'));
        $this->assertSame('community', $user->activeMembership()?->plan->slug);
        $this->assertSame(1, User::query()->where('email', 'connected.guest@example.com')->count());
        $this->assertSame(2, FormSubmission::query()->where('user_id', $user->id)->count());
    }

    public function test_authenticated_forms_prefill_and_update_profile_without_changing_login_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Stored Customer',
            'email' => 'stored@example.com',
            'phone' => '602-555-0100',
        ]);

        $this->actingAs($user)
            ->get('/contact')
            ->assertOk()
            ->assertSee('stored@example.com')
            ->assertSee('Stored Customer');

        $this->actingAs($user)->postJson(route('forms.contact'), [
            'name' => 'Updated Customer',
            'email' => 'different@example.com',
            'phone' => '602-555-0199',
            'topic' => 'General',
            'message' => 'Please save my updated contact details.',
        ])->assertOk();

        $user->refresh();
        $submission = FormSubmission::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('Updated Customer', $user->name);
        $this->assertSame('602-555-0199', $user->phone);
        $this->assertSame('stored@example.com', $user->email);
        $this->assertSame('stored@example.com', $submission->email);
    }

    public function test_guest_booking_creates_and_owns_customer_account(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('bookingCheckout')->once()->andReturn(Session::constructFrom([
                'id' => 'cs_test_booking',
                'url' => 'https://checkout.stripe.test/session',
            ]));
        });

        $this->postJson(route('booking.store'), [
            'resourceId' => 'studio-a',
            'date' => now()->addMonth()->toDateString(),
            'start' => 600,
            'durationHours' => 2,
            'name' => 'Booking Guest',
            'email' => 'booking.guest@example.com',
            'phone' => '602-555-0133',
        ])->assertOk()->assertJsonPath('url', 'https://checkout.stripe.test/session');

        $user = User::query()->where('email', 'booking.guest@example.com')->firstOrFail();
        $booking = Booking::query()->where('customer_email', $user->email)->firstOrFail();

        $this->assertSame($user->id, $booking->user_id);
        $this->assertTrue($user->hasRole('customer'));
    }

    public function test_legacy_php_availability_endpoint_is_no_longer_used(): void
    {
        $this->get('/api/availability.php')->assertNotFound();
        $this->get('/book')
            ->assertOk()
            ->assertSee(route('booking.availability'), false)
            ->assertSee(route('booking.store'), false);
    }
}
