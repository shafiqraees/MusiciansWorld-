<?php

namespace App\Services;

use App\Mail\BookingDetails;
use App\Models\Booking;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CustomerCalendarInvite
{
    /**
     * @return array{google: string, outlook: string, yahoo: string, download: string}
     */
    public function bookingLinks(Booking $booking): array
    {
        return $this->links($this->bookingEntry($booking), route('booking.calendar', $booking->reference));
    }

    /**
     * @return array{google: string, outlook: string, yahoo: string, download: string}
     */
    public function orderLinks(Order $order): array
    {
        return $this->links($this->orderEntry($order), route('tickets.calendar', $order->reference));
    }

    public function bookingIcs(Booking $booking): string
    {
        return $this->ics($this->bookingEntry($booking), 'booking-'.$booking->reference.'@musicianswcn.org');
    }

    public function orderIcs(Order $order): string
    {
        return $this->ics($this->orderEntry($order), 'order-'.$order->reference.'@musicianswcn.org');
    }

    public function emailBooking(Booking $booking): void
    {
        if (! filled($booking->customer_email)) {
            return;
        }

        $links = $this->bookingLinks($booking);
        $this->send(
            $booking->customer_email,
            (string) $booking->customer_name,
            'Add your MusiciansWorld booking to your calendar',
            'Your studio time is booked.',
            $links,
            $this->bookingIcs($booking),
            $booking->reference.'.ics',
            BookingDetails::rows($booking),
        );
    }

    public function emailOrder(Order $order): void
    {
        $order->loadMissing('event');
        if (! $order->event || ! filled($order->buyer_email)) {
            return;
        }

        $links = $this->orderLinks($order);
        $this->send(
            $order->buyer_email,
            (string) $order->buyer_name,
            'Add '.$order->event->title.' to your calendar',
            'Your tickets are ready.',
            $links,
            $this->orderIcs($order),
            $order->reference.'.ics',
        );
    }

    /**
     * @param  array{google: string, outlook: string, yahoo: string, download: string}  $links
     * @param  list<array{label: string, value: string}>  $rows
     */
    private function send(string $email, string $name, string $subject, string $headline, array $links, string $ics, string $filename, array $rows = []): void
    {
        try {
            Mail::send('emails.calendar-invite', compact('headline', 'links', 'name', 'rows'), function ($message) use ($email, $name, $subject, $ics, $filename) {
                $message->to($email, $name)
                    ->subject($subject)
                    ->attachData($ics, $filename, ['mime' => 'text/calendar; charset=UTF-8; method=PUBLISH']);
            });
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array{title: string, details: string, location: string, start: CarbonInterface, end: CarbonInterface, timezone: string}  $entry
     * @return array{google: string, outlook: string, yahoo: string, download: string}
     */
    private function links(array $entry, string $download): array
    {
        $start = $entry['start']->format('Ymd\THis');
        $end = $entry['end']->format('Ymd\THis');
        $google = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            .'&text='.rawurlencode($entry['title'])
            .'&dates='.rawurlencode($start.'/'.$end)
            .'&ctz='.rawurlencode($entry['timezone'])
            .'&details='.rawurlencode($entry['details'])
            .'&location='.rawurlencode($entry['location']);
        $outlook = 'https://outlook.office.com/calendar/0/deeplink/compose?path=/calendar/action/compose&rru=addevent'
            .'&subject='.rawurlencode($entry['title'])
            .'&startdt='.rawurlencode($entry['start']->format('Y-m-d\TH:i:s').'-07:00')
            .'&enddt='.rawurlencode($entry['end']->format('Y-m-d\TH:i:s').'-07:00')
            .'&body='.rawurlencode($entry['details'])
            .'&location='.rawurlencode($entry['location']);
        $yahoo = 'https://calendar.yahoo.com/?v=60&view=d&type=20'
            .'&title='.rawurlencode($entry['title'])
            .'&st='.rawurlencode($start)
            .'&et='.rawurlencode($end)
            .'&desc='.rawurlencode($entry['details'])
            .'&in_loc='.rawurlencode($entry['location']);

        return ['google' => $google, 'outlook' => $outlook, 'yahoo' => $yahoo, 'download' => $download];
    }

    /**
     * @param  array{title: string, details: string, location: string, start: CarbonInterface, end: CarbonInterface, timezone: string}  $entry
     */
    private function ics(array $entry, string $uid): string
    {
        $timezone = $entry['timezone'];
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//MusiciansWorld Creative Network//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART;TZID='.$timezone.':'.$entry['start']->format('Ymd\THis'),
            'DTEND;TZID='.$timezone.':'.$entry['end']->format('Ymd\THis'),
            'SUMMARY:'.$this->escape($entry['title']),
            'DESCRIPTION:'.$this->escape($entry['details']),
            'LOCATION:'.$this->escape($entry['location']),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * @return array{title: string, details: string, location: string, start: CarbonInterface, end: CarbonInterface, timezone: string}
     */
    private function bookingEntry(Booking $booking): array
    {
        $booking->loadMissing('resource');
        $timezone = $booking->resource?->timezone ?: 'America/Phoenix';
        $place = $booking->resource?->name ?: 'Studio A';
        $rows = BookingDetails::rows($booking);

        return [
            'title' => $place.' — '.$booking->customer_name,
            'details' => implode("\n", array_map(
                fn (array $row): string => $row['label'].': '.$row['value'],
                $rows,
            )),
            'location' => $place.', '.LOCATION,
            'start' => $booking->starts_at->copy(),
            'end' => $booking->ends_at->copy(),
            'timezone' => $timezone,
        ];
    }

    /**
     * @return array{title: string, details: string, location: string, start: CarbonInterface, end: CarbonInterface, timezone: string}
     */
    private function orderEntry(Order $order): array
    {
        $order->loadMissing('event');
        $event = $order->event;
        $location = match ($event->location_type) {
            'virtual' => 'Online',
            'external' => $event->venue_name ?: 'External venue',
            default => 'SoundTunnel',
        };
        if (filled($event->venue_address)) {
            $location .= ', '.$event->venue_address;
        }

        return [
            'title' => $event->title,
            'details' => implode("\n", array_filter([
                $event->description,
                'Order '.$order->reference,
                $order->buyer_name,
                $event->slug ? url('/events/'.$event->slug) : null,
            ], fn ($line) => filled($line))),
            'location' => $location,
            'start' => $event->starts_at->copy(),
            'end' => $event->ends_at->copy(),
            'timezone' => 'America/Phoenix',
        ];
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "\r\n", "\n", ',', ';'], ['\\\\', '\\n', '\\n', '\\,', '\\;'], $value);
    }
}
