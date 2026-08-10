<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Services\CertificateNumberService;
use App\Services\PdfService;
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
#[OA\Schema(schema: "EventCloneTemplateRequest", required: ["source_template_id", "name"], properties: [
    new OA\Property(property: "source_template_id", type: "string", format: "uuid"),
    new OA\Property(property: "name", type: "string"),
])]
#[OA\Schema(schema: "EventCloneResponse", properties: [
    new OA\Property(property: "template_id", type: "string", format: "uuid"),
    new OA\Property(property: "name", type: "string"),
])]
#[OA\Schema(schema: "EventBulkIssueRequest", required: ["attendee_ids"], properties: [
    new OA\Property(property: "attendee_ids", type: "array", items: new OA\Items(type: "string", format: "uuid")),
    new OA\Property(property: "send_email", type: "boolean", default: false),
])]
#[OA\Schema(schema: "EventReissueRequest", required: ["attendee_ids"], properties: [
    new OA\Property(property: "attendee_ids", type: "array", items: new OA\Items(type: "string", format: "uuid")),
])]
#[OA\Schema(schema: "EventIssueCompletedRequest", properties: [
    new OA\Property(property: "attendee_ids", type: "array", items: new OA\Items(type: "string", format: "uuid")),
    new OA\Property(property: "send_email", type: "boolean", default: false),
])]
#[OA\Schema(schema: "EventIssueResponse", properties: [
    new OA\Property(property: "success", type: "integer"),
    new OA\Property(property: "failed", type: "integer"),
    new OA\Property(property: "errors", type: "array", items: new OA\Items(properties: [
        new OA\Property(property: "attendee_id", type: "string", format: "uuid"),
        new OA\Property(property: "reason", type: "string"),
    ])),
    new OA\Property(property: "certificates", type: "array", items: new OA\Items(type: "string", format: "uuid")),
])]
#[OA\Schema(schema: "EventRevokeExpiredCountResponse", properties: [
    new OA\Property(property: "event_id", type: "string", format: "uuid"),
    new OA\Property(property: "expired", type: "integer"),
])]
#[OA\Schema(schema: "EventRevokeExpiredResponse", properties: [
    new OA\Property(property: "event_id", type: "string", format: "uuid"),
    new OA\Property(property: "revoked", type: "integer"),
])]
class EventController extends Controller
{
    public function __construct(
        private readonly CertificateNumberService $certificateNumberService,
        private readonly PdfService $pdfService,
    ) {
    }

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
        $query = Event::withCount(['attendees', 'certificates']);

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

        $total = $query->count();
        $events = $query->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(fn (Event $event) => $this->formatEvent($event));

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

        $event = Event::create(array_merge($request->all(), [
            'organization_id' => config('cert-platform.organization_id'),
        ]));

        return response()->json([
            'data' => $this->formatEvent($event->loadCount(['attendees', 'certificates']))
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
        $event = Event::withCount(['attendees', 'certificates'])->findOrFail($id);
        
        return response()->json([
            'data' => $this->formatEvent($event)
        ]);
    }

    /**
     * Update the specified event in storage.
     */
    #[OA\Patch(
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
            'data' => $this->formatEvent($event->fresh()->loadCount(['attendees', 'certificates']))
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

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: "/api/v1/events/{id}/clone-template",
        summary: "Clone a certificate template and attach it to the event",
        tags: ["Events"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/EventCloneTemplateRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/EventCloneResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Event or template not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function cloneTemplate(Request $request, string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $request->validate([
            'source_template_id' => 'required|uuid',
            'name' => 'required|string',
        ]);

        $source = CertificateTemplate::where('id', $request->input('source_template_id'))
            ->where('organization_id', $event->organization_id)
            ->where('type', 'certificate')
            ->first();

        if (!$source) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate template not found.',
            ], 404);
        }

        $clone = CertificateTemplate::create([
            'organization_id' => $event->organization_id,
            'name' => $request->input('name'),
            'description' => $source->description,
            'type' => 'certificate',
            'html_content' => $source->html_content,
            'css_content' => $source->css_content,
            'created_by' => $source->created_by,
        ]);

        $event->update(['template_id' => $clone->id]);

