<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\FormSubmission;
use App\Models\Resource;
use App\Models\User;
use App\Services\BookingService;
use App\Services\StripeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Stripe\Checkout\Session;
use Tests\TestCase;

class ClientPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('bookingCheckout')->andReturnUsing(function () {
                return Session::constructFrom([
                    'id' => 'cs_'.uniqid(),
                    'url' => 'https://checkout.stripe.test/price',
                ]);
            });
        });
    }

    public function test_first_booking_uses_thirty_dollars_and_later_bookings_use_forty_even_with_a_new_email(): void
    {
        $this->book('first@example.com', '480-565-6708', 12)->assertOk();
        $first = Booking::query()->where('customer_email', 'first@example.com')->firstOrFail();
        $this->assertSame(6000, $first->total_cents);
        $this->assertSame('first_time', $first->meta['rate']);

        $this->book('first@example.com', '480-565-6708', 13)->assertOk();
        $second = Booking::query()->where('customer_email', 'first@example.com')->latest('id')->firstOrFail();
        $this->assertSame(8000, $second->total_cents);
        $this->assertSame('standard', $second->meta['rate']);

        $this->book('different@example.com', '1 (480) 565-6708', 14)->assertOk();
        $samePhone = Booking::query()->where('customer_email', 'different@example.com')->firstOrFail();
        $this->assertSame(8000, $samePhone->total_cents);
        $this->assertSame('4805656708', $samePhone->customer_phone_digits);
    }

    public function test_a_coupon_discounts_a_returning_client_and_cannot_stack_on_the_first_time_rate(): void
    {
        Coupon::query()->create($this->coupon('SAVE20', 20));

        $this->book('new@example.com', '602-555-0101', 12, 'SAVE20')->assertOk();
        $first = Booking::query()->where('customer_email', 'new@example.com')->firstOrFail();
        $this->assertSame(6000, $first->total_cents);
        $this->assertSame(0, $first->discount_cents);
        $this->assertSame('This code cannot be combined with the first-time client rate.', $first->meta['pricing_message']);
        $this->assertSame(0, CouponRedemption::query()->count());

        $this->book('new@example.com', '602-555-0101', 13, 'SAVE20')->assertOk();
        $returning = Booking::query()->where('customer_email', 'new@example.com')->latest('id')->firstOrFail();
        $this->assertSame(8000, $returning->subtotal_cents);
        $this->assertSame(1600, $returning->discount_cents);
        $this->assertSame(6400, $returning->total_cents);
        $this->assertSame('SAVE20', $returning->meta['coupon_code']);
        $this->assertSame('reserved', CouponRedemption::query()->where('booking_id', $returning->id)->value('status'));

        app(BookingService::class)->confirm($returning->fresh());
        $this->assertSame('used', CouponRedemption::query()->where('booking_id', $returning->id)->value('status'));
    }

    public function test_invalid_family_and_inactive_codes_are_rejected_without_a_booking(): void
    {
        Coupon::query()->create($this->coupon('FAMILY15', 15, [
            'kind' => 'family',
            'assigned_email' => 'approved@example.com',
        ]));
        Coupon::query()->create($this->coupon('OFF20', 20, ['is_active' => false]));

        $this->book('stranger@example.com', '602-555-0199', 12, 'FAMILY15')
            ->assertStatus(422)
            ->assertJsonPath('error', 'This code is not valid.');
        $this->book('anyone@example.com', '602-555-0188', 13, 'OFF20')
            ->assertStatus(422)
            ->assertJsonPath('error', 'This code is turned off.');
        $this->book('anyone@example.com', '602-555-0188', 14, 'MISSING')
            ->assertStatus(422)
            ->assertJsonPath('error', 'This code is not valid.');

        $this->assertSame(0, Booking::query()->whereIn('customer_email', ['stranger@example.com', 'anyone@example.com'])->count());
        $this->assertSame(0, CouponRedemption::query()->count());
    }

    public function test_admin_can_create_a_family_code_and_the_usage_report_shows_an_inquiry(): void
    {
        $admin = User::query()->where('email', 'admin@musiciansworld.com')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.coupons.store'), [
            'name' => 'Approved family',
            'code' => 'family30',
            'kind' => 'family',
            'percent' => 30,
            'max_uses' => 4,
            'assigned_email' => 'approved@example.com',
            'services' => ['soundtunnel', 'dj_school'],
            'is_active' => '1',
            'once_per_customer' => '1',
            'reason' => 'Approved family discount.',
        ])->assertRedirect();

        $this->actingAs($admin)->get(route('admin.coupons'))
            ->assertOk()
            ->assertSee('FAMILY30')
            ->assertSee('approved@example.com')
            ->assertSee('Usage report');

        auth()->logout();

        $this->postJson(route('forms.soundtunnel'), [
            'name' => 'Approved Family',
            'email' => 'approved@example.com',
            'phone' => '480-565-6708',
            'eventName' => 'Listening night',
            'eventType' => 'Private',
            'date' => now()->addDays(20)->toDateString(),
            'startTime' => '18:00',
            'duration' => '3 hours',
            'headcount' => 40,
            'audience' => 'Friends',
            'ticketed' => 'No',
            'alcohol' => 'No',
            'message' => 'We need the room for a private listening session.',
            'terms' => '1',
            'coupon' => 'FAMILY30',
        ])->assertOk()->assertJsonPath('note', 'Code applied. Application received. Check your email for the next registration step.');

        $this->assertSame(1, FormSubmission::query()->where('email', 'approved@example.com')->where('type', 'soundtunnel_rental')->count());
        $this->assertSame('used', CouponRedemption::query()->where('email', 'approved@example.com')->value('status'));

        $this->actingAs($admin)->get(route('admin.coupons'))
            ->assertOk()
            ->assertSee('approved@example.com')
            ->assertSee('SoundTunnel');
    }

    public function test_customer_portal_shows_the_studio_rate_and_an_applied_code(): void
    {
        $user = User::factory()->create([
            'email' => 'portal@example.com',
            'phone' => '480-565-6708',
        ]);

        $this->actingAs($user)->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee('$30.00 an hour')
            ->assertSee('on your first booking');

        $resource = Resource::query()->where('slug', 'studio-a')->firstOrFail();
        Booking::query()->create([
            'reference' => 'MW-PORTAL',
            'user_id' => $user->id,
            'resource_id' => $resource->id,
            'service_type' => 'studio_booking',
            'starts_at' => now()->addDays(4)->setTime(18, 0),
            'ends_at' => now()->addDays(4)->setTime(20, 0),
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'subtotal_cents' => 8000,
            'discount_cents' => 1600,
            'total_cents' => 6400,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'customer_phone_digits' => '4805656708',
            'meta' => ['coupon_code' => 'SAVE20', 'rate' => 'standard'],
        ]);

        $this->actingAs($user)->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee('$40.00 an hour')
            ->assertDontSee('on your first booking');

        $this->actingAs($user)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('SAVE20')
            ->assertSee('$16.00 off')
            ->assertDontSee('member discount');
    }

    public function test_an_assigned_one_time_code_works_only_for_that_customer_and_only_once(): void
    {
        $owner = User::factory()->create([
            'email' => 'owner@example.com',
            'phone' => '480-555-0140',
        ]);
        $other = User::factory()->create([
            'email' => 'other@example.com',
            'phone' => '480-555-0141',
        ]);
        Coupon::query()->create($this->coupon('OWNER20', 20, [
            'assigned_email' => 'owner@example.com',
            'assigned_user_id' => $owner->id,
            'max_uses' => 1,
            'once_per_customer' => true,
        ]));

        $this->book('owner@example.com', '480-555-0140', 12)->assertOk();
        $this->book('owner@example.com', '480-555-0140', 13, 'OWNER20')->assertOk();

        $used = Booking::query()->where('customer_email', 'owner@example.com')->latest('id')->firstOrFail();
        $this->assertSame(6400, $used->total_cents);
        $this->assertSame(1, CouponRedemption::query()->count());

        $this->book('owner@example.com', '480-555-0199', 14, 'OWNER20')
            ->assertStatus(422)
            ->assertJsonPath('error', 'You have already used this code.');
        $this->book('other@example.com', '480-555-0140', 15, 'OWNER20')
            ->assertStatus(422)
            ->assertJsonPath('error', 'This code is not valid.');
        $this->actingAs($other)->postJson(route('booking.store'), [
            'resourceId' => 'studio-a',
            'from' => now('America/Phoenix')->addDays(16)->setTime(18, 0)->format('Y-m-d\TH:i'),
            'to' => now('America/Phoenix')->addDays(16)->setTime(20, 0)->format('Y-m-d\TH:i'),
            'name' => 'Other Customer',
            'email' => 'owner@example.com',
            'phone' => '480-555-0140',
            'coupon' => 'OWNER20',
        ])->assertStatus(422)->assertJsonPath('error', 'This code is not valid.');

        $this->assertSame(1, CouponRedemption::query()->count());

        app(BookingService::class)->confirm($used->fresh());
        $admin = User::query()->where('email', 'admin@musiciansworld.com')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('OWNER20')
            ->assertSee('One time')
            ->assertSee('owner@example.com');
        $this->actingAs($owner)->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee('OWNER20')
            ->assertSee('This code cannot be used again')
            ->assertSee('Used once');
        $this->actingAs($other)->get(route('account.dashboard'))
            ->assertOk()
            ->assertDontSee('OWNER20');
    }

    public function test_one_use_per_customer_blocks_a_second_use_by_the_same_customer(): void
    {
        Coupon::query()->create($this->coupon('REPEAT15', 15, [
            'max_uses' => 5,
            'once_per_customer' => true,
        ]));

        $this->book('repeat@example.com', '602-555-0177', 20)->assertOk();
        $this->book('repeat@example.com', '602-555-0177', 21, 'REPEAT15')->assertOk();
        $this->book('repeat@example.com', '602-555-0188', 22, 'REPEAT15')
            ->assertStatus(422)
            ->assertJsonPath('error', 'You have already used this code.');
        $this->book('someone@example.com', '602-555-0166', 23)->assertOk();
        $this->book('someone@example.com', '602-555-0166', 24, 'REPEAT15')->assertOk();

        $this->assertSame(2, CouponRedemption::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function coupon(string $code, int $percent, array $overrides = []): array
    {
        return array_merge([
            'code' => $code,
            'name' => $code,
            'kind' => 'coupon',
            'percent' => $percent,
            'max_uses' => 10,
            'once_per_customer' => false,
            'is_active' => true,
            'services' => ['studio_a', 'soundtunnel', 'dj_school'],
        ], $overrides);
    }

    private function book(string $email, string $phone, int $dayOffset, ?string $coupon = null): TestResponse
    {
        $from = now('America/Phoenix')->addDays($dayOffset)->setTime(18, 0);

        return $this->postJson(route('booking.store'), array_filter([
            'resourceId' => 'studio-a',
            'from' => $from->format('Y-m-d\TH:i'),
            'to' => $from->copy()->addHours(2)->format('Y-m-d\TH:i'),
            'name' => 'Pricing Guest',
            'email' => $email,
            'phone' => $phone,
            'coupon' => $coupon,
        ], fn ($value) => $value !== null));
    }
}
