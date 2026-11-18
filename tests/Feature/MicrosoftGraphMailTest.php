<?php

namespace Tests\Feature;

use App\Mail\BookingAdminMail;
use App\Mail\BookingCalendarMail;
use App\Mail\BookingCustomerMail;
use App\Models\Booking;
use App\Models\Resource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class MicrosoftGraphMailTest extends TestCase
{
    private function booking(): Booking
    {
        $booking = new Booking([
            'reference' => 'BKG-GRAPH-1',
            'customer_name' => 'Rachel Hester',
            'customer_email' => 'rachel@example.com',
            'customer_phone' => '480-565-6708',
            'notes' => 'Session notes',
            'subtotal_cents' => 8000,
            'total_cents' => 8000,
            'discount_cents' => 0,
            'meta' => ['from' => '11/18/2026 18:00', 'to' => '11/18/2026 20:00', 'rate' => 'standard'],
        ]);
        $booking->starts_at = now()->addDay();
        $booking->ends_at = now()->addDay()->addHours(2);
        $booking->setRelation('resource', new Resource([
            'name' => 'Studio A',
            'timezone' => 'America/Phoenix',
        ]));

        return $booking;
    }

    private function configureGraph(?string $secret = 'test-secret'): void
    {
        config([
            'mail.default' => 'microsoft-graph',
            'mail.from.address' => 'booking@wavemakerconcepts.com',
            'mail.from.name' => 'MusiciansWorld',
            'services.microsoft.mail.tenant_id' => '6e40ab5c-eccf-4454-9721-f9fabe93121c',
            'services.microsoft.mail.client_id' => '1b281aec-73e5-49b4-ae92-8b7bd05f7fab',
            'services.microsoft.mail.client_secret' => $secret,
            'services.microsoft.mail.mailbox' => 'booking@wavemakerconcepts.com',
        ]);
    }

    public function test_booking_confirmation_is_sent_through_graph_send_mail(): void
    {
        $this->configureGraph();
        Http::preventStrayRequests();
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'graph-token-1',
                'expires_in' => 3600,
            ]),
            'https://graph.microsoft.com/*' => Http::response('', 202),
        ]);

        $booking = $this->booking();
        $ics = "BEGIN:VCALENDAR\r\nSUMMARY:Studio A\r\nEND:VCALENDAR";

        Mail::mailer('microsoft-graph')
            ->to('rachel@example.com', 'Rachel Hester')
            ->send(new BookingCustomerMail($booking));
        Mail::mailer('microsoft-graph')
            ->to('booking@wavemakerconcepts.com')
            ->send(new BookingAdminMail($booking));
        Mail::mailer('microsoft-graph')
            ->to('rachel@example.com', 'Rachel Hester')
            ->send(new BookingCalendarMail($booking, [
                'google' => 'https://calendar.google.com/calendar/render',
                'outlook' => 'https://outlook.live.com/calendar/0/deeplink/compose',
                'yahoo' => 'https://calendar.yahoo.com/',
                'download' => 'https://example.test/calendar/BKG-GRAPH-1',
            ], $ics));

        Http::assertSentCount(4);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://login.microsoftonline.com/6e40ab5c-eccf-4454-9721-f9fabe93121c/oauth2/v2.0/token'
                && $request['grant_type'] === 'client_credentials'
                && $request['scope'] === 'https://graph.microsoft.com/.default'
                && $request['client_id'] === '1b281aec-73e5-49b4-ae92-8b7bd05f7fab'
                && $request['client_secret'] === 'test-secret';
        });
        Http::assertSent(function (Request $request): bool {
            $message = $request->data()['message'] ?? [];

            return $request->url() === 'https://graph.microsoft.com/v1.0/users/booking%40wavemakerconcepts.com/sendMail'
                && $request->hasHeader('Authorization', 'Bearer graph-token-1')
                && ($request->data()['saveToSentItems'] ?? false) === true
                && ($message['subject'] ?? '') === 'Welcome to MusiciansWorld — your Studio A booking'
                && ($message['from']['emailAddress']['address'] ?? '') === 'booking@wavemakerconcepts.com'
                && ($message['toRecipients'][0]['emailAddress']['address'] ?? '') === 'rachel@example.com'
                && str_contains((string) ($message['body']['content'] ?? ''), 'BKG-GRAPH-1')
                && ($message['body']['contentType'] ?? '') === 'HTML';
        });
        Http::assertSent(function (Request $request): bool {
            $message = $request->data()['message'] ?? [];

            return ($message['subject'] ?? '') === 'New Studio A lead — Rachel Hester'
                && ($message['toRecipients'][0]['emailAddress']['address'] ?? '') === 'booking@wavemakerconcepts.com'
                && ($message['replyTo'][0]['emailAddress']['address'] ?? '') === 'rachel@example.com';
        });
        $calendar = collect(Http::recorded())->map(fn (array $pair) => $pair[0])->first(function (Request $request): bool {
            $names = array_column($request->data()['message']['attachments'] ?? [], 'name');

            return in_array('BKG-GRAPH-1.ics', $names, true);
        });
        $this->assertInstanceOf(Request::class, $calendar);
        $attachments = $calendar->data()['message']['attachments'];
        $icsAttachment = collect($attachments)->firstWhere('name', 'BKG-GRAPH-1.ics');
        $logo = collect($attachments)->firstWhere('name', 'logo.png');
        $this->assertSame('#microsoft.graph.fileAttachment', $icsAttachment['@odata.type']);
        $this->assertSame($ics, base64_decode((string) $icsAttachment['contentBytes'], true));
        $this->assertTrue($logo['isInline']);
        $this->assertStringContainsString('cid:'.$logo['contentId'], (string) $calendar->data()['message']['body']['content']);
    }

    public function test_graph_mail_is_not_sent_without_a_client_secret(): void
    {
        $this->configureGraph(null);
        Http::preventStrayRequests();
        Http::fake();

        try {
            Mail::mailer('microsoft-graph')
                ->to('rachel@example.com')
                ->send(new BookingCustomerMail($this->booking()));
            $this->fail('Graph mail was sent without a client secret.');
        } catch (TransportException $exception) {
            $this->assertStringContainsString('client secret', $exception->getMessage());
        }

        Http::assertNothingSent();
    }
}
