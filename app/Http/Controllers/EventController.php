<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Validator;
use Illuminate\Validation\ValidationException;
use App\Models\Event;
use App\Services\PdfService;
use stdClass; // Use stdcass for dynamic JSON response structures if needed, but stick to Laravel helpers

class EventController extends Controller
{
    protected PdfService $pdfService;

    public function __construct(PdfService $pdfService)
    {
        $this->pdfService = $pdfService;
	}


    // --- OpenAPI Schemas Start ---

    /**
     * @OA\Schema(
     *     schema="Event",
     *     title="Event",
     *     description="The Event aggregate model.",
     *     @OA\Property(property="id", type="string", format="uuid", example="..."),
     *     @OA\Property(property="name", type="string", description="Full name of the event."),
     *     @OA\Property(property="description", type="string", nullable=true, description="Detailed description."),
     *     @OA\Property(property="start_date", type="string", format="date"),
     *     @OA\Property(property="end_date", type="string", format="date"),
     *     @OA\Property(property="location", type="string", nullable=true),
     *     @OA\Property(property="timezone", type="string", example="UTC-5:00"),
     *     @OA\Property(property="template_id", type="string", format="uuid", nullable=true, description="Source template ID."),
     *     @OA\Property(property="email_template_id", type="string", format="uuid", nullable=true, description="Source email template ID.")
     * )
     */

    /**
     * @OA\Schema(
     *     schema="EventListResponse",
     *     title="Paginated list of Events",
     *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Event")),
     *     @OA\Property(property="meta", type="object", @OA\Schema(
     *         @OA\Property(property="limit", type="integer"),
     *         @OA\Property(property="offset", type="integer"),
     *         @OA\Property(property="total", type="integer"),
     *         @OA\Property(property="has_more", type="boolean")
     *     )
     *)
     */

    /**
     * @OA\Schema(
     *     schema="EventSingleResponse",
     *     title="Single Event object",
     *     @OA\Property(property="data", ref="#/components/schemas/Event")
     *)
     */

    /**
     * @OA\RequestBody(
     *     @OA\JsonContent(
     *         required={"name", "description"},
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="start_date", type="string", format="date"),
     *         @OA\Property(property="end_date", type="string", format="date"),
     *         @OA\Property(property="location", type="string", nullable=true),
     *         @OA\Property(property="timezone", type="string", description="Timezone (e.g., America/Mexico_City)"),
     *         @OA\Property(property="template_id", type="string", format="uuid", nullable=true),
     *         @OA\Property(property="email_template_id", type="string", format="uuid", nullable=true)
     *     )
     *)
     */

    /**
     * @OA\Schema(
     *     schema="EventCreateRequest",
     *     title="Event Creation Payload",
     *     required={"name", "description"},
     * )
     * 
     * @OA\RequestBody(
     *     content={
     *         @OA\MediaType(mediaType="application/json", schema=@OA\Schema(ref="#/components/schemas/EventCreateRequest")),
     *     }
     * )
     */

    /**
     * @OA\Schema(
     *     schema="EventUpdateRequest",
     *     title="Event Update Payload",
     *     required={"name"},
     * )
     * 
     * @OA\RequestBody(
     *     content={
     *         @OA\MediaType(mediaType="application/json", schema=@OA\Schema(ref="#/components/schemas/EventUpdateRequest")),
     *     }
     * )
     */

    /**
     * @OA\Schema(
     *     schema="CloneTemplateRequest",
     *     title="Template Cloning Payload",
     *     required={"source_template_id"},
     * )
     * 
     * @OA\RequestBody(
     *     content={
     *         @OA\MediaType(mediaType="application/json", schema=@OA\Schema(ref="#/components/schemas/CloneTemplateRequest")),
     *     }
     * )
     */

    /**
     * @OA\Schema(
     *     schema="BulkIssueRequest",
     *     title="Bulk Certificate Issuance Payload",
     *     required={"attendee_ids"},
     * )
     * 
     * @OA\RequestBody(
     *     content={
     *         @OA\MediaType(mediaType="application/json", schema=@OA\Schema(ref="#/components/schemas/BulkIssueRequest")),
     *     }
     * )
     */

