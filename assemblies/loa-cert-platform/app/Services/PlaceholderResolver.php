<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Event;
use App\Models\Organization;

class PlaceholderResolver
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
    ) {
    }

    public function resolve(string $html, Certificate $certificate): string
    {
        $event = $certificate->event;
        $organization = $certificate->organization;

        $url = config('app.url') . '/certificates/' . $certificate->certificate_number;
        $qrDataUri = $this->qrCodeService->toDataUri($url);
        $qrImageTag = '<img src="' . $qrDataUri . '" style="width:100%;height:100%;object-fit:contain;" />';

        $placeholders = [
            '{{recipient_name}}' => $certificate->recipient_name,
            '{{certificate_number}}' => $certificate->certificate_number,
            '{{issued_date}}' => $certificate->issued_at?->format('F j, Y') ?? '',
            '{{event_name}}' => $event?->name ?? '',
            '{{event_date}}' => $event?->event_date?->format('F j, Y') ?? '',
            '{{event_location}}' => $event?->location ?? '',
            '{{organization_name}}' => $organization?->name ?? '',
            '{{qr_code}}' => $qrImageTag,
        ];

        return str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            $html
        );
    }
}
