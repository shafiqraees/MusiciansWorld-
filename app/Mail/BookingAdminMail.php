<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Studio A lead — '.$this->booking->customer_name,
            replyTo: [
                new Address($this->booking->customer_email, $this->booking->customer_name),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.brand-message',
            with: [
                'kicker' => 'Booking lead',
                'headline' => 'A customer is going to payment.',
                'paragraphs' => [
                    $this->booking->customer_name.' confirmed these Studio A details and was sent to checkout.',
                    'Reply to this email to reach the customer directly.',
                ],
                'rows' => BookingDetails::rows($this->booking),
                'closing' => 'This lead was created when the customer chose Confirm and pay. The time stays held until payment finishes or the hold expires.',
            ],
        );
    }
}
