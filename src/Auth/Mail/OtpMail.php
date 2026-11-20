<?php

namespace IkongDev\RestApi\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OTPMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public int $expiresInMinutes = 5,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your One-Time Password (OTP)',
        );
    }

    public function content(): Content
    {
        // `view`, not `markdown`: the template is a full HTML document,
        // and markdown mode would wrap it in Laravel's mail layout.
        return new Content(
            view: config('restapi.otp.template', 'restapi::otp'),
            with: [
                'otp'              => $this->otp,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}