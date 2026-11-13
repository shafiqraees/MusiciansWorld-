<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCalendarMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{google: string, outlook: string, yahoo: string, download: string}  $links
     */
    public function __construct(public Booking $booking, public array $links, public string $ics) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Add your Studio A booking to your calendar',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-calendar',
            with: [
                'rows' => BookingDetails::rows($this->booking),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->ics, $this->booking->reference.'.ics')
                ->withMime('text/calendar; charset=UTF-8'),
        ];
    }
}
