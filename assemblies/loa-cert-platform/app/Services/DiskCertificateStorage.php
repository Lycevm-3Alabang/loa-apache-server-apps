<?php

namespace App\Services;

use App\Interfaces\CertificateStorage;
use App\Models\Certificate;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class DiskCertificateStorage implements CertificateStorage
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
                $filePath = 'certificates/' . $certificate->certificate_number . '.pdf';
                Storage::disk('local')->put($filePath, $decoded);
                $certificate->update(['file_path' => $filePath]);
                return;
            }
        }

        try {
            $this->pdfService->generateCertificatePdf($certificate->fresh(['event', 'template', 'organization']));
        } catch (\Exception) {
            // PDF generation failure is non-fatal
        }
    }

    public function delete(Certificate $certificate): void
    {
        if ($certificate->file_path && Storage::disk('local')->exists($certificate->file_path)) {
            Storage::disk('local')->delete($certificate->file_path);
        }
    }

    public function pdf(Certificate $certificate): Response
    {
        return $this->pdfService->streamCertificatePdf($certificate);
    }

    public function download(Certificate $certificate): Response
    {
        return $this->pdfService->downloadCertificatePdf($certificate);
    }

    public function emailAttachment(Certificate $certificate): ?string
    {
        if ($certificate->file_path && Storage::disk('local')->exists($certificate->file_path)) {
            return Storage::disk('local')->get($certificate->file_path);
        }

        return null;
    }
}
