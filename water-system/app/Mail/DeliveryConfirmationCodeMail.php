<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeliveryConfirmationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $deliveryReference,
        public string $recipientName,
        public string $code,
        public string $expiresAt,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Taala Crystal delivery confirmation code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.delivery-confirmation-code',
        );
    }
}