    /**
     * @OA\Schema(
     *     schema="ReissueRequest",
     *     title="Certificate Reissue Payload",
     *     required={"attendee_ids"}
     * )
     * 
     * @OA\RequestBody(
     *     content={
     *         @OA\MediaType(mediaType="application/json", schema=@OA\Schema(ref="#/components/schemas/ReissueRequest")),
     *     }
     * )
     */

    /**
     * @OA\Schema(
     *     schema="IssueCompletedRequest",
     *     title="Issue Completed Attendance Payload",
     *     required={"send_email"},
     * )
     * 
     * @OA\RequestBody(
     *     content={
     *         @OA\MediaType(mediaType="application/json", schema=@OA\Schema(ref="#/components/schemas/IssueCompletedRequest")),
     *     }
     * )
     */

    /**
     * @OA\Schema(
     *     schema="EventStatsResponse",
     *     title="Event Statistics Report",
     *     @OA\Property(property="event_id", type="string"),
     *     @OA\Property(property="attendees", type="object", @OA\Schema(
     *         @OA\Property(property="total", type="integer"),
     *         @OA\Property(property="attended", type="integer"),
     *         @OA\Property(property="completed", type="integer")
     *     )),
     *     @OA\Property(property="certificates", type="object", @OA\Schema(
     *         @OA\Property(property="issued", type="integer"),
     *         @OA\Property(property="active", type="integer"),
     *         @OA\Property(property="revoked", type="integer"),
     *         @OA\Property(property="expired", type="integer")
     *     )),
     *     @OA\Property(property="expiring", type="integer", description="Count of certificates expiring in the next 30 days.")
     *)

    // --- OpenAPI Schemas End ---


    /**
     * @OAPlayer/v1/events
     * @Router(
     *     path="/api/v1/events",
     *     method="get",
     *     summary="List events with filtering and pagination.",
     *     tags={"Events"},
     *     requestBody={...},
     *     responses={
     *         @OA\Response(response=200, description="A paginated list of events", content=@OA\JsonContent(ref="#/components/schemas/EventListResponse")),
     *         @OA\Response(response=422, description="Validation errors")
     *     }
     *)
    public function index(Request $request)
    {
        $this->validate($request, [
            'search' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $limit = $request->get('limit', 15);
        $offset = $request->get('offset', 0);
        $query = Event::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        [$events, $total] = $query->paginate($limit, ['*'], 'page', $offset)->toArray();
        return response()->json([
            'data' => $events,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'has_more' => ($offset + $limit) < $total,
            ]
        ]);
    }

    /**
     * @OAPlayer/v1/events
     * @Router(
     *     path="/api/v1/events",
     *     method="post",
     *     summary="Create a new event.",
     *     tags={"Events"},
     *     requestBody={...},
     *     responses={
     *         @OA\Response(response=201, description="Successfully created event", content=@OA\JsonContent(ref="#/components/schemas/EventSingleResponse")),
     *         @OA\Response(response=422, description="Validation errors")
     *     }
     *)
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'location' => 'nullable|string|max:255',
            'timezone' => 'nullable|string',
        ]);

        $event = Event::create(array_merge($request->all(), ['is_active' => true]));
        return response()->json(['data' => $event], 201);
    }

    /**
     * @OAPlayer/v1/events/{id}
     * @Router(
     *     path="/api/v1/events/{id}",
     *     method="get",
     *     summary="Retrieve a single event by ID.",
     *     tags={"Events"},
     *     parameters={
     *         @OA\Parameter(name="id", in="path", required=true, schema=@OA\Schema(type="string", format="uuid"))
     *     },
     *     responses={
     *         @OA\Response(response=200, description="Successfully retrieved event", content=@OA\JsonContent(ref="#/components/schemas/EventSingleResponse")),
     *         @OA\Response(response=404, description="Event not found")
     *     }
     *)
    public function show($id)
    {
        $event = Event::where('id', $id)->firstOrFail();
        return response()->json(['data' => $event]);
    }

    /**
     * @OAPlayer/v1/events/{id}
     * @Router(
     *     path="/api/v1/events/{id}",
     *     method="patch",
     *     summary="Partially update an event's details.",
     *     tags={"Events"},
     *     parameters={
     *         @OA\Parameter(name="id", in="path", required=true, schema=@OA\Schema(type="string", format="uuid"))
     *     },
     *     requestBody={...}, // Reuse EventCreateRequest structure for simplicity, but validate only provided fields
     *     responses={
     *         @OA\Response(response=200, description="Successfully updated event", content=@OA\JsonContent(ref="#/components/schemas/EventSingleResponse")),
     *         @OA\Response(response=404, description="Event not found")
     *     }
     *)
    public function update(Request $request, $id)
    {
        $event = Event::where('id', $id)->firstOrFail();

        // Simplified validation: only required for name/description if provided
        $rules = [];
        if ($request->has('name')) {
            $rules['name'] = 'string|max:255';
        }
        if ($request->has('description')) {
            $rules['description'] = 'string';
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $event->update($request->only(['name', 'description', 'start_date', 'end_date', 'location', 'timezone']));
        return response()->json(['data' => $event]);
    }

    /**
     * @OAPlayer/v1/events/{id}
     * @Router(
     *     path="/api/v1/events/{id}",
     *     method="delete",
     *     summary="Delete an event and cascade related data.",
     *     tags={"Events"},
     *     parameters={
     *         @OA\Parameter(name="id", in="path", required=true, schema=@OA\Schema(type="string", format="uuid"))
     *     },
     *     responses={
     *         @OA\Response(response=204, description="Event successfully deleted")
     *     }
     *)
    public function destroy($id)
    {
        $event = Event::find($id);
        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => 'Model not found.',
            ], 404);
        }

        $event->delete();
        return response(null, 204);
    }

    /**
     * @OAPlayer/v1/events/{id}/stats
     * @Router(
     *     path="/api/v1/events/{id}/stats",
     *     method="get",
     *     summary="Retrieve event statistics (attendees, certificates).",
     *     tags={"Events"},
     *     parameters={
     *         @OA\Parameter(name="id", in="path", required=true, schema=@OA\Schema(type="string", format="uuid"))
     *     },
     *     responses={
     *         @OA\Response(response=200, description="Event statistics report", content=@OA\JsonContent(ref="#/components/schemas/EventStatsResponse")),
     *         @OA\Response(response=404, description="Event not found")
     *     }
     *)
    public function stats($id)
    {
        $event = Event::where('id', $id)->firstOrFail();

        // Fix: Using relationship count methods for accurate statistics
        $attendees = $event->attendees(); 
        
        // Ensure the relationship is loaded for efficient querying in multiple places (e.g., Certificate)
        $certificates = $event->certificates()->with('eventAttendee'); // Assuming 'eventAttendee' belongs to Event

        return response()->json([
            'data' => [
                'event_id' => $event->id,
                'attendees' => [
                    'total' => $attendees->count(),
                    'attended' => $attendees->where('attended', true)->count(),
                    'completed' => $attendees->where('completed', true)->count(),
                ],
                'certificates' => [
                    'issued' => $certificates->count(),
                    // Active: issued, not revoked_at=null, and expires at or after now (or null)
                    'active' => $certificates->whereNull('revoked_at')->where(function ($q){ 
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>=', now()); 
                    })->count(),
                    // Revoked: revoked_at IS NOT null
                    'revoked' => $certificates->whereNotNull('revoked_at')->count(),
                    // Expired: not revoked AND expires at < now()
                    'expired' => $certificates->whereNull('revoked_at')->whereNotNull('expires_at')->where('expires_at', '<', now())->count(),
                ],
                // Expiring: not revoked, expires AT or after now, BUT <= 30 days from now
                'expiring' => $certificates->whereNull('revoked_at')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '>=', now())
                    ->where('expires_at', '<=', now()->addDays(30))->count(),
            ]
        ]);
    }

    /**
     * @OAPlayer/v1/events/{id}/clone-template
     * @Router(
     *     path="/api/v1/events/{id}/clone-template",
     *     method="post",
     *     summary="Clone event template from a source Event.",
     *     tags={"Events"},
     *     parameters={
     *         @OA\Parameter(name="id", in="path", required=true, schema=@OA\Schema(type="string", format="uuid"))
     *     },
     *     requestBody={...} // Reuse CloneTemplateRequest structure (source_template_id, name)
     *     responses={
     *         @OA\Response(response=201, description="Successfully cloned template"),
     *         @OA\Response(response=422, description="Validation errors")
     *     }
     *)
    public function cloneTemplate(Request $request, $id)
    {
        $this->validate($request, [
            'source_template_id' => 'required|string',
            'name' => 'required|string|max:255',
        ]);

        // 1. Validate source template existence and type (certificate)
        $sourceTemplateId = $this->resolveOrganizationId(); // Using org scope for simplicity if not passed/retrieved from Event model context
        $sourceTemplate = CertificateTemplate::where('id', $request->source_template_id)->where('type', 'certificate')->first();

        if (!$sourceTemplate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or non-certificate template source specified.',
            ], 409);
        }

        // 2. Create the new Event as a Template copy
        $event = Event::create([
            'name' => $request->name,
            'description' => "Cloned from: {$sourceTemplate->name}",
            'start_date' => $sourceTemplate->scheduled_date ? $sourceTemplate->scheduled_date : now()->addMonth(), // Use available date or fallback
            'end_date' => $sourceTemplate->scheduled_date ? $sourceTemplate->scheduled_date : now()->addMonths(3),
            'location' => $sourceTemplate->location,
            // ... other fields mapped from template/event structure
            'template_id' => $sourceTemplate->id, // Link source for traceability
        ]);

        return response()->json([
            'data' => [
                'template_id' => $event->id, 
                'name' => $event->name
            ]
        ], 201);
    }

    /**
     * @OAPlayer/v1/events/{id}/clone-email-template
     * @Router(
     *     path="/api/v1/events/{id}/clone-email-template",
     *     method="post",
     *     summary="Clone event email template from a source Event.",
     *     tags={"Events"},
     *     parameters={
     *         @OA\Parameter(name="id", in="path", required=true, schema=@OA\Schema(type="string", format="uuid"))
     *     },
     *     requestBody={...} // Reuse CloneTemplateRequest structure (source_template_id, name)
     *     responses={
     *         @OA\Response(response=201, description="Successfully cloned template"),
     *         @OA\Response(response=422, description="Validation errors")
     *     }
     *)
    public function cloneEmailTemplate(Request $request, $id)
    {
        $this->validate($request, [
            'source_template_id' => 'required|string',
            'name' => 'required|string|max:255',
        ]);

        // 1. Validate source template existence and type (email)
        $sourceTemplateId = $this->resolveOrganizationId();
        $sourceTemplate = CertificateTemplate::where('id', $request->source_template_id)->where('type', 'mail')->first(); // Assuming mail type for email

        if (!$sourceTemplate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or non-email template source specified.',
            ], 409);
        }

        // 2. Create the new Event (or rather, a related record/event_attendee structure if we mimic cert flow)
        // Following spec: attach as email_template_id
        $event = Event::where('id', $id)->firstOrFail(); // Ensure target event exists
        
        // Update an existing field or create new template linkage (if schema allows linking via ID update on event/related table)
        // For simplicity, we assume a method *must* exist to link the email_template_id. 
        $event->email_template_id = $sourceTemplate->id;
        $event->save();

        return response()->json([
            'data' => [
                'email_template_id' => $sourceTemplate->id, 
                'name' => "Cloned {$sourceTemplate->name}"
            ]
        ], 201);
    }

    /**
     * @OAPlayer/v1/events/{id}/bulk-issue
     * @Router(
     *     path="/api/v1/events/{id}/bulk-issue",
     *     method="post",
     *     summary="Bulk issue certificates to multiple attendees.",
     *     tags={"Events"},
     *     parameters={
     *         @OA\Parameter(name="id", in="path", required=true, schema=@OA\Schema(type="string", format="uuid"))
     *     },
     *     requestBody={...} // BulkIssueRequest (attendee_ids[], send_email)
     *     responses={
     *         @OA\Response(response=200, description="Bulk issue results", content=@OA\JsonContent())
     *     }
     *)
    public function bulkIssue(Request $request, $id)
    {
        $this->validate($request, [
            'attendee_ids' => 'required|array',
            'send_email' => 'nullable|boolean',
            // Assuming a base template ID is needed for generation pattern
            'certificate_number_pattern' => 'required|string',
        ]);

        $event = Event::where('id', $id)->firstOrFail();
        $attendeeIds = array_column($request->attendee_ids, 'id');
        $sendEmail = (bool)$request->input('send_email', false);
        $pattern = $request->certificate_number_pattern;

        // Use transaction for atomicity
        return DB::transaction(function () use ($event, $attendeeIds, $sendEmail, $pattern) {
            $results = [
                'success' => [],
                'failed' => [],
                'errors' => []
            ];

            foreach ($attendeeIds as $attendeeId) {
                try {
                    // 1. Generate Certificate Number (Atomic Operation)
                    $certificateNumber = $this->generateCertificateNumber($event->organization_id, $pattern);

                    // 2. Create/Link Certificate and EventAttendee record
                    // Find the existing attendee data to link it to certificate
                    $attendeeEvent = $event->attendees()->where('user_id', '!=', null) // Example filter assumption
                        ->findOrFail($attendeeId); 

                    $certificate = \App\Models\Certificate::create([
                        'event_id' => $event->id,
                        'attendee_id' => $attendeeId, 
                        'sequence_number' => $certificateNumber,
                        'is_valid' => true,
                        'expires_at' => now()->addYears(1), // Example expiration
                        // ... other required fields
                    ]);

                    // Update EventAttendee status/linkage
                    $attendeeEvent->update(['is_certified' => true, 'certificate_id' => $certificate->id]);


                    if ($sendEmail && $sendEmail) { 
                        // Placeholder for email queueing which does not block the HTTP response
                        // EmailService::dispatch($event->name, $certificateNumber); 
                    }

                     $results['success'][] = [
                        'attendee_id' => $attendeeId,
                        'certificate_number' => $certificateNumber,
                        'message' => 'Certificate issued successfully.'
                    ];

                } catch (\Exception $e) {
                    $results['failed'][] = ['attendee_id' => $attendeeId, 'error' => $e->getMessage()];
                    $results['errors'][] = "Failed to process attendee {$attendeeId}: {$e->getMessage()}";
                }
            }

            return response()->json(['data' => $results], 200);
        });

    }

    /**
     * @OAPlayer/v1/events/{id}/reissue
     * @Router(
     *     path="/api/v1/events/{id}/reissue",
     *     method="post",
     *     summary="Reissue certificates for specified attendees.",
     *     tags={"Events"},
     *     parameters={
     *         @OA\Parameter(name="id", in="path", required=true, schema=@OA\Schema(type="string", format="uuid"))
     *     },
     *     requestBody={...} // ReissueRequest (attendee_ids[])
     *     responses={
     *         @OA\Response(response=200, description="Reissuance results", content=@OA\JsonContent())
     *     }
     *)
    public function reissue(Request $request, $id)
    {
        $this->validate($request, [
            'attendee_ids' => 'required|array',
        ]);

        $event = Event::where('id', $id)->firstOrFail();
        $attendeeIds = array_column($request->attendee_ids, 'id');
        $reissueResults = [];

         // Check if the request is admin-only (implicitly handled by caller based on context since middleware is absent)
        
        return DB::transaction(function () use ($event, $attendeeIds, &$reissueResults) {
            foreach ($attendeeIds as $attendeeId) {
                try {
                    // 1. Find the current active certificate for the attendee at this event
                    $currentCert = $event->certificates()
                        ->where('attendee_id', $attendeeId)
                        ->whereNull('revoked_at') // Must be active
                        ->latest('issued_at')
                        ->first();

                    if (!$currentCert) {
                         throw new \Exception("No currently active certificate found to reissue.");
                    }

                    // 2. Revoke the existing certificate (Requirement per Spec §5.1.10)
                    $currentCert->update(['revoked_at' => now(), 'revoke_reason' => 'Reissued by Admin']);
                    $event->save(); // Save event status if needed

                    // 3. Issue a new number and generate the new certificate instance
                    $pattern = $request->get('certificate_number_pattern', '####'); // Fallback pattern or read from request
                    $newCertificateNumber = $this->generateCertificateNumber($event->organization_id, $pattern);

                    $newCertificate = \App\Models\Certificate::create([
                        'event_id' => $event->id,
                        'attendee_id' => $attendeeId, 
                        'sequence_number' => $newCertificateNumber,
                        'is_valid' => true,
                        'expires_at' => now()->addYears(1),
                    ]);

                    // Update EventAttendee to link to the new cert (optional but good practice)
                     $event->attendees()->find($attendeeId)->update(['certificate_id' => $newCertificate->id]);


                    $reissueResults[] = [
                        'attendee_id' => $attendeeId,
                        'old_cert_number' => $currentCert->sequence_number,
                        'new_cert_number' => $newCertificateNumber,
                        'message' => 'Reissued successfully.'
                    ];

                } catch (\Exception $e) {
                    $reissueResults[] = [
                        'attendee_id' => $attendeeId,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json(['data' => $reissueResults], 200);
        });
    }


    /**
     * @OAPlayer/v1/events/{id}/revoke-expired/count
     * @Router(
     *     path="/api/v1/events/{id}/revoke-expired",
     *     method="get",
     *     summary="Count of expired but non-revoked certificates for an event.",
     *     tags={"Events"},
     *     parameters={...},
     *     responses={
     *         @OA\Response(response=200, description="Expiration count", content=@OA\JsonContent(properties={"count": {"type":"integer"}})),
     *         @OA\Response(response=404, description="Event not found")
     *     }
     *)
    public function revokeExpiredCount($id)
    {
        $event = Event::where('id', $id)->firstOrFail();

        // Count certs: not revoked AND expires before now()
        $count = $event->certificates()->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();

        return response()->json([
            'data' => [
                'count' => $count,
                'message' => 'Expired certificate count retrieved.'
            ]
        ]);
    }

    /**
     * @OAPlayer/v1/events/{id}/revoke-expired
     * @Router(
     *     path="/api/v1/events/{id}/revoke-expired",
     *     method="post",
     *     summary="Bulk revokes all currently expired certificates for an event.",
     *     tags={"Events"},
     *     parameters={...},
     *     responses={
     *         @OA\Response(response=204, description="Certificates marked as revoked")
     *     }
     *)
    public function revokeExpired($id)
    {
        $event = Event::where('id', $id)->firstOrFail();

        // Revoke all expired certs: not revoked AND expires before NOW.
        $count = $event->certificates()->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update([
                'revoked_at' => now(),
                'revoke_reason' => 'Auto-expired by system process.'
            ]);

        return response()->json([
            'message' => "Successfully revoked {$count} expired certificates."
        ], 204);
    }

    /**
     * @OAPlayer/v1/events/{id}/issue-completed
     * @Router(
     *     path="/api/v1/events/{id}/issue-completed",
     *     method="post",
     *     summary="Bulk issue certificates for attendees marked as completed.",
     *     tags={"Events"},
     *     parameters={...},
     *     requestBody={...} // IssueCompletedRequest (send_email, attendee_ids?)
     *     responses={
     *         @OA\Response(response=200, description="Completion status report", content=@OA\JsonContent())
     *     }
     *)
    public function issueCompleted(Request $request, $id)
    {
        $this->validate($request, [
            'send_email' => 'nullable|boolean',
            // Optional: Filter by specific attendee IDs
            'attendee_ids' => 'nullable|array', 
        ]);

        $event = Event::where('id', $id)->firstOrFail();
        $sendEmail = (bool)$request->input('send_email', false);
        $filterAttendeeIds = $request->input('attendee_ids');


        // 1. Determine attendees who are 'completed' and potentially filter by provided IDs
        $targetAttendeesQuery = $event->attendees()
            ->where('completed', true);

        if ($filterAttendeeIds) {
            $targetAttendeesQuery->whereIn('id', $filterAttendeeIds);
        }

        $attendeeIdsToProcess = $targetAttendeesQuery->get()->pluck('id');
        
        if ($attendeeIdsToProcess->isEmpty()) {
             return response()->json(['data' => ['message' => 'No completed attendees found for issuance.']], 200);
        }


        // 2. Process the batch issue (Reusing pattern from bulkIssue)
         return DB::transaction(function () use ($event, $attendeeIdsToProcess, $sendEmail) {
            $reissueResults = [
                'success' => [],
                'failed' => [],
                'errors' => []
            ];

            // The process is identical to bulkIssue/reissue, but triggered by 'completed' status.
            foreach ($attendeeIdsToProcess as $attendeeId) {
                try {
                    $pattern = '####'; // Hardcode or retrieve pattern for this function scope
                     
                    // 1. Generate Certificate Number (Atomic Operation)
                    $certificateNumber = $this->generateCertificateNumber($event->organization_id, $pattern);

                    // 2. Identify related records and issue cert
                    $attendeeEvent = $event->attendees()->findOrFail($attendeeId); 

                    $certificate = \App\Models\Certificate::create([
                        'event_id' => $event->id,
                        'attendee_id' => $attendeeId, 
                        'sequence_number' => $certificateNumber,
                        'is_valid' => true,
                        'expires_at' => now()->addYears(1), 
                    ]);

                    // Update EventAttendee status/linkage with the new certificate ID
                     $attendeeEvent->update(['is_certified' => true, 'certificate_id' => $certificate->id]);


                    if ($sendEmail && $sendEmail) { 
                         // Placeholder for email queueing
                    }

                     $reissueResults['success'][] = [
                        'attendee_id' => $attendeeId,
                        'certificate_number' => $certificateNumber,
                        'message' => 'Certificate issued successfully.'
                    ];

                } catch (\Exception $e) {
                    $reissueResults['failed'][] = ['attendee_id' => $attendeeId, 'error' => $e->getMessage()];
                    $reissueResults['errors'][] = "Failed to process attendee {$attendeeId}: {$e->getMessage()}";
                }
            }

            return response()->json(['data' => $reissueResults], 200);
        });
    }


    // Helper function replacement for generating certificate number using the method signature.
    /**
     * Generates a uniquely numbered sequence based on the pattern.
     */
    private function generateCertificateNumber(string $organizationId, string $pattern): string
    {
        return DB::transaction(function () use ($organizationId, $pattern) {
            $sequence = \App\Models\CertificateSequence::lockForUpdate()
                ->firstOrCreate(
                    ['organization_id' => $organizationId, 'pattern' => $pattern],
                    ['next_value' => 1]
                );

            $value = (int)$sequence->next_value;
            $sequence->increment('next_value');

            // Original pattern logic assumes #### placeholder for padded digits.
            $width = substr_count($pattern, '#');
            $paddedValue = str_pad($value, $width, '0', STR_PAD_LEFT);

            // Use regex replacement matching the literal #### if necessary, or follow original simplified string replace pattern
            $number = str_replace('####', $paddedValue, $pattern); 
            $number = str_replace('YYYY', date('Y'), $number); // Year handling (though usually redundant if we use today's context)

            return $number;
        });
    }
}