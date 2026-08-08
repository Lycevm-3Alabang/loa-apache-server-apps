<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Event;
use App\Models\Organization;

class PlaceholderResolver
{
    public function resolve(string $html, Certificate $certificate): string
    {
        $event = $certificate->event;
        $organization = $certificate->organization;

        $placeholders = [
            '{{recipient_name}}' => $certificate->recipient_name,
            '{{certificate_number}}' => $certificate->certificate_number,
            '{{issued_date}}' => $certificate->issued_at?->format('F j, Y') ?? '',
            '{{event_name}}' => $event?->name ?? '',
            '{{event_date}}' => $event?->event_date?->format('F j, Y') ?? '',
            '{{event_location}}' => $event?->location ?? '',
            '{{organization_name}}' => $organization?->name ?? '',
            '{{qr_code}}' => '',
        ];

        return str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            $html
        );
    }
}
