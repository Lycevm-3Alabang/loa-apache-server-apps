<?php

namespace App\Interfaces;

use App\Models\Certificate;
use Illuminate\Http\Response;

interface CertificateStorage
{
    /**
     * Store a certificate PDF to persistent storage.
     */
    public function store(Certificate $certificate, string $decodedPdf): void;

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
