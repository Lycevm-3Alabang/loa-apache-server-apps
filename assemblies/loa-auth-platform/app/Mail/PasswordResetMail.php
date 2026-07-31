<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $email,
        public readonly string $token,
        public readonly bool $change = false,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->change
                ? 'Change your LOA Platform password'
                : 'Reset your LOA Platform password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: $this->change ? 'emails.change-password' : 'emails.reset-password',
            with: [
                'resetUrl' => $this->resetUrl(),
            ],
        );
    }

    private function resetUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $this->email,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
