<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificateEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $recipientEmail,
        public readonly string $certificateNumber,
        public readonly ?string $eventName,
        public readonly string $issuedDate,
        public readonly ?string $pdfPath,
        public readonly ?string $downloadUrl,
        public readonly ?string $verifyUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Certificate: ' . $this->certificateNumber,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.certificate',
            with: [
                'recipientName' => $this->recipientName,
                'certificateNumber' => $this->certificateNumber,
                'eventName' => $this->eventName,
                'issuedDate' => $this->issuedDate,
                'downloadUrl' => $this->downloadUrl,
                'verifyUrl' => $this->verifyUrl,
            ],
        );
    }

    public function attachments(): array
    {
        if ($this->pdfPath && file_exists(storage_path('app/' . $this->pdfPath))) {
            return [
                \Illuminate\Mail\Mailables\Attachment::fromStorageDisk('local')
                    ->path($this->pdfPath)
                    ->as('certificate-' . $this->certificateNumber . '.pdf')
                    ->withMime('application/pdf'),
            ];
        }
        return [];
    }
}