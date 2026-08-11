<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Public", description: "Public certificate verification and viewing (no auth)")]
#[OA\Schema(schema: "PublicOrganization", properties: [
    new OA\Property(property: "name", type: "string"),
])]
#[OA\Schema(schema: "VerifyResult", properties: [
    new OA\Property(property: "valid", type: "boolean"),
    new OA\Property(property: "certificate_number", type: "string"),
    new OA\Property(property: "issued_date", type: "string", format: "date"),
    new OA\Property(property: "valid_until", type: "string", format: "date", nullable: true),
    new OA\Property(property: "status", type: "string", enum: ["active", "revoked", "expired"]),
    new OA\Property(property: "recipient_name", type: "string"),
    new OA\Property(property: "event_name", type: "string", nullable: true),
    new OA\Property(property: "organization", ref: "#/components/schemas/PublicOrganization"),
])]
class PublicCertificateController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    #[OA\Get(
        path: "/api/v1/verify/{certificate_number}",
        summary: "Verify a certificate by number (public)",
        tags: ["Public"],
        parameters: [
            new OA\Parameter(name: "certificate_number", in: "path", required: true, schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Verification result", content: new OA\JsonContent(ref: "#/components/schemas/VerifyResult")),
            new OA\Response(response: 404, description: "Certificate number not found"),
        ]
    )]
    public function verify(Request $request, string $certificateNumber): JsonResponse
    {
        $organizationId = $this->resolveOrganizationId();

        $certificate = Certificate::with(['event'])
            ->where('organization_id', $organizationId)
            ->where('certificate_number', $certificateNumber)
            ->first();

        if (!$certificate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate not found.',
            ], 404);
        }

        $this->auditLogger->record(
            'certificate.viewed',
            'public',
            'certificate',
            $certificate->id,
            ['certificate_number' => $certificate->certificate_number, 'channel' => 'verify'],
        );

        $valid = $certificate->status === 'active';

        return response()->json([
            'data' => [
                'valid' => $valid,
                'certificate_number' => $certificate->certificate_number,
                'issued_date' => $certificate->issued_at?->toDateString(),
                'valid_until' => $certificate->expires_at?->toDateString(),
                'status' => $certificate->status,
                'recipient_name' => $certificate->recipient_name,
                'event_name' => $certificate->event?->name,
                'organization' => [
                    'name' => $this->resolveOrganizationName(),
                ],
            ],
        ])->header('Cache-Control', 'public, s-maxage=300, stale-while-revalidate=600');
    }

    #[OA\Get(
        path: "/api/v1/view/{id}",
        summary: "Public certificate viewer data (no auth)",
        tags: ["Public"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Viewer data"),
            new OA\Response(response: 404, description: "Not found"),
            new OA\Response(response: 410, description: "Revoked"),
        ]
    )]
    public function view(Request $request, string $id): JsonResponse
    {
        $organizationId = $this->resolveOrganizationId();

        $certificate = Certificate::with(['event', 'template'])
            ->where('organization_id', $organizationId)
            ->find($id);

        if (!$certificate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate not found.',
            ], 404);
        }

        if ($certificate->status === 'revoked') {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate is revoked.',
            ], 410);
        }

        $this->auditLogger->record(
            'certificate.viewed',
            'public',
            'certificate',
            $certificate->id,
            ['certificate_number' => $certificate->certificate_number, 'channel' => 'view'],
        );

        return response()->json([
            'data' => [
                'certificate' => [
                    'id' => $certificate->id,
                    'certificate_number' => $certificate->certificate_number,
                    'status' => $certificate->status,
                    'recipient_name' => $certificate->recipient_name,
                    'issued_at' => $certificate->issued_at?->toIso8601String(),
                    'expires_at' => $certificate->expires_at?->toIso8601String(),
                    'revoked_at' => $certificate->revoked_at?->toIso8601String(),
                ],
                'template' => $certificate->template ? [
                    'name' => $certificate->template->name,
                    'html_content' => $certificate->template->html_content,
                    'css_content' => $certificate->template->css_content,
                ] : null,
                'event' => $certificate->event ? [
                    'id' => $certificate->event->id,
                    'name' => $certificate->event->name,
                    'event_date' => $certificate->event->event_date?->toDateString(),
                    'location' => $certificate->event->location,
                ] : null,
                'qr_data_url' => 'data:image/png;base64,QR_CODE_NOT_IMPLEMENTED',
                'organization' => [
                    'name' => $this->resolveOrganizationName(),
                ],
            ],
        ]);
    }

    private function resolveOrganizationId(): string
    {
        return config('cert-platform.organization_id', '00000000-0000-0000-0000-000000000001');
    }

    private function resolveOrganizationName(): string
    {
        $organization = Organization::find($this->resolveOrganizationId());

        return $organization?->name ?? ucfirst((string) config('cert-platform.tenant_slug', 'LOA'));
    }
}
