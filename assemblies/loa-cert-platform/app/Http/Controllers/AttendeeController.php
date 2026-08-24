<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Attendees", description: "Event attendee management")]
#[OA\Schema(schema: "Attendee", properties: [
    new OA\Property(property: "id", type: "string", format: "uuid"),
    new OA\Property(property: "event_id", type: "string", format: "uuid"),
    new OA\Property(property: "organization_id", type: "string", format: "uuid"),
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "email", type: "string", format: "email"),
    new OA\Property(property: "attended", type: "boolean"),
    new OA\Property(property: "completed", type: "boolean"),
    new OA\Property(property: "attended_at", type: "string", format: "date-time", nullable: true),
    new OA\Property(property: "completed_at", type: "string", format: "date-time", nullable: true),
    new OA\Property(property: "certificate_id", type: "string", format: "uuid", nullable: true),
    new OA\Property(property: "certificate_number", type: "string", nullable: true),
    new OA\Property(property: "metadata", type: "object", nullable: true),
    new OA\Property(property: "created_at", type: "string", format: "date-time"),
    new OA\Property(property: "updated_at", type: "string", format: "date-time"),
])]
#[OA\Schema(schema: "AttendeeListResponse", properties: [
    new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Attendee")),
    new OA\Property(property: "meta", type: "object", properties: [
        new OA\Property(property: "limit", type: "integer"),
        new OA\Property(property: "offset", type: "integer"),
        new OA\Property(property: "total", type: "integer"),
        new OA\Property(property: "has_more", type: "boolean"),
    ]),
])]
#[OA\Schema(schema: "AttendeeSingleResponse", properties: [
    new OA\Property(property: "data", ref: "#/components/schemas/Attendee"),
])]
#[OA\Schema(schema: "AttendeeCreateRequest", required: ["name", "email"], properties: [
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "email", type: "string", format: "email"),
    new OA\Property(property: "attended", type: "boolean"),
    new OA\Property(property: "completed", type: "boolean"),
    new OA\Property(property: "metadata", type: "object"),
])]
#[OA\Schema(schema: "AttendeeUpdateRequest", properties: [
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "email", type: "string", format: "email"),
    new OA\Property(property: "attended", type: "boolean"),
    new OA\Property(property: "completed", type: "boolean"),
    new OA\Property(property: "metadata", type: "object"),
])]
#[OA\Schema(schema: "AttendeeImportRequest", required: ["attendees"], properties: [
    new OA\Property(property: "attendees", type: "array", items: new OA\Items(properties: [
        new OA\Property(property: "name", type: "string"),
        new OA\Property(property: "email", type: "string", format: "email"),
        new OA\Property(property: "attended", type: "boolean"),
        new OA\Property(property: "completed", type: "boolean"),
        new OA\Property(property: "metadata", type: "object"),
    ])),
    new OA\Property(property: "mode", type: "string", enum: ["merge", "replace"]),
    new OA\Property(property: "confirm", type: "boolean"),
])]
#[OA\Schema(schema: "AttendeeImportResponse", properties: [
    new OA\Property(property: "imported", type: "integer"),
    new OA\Property(property: "skipped", type: "integer"),
    new OA\Property(property: "errors", type: "array", items: new OA\Items(properties: [
        new OA\Property(property: "row", type: "integer"),
        new OA\Property(property: "email", type: "string"),
        new OA\Property(property: "reason", type: "string"),
    ])),
])]
#[OA\Schema(schema: "AttendeeDeletePreviewResponse", properties: [
    new OA\Property(property: "attendee_id", type: "string", format: "uuid"),
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "email", type: "string", format: "email"),
    new OA\Property(property: "linked_certificate", type: "string", format: "uuid", nullable: true),
    new OA\Property(property: "deletes_certificate", type: "boolean"),
])]
#[OA\Schema(schema: "AttendeeFileDataResponse", properties: [
    new OA\Property(property: "generation_mode", type: "string", enum: ["template", "file"]),
])]
class AttendeeController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Display a listing of attendees for an event.
     */
    #[OA\Get(
        path: "/api/v1/events/{eventId}/attendees",
        summary: "List event attendees",
        tags: ["Attendees"],
        parameters: [
            new OA\Parameter(name: "eventId", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "attended", in: "query", schema: new OA\Schema(type: "boolean")),
            new OA\Parameter(name: "completed", in: "query", schema: new OA\Schema(type: "boolean")),
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer", default: 25)),
            new OA\Parameter(name: "offset", in: "query", schema: new OA\Schema(type: "integer", default: 0)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/AttendeeListResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Event not found"),
        ]
    )]
    public function index(Request $request, string $eventId): JsonResponse
    {
        $event = Event::find($eventId);

        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found.',
            ], 404);
        }

        $query = EventAttendee::where('event_id', $eventId);

        // Apply search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply attendance filters
        if ($request->has('attended')) {
            $query->where('attended', $request->input('attended'));
        }

        if ($request->has('completed')) {
            $query->where('completed', $request->input('completed'));
        }

        // Apply pagination
        $limit = min($request->input('limit', 25), 100);
        $offset = $request->input('offset', 0);
        
        $attendees = $query->skip($offset)
                          ->take($limit)
                          ->get();

        $total = $query->count();

        return response()->json([
            'data' => $attendees,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'has_more' => $total > ($offset + $limit)
            ]
        ]);
    }

    /**
     * Store a newly created attendee in storage.
     */
    #[OA\Post(
        path: "/api/v1/events/{eventId}/attendees",
        summary: "Create attendee",
        tags: ["Attendees"],
        parameters: [
            new OA\Parameter(name: "eventId", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/AttendeeCreateRequest")
        ),
        responses: [
            new OA\Response(response: 201, description: "Created", content: new OA\JsonContent(ref: "#/components/schemas/AttendeeSingleResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Event not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request, string $eventId): JsonResponse
    {
        $event = Event::find($eventId);

        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found.',
            ], 404);
        }

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'attended' => 'nullable|boolean',
            'completed' => 'nullable|boolean',
            'metadata' => 'nullable|array'
        ]);

        $attendee = EventAttendee::updateOrCreate(
            ['event_id' => $eventId, 'email' => $request->input('email')],
            array_merge($request->only(['name', 'attended', 'completed', 'metadata']), [
                'organization_id' => $event->organization_id,
            ])
        );

        if ($request->boolean('attended') && $attendee->attended_at === null) {
            $attendee->update(['attended_at' => now()]);
        }

        if ($request->boolean('completed') && $attendee->completed_at === null) {
            $attendee->update(['completed_at' => now()]);
        }

        $this->auditLogger->record('attendee.created', 'api', 'attendee', $attendee->id, [
            'event_id' => $eventId,
            'email' => $attendee->email,
        ]);

        return response()->json([
            'data' => $attendee->fresh()
        ], 201);
    }

    /**
     * Update the specified attendee in storage.
     */
    #[OA\Patch(
        path: "/api/v1/attendees/{id}",
        summary: "Update attendee",
        tags: ["Attendees"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/AttendeeUpdateRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/AttendeeSingleResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $attendee = EventAttendee::findOrFail($id);

        $request->validate([
            'name' => 'nullable|string',
            'email' => 'nullable|email|unique:event_attendees,email,' . $id . ',id,event_id,' . $attendee->event_id,
            'attended' => 'nullable|boolean',
            'completed' => 'nullable|boolean',
            'attended_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'metadata' => 'nullable|array'
        ]);

        $attendee->update($request->only([
            'name', 'email', 'attended', 'completed', 'attended_at', 'completed_at', 'metadata'
        ]));

        $this->auditLogger->record('attendee.updated', 'api', 'attendee', $attendee->id, [
            'event_id' => $attendee->event_id,
            'email' => $attendee->email,
        ]);

        return response()->json([
            'data' => $attendee->fresh()
        ]);
    }

    /**
     * Delete an attendee together with its linked certificate.
     */
    #[OA\Delete(
        path: "/api/v1/attendees/{id}/with-cert",
        summary: "Delete attendee and linked certificate",
        tags: ["Attendees"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 204, description: "Deleted"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function destroyWithCert(string $id): JsonResponse
    {
        $attendee = EventAttendee::findOrFail($id);

        if ($attendee->certificate_id) {
            $this->auditLogger->record('certificate.deleted', 'api', 'certificate', $attendee->certificate_id, [
                'certificate_number' => $attendee->certificate_number,
                'channel' => 'attendee_delete',
            ]);

            Certificate::where('id', $attendee->certificate_id)->delete();
        }

        $this->auditLogger->record('attendee.deleted', 'api', 'attendee', $attendee->id, [
            'event_id' => $attendee->event_id,
            'email' => $attendee->email,
            'with_certificate' => true,
        ]);

        $attendee->delete();

        return response()->json(null, 204);
    }

    /**
     * Remove the specified attendee from storage.
     */
    #[OA\Delete(
        path: "/api/v1/attendees/{id}",
        summary: "Delete attendee",
        tags: ["Attendees"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 204, description: "Deleted"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function destroy(string $id): JsonResponse
    {
        $attendee = EventAttendee::findOrFail($id);

        $this->auditLogger->record('attendee.deleted', 'api', 'attendee', $attendee->id, [
            'event_id' => $attendee->event_id,
            'email' => $attendee->email,
        ]);

        $attendee->delete();

        return response()->json(null, 204);
    }

    /**
     * Import attendees in bulk.
     */
    #[OA\Post(
        path: "/api/v1/events/{eventId}/attendees/import",
        summary: "Import attendees",
        tags: ["Attendees"],
        parameters: [
            new OA\Parameter(name: "eventId", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/AttendeeImportRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/AttendeeImportResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Event not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function import(Request $request, string $eventId): JsonResponse
    {
        $event = Event::find($eventId);

        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found.',
            ], 404);
        }

        $request->validate([
            'attendees' => 'required|array',
            'attendees.*.name' => 'required|string',
            'attendees.*.email' => 'required|email',
            'mode' => 'nullable|string|in:merge,replace',
            'confirm' => 'required_if:mode,replace|boolean'
        ]);

        $mode = $request->input('mode', 'merge');

        if ($mode === 'replace') {
            if (!$request->boolean('confirm')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Replacing attendees requires confirm=true.',
                ], 422);
            }

            EventAttendee::where('event_id', $eventId)->delete();
        }

        $attendeesData = $request->input('attendees');

        // Process the import
        $result = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        foreach ($attendeesData as $index => $attendeeData) {
            try {
                // Basic validation
                if (empty($attendeeData['name']) || empty($attendeeData['email'])) {
                    $result['errors'][] = [
                        'row' => $index + 1,
                        'email' => $attendeeData['email'] ?? '',
                        'reason' => 'Missing name or email'
                    ];
                    $result['skipped']++;
                    continue;
                }

                if (!filter_var($attendeeData['email'], FILTER_VALIDATE_EMAIL)) {
                    $result['errors'][] = [
                        'row' => $index + 1,
                        'email' => $attendeeData['email'],
                        'reason' => 'Invalid email format'
                    ];
                    $result['skipped']++;
                    continue;
                }

                // Create or update attendee
                $attendee = EventAttendee::updateOrCreate(
                    [
                        'event_id' => $eventId,
                        'email' => $attendeeData['email']
                    ],
                    array_merge($attendeeData, [
                        'event_id' => $eventId,
                        'organization_id' => $event->organization_id
                    ])
                );

                $result['imported']++;
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'row' => $index + 1,
                    'email' => $attendeeData['email'] ?? '',
                    'reason' => $e->getMessage()
                ];
                $result['skipped']++;
            }
        }

        $this->auditLogger->record('attendee.imported', 'api', 'event', $eventId, [
            'mode' => $mode,
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
        ]);

        return response()->json([
            'data' => $result
        ]);
    }

    /**
     * Get attendee delete preview.
     */
    #[OA\Get(
        path: "/api/v1/attendees/{id}/delete-preview",
        summary: "Get attendee delete preview",
        tags: ["Attendees"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/AttendeeDeletePreviewResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function deletePreview(string $id): JsonResponse
    {
        $attendee = EventAttendee::with('certificate')->findOrFail($id);

        $certificate = $attendee->certificate;

        return response()->json([
            'data' => [
                'attendee_id' => $attendee->id,
                'name' => $attendee->name,
                'email' => $attendee->email,
                'linked_certificate' => $certificate ? [
                    'id' => $certificate->id,
                    'number' => $certificate->certificate_number,
                    'status' => $certificate->status,
                ] : null,
                'deletes_certificate' => $certificate !== null,
            ]
        ]);
    }

    /**
     * Return the uploaded certificate source file for an attendee.
     */
    #[OA\Get(
        path: "/api/v1/attendees/{id}/file-data",
        summary: "Get attendee file data",
        tags: ["Attendees"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/AttendeeFileDataResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
            new OA\Response(response: 410, description: "File removed"),
        ]
    )]
    public function fileData(string $id)
    {
        $attendee = EventAttendee::findOrFail($id);

        $metadata = $attendee->metadata ?? [];
        $mode = $metadata['generation_mode'] ?? 'template';

        if ($mode !== 'file') {
            return response()->json([
                'data' => [
                    'generation_mode' => 'template'
                ]
            ]);
        }

        $path = $metadata['file_path'] ?? null;

        if (!$path || !Storage::disk('public')->exists($path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Uploaded file has been removed.',
            ], 410);
        }

        return Storage::disk('public')->download($path, $metadata['file_name'] ?? basename($path));
    }

    /**
     * Cross-event attendee lookup by email (single aggregate query set — spec
     * e-cert specs/auth/user-activity.md §3.3). Returns every event roster the
     * email appears on plus certificate totals. No match → empty data, 200.
     */
    public function lookup(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->query('email', '')));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'status' => 'error',
                'message' => 'A valid email query parameter is required.',
            ], 422);
        }

        $attendees = EventAttendee::query()
            ->with('event:id,name')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orderByDesc('updated_at')
            ->get();

        $certificateIds = $attendees
            ->map(fn (EventAttendee $attendee) => $attendee->certificate_id)
            ->filter()
            ->unique()
            ->values();

        $revokedCertificateIds = $certificateIds->isEmpty()
            ? collect()
            : Certificate::whereIn('id', $certificateIds)
                ->whereNotNull('revoked_at')
                ->pluck('id');

        $events = $attendees->map(fn (EventAttendee $attendee) => [
            'id' => $attendee->event_id,
            'name' => $attendee->event?->name,
            'attended' => (bool) $attendee->attended,
            'completed' => (bool) $attendee->completed,
            'attended_at' => $attendee->attended_at?->toIso8601String(),
            'completed_at' => $attendee->completed_at?->toIso8601String(),
            'has_certificate' => $attendee->certificate_id !== null,
            'certificate_revoked' => $attendee->certificate_id !== null
                && $revokedCertificateIds->contains($attendee->certificate_id),
        ]);

        $standaloneCertificates = Certificate::whereRaw('LOWER(recipient_email) = ?', [$email]);

        return response()->json([
            'data' => [
                'email' => $email,
                'events' => $events,
                'totals' => [
                    'events' => $events->count(),
                    'attended' => $events->where('attended', true)->count(),
                    'certificates_active' => (clone $standaloneCertificates)
                        ->whereNull('revoked_at')
                        ->where(function ($q) {
                            $q->whereNull('expires_at')
                                ->orWhere('expires_at', '>=', now());
                        })
                        ->count(),
                    'certificates_revoked' => (clone $standaloneCertificates)
                        ->whereNotNull('revoked_at')
                        ->count(),
                ],
            ],
        ]);
    }
}