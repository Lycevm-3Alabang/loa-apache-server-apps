<?php

namespace App\Http\Controllers;

use App\Models\EventAttendee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:event_attendees,event_id,' . $eventId . ',event_id,email',
            'attended' => 'nullable|boolean',
            'completed' => 'nullable|boolean',
            'metadata' => 'nullable|array'
        ]);

        $attendee = EventAttendee::create(array_merge($request->all(), [
            'event_id' => $eventId,
            'organization_id' => $this->getOrganizationId()
        ]));

        return response()->json([
            'data' => $attendee
        ], 201);
    }

    /**
     * Update the specified attendee in storage.
     */
    #[OA\Put(
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
            'email' => 'nullable|email|unique:event_attendees,id,' . $id . ',id,email',
            'attended' => 'nullable|boolean',
            'completed' => 'nullable|boolean',
            'metadata' => 'nullable|array'
        ]);

        $attendee->update($request->all());

        return response()->json([
            'data' => $attendee
        ]);
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
        $attendee->delete();

        return response()->noContent();
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
        $request->validate([
            'attendees' => 'required|array',
            'attendees.*.name' => 'required|string',
            'attendees.*.email' => 'required|email',
            'mode' => 'nullable|string|in:merge,replace',
            'confirm' => 'required_if:mode,replace|boolean'
        ]);

        $mode = $request->input('mode', 'merge');
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
                        'organization_id' => $this->getOrganizationId()
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
        $attendee = EventAttendee::findOrFail($id);
        
        // In a real implementation, this would check if attendee has linked certificate
        return response()->json([
            'data' => [
                'attendee_id' => $attendee->id,
                'name' => $attendee->name,
                'email' => $attendee->email,
                'linked_certificate' => null,
                'deletes_certificate' => false
            ]
        ]);
    }

    /**
     * Get attendee file data.
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
        ]
    )]
    public function fileData(string $id): JsonResponse
    {
        $attendee = EventAttendee::findOrFail($id);
        
        if ($attendee->metadata && isset($attendee->metadata['file_path'])) {
            // In a real implementation, this would return the actual file
            return response()->json([
                'data' => [
                    'generation_mode' => 'file'
                ]
            ]);
        }
        
        return response()->json([
            'data' => [
                'generation_mode' => 'template'
            ]
        ]);
    }

    /**
     * Get organization ID (placeholder implementation).
     */
    private function getOrganizationId(): string
    {
        // In a real implementation, this would be retrieved from the JWT or request
        // For now, we'll return a default organization ID for demo purposes
        return '123e4567-e89b-12d3-a456-426614174000';
    }
}