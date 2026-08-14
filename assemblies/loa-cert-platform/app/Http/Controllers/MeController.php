<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Me", description: "Caller-scoped (own) resources")]
#[OA\Schema(schema: "MyCertificate", properties: [
    new OA\Property(property: "id", type: "string", format: "uuid"),
    new OA\Property(property: "certificate_number", type: "string"),
    new OA\Property(property: "recipient_name", type: "string"),
    new OA\Property(property: "issued_at", type: "string", format: "date-time"),
    new OA\Property(property: "expires_at", type: "string", format: "date-time", nullable: true),
    new OA\Property(property: "revoked_at", type: "string", format: "date-time", nullable: true),
    new OA\Property(property: "revoke_reason", type: "string", nullable: true),
    new OA\Property(property: "status", type: "string", enum: ["active", "revoked", "expired"]),
    new OA\Property(property: "event_id", type: "string", format: "uuid", nullable: true),
    new OA\Property(property: "event_name", type: "string", nullable: true),
    new OA\Property(property: "created_at", type: "string", format: "date-time"),
])]
#[OA\Schema(schema: "MyCertificateListResponse", properties: [
    new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/MyCertificate")),
    new OA\Property(property: "meta", type: "object", properties: [
        new OA\Property(property: "limit", type: "integer"),
        new OA\Property(property: "offset", type: "integer"),
        new OA\Property(property: "total", type: "integer"),
        new OA\Property(property: "has_more", type: "boolean"),
    ]),
])]
#[OA\Schema(schema: "MyCertificateSingleResponse", properties: [
    new OA\Property(property: "data", ref: "#/components/schemas/MyCertificate"),
])]
class MeController extends Controller
{
    #[OA\Get(
        path: "/api/v1/me/certificates",
        summary: "List the caller's own certificates",
        tags: ["Me"],
        parameters: [
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["active", "revoked", "expired"])),
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer", default: 25)),
            new OA\Parameter(name: "offset", in: "query", schema: new OA\Schema(type: "integer", default: 0)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/MyCertificateListResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Forbidden"),
        ]
    )]
    public function certificates(Request $request): JsonResponse
    {
        $claims = $request->attributes->get('jwt_claims') ?? [];
        $email = $claims['email'] ?? null;

        $query = Certificate::with(['event'])
            ->where('recipient_email', $email);

        if ($status = $request->query('status')) {
            $query->where(function ($q) use ($status) {
                match ($status) {
                    'revoked' => $q->whereNotNull('revoked_at'),
                    'expired' => $q->whereNull('revoked_at')
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<', now()),
                    'active' => $q->whereNull('revoked_at')
                        ->where(function ($q2) {
                            $q2->whereNull('expires_at')
                                ->orWhere('expires_at', '>=', now());
                        }),
                    default => null,
                };
            });
        }

        $limit = min((int) $request->query('limit', 25), 100);
        $offset = (int) $request->query('offset', 0);

        $total = $query->count();
        $certificates = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (Certificate $cert) => $this->formatCertificate($cert));

        return response()->json([
            'data' => $certificates,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'has_more' => ($offset + $limit) < $total,
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/v1/me/certificates/{id}",
        summary: "Get one of the caller's own certificates",
        tags: ["Me"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/MyCertificateSingleResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Not the owner"),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function certificate(Request $request, string $id): JsonResponse
    {
        $claims = $request->attributes->get('jwt_claims') ?? [];
        $email = $claims['email'] ?? null;

        $certificate = Certificate::with(['event'])->find($id);

        if (!$certificate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate not found.',
            ], 404);
        }

        if ($certificate->recipient_email !== $email) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden',
                'reason' => 'not_owner',
            ], 403);
        }

        return response()->json([
            'data' => $this->formatCertificate($certificate),
        ]);
    }

    #[OA\Get(
        path: "/api/v1/me/events",
        summary: "List events the caller created",
        tags: ["Me"],
        parameters: [
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["draft", "active", "archive"])),
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer", default: 25)),
            new OA\Parameter(name: "offset", in: "query", schema: new OA\Schema(type: "integer", default: 0)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/EventListResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Forbidden"),
        ]
    )]
    public function events(Request $request): JsonResponse
    {
        $claims = $request->attributes->get('jwt_claims') ?? [];
        $sub = $claims['sub'] ?? null;

        $query = Event::withCount(['attendees', 'certificates'])
            ->where('created_by', $sub);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('organizer', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $limit = min((int) $request->query('limit', 25), 100);
        $offset = (int) $request->query('offset', 0);

        $total = $query->count();
        $events = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (Event $event) => $this->formatEvent($event));

        return response()->json([
            'data' => $events,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'has_more' => ($offset + $limit) < $total,
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/v1/me/templates",
        summary: "List templates the caller created",
        tags: ["Me"],
        parameters: [
            new OA\Parameter(name: "type", in: "query", schema: new OA\Schema(type: "string", enum: ["certificate", "email"])),
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer", default: 25)),
            new OA\Parameter(name: "offset", in: "query", schema: new OA\Schema(type: "integer", default: 0)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/TemplateListResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Forbidden"),
        ]
    )]
    public function templates(Request $request): JsonResponse
    {
        $claims = $request->attributes->get('jwt_claims') ?? [];
        $sub = $claims['sub'] ?? null;

        $query = CertificateTemplate::where('created_by', $sub);

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $limit = min((int) $request->query('limit', 25), 100);
        $offset = (int) $request->query('offset', 0);

        $total = $query->count();
        $templates = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (CertificateTemplate $template) => $this->formatTemplate($template));

        return response()->json([
            'data' => $templates,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'has_more' => ($offset + $limit) < $total,
            ],
        ]);
    }

    private function formatCertificate(Certificate $certificate): array
    {
        return [
            'id' => $certificate->id,
            'certificate_number' => $certificate->certificate_number,
            'recipient_name' => $certificate->recipient_name,
            'issued_at' => $certificate->issued_at?->toIso8601String(),
            'expires_at' => $certificate->expires_at?->toIso8601String(),
            'revoked_at' => $certificate->revoked_at?->toIso8601String(),
            'revoke_reason' => $certificate->revoke_reason,
            'status' => $certificate->status,
            'event_id' => $certificate->event_id,
            'event_name' => $certificate->event?->name,
            'created_at' => $certificate->created_at?->toIso8601String(),
        ];
    }

    private function formatEvent(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'description' => $event->description,
            'event_date' => $event->event_date?->toDateString(),
            'location' => $event->location,
            'organizer' => $event->organizer,
            'certificate_title' => $event->certificate_title,
            'certificate_number_pattern' => $event->certificate_number_pattern,
            'valid_until' => $event->valid_until?->toDateString(),
            'status' => $event->status,
            'template_id' => $event->template_id,
            'email_template_id' => $event->email_template_id,
            'attendees_count' => $event->attendees_count ?? 0,
            'certificates_issued' => $event->certificates_count ?? 0,
            'created_at' => $event->created_at?->toIso8601String(),
            'updated_at' => $event->updated_at?->toIso8601String(),
        ];
    }

    private function formatTemplate(CertificateTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'type' => $template->type,
            'html_content' => $template->html_content,
            'css_content' => $template->css_content,
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }
}
