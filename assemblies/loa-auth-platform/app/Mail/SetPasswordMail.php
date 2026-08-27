<?php

namespace App\Mail;

use App\Models\PasswordSetToken;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $rawToken,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Set your LOA Platform password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.set-password',
            with: [
                'user' => $this->user,
                'token' => $this->rawToken,
            ],
        );
    }
}
