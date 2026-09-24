<?php

namespace App\Services;

use App\Interfaces\CertificateStorage;
use App\Models\Certificate;
use App\Models\EventAttendee;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class MetadataCertificateStorage implements CertificateStorage
{
    public function __construct(
        private readonly PdfService $pdfService,
    ) {
    }

    public function store(Certificate $certificate, array $metadata = []): void
    {
        $mode = $metadata['generation_mode'] ?? 'template';

        if ($mode === 'file' && !empty($metadata['file_data'])) {
            $raw = $metadata['file_data'];
            if (str_starts_with($raw, 'data:')) {
                $raw = substr($raw, strpos($raw, ',') + 1);
            }
            $decoded = base64_decode($raw, true);
            if ($decoded !== false) {
                return;
            }
        }

        try {
            $this->pdfService->generateCertificatePdf($certificate->fresh(['event', 'template', 'organization']));
        } catch (\Exception) {
            // PDF generation failure is non-fatal; certificate record is still valid
        }
    }

    public function delete(Certificate $certificate): void
    {
        // No-op: no files on disk to clean up
    }

    public function pdf(Certificate $certificate): Response
    {
        $binary = $this->resolveServeBinary($certificate);

        if ($binary === null) {
            return $this->pdfService->streamCertificatePdf($certificate);
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $certificate->certificate_number . '.pdf"',
        ]);
    }

    public function download(Certificate $certificate): Response
    {
        $binary = $this->resolveServeBinary($certificate);

        if ($binary === null) {
            return $this->pdfService->downloadCertificatePdf($certificate);
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $certificate->certificate_number . '.pdf"',
        ]);
    }

    public function emailAttachment(Certificate $certificate): ?string
    {
        return $this->resolveServeBinary($certificate);
    }

    private function resolveServeBinary(Certificate $certificate): ?string
    {
        $metadataBinary = $this->resolvePdfBinary($certificate);
        if ($metadataBinary !== null) {
            return $metadataBinary;
        }

        $attendee = EventAttendee::where('certificate_id', $certificate->id)->first();
        $metadata = $attendee?->metadata ?? [];
        $mode = $metadata['generation_mode'] ?? 'template';

        if ($mode === 'file') {
            return $this->readFromDisk($certificate);
        }

        return null;
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
            if (str_starts_with($raw, 'data:')) {
                $raw = substr($raw, strpos($raw, ',') + 1);
            }
            $decoded = base64_decode($raw, true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return null;
    }

    private function readFromDisk(Certificate $certificate): ?string
    {
        if ($certificate->file_path && Storage::disk('local')->exists($certificate->file_path)) {
            return Storage::disk('local')->get($certificate->file_path);
        }

        return null;
    }
}
