<?php

namespace App\Services;

use App\Interfaces\CertificateStorage;
use App\Models\Certificate;
use App\Models\EventAttendee;
use Illuminate\Http\Response;

class MetadataCertificateStorage implements CertificateStorage
{
    public function __construct(
        private readonly PdfService $pdfService,
    ) {
    }

    public function store(Certificate $certificate, string $decodedPdf): void
    {
        // No-op: data already in event_attendees.metadata.file_data
    }

    public function delete(Certificate $certificate): void
    {
        // No-op: no files on disk to clean up
    }

    public function pdf(Certificate $certificate): Response
    {
        $binary = $this->resolvePdfBinary($certificate);

        if ($binary === null) {
            // Template mode: render on-the-fly via PdfService
            return $this->pdfService->streamCertificatePdf($certificate);
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $certificate->certificate_number . '.pdf"',
        ]);
    }

    public function download(Certificate $certificate): Response
    {
        $binary = $this->resolvePdfBinary($certificate);

        if ($binary === null) {
            // Template mode: render on-the-fly via PdfService
            return $this->pdfService->downloadCertificatePdf($certificate);
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $certificate->certificate_number . '.pdf"',
        ]);
    }

    public function emailAttachment(Certificate $certificate): ?string
    {
        return $this->resolvePdfBinary($certificate);
    }

    private function resolvePdfBinary(Certificate $certificate): ?string
    {
        $attendee = EventAttendee::where('certificate_id', $certificate->id)->first();

        if (!$attendee) {
            return null;
        }

        $metadata = $attendee->metadata ?? [];
        $mode = $metadata['generation_mode'] ?? 'template';

        if ($mode === 'file' && !empty($metadata['file_data'])) {
            $raw = $metadata['file_data'];
            $decoded = base64_decode($raw, true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return null;
    }
}
