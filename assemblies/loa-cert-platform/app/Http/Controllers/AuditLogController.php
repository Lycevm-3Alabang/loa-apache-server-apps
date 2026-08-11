<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Admin", description: "Administrative endpoints")]
#[OA\Schema(schema: "AuditLogItem", properties: [
    new OA\Property(property: "id", type: "string", format: "uuid"),
    new OA\Property(property: "user_id", type: "string", nullable: true),
    new OA\Property(property: "user_email", type: "string", nullable: true),
    new OA\Property(property: "action", type: "string"),
    new OA\Property(property: "source", type: "string"),
    new OA\Property(property: "entity_type", type: "string", nullable: true),
    new OA\Property(property: "entity_id", type: "string", nullable: true),
    new OA\Property(property: "details", type: "object", nullable: true),
    new OA\Property(property: "ip_address", type: "string", nullable: true),
    new OA\Property(property: "user_agent", type: "string", nullable: true),
    new OA\Property(property: "created_at", type: "string", format: "date-time"),
])]
class AuditLogController extends Controller
{
    #[OA\Get(
        path: "/api/v1/admin/audit-logs",
        summary: "List audit logs",
        tags: ["Admin"],
        parameters: [
            new OA\Parameter(name: "action", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "entity_type", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "user_email", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "from", in: "query", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "to", in: "query", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer", default: 25)),
            new OA\Parameter(name: "offset", in: "query", schema: new OA\Schema(type: "integer", default: 0)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(properties: [
                new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/AuditLogItem")),
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
    public function index(Request $request): JsonResponse
    {
        $organizationId = config('cert-platform.organization_id');

        $query = AuditLog::where('organization_id', $organizationId);

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($entityType = $request->query('entity_type')) {
            $query->where('entity_type', $entityType);
        }

        if ($userEmail = $request->query('user_email')) {
            $query->where('user_email', 'like', "%{$userEmail}%");
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $limit = min((int) $request->query('limit', 25), 100);
        $offset = (int) $request->query('offset', 0);

        $total = $query->count();
        $logs = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_email' => $log->user_email,
                'action' => $log->action,
                'source' => $log->source,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'details' => $log->details,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $logs,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'has_more' => ($offset + $limit) < $total,
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/v1/admin/audit-logs/export",
        summary: "Export audit logs as CSV",
        tags: ["Admin"],
        parameters: [
            new OA\Parameter(name: "action", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "entity_type", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "user_email", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "from", in: "query", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "to", in: "query", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "format", in: "query", schema: new OA\Schema(type: "string", enum: ["csv", "json"], default: "csv")),
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer", default: 10000)),
        ],
        responses: [
            new OA\Response(response: 200, description: "CSV/JSON file download"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Forbidden"),
        ]
    )]
    public function export(Request $request): Response
    {
        $organizationId = config('cert-platform.organization_id');

        $query = AuditLog::where('organization_id', $organizationId);

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($entityType = $request->query('entity_type')) {
            $query->where('entity_type', $entityType);
        }

        if ($userEmail = $request->query('user_email')) {
            $query->where('user_email', 'like', "%{$userEmail}%");
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $limit = min((int) $request->query('limit', 10000), 100000);
        $logs = $query->orderBy('created_at', 'desc')->limit($limit)->get();

        $format = $request->query('format', 'csv') === 'json' ? 'json' : 'csv';
        $fileName = 'audit-logs-' . now()->format('Y-m-d-His');

        if ($format === 'json') {
            $data = $logs->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_email' => $log->user_email,
                'action' => $log->action,
                'source' => $log->source,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'details' => $log->details,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values()->toArray();

            return response()->json($data)->withHeaders([
                'Content-Disposition' => "attachment; filename=\"{$fileName}.json\"",
            ]);
        }

        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, [
            'id', 'user_id', 'user_email', 'action', 'source',
            'entity_type', 'entity_id', 'details', 'ip_address', 'user_agent', 'created_at',
        ]);

        foreach ($logs as $log) {
            fputcsv($stream, [
                $log->id,
                $log->user_id,
                $log->user_email,
                $log->action,
                $log->source,
                $log->entity_type,
                $log->entity_id,
                $log->details ? json_encode($log->details) : null,
                $log->ip_address,
                $log->user_agent,
                $log->created_at?->toIso8601String(),
            ]);
        }

        rewind($stream);

        return response()->streamDownload(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, $fileName . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
