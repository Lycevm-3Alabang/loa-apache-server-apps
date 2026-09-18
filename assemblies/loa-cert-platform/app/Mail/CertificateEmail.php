<?php

namespace App\Mail;

use App\Services\QrCodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificateEmail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $qrDataUri;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $recipientEmail,
        public readonly string $certificateNumber,
        public readonly ?string $eventName,
        public readonly string $issuedDate,
        public readonly ?string $pdfPath,
        public readonly ?string $downloadUrl,
        public readonly ?string $verifyUrl,
        public readonly bool $isRegistered = true,
        public readonly ?string $activateUrl = null,
        public readonly ?string $fileData = null,
    ) {
        $qrService = app(QrCodeService::class);
        $this->qrDataUri = $qrService->toDataUri($this->verifyUrl);
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
                'qrDataUri' => $this->qrDataUri,
                'isRegistered' => $this->isRegistered,
                'activateUrl' => $this->activateUrl,
            ],
        );
    }

    public function attachments(): array
    {
        if ($this->fileData) {
            return [
                \Illuminate\Mail\Mailables\Attachment::fromData(
                    fn () => $this->fileData,
                    'certificate-' . $this->certificateNumber . '.pdf'
                )->withMime('application/pdf'),
            ];
        }
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