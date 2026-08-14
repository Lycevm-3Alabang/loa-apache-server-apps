<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\EventAttendee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Dashboard", description: "Organization-wide dashboard")]
#[OA\Schema(schema: "DashboardStats", properties: [
    new OA\Property(property: "certificates", type: "object", properties: [
        new OA\Property(property: "total", type: "integer"),
        new OA\Property(property: "active", type: "integer"),
        new OA\Property(property: "revoked", type: "integer"),
        new OA\Property(property: "expired", type: "integer"),
        new OA\Property(property: "issued_30d", type: "integer"),
    ]),
    new OA\Property(property: "events", type: "object", properties: [
        new OA\Property(property: "total", type: "integer"),
        new OA\Property(property: "active", type: "integer"),
    ]),
    new OA\Property(property: "attendees", type: "object", properties: [
        new OA\Property(property: "total", type: "integer"),
    ]),
    new OA\Property(property: "templates", type: "object", properties: [
        new OA\Property(property: "total", type: "integer"),
    ]),
    new OA\Property(property: "expiring_soon", type: "integer"),
])]
#[OA\Schema(schema: "DashboardActivityItem", properties: [
    new OA\Property(property: "id", type: "string", format: "uuid"),
    new OA\Property(property: "action", type: "string"),
    new OA\Property(property: "entity_type", type: "string", nullable: true),
    new OA\Property(property: "entity_id", type: "string", nullable: true),
    new OA\Property(property: "user_email", type: "string", nullable: true),
    new OA\Property(property: "created_at", type: "string", format: "date-time"),
    new OA\Property(property: "details", type: "object", nullable: true),
])]
class DashboardController extends Controller
{
    #[OA\Get(
        path: "/api/v1/dashboard/stats",
        summary: "Organization-wide summary",
        tags: ["Dashboard"],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/DashboardStats")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Forbidden"),
        ]
    )]
    public function stats(): JsonResponse
    {
        $organizationId = config('cert-platform.organization_id');

        $certTotal = Certificate::where('organization_id', $organizationId)->count();
        $certRevoked = Certificate::where('organization_id', $organizationId)->whereNotNull('revoked_at')->count();
        $certExpired = Certificate::where('organization_id', $organizationId)
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();
        $certActive = $certTotal - $certRevoked - $certExpired;
        $certIssued30d = Certificate::where('organization_id', $organizationId)
            ->where('issued_at', '>=', now()->subDays(30))
            ->count();

        $expiringSoon = Certificate::where('organization_id', $organizationId)
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', now())
            ->where('expires_at', '<=', now()->addDays(30))
            ->count();

        $eventsTotal = Event::where('organization_id', $organizationId)->count();
        $eventsActive = Event::where('organization_id', $organizationId)->where('status', 'active')->count();

        $attendeesTotal = EventAttendee::where('organization_id', $organizationId)->count();

        $templatesTotal = CertificateTemplate::where('organization_id', $organizationId)->count();

        return response()->json([
            'data' => [
                'certificates' => [
                    'total' => $certTotal,
                    'active' => $certActive,
                    'revoked' => $certRevoked,
                    'expired' => $certExpired,
                    'issued_30d' => $certIssued30d,
                ],
                'events' => [
                    'total' => $eventsTotal,
                    'active' => $eventsActive,
                ],
                'attendees' => [
                    'total' => $attendeesTotal,
                ],
                'templates' => [
                    'total' => $templatesTotal,
                ],
                'expiring_soon' => $expiringSoon,
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/v1/dashboard/activity",
        summary: "Recent activity feed",
        tags: ["Dashboard"],
        parameters: [
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer", default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(properties: [
                new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/DashboardActivityItem")),
                new OA\Property(property: "meta", type: "object", properties: [
                    new OA\Property(property: "limit", type: "integer"),
                    new OA\Property(property: "offset", type: "integer"),
                    new OA\Property(property: "total", type: "integer"),
                    new OA\Property(property: "has_more", type: "boolean"),
                ]),
            ])),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Forbidden"),
        ]
    )]
    public function activity(Request $request): JsonResponse
    {
        $organizationId = config('cert-platform.organization_id');

        $limit = min((int) $request->query('limit', 20), 50);
        $offset = (int) $request->query('offset', 0);

        $query = AuditLog::where('organization_id', $organizationId);

        $total = $query->count();
        $items = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'user_email' => $log->user_email,
                'created_at' => $log->created_at?->toIso8601String(),
                'details' => $log->details,
            ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'has_more' => ($offset + $limit) < $total,
            ],
        ]);
    }
}