        return response()->json([
            'data' => [
                'template_id' => $clone->id,
                'name' => $clone->name,
            ],
        ]);
    }

    #[OA\Post(
        path: "/api/v1/events/{id}/clone-email-template",
        summary: "Clone an email template and attach it to the event",
        tags: ["Events"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/EventCloneTemplateRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/EventCloneResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Event or template not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function cloneEmailTemplate(Request $request, string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $request->validate([
            'source_template_id' => 'required|uuid',
            'name' => 'required|string',
        ]);

        $source = CertificateTemplate::where('id', $request->input('source_template_id'))
            ->where('organization_id', $event->organization_id)
            ->where('type', 'email')
            ->first();

        if (!$source) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email template not found.',
            ], 404);
        }

        $clone = CertificateTemplate::create([
            'organization_id' => $event->organization_id,
            'name' => $request->input('name'),
            'description' => $source->description,
            'type' => 'email',
            'html_content' => $source->html_content,
            'css_content' => $source->css_content,
            'created_by' => $source->created_by,
        ]);

        $event->update(['email_template_id' => $clone->id]);

        return response()->json([
            'data' => [
                'template_id' => $clone->id,
                'name' => $clone->name,
            ],
        ]);
    }

    #[OA\Post(
        path: "/api/v1/events/{id}/bulk-issue",
        summary: "Issue certificates in bulk for selected attendees",
        tags: ["Events"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/EventBulkIssueRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/EventIssueResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Event not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function bulkIssue(Request $request, string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $request->validate([
            'attendee_ids' => 'required|array|min:1',
            'attendee_ids.*' => 'uuid',
            'send_email' => 'nullable|boolean',
        ]);

        if (!$event->certificate_number_pattern) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event has no certificate number pattern.',
            ], 422);
        }

        if (!$event->template_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event has no certificate template.',
            ], 422);
        }

        $attendees = EventAttendee::where('event_id', $id)
            ->whereIn('id', $request->input('attendee_ids'))
            ->get();

        $result = $this->issueCertificates($event, $attendees);

        return response()->json([
            'data' => $result,
        ]);
    }

    #[OA\Post(
        path: "/api/v1/events/{id}/issue-completed",
        summary: "Issue certificates to completed attendees",
        tags: ["Events"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(ref: "#/components/schemas/EventIssueCompletedRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/EventIssueResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Event not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function issueCompleted(Request $request, string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $request->validate([
            'send_email' => 'nullable|boolean',
            'attendee_ids' => 'nullable|array',
            'attendee_ids.*' => 'uuid',
        ]);

        if (!$event->certificate_number_pattern) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event has no certificate number pattern.',
            ], 422);
        }

        if (!$event->template_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event has no certificate template.',
            ], 422);
        }

        $query = EventAttendee::where('event_id', $id)->where('completed', true);

        if ($request->filled('attendee_ids')) {
            $query->whereIn('id', $request->input('attendee_ids'));
        }

        $result = $this->issueCertificates($event, $query->get());

        return response()->json([
            'data' => $result,
        ]);
    }

    private function issueCertificates(Event $event, $attendees): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];
        $certificateIds = [];

        foreach ($attendees as $attendee) {
            try {
                $existing = Certificate::where('event_id', $event->id)
                    ->where('recipient_email', $attendee->email)
                    ->whereNull('revoked_at')
                    ->exists();

                if ($existing) {
                    $failed++;
                    $errors[] = [
                        'attendee_id' => $attendee->id,
                        'reason' => 'Active certificate already exists',
                    ];
                    continue;
                }

                $certificateNumber = $this->certificateNumberService->generate(
                    $event->organization_id,
                    $event->certificate_number_pattern
                );

                $certificate = Certificate::create([
                    'organization_id' => $event->organization_id,
                    'event_id' => $event->id,
                    'template_id' => $event->template_id,
                    'recipient_name' => $attendee->name,
                    'recipient_email' => $attendee->email,
                    'certificate_number' => $certificateNumber,
                    'expires_at' => $event->valid_until,
                ]);

                $attendee->update([
                    'certificate_id' => $certificate->id,
                    'certificate_number' => $certificateNumber,
                ]);

                try {
                    $this->pdfService->generateCertificatePdf($certificate->fresh(['event', 'template', 'organization']));
                } catch (\Exception $e) {
                    // PDF generation failure is non-fatal; certificate is still created
                }

                $success++;
                $certificateIds[] = $certificate->id;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'attendee_id' => $attendee->id,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
            'certificates' => $certificateIds,
        ];
    }

    #[OA\Post(
        path: "/api/v1/events/{id}/reissue",
        summary: "Reissue certificates for selected attendees",
        tags: ["Events"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/EventReissueRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/EventIssueResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Event not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function reissue(Request $request, string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $request->validate([
            'attendee_ids' => 'required|array|min:1',
            'attendee_ids.*' => 'uuid',
        ]);

        if (!$event->certificate_number_pattern) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event has no certificate number pattern.',
            ], 422);
        }

        if (!$event->template_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event has no certificate template.',
            ], 422);
        }

        $attendees = EventAttendee::where('event_id', $id)
            ->whereIn('id', $request->input('attendee_ids'))
            ->get();

        $success = 0;
        $failed = 0;
        $errors = [];
        $certificateIds = [];

        foreach ($attendees as $attendee) {
            try {
                $existing = Certificate::where('event_id', $id)
                    ->where('recipient_email', $attendee->email)
                    ->whereNull('revoked_at')
                    ->latest('issued_at')
                    ->first();

                if ($existing) {
                    $existing->update([
                        'revoked_at' => now(),
                        'revoke_reason' => 'Reissued',
                    ]);
                }

                $certificateNumber = $this->certificateNumberService->generate(
                    $event->organization_id,
                    $event->certificate_number_pattern
                );

                $certificate = Certificate::create([
                    'organization_id' => $event->organization_id,
                    'event_id' => $id,
                    'template_id' => $event->template_id,
                    'recipient_name' => $attendee->name,
                    'recipient_email' => $attendee->email,
                    'certificate_number' => $certificateNumber,
                    'expires_at' => $event->valid_until,
                ]);

                $attendee->update([
                    'certificate_id' => $certificate->id,
                    'certificate_number' => $certificateNumber,
                ]);

                try {
                    $this->pdfService->generateCertificatePdf($certificate->fresh(['event', 'template', 'organization']));
                } catch (\Exception $e) {
                    // PDF generation failure is non-fatal; certificate is still created
                }

                $success++;
                $certificateIds[] = $certificate->id;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'attendee_id' => $attendee->id,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'data' => [
                'success' => $success,
                'failed' => $failed,
                'errors' => $errors,
                'certificates' => $certificateIds,
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/v1/events/{id}/revoke-expired",
        summary: "Count expired certificates for the event",
        tags: ["Events"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/EventRevokeExpiredCountResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Event not found"),
        ]
    )]
    public function revokeExpiredCount(string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $expired = Certificate::where('event_id', $id)
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();

        return response()->json([
            'data' => [
                'event_id' => $event->id,
                'expired' => $expired,
            ],
        ]);
    }

    #[OA\Post(
        path: "/api/v1/events/{id}/revoke-expired",
        summary: "Revoke expired certificates for the event",
        tags: ["Events"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/EventRevokeExpiredResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Event not found"),
        ]
    )]
    public function revokeExpired(string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $revoked = Certificate::where('event_id', $id)
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update([
                'revoked_at' => now(),
                'revoke_reason' => 'Auto-expired',
            ]);

        return response()->json([
            'data' => [
                'event_id' => $event->id,
                'revoked' => $revoked,
            ],
        ]);
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

        $attendeesTotal = EventAttendee::where('event_id', $id)->count();
        $attendeesAttended = EventAttendee::where('event_id', $id)->where('attended', true)->count();
        $attendeesCompleted = EventAttendee::where('event_id', $id)->where('completed', true)->count();

        $certificatesIssued = Certificate::where('event_id', $id)->count();
        $certificatesRevoked = Certificate::where('event_id', $id)->whereNotNull('revoked_at')->count();
        $certificatesExpired = Certificate::where('event_id', $id)
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();
        $certificatesActive = $certificatesIssued - $certificatesRevoked - $certificatesExpired;
        $expiring = Certificate::where('event_id', $id)
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', now())
            ->where('expires_at', '<=', now()->addDays(30))
            ->count();

        return response()->json([
            'data' => [
                'event_id' => $event->id,
                'attendees' => [
                    'total' => $attendeesTotal,
                    'attended' => $attendeesAttended,
                    'completed' => $attendeesCompleted,
                ],
                'certificates' => [
                    'issued' => $certificatesIssued,
                    'active' => $certificatesActive,
                    'revoked' => $certificatesRevoked,
                    'expired' => $certificatesExpired,
                ],
                'expiring' => $expiring,
            ]
        ]);
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
}