<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Resource;
use App\Services\StripeService;
use Mockery;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class StripeCheckoutCurrencyTest extends TestCase
{
    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    public function test_blank_booking_currency_is_sent_to_stripe_as_usd(): void
    {
        config(['services.stripe.secret' => 'sk_test_currency']);

        $captured = null;
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->once()->andReturnUsing(function (string $method, string $url, array $headers, array $params) use (&$captured): array {
            $captured = $params;

            return [json_encode([
                'id' => 'cs_test_currency',
                'object' => 'checkout.session',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_currency',
            ], JSON_THROW_ON_ERROR), 200, []];
        });
        ApiRequestor::setHttpClient($http);

        $resource = new Resource(['timezone' => 'America/Phoenix']);
        $booking = new Booking([
            'reference' => 'MW-CURRENCY',
            'customer_email' => 'customer@musiciansworld.test',
            'total_cents' => 8000,
            'currency' => '',
            'starts_at' => now()->addDay(),
        ]);
        $booking->id = 15;
        $booking->setRelation('resource', $resource);

        $session = app(StripeService::class)->bookingCheckout($booking);

        $this->assertSame('cs_test_currency', $session->id);
        $this->assertSame('usd', $captured['line_items'][0]['price_data']['currency']);
    }
}
