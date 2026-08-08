<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventAttendee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Events", description: "Event management")]
#[OA\Schema(schema: "Event", properties: [
    new OA\Property(property: "id", type: "string", format: "uuid"),
    new OA\Property(property: "organization_id", type: "string", format: "uuid"),
    new OA\Property(property: "template_id", type: "string", format: "uuid", nullable: true),
    new OA\Property(property: "email_template_id", type: "string", format: "uuid", nullable: true),
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "description", type: "string", nullable: true),
    new OA\Property(property: "event_date", type: "string", format: "date", nullable: true),
    new OA\Property(property: "location", type: "string", nullable: true),
    new OA\Property(property: "organizer", type: "string", nullable: true),
    new OA\Property(property: "certificate_title", type: "string", nullable: true),
    new OA\Property(property: "certificate_number_pattern", type: "string"),
    new OA\Property(property: "valid_until", type: "string", format: "date", nullable: true),
    new OA\Property(property: "status", type: "string", enum: ["draft", "active", "archive"]),
    new OA\Property(property: "created_by", type: "string", format: "uuid", nullable: true),
    new OA\Property(property: "created_at", type: "string", format: "date-time"),
    new OA\Property(property: "updated_at", type: "string", format: "date-time"),
])]
#[OA\Schema(schema: "EventListResponse", properties: [
    new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Event")),
    new OA\Property(property: "meta", type: "object", properties: [
        new OA\Property(property: "limit", type: "integer"),
        new OA\Property(property: "offset", type: "integer"),
        new OA\Property(property: "total", type: "integer"),
        new OA\Property(property: "has_more", type: "boolean"),
    ]),
])]
#[OA\Schema(schema: "EventSingleResponse", properties: [
    new OA\Property(property: "data", ref: "#/components/schemas/Event"),
])]
#[OA\Schema(schema: "EventCreateRequest", required: ["name", "certificate_number_pattern"], properties: [
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "description", type: "string"),
    new OA\Property(property: "event_date", type: "string", format: "date"),
    new OA\Property(property: "location", type: "string"),
    new OA\Property(property: "organizer", type: "string"),
    new OA\Property(property: "certificate_title", type: "string"),
    new OA\Property(property: "certificate_number_pattern", type: "string"),
    new OA\Property(property: "valid_until", type: "string", format: "date"),
    new OA\Property(property: "template_id", type: "string", format: "uuid"),
    new OA\Property(property: "email_template_id", type: "string", format: "uuid"),
    new OA\Property(property: "status", type: "string", enum: ["draft", "active", "archive"]),
])]
#[OA\Schema(schema: "EventUpdateRequest", properties: [
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "description", type: "string"),
    new OA\Property(property: "event_date", type: "string", format: "date"),
    new OA\Property(property: "location", type: "string"),
    new OA\Property(property: "organizer", type: "string"),
    new OA\Property(property: "certificate_title", type: "string"),
    new OA\Property(property: "certificate_number_pattern", type: "string"),
    new OA\Property(property: "valid_until", type: "string", format: "date"),
    new OA\Property(property: "template_id", type: "string", format: "uuid"),
    new OA\Property(property: "email_template_id", type: "string", format: "uuid"),
    new OA\Property(property: "status", type: "string", enum: ["draft", "active", "archive"]),
])]
#[OA\Schema(schema: "EventStatsResponse", properties: [
    new OA\Property(property: "event_id", type: "string"),
    new OA\Property(property: "attendees", type: "object", properties: [
        new OA\Property(property: "total", type: "integer"),
        new OA\Property(property: "attended", type: "integer"),
        new OA\Property(property: "completed", type: "integer"),
    ]),
    new OA\Property(property: "certificates", type: "object", properties: [
        new OA\Property(property: "issued", type: "integer"),
        new OA\Property(property: "active", type: "integer"),
        new OA\Property(property: "revoked", type: "integer"),
        new OA\Property(property: "expired", type: "integer"),
    ]),
    new OA\Property(property: "expiring", type: "integer"),
])]
class EventController extends Controller
{
    /**
     * Display a listing of the events.
     */
    #[OA\Get(
        path: "/api/v1/events",
        summary: "List events",
        tags: ["Events"],
        parameters: [
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["draft", "active", "archive"])),
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer", default: 25)),
            new OA\Parameter(name: "offset", in: "query", schema: new OA\Schema(type: "integer", default: 0)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/EventListResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Event::query();

        // Apply search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('organizer', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Apply pagination
        $limit = min($request->input('limit', 25), 100);
        $offset = $request->input('offset', 0);
        
        $events = $query->skip($offset)
                       ->take($limit)
                       ->get();

        $total = $query->count();

        return response()->json([
            'data' => $events,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'has_more' => $total > ($offset + $limit)
            ]
        ]);
    }

    /**
     * Store a newly created event in storage.
     */
    #[OA\Post(
        path: "/api/v1/events",
        summary: "Create event",
        tags: ["Events"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/EventCreateRequest")
        ),
        responses: [
            new OA\Response(response: 201, description: "Created", content: new OA\JsonContent(ref: "#/components/schemas/EventSingleResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'event_date' => 'nullable|date',
            'location' => 'nullable|string',
            'organizer' => 'nullable|string',
            'certificate_title' => 'nullable|string',
            'certificate_number_pattern' => 'required|string',
            'valid_until' => 'nullable|date',
            'template_id' => 'nullable|uuid',
            'email_template_id' => 'nullable|uuid',
            'status' => 'nullable|in:draft,active,archive'
        ]);

        $event = Event::create($request->all());

        return response()->json([
            'data' => $event
        ], 201);
    }

    /**
     * Display the specified event.
     */
    #[OA\Get(
        path: "/api/v1/events/{id}",
        summary: "Get event",
        tags: ["Events"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/EventSingleResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        
        return response()->json([
            'data' => $event
        ]);
    }

    /**
     * Update the specified event in storage.
     */
    #[OA\Put(
        path: "/api/v1/events/{id}",
        summary: "Update event",
        tags: ["Events"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/EventUpdateRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/EventSingleResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $request->validate([
            'name' => 'nullable|string',
            'description' => 'nullable|string',
            'event_date' => 'nullable|date',
            'location' => 'nullable|string',
            'organizer' => 'nullable|string',
            'certificate_title' => 'nullable|string',
            'certificate_number_pattern' => 'nullable|string',
            'valid_until' => 'nullable|date',
            'template_id' => 'nullable|uuid',
            'email_template_id' => 'nullable|uuid',
            'status' => 'nullable|in:draft,active,archive'
        ]);

        $event->update($request->all());

        return response()->json([
            'data' => $event
        ]);
    }

    /**
     * Remove the specified event from storage.
     */
    #[OA\Delete(
        path: "/api/v1/events/{id}",
        summary: "Delete event",
        tags: ["Events"],
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
        $event = Event::findOrFail($id);
        $event->delete();

        return response()->noContent();
    }

    /**
     * Get event statistics.
     */
    #[OA\Get(
        path: "/api/v1/events/{id}/stats",
        summary: "Get event statistics",
        tags: ["Events"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/EventStatsResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function stats(string $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        
        // In a real implementation, this would count attendees and certificates
        // For now, we return mock data based on what is in the schema 
        return response()->json([
            'data' => [
                'event_id' => $event->id,
                'attendees' => ['total' => 0, 'attended' => 0, 'completed' => 0],
                'certificates' => ['issued' => 0, 'active' => 0, 'revoked' => 0, 'expired' => 0],
                'expiring' => 0
            ]
        ]);
    }
}