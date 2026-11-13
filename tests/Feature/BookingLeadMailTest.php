<?php

namespace Tests\Feature;

use App\Mail\BookingAdminMail;
use App\Mail\BookingCalendarMail;
use App\Mail\BookingCustomerMail;
use App\Models\Booking;
use App\Services\StripeService;
use Database\Seeders\DatabaseSeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Stripe\Checkout\Session;
use Tests\TestCase;

class BookingLeadMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_confirm_and_pay_emails_the_customer_and_the_booking_inbox(): void
    {
        Mail::fake();
        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('bookingCheckout')->once()->andReturn(Session::constructFrom([
                'id' => 'cs_test_lead',
                'url' => 'https://checkout.stripe.test/lead',
            ]));
        });

        $this->postJson(route('booking.store'), [
            'resourceId' => 'studio-a',
            'from' => '2027-03-15T18:00',
            'to' => '2027-03-15T20:00',
            'name' => 'Rachel Hester',
            'email' => 'rachel.hester@example.com',
            'phone' => '925-336-5274',
            'notes' => 'Vocal booth',
        ])->assertOk()->assertJsonPath('url', 'https://checkout.stripe.test/lead');

        Mail::assertSent(BookingCustomerMail::class, function (BookingCustomerMail $mail): bool {
            $mail->assertSeeInHtml('Welcome, Rachel Hester.');
            $mail->assertSeeInHtml('Studio A');
            $mail->assertSeeInHtml('Arizona, USA');
            $mail->assertSeeInHtml('03/15/2027 18:00');
            $mail->assertSeeInHtml('03/15/2027 20:00');
            $mail->assertSeeInHtml('First-time · $30/hour');
            $mail->assertSeeInHtml('$60.00');
            $mail->assertSeeInHtml('925-336-5274');
            $mail->assertSeeInHtml('Vocal booth');
            $mail->assertSeeInHtml('First-time client rate applied.');

            return $mail->hasTo('rachel.hester@example.com');
        });
        $reference = Booking::query()->where('customer_email', 'rachel.hester@example.com')->value('reference');
        Mail::assertSent(BookingCalendarMail::class, function (BookingCalendarMail $mail) use ($reference): bool {
            $mail->assertSeeInHtml('Hi Rachel Hester,');
            $mail->assertSeeInHtml('Google Calendar');
            $mail->assertSeeInHtml('Outlook Calendar (ICS)');
            $mail->assertSeeInHtml('Yahoo Calendar');
            $mail->assertSeeInHtml('03/15/2027 18:00');
            $mail->assertSeeInHtml('03/15/2027 20:00');
            $mail->assertSeeInHtml('Vocal booth');
            $mail->assertSeeInHtml('https://calendar.google.com/calendar/render?action=TEMPLATE', false);
            $mail->assertSeeInHtml('https://calendar.yahoo.com/?v=60', false);
            $mail->assertHasAttachedData($mail->ics, $reference.'.ics', ['mime' => 'text/calendar; charset=UTF-8']);

            return $mail->hasTo('rachel.hester@example.com');
        });
        Mail::assertSent(BookingAdminMail::class, function (BookingAdminMail $mail): bool {
            $mail->assertSeeInHtml('A customer is going to payment.');
            $mail->assertSeeInHtml('Rachel Hester');
            $mail->assertSeeInHtml('Vocal booth');
            $mail->assertSeeInHtml('$60.00');

            return $mail->hasTo(config('mail.from.address'))
                && $mail->hasReplyTo('rachel.hester@example.com');
        });
        $this->assertSame('pending_payment', Booking::query()->where('customer_email', 'rachel.hester@example.com')->value('status'));
    }

    public function test_a_checkout_that_does_not_start_sends_no_booking_email(): void
    {
        Mail::fake();
        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('bookingCheckout')->once()->andThrow(new Exception('Stripe is down.'));
        });

        $this->postJson(route('booking.store'), [
            'resourceId' => 'studio-a',
            'from' => '2027-03-16T18:00',
            'to' => '2027-03-16T20:00',
            'name' => 'Rachel Hester',
            'email' => 'rachel.hester@example.com',
            'phone' => '925-336-5274',
        ])->assertStatus(502);

        Mail::assertNothingSent();
        $this->assertSame('failed', Booking::query()->where('customer_email', 'rachel.hester@example.com')->value('payment_status'));
    }
}
