<?php

namespace App\Interfaces;

use App\Models\Certificate;
use Illuminate\Http\Response;

interface CertificateStorage
{
    /**
     * Generate and store a certificate PDF.
     * Decides generation mode from attendee metadata:
     *   - 'file': decode base64 file_data from metadata
     *   - 'template': render HTML via PdfService
     */
    public function store(Certificate $certificate, array $metadata = []): void;

    /**
     * Delete a certificate PDF from persistent storage.
     */
    public function delete(Certificate $certificate): void;

    /**
     * Stream a certificate PDF as an HTTP response (inline).
     */
    public function pdf(Certificate $certificate): Response;

    /**
     * Return a certificate PDF as a download response.
     */
    public function download(Certificate $certificate): Response;

    /**
     * Return the raw PDF binary for email attachment.
     * Returns null if PDF cannot be produced.
     */
    public function emailAttachment(Certificate $certificate): ?string;
}
