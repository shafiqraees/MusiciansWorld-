<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to MusiciansWorld — your Studio A booking',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.brand-message',
            with: [
                'kicker' => 'Studio A',
                'headline' => 'Welcome, '.$this->booking->customer_name.'.',
                'paragraphs' => [
                    'Thank you for choosing MusiciansWorld Creative Network. Your Studio A time is held, and checkout is open so you can complete payment.',
                    'These are the details we have for your visit.',
                ],
                'rows' => BookingDetails::rows($this->booking),
                'closing' => 'Finish the payment page to confirm this time. We sent this as soon as you chose Confirm and pay.',
            ],
        );
    }
}
