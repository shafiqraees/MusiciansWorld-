<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\FinancialTransaction;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Services\StripeService;
use App\Support\Finance;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Stripe\Checkout\Session;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function customer(): User
    {
        $user = User::create([
            'name' => 'Avery Member',
            'email' => 'avery.member@example.com',
            'phone' => '602-555-0199',
            'password' => Hash::make('Password123'),
            'communication_preferences' => ['marketing' => true, 'legacy_flag' => true],
        ]);
        $user->roles()->attach(Role::where('name', 'customer')->value('id'));
        $user->memberships()->create([
            'membership_plan_id' => MembershipPlan::where('slug', 'community')->value('id'),
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_portal_sidebar_lists_every_module_and_each_page_renders(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertSee('My Profile')
            ->assertSee('My Membership')
            ->assertSee('Benefits')
            ->assertSee('My Bookings')
            ->assertSee('DJ School')
            ->assertSee('My Tickets')
            ->assertSee('My Events')
            ->assertSee('Payments &amp; Receipts', false)
            ->assertSee(route('account.profile'), false)
            ->assertSee(route('account.payments'), false);

        foreach (['account.profile', 'account.membership', 'account.benefits', 'account.bookings', 'account.school', 'account.tickets', 'account.events', 'account.payments'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk()->assertSee('aria-current="page"', false);
        }

        $this->actingAs($user)->get(route('account.membership'))->assertSee('Free Community');
        $this->actingAs($user)->get(route('account.benefits'))->assertSee('Promoter platform fee');
    }

    public function test_profile_update_keeps_existing_preferences(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->patch(route('account.profile.update'), [
            'name' => 'Avery Updated',
            'phone' => '602-555-0100',
            'city' => 'Phoenix',
            'state' => 'AZ',
            'postal_code' => '85004',
            'booking_updates' => '1',
            'event_updates' => '1',
        ])->assertRedirect(route('account.profile'));

        $user->refresh();
        $this->assertSame('Avery Updated', $user->name);
        $this->assertFalse($user->communication_preferences['marketing']);
        $this->assertTrue($user->communication_preferences['legacy_flag']);
        $this->assertTrue($user->communication_preferences['booking_updates']);
        $this->assertFalse($user->communication_preferences['school_updates']);
    }

    public function test_paid_membership_can_be_scheduled_to_end_without_deleting_history(): void
    {
        $user = $this->customer();
        $founder = MembershipPlan::where('slug', 'founder')->firstOrFail();
        $paid = $user->memberships()->create([
            'membership_plan_id' => $founder->id,
            'status' => 'active',
            'current_period_start' => now()->subDays(3),
            'current_period_end' => now()->addDays(27),
        ]);

        $this->actingAs($user)->post(route('account.membership.cancel'), ['confirm' => '1'])
            ->assertRedirect(route('account.membership'));

        $paid->refresh();
        $this->assertSame('active', $paid->status);
        $this->assertNotNull($paid->cancelled_at);
        $this->assertTrue($user->fresh()->memberships()->whereKey($paid->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership.cancellation_scheduled', 'auditable_id' => $paid->id]);

        $this->actingAs($user)->post(route('account.membership.resume'))
            ->assertRedirect(route('account.membership'));
        $this->assertNull($paid->fresh()->cancelled_at);
    }

    public function test_free_community_cannot_be_cancelled(): void
    {
        $user = $this->customer();
        $membership = $user->memberships()->first();

        $this->actingAs($user)->post(route('account.membership.cancel'), ['confirm' => '1'])
            ->assertRedirect()
            ->assertSessionHasErrors('membership');

        $this->assertNull($membership->fresh()->cancelled_at);
        $this->assertSame('active', $membership->fresh()->status);
    }

    public function test_booking_change_request_does_not_move_the_reservation(): void
    {
        $user = $this->customer();
        $studio = Resource::where('slug', 'studio-a')->firstOrFail();
        $starts = now()->addDays(4)->setTime(14, 0);
        $booking = Booking::create([
            'reference' => 'BKG-TEST-1',
            'user_id' => $user->id,
            'resource_id' => $studio->id,
            'service_type' => 'studio_booking',
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHours(2),
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'subtotal_cents' => 8000,
            'total_cents' => 8000,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'notes' => 'Original session note',
        ]);

        $this->actingAs($user)->post(route('account.bookings.change', $booking), [
            'message' => 'Please move this to Friday if the room is open.',
        ])->assertRedirect(route('account.bookings'));

        $booking->refresh();
        $this->assertTrue($booking->starts_at->equalTo($starts));
        $this->assertSame('confirmed', $booking->status);
        $this->assertStringContainsString('Original session note', $booking->notes);
        $this->assertStringContainsString('Please move this to Friday', $booking->notes);
    }

    public function test_pending_booking_page_offers_payment_and_keeps_the_stored_time(): void
    {
        $user = $this->customer();
        $studio = Resource::where('slug', 'studio-a')->firstOrFail();
        $starts = now()->addDays(4)->setTime(20, 0);
        $booking = Booking::create([
            'reference' => 'BKG-PAY-1',
            'user_id' => $user->id,
            'resource_id' => $studio->id,
            'service_type' => 'studio_booking',
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHours(2),
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'hold_expires_at' => now()->subMinutes(5),
            'subtotal_cents' => 8000,
            'total_cents' => 8000,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('bookingCheckout')->once()->andReturn(Session::constructFrom([
                'id' => 'cs_portal_pay',
                'url' => 'https://checkout.stripe.test/portal',
            ]));
        });

        $this->actingAs($user)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Pay $80.00')
            ->assertSee('BKG-PAY-1');

        $this->actingAs($user)->post(route('account.bookings.pay', $booking))
            ->assertRedirect('https://checkout.stripe.test/portal');

        $booking->refresh();
        $this->assertSame('cs_portal_pay', $booking->stripe_checkout_session_id);
        $this->assertSame('pending_payment', $booking->status);
        $this->assertSame('pending', $booking->payment_status);
        $this->assertSame($starts->format('Y-m-d H:i'), $booking->starts_at->format('Y-m-d H:i'));
        $this->assertTrue($booking->hold_expires_at->isFuture());
    }

    public function test_another_customer_cannot_pay_a_pending_booking(): void
    {
        $owner = $this->customer();
        $other = User::create([
            'name' => 'Other Member',
            'email' => 'other.member@example.com',
            'password' => Hash::make('Password123'),
        ]);
        $studio = Resource::where('slug', 'studio-a')->firstOrFail();
        $starts = now()->addDays(6)->setTime(16, 0);
        $booking = Booking::create([
            'reference' => 'BKG-PAY-2',
            'user_id' => $owner->id,
            'resource_id' => $studio->id,
            'service_type' => 'studio_booking',
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHour(),
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'hold_expires_at' => now()->addMinutes(20),
            'subtotal_cents' => 4000,
            'total_cents' => 4000,
            'customer_name' => $owner->name,
            'customer_email' => $owner->email,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('bookingCheckout')->never();
        });

        $this->actingAs($other)->post(route('account.bookings.pay', $booking))->assertForbidden();
        $this->assertNull($booking->fresh()->stripe_checkout_session_id);
    }

    public function test_paid_booking_cannot_be_paid_again(): void
    {
        $user = $this->customer();
        $studio = Resource::where('slug', 'studio-a')->firstOrFail();
        $starts = now()->addDays(7)->setTime(18, 0);
        $booking = Booking::create([
            'reference' => 'BKG-PAY-3',
            'user_id' => $user->id,
            'resource_id' => $studio->id,
            'service_type' => 'studio_booking',
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHour(),
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'subtotal_cents' => 4000,
            'total_cents' => 4000,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('bookingCheckout')->never();
        });

        $this->actingAs($user)->post(route('account.bookings.pay', $booking))->assertUnprocessable();
    }

    public function test_pending_payment_is_refused_when_the_slot_was_taken(): void
    {
        $user = $this->customer();
        $studio = Resource::where('slug', 'studio-a')->firstOrFail();
        $starts = now()->addDays(8)->setTime(19, 0);
        Booking::create([
            'reference' => 'BKG-TAKEN',
            'user_id' => $user->id,
            'resource_id' => $studio->id,
            'service_type' => 'studio_booking',
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHour(),
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'subtotal_cents' => 4000,
            'total_cents' => 4000,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
        ]);
        $pending = Booking::create([
            'reference' => 'BKG-PAY-4',
            'user_id' => $user->id,
            'resource_id' => $studio->id,
            'service_type' => 'studio_booking',
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHour(),
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'hold_expires_at' => now()->subMinute(),
            'subtotal_cents' => 4000,
            'total_cents' => 4000,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('bookingCheckout')->never();
        });

        $this->actingAs($user)->from(route('account.bookings'))
            ->post(route('account.bookings.pay', $pending))
            ->assertRedirect(route('account.bookings'))
            ->assertSessionHasErrors(['booking' => 'That time is no longer available. Choose another open slot.']);

        $this->assertNull($pending->fresh()->stripe_checkout_session_id);
    }

    public function test_customer_receipts_hide_organizer_ledger_rows(): void
    {
        $user = $this->customer();
        $visible = FinancialTransaction::create([
            'user_id' => $user->id,
            'type' => 'studio_booking',
            'status' => 'succeeded',
            'amount_cents' => 8000,
            'idempotency_key' => 'test-receipt-visible',
        ]);
        $hidden = FinancialTransaction::create([
            'user_id' => $user->id,
            'type' => 'mwcn_platform_fee',
            'status' => 'succeeded',
            'amount_cents' => 400,
            'idempotency_key' => 'test-receipt-hidden',
        ]);
        $other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => Hash::make('Password123')]);
        $foreign = FinancialTransaction::create([
            'user_id' => $other->id,
            'type' => 'studio_booking',
            'status' => 'succeeded',
            'amount_cents' => 1000,
            'idempotency_key' => 'test-receipt-foreign',
        ]);

        $this->actingAs($user)->get(route('account.payments'))->assertOk()->assertSee('Studio A booking')->assertDontSee('MWCN platform fee');
        $this->actingAs($user)->get(route('account.receipts.show', $visible))->assertOk()->assertSee('Receipt')->assertSee('$80.00');
        $this->actingAs($user)->get(route('account.receipts.show', $hidden))->assertNotFound();
        $this->actingAs($user)->get(route('account.receipts.show', $foreign))->assertForbidden();
    }

    public function test_ticket_purchase_money_flow_is_visible_to_customer_promoter_and_admin(): void
    {
        $buyer = User::where('email', 'customer@musiciansworld.test')->firstOrFail();
        $organizer = User::where('email', 'organizer@musiciansworld.test')->firstOrFail();
        $order = Order::where('reference', 'DEMO-ORD-3001')->with('event')->firstOrFail();
        $event = $order->event;
        $feeBps = (int) $event->platform_fee_bps;
        $fee = (int) round($order->subtotal_cents * $feeBps / 10000);
        $this->assertGreaterThan(0, $fee);

        // Customer: order breakdown shows what they paid and where it goes.
        $receipt = $order->transactions()->where('type', 'gross_ticket_revenue')->firstOrFail();
        $this->actingAs($buyer)->get(route('account.tickets'))
            ->assertOk()
            ->assertSee($order->reference)
            ->assertSee('MWCN platform fee')
            ->assertSee(a_money($fee));
        $this->actingAs($buyer)->get(route('account.receipts.show', $receipt))
            ->assertOk()
            ->assertSee('Where your payment goes')
            ->assertSee(a_money($fee));

        // Promoter: per-event statement with gross, fee, proceeds, payout status.
        $this->actingAs($organizer)->get(route('account.events'))
            ->assertOk()
            ->assertSee($event->title)
            ->assertSee('MWCN fee ('.Finance::percent($feeBps).')', false)
            ->assertSee('Your proceeds');
        $this->actingAs($organizer)->get(route('account.events.show', $event))
            ->assertOk()
            ->assertSee('Settlement statement')
            ->assertSee('Transaction ledger')
            ->assertSee($order->reference)
            ->assertSee(a_money($fee));

        // Another customer cannot open the promoter's event finances.
        $this->actingAs($buyer)->get(route('account.events.show', $event))->assertForbidden();

        // Admin: platform ledger and per-event money flow.
        $admin = User::where('email', 'admin@musiciansworld.com')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.transactions', ['from' => now()->subYears(2)->toDateString()]))
            ->assertOk()
            ->assertSee('Transactions &amp; money flow', false)
            ->assertSee('Per-event money flow')
            ->assertSee($event->title)
            ->assertSee('Kept by MWCN');
        $this->actingAs($admin)->get(route('admin.transactions', ['from' => now()->subYears(2)->toDateString(), 'event' => $event->id, 'type' => 'mwcn_platform_fee']))
            ->assertOk()
            ->assertSee(a_money($fee));
        $this->actingAs($admin)->get(route('admin.transactions', ['format' => 'csv', 'from' => now()->subYears(2)->toDateString()]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // A customer account cannot reach the admin ledger.
        $this->actingAs($buyer)->get(route('admin.transactions'))->assertForbidden();
    }
}
