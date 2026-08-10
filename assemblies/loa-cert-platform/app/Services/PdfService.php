<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;
use Illuminate\Support\Facades\Storage;

class PdfService
{
    public function __construct(
        private readonly PlaceholderResolver $placeholderResolver,
    ) {
    }

    public function generateCertificatePdf(Certificate $certificate): string
    {
        $template = $certificate->template;

        if (!$template) {
            throw new \RuntimeException('Certificate has no template.');
        }

        $html = $this->buildHtml($template, $certificate);
        $pdf = DomPDF::loadHtml($html);

        $pdf->setPaper('letter', 'landscape');
        $pdf->setOption('defaultFont', 'sans-serif');

        $filePath = 'certificates/' . $certificate->certificate_number . '.pdf';
        Storage::disk('local')->put($filePath, $pdf->output());

        $certificate->update(['file_path' => $filePath]);

        return $filePath;
    }

    public function streamCertificatePdf(Certificate $certificate): \Symfony\Component\HttpFoundation\Response
    {
        $template = $certificate->template;

        if (!$template) {
            throw new \RuntimeException('Certificate has no template.');
        }

        $html = $this->buildHtml($template, $certificate);
        $pdf = DomPDF::loadHtml($html);

        $pdf->setPaper('letter', 'landscape');
        $pdf->setOption('defaultFont', 'sans-serif');

        return $pdf->inline($certificate->certificate_number . '.pdf');
    }

    public function downloadCertificatePdf(Certificate $certificate): \Symfony\Component\HttpFoundation\Response
    {
        $template = $certificate->template;

        if (!$template) {
            throw new \RuntimeException('Certificate has no template.');
        }

        $html = $this->buildHtml($template, $certificate);
        $pdf = DomPDF::loadHtml($html);

        $pdf->setPaper('letter', 'landscape');
        $pdf->setOption('defaultFont', 'sans-serif');

        return $pdf->download($certificate->certificate_number . '.pdf');
    }

    private function buildHtml(CertificateTemplate $template, Certificate $certificate): string
    {
        $htmlContent = $this->placeholderResolver->resolve($template->html_content, $certificate);
        $cssContent = $template->css_content ?? '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        {$cssContent}
    </style>
</head>
<body>
    {$htmlContent}
</body>
</html>
HTML;
    }
}
