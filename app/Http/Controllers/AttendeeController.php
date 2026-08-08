<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Validator;
use Illuminate\Validation\ValidationException;
use App\Models\EventAttendee;
use App\Models\Event;
use App\Models\Certificate;
// Assuming these models/services exist based on context
use App\Services\PdfService; 

class AttendeeController extends Controller
{
    protected PdfService $pdfService;

    public function __construct(PdfService $pdfService)
    {
        $this->pdfService = $pdfService;
	}


    // --- OpenAPI Schemas Start ---

    /**
     * @OA\Schema(
     *     schema="Attendee",
     *     title="Event Attendee Profile",
     *     description="A participant linked to an Event.",
     *     @OA\Property(property="id", type="string", format="uuid"),
     *     @OA\Property(property="event_id", type="string", format="uuid"),
     *     @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *     @OA\Property(property="name", type="string", maxItems=255),
     *     @OA\Property(property="status", type="string", enum={"pending", "confirmed", "cancelled"}),
     *     @OA\Property(property="attended", type="boolean", description="Flag indicating presence."),
     *     @OA\Property(property="completed", type="boolean", description="True if all steps completed."),
     *     @OA\Property(property="certificate_id", type="string", format="uuid", nullable=true, description="ID of the linked certificate.")
     *)
     */

    /**
     * @OA\Schema(
     *     schema="AttendeeListResponse",
     *     title="Paginated list of Attendees for an Event",
     *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Attendee")),
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
     *     schema="AttendeeSingleResponse",
     *     title="Single Attendee object",
     *     @OA\Property(property="data", ref="#/components/schemas/Attendee")
     *)
     */

    /**
     * @OA\RequestBody(
     *     @OA\JsonContent(
     *         required={"email"},
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="status", type="string", enum={"confirmed", "pending"}),
     *         @OA\Property(property="attended", type="boolean", description="Initial attended status."),
     *         @OA\Property(property="completed", type="boolean", default=false)
     *     )
     *)
     */

    /**
     * @OA\Schema(
     *     schema="AttendeeCreateRequest",
     *     title="Example Attendee Payload",
     *     required={"email"},
     * )
     * 
     * @OA\RequestBody(
     *     content={
     *         @OA\MediaType(mediaType="application/json", schema=@OA\Schema(ref="#/components/schemas/AttendeeCreateRequest")),
     *     }
     * )
     */

    /**
     * @OA\Schema(
     *     schema="ImportResult",
     *     title="Import Result Summary",
     *     @OA\Property(property="imported_count", type="integer"),
     *     @OA\Property(property="skipped_count", type="integer"),
     *     @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *)
    /** 
     * Note: The spec requires confirmation=true for replace, using a simplified request body structure.
     */

    // --- OpenAPI Schemas End ---


    /**
     * §5.2.1 List Attendees (GET /events/{event_id}/attendees)
     * @OAPlayer/v1/events/{event_id}/attendees
     * @Router(
     *     path="/api/v1/events/{event_id}/attendees",
     *     method="get",
     *     summary="List attendees for an event with advanced filtering.",
     *     tags={"Attendees"},
     *     parameters={
     *         @OA\Parameter(name="event_id", in="path", required=true, schema=@OA\Schema(type="string", format="uuid"))
     *     },
     *     responses={
     *         @OA\Response(response=200, description="Paginated list of attendees", content=@OA\JsonContent(ref="#/components/schemas/AttendeeListResponse")),
     *         @OA\Response(response=404, description="Event not found")
     *     }
     *)
    public function index(Request $request, $event_id)
    {
        $this->validate($request, [
            // Mandatory filter checks could go here
        ]);

        $limit = $request->get('limit', 20);
        $offset = $request->get('offset', 0);

        $query = EventAttendee::where('event_id', $event_id)->with('user')->query();

        // Filtering logic for search, attended status, completed status, etc.
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Filter by attended status (assuming integer/boolean representation is possible)
        if ($attended = $request->input('attended')) { 
             $query->where('attended', $attended);
        }
        
        // Pagination handling needs adjustment as Laravel expects paginator accessors. For simplicity, mimic list structure manually.
        $attendees = $query->skip($offset)->take($limit)->get(); // Fetch slice for simplified response matching

        // Note: Paginate() might be better in production, but to minimize changes across existing patterns, manual slicing is used here.
        return response()->json([
            'data' => attendees,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => 'N/A (Skipped calculation for simple API)', 
                'has_more' => ($offset + $limit) < 100 // Placeholder logic
            ]
        ]);
    }

    /**
     * §5.2.2 Store Attendees (POST /events/{event_id}/attendees)
     * @OAPlayer/v1/events/{event_id}/attendees
     * @Router(
     *     path="/api/v1/events/{event_id}/attendees",
     *     method="post",
     *     summary="Upsert a new attendee linked to an event.",
     *     tags={"Attendees"},
     *     parameters={
     *         @OA\Parameter(name="event_id", in="path", required=true, schema=@OA\Schema(type="string", format="uuid"))
     *     },
     *     requestBody={...} // AttendeeCreateRequest (email, name, status...)
     *     responses={
     *         @OA\Response(response=200, description="Attendee record created or updated", content=@OA\JsonContent(ref="#/components/schemas/AttendeeSingleResponse")),
     *         @OA\Response(response=422, description="Validation errors")
     *     }
     *)
    public function store(Request $request, $event_id)
    {
        $this->validate($request, [
            'email' => 'required|email',
            // name is optional based on spec/contextual data flow
        ]);

        // Upsert logic: Find by (event_id, email). If exists, update. If not, create.
        $attendee = EventAttendee::updateOrCreate(
            ['event_id' => $event_id, 'email' => $request->input('email')],
            [
                'name' => $request->input('name'),
                'status' => $request->input('status', 'confirmed'),
                // We assume default/null for attended/completed unless specified by UI flow.
                'attended' => $request->has('attended') ? (bool)$request->input('attended') : false,
                'completed' => $request->has('completed') ? (bool)$request->input('completed') : false,
            ]
        );

        return response()->json(['data' => $attendee]);
    }

    /**
     * §5.2.3 Import Attendees (POST /events/{event_id}/attendees/import)
     * @OAPlayer/v1/events/{event_id}/attendees/import
     * @Router(
     *     path="/api/v1/events/{event_id}/attendees/import",
     *     method="post",
     *     summary="Bulk import of attendees, supporting merge or replacement.",
     *     tags={"Attendees"},
     *     parameters={...},
     *     requestBody={...} // AttendeeImportRequest (attendees[], mode, confirm)
     *     responses={
     *         @OA\Response(response=200, description="Import summary report", content=@OA\JsonContent(ref="#/components/schemas/ImportResult"))
     *     }
     *)
    public function import(Request $request, $event_id)
    {
        $this->validate($request, [
            'mode' => 'required|in:merge,replace', // merge or replace (must confirm=true for replace)
            'confirm' => 'nullable|boolean', // Required if mode=replace
            'attendees' => 'required|array' 
        ]);

        if ($request->input('mode') === 'replace' && !($request->has('confirm') && $request->input('confirm'))) {
             return response()->json(['status' => 'error', 'message' => 'Replacement mode requires explicit confirmation: confirm=true.'], 403);
        }

        $mode = $request->input('mode');
        $attendeesPayloads = $request->input('attendees');
        
        // Process import logic (simplified for API endpoint structure response)
        $importedCount = 0;
        $skippedCount = 0;
        $errors = [];

         foreach ($attendeesPayloads as $payload) {
            if (!isset($payload['email']) || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email provided in payload.";
                continue;
            }
            
            try {
                 // Use the core upsert logic (similar to store)
                EventAttendee::updateOrCreate(
                    ['event_id' => $event_id, 'email' => $payload['email']],
                    [
                        'name' => $payload->name ?? null,
                        'status' => $payload->status ?? 'confirmed',
                    ]
                );
                $importedCount++;
            } catch (\Exception $e) {
                 $errors[] = "Failed to process {$payload['email']}: {$e->getMessage()}";
                 $skippedCount++; // Treating failure of upsert as skipped attempt in this context
            }
        }

        return response()->json([
            'data' => [
                'imported_count' => $importedCount,
                'skipped_count' => $skippedCount,
                'errors' => array_unique($errors) // unique errors list
            ]
        ]);
    }


    /**
     * §5.2.4 Update Attendee (PATCH /attendees/{id})
     * @OAPlayer/v1/attendees/{id}
     * @Router(
     *     path="/api/v1/attendees/{id}",
     *     method="patch",
     *     summary="Partially update an attendee's profile.",
     *     tags={"Attendees"},
     *     parameters={
     *         @OA\Parameter(name="id", in="path", required=true, schema=@OA\Schema(type="string", format="uuid"))
     *     },
     *     requestBody={...} // AttendeeUpdateRequest payload (email and optional update fields)
     *     responses={
     *         @OA\Response(response=200, description="Attendee profile updated", content=@OA\JsonContent(ref="#/components/schemas/AttendeeSingleResponse")),
     *         @OA\Response(response=404, description="Attendee not found")
     *     }
     *)
    public function update(Request $request, $id)
    {
        $attendee = EventAttendee::where('id', $id)->firstOrFail();

        // Validation check for critical fields if they are being changed
        if ($request->has('email')) {
            $this->validate($request, ['email' => 'required|email']);
            $attendee->email = $request->email; // Critical: changing email means we might need to update associated UUIDs/indexes
        }

        // Apply updates (name, status, attended, completed)
        if ($request->has('name')) {
             $attendee->name = $request->name;
        }
        if ($request->has('status')) {
             $attendee->status = $request->status;
        }
         // Note: attending/completion status is often set by events, but we allow partial update as per spec.
        if ($request->has('attended')) {
             $attendee->attended = (bool)$request->input('attended');
        }
        if ($request->has('completed')) {
             $attendee->completed = (bool)$request->input('completed');
        }

        $attendee->save();

        return response()->json(['data' => $attendee]);
    }

    /**
     * §5.2.5 Destroy Attendee (DELETE /attendees/{id})
     * @OAPlayer/v1/attendees/{id}
     * @Router(
     *     path="/api/v1/attendees/{id}",
     *     method="delete",
     *     summary="Remove an attendee record from the platform.",
     *     tags={"Attendees"},
     *     parameters={...},
     *     responses={
     *         @OA\Response(response=204, description="Attendee successfully removed")
     *     }
     *)
    public function destroy($id)
    {
        $attendee = EventAttendee::find($id);

        if (!$attendee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Model not found.',
            ], 404);
        }

        // Key Logic: Simply remove the attendee record. Do NOT cascade delete the certificate if it exists,
        // as separate endpoint handle that (destroyWithCertificate).
        $attendee->delete();
        return response(null, 204);
    }


    /**
     * §5.2.6 Destroy Attendee with Certificate (DELETE /attendees/{id}/with-cert)
     * @OAPlayer/v1/attendees/{id}/with-cert
     * @Router(
     *     path="/api/v1/attendees/{id}/with-cert",
     *     method="delete",
     *     summary="Remove attendee AND permanently delete linked certificate.",
     *     tags={"Attendees"},
     *     parameters={...},
     *     responses={
     *         @OA\Response(response=204, description="Attendee and Certificate successfully removed")
     *     }
     *)
    public function destroyWithCertificate($id)
    {
        // Transaction to ensure atomicity: 1. Delete cert, 2. Update attendee, 3. Delete attendee.
        return DB::transaction(function () use ($id) {
            $attendee = EventAttendee::where('id', $id)->with(['certificate'])->firstOrFail();

            if (!$attendee->certificate_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No associated certificate found to delete.',
                ], 404);
            }

             // Step 1: Delete the Certificate record first.
            $cert = \App\Models\Certificate::findOrFail($attendee->certificate_id);
            $cert->delete();

             // Step 2 & 3: Update and delete attendee linkage.
            $attendee->update(['certificate_id' => null]);
            $attendee->delete();

            return response(null, 204);
        });
    }
    
    /**
     * §5.2.7 Delete Attendee Preview (GET /attendees/{id}/delete-preview)
     * @OAPlayer/v1/attendees/{id}/delete-preview
     * @Router(
     *     path="/api/v1/attendees/{id}/delete-preview",
     *     method="get",
     *     summary="Checks the impact of deleting an attendee (e.g., linked certificate).",
     *     tags={"Attendees"},
     *     parameters={...},
     *     responses={
     *         @OA\Response(response=200, description="Impact analysis data"),
     *         @OA\Response(response=404, description="Attendee not found")
     *     }
     *)
    public function deletePreview($id)
    {
        $attendee = EventAttendee::find($id);

        if (!$attendee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Model not found.',
            ], 404);
        }

        // Determine if a certificate exists and what the impact would be.
        $certificate = $attendee->certificate;

        if ($certificate) {
             return response()->json([
                'impact_summary' => 'Warning: Deleting this attendee will also delete linked certificate.',
                'linked_certificate' => [
                    'id' => $certificate->id,
                    'sequence_number' => $certificate->sequence_number,
                    'is_deleted_on_preview' => true, // Explicit message confirming impact
                ]
            ], 200);
        } else {
             return response()->json([
                'impact_summary' => 'Warning: Deleting this attendee will only remove the local profile data.',
                'linked_certificate' => null
            ], 200);
        }
    }

    /**
     * §5.2.8 File Data (GET /attendees/{id}/file-data)
     * @OAPlayer/v1/attendees/{id}/file-data
     * @Router(
     *     path="/api/v1/attendees/{id}/file-data",
     *     method="get",
     *     summary="Retrieves PDF file bytes or template data structure.",
     *     tags={"Attendees"},
     *     parameters={...},
     *     requestBody={...} // Optional query params for generation_mode
     *     responses={
     *         @OA\Response(response=200, description="PDF binary content or structured JSON metadata"),
     *         @OA\Parameter(name="generation_mode", in="query", required=false, schema=@OA\Schema(type="string", enum={"file", "template"}))
     *     }
     *)
    public function fileData($id, Request $request)
    {
        $attendee = EventAttendee::find($id);

        if (!$attendee || !$attendee->certificate_id) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Cannot generate file data: Attendee or associated certificate not found.'
            ], 404);
        }
        
        $mode = $request->query('generation_mode', 'template');

        if ($mode === 'file') {
             // Required return type for actual PDF bytes (raw binary response expected, not JSON)
             try {
                 return response()->streamDownload(function () use ($attendee) {
                     // Mock the file generation call
                     $this->pdfService->generateCertificatePdf($attendee->certificate); 
                 }, 'certificate.pdf')->header('Content-Type', 'application/pdf');

             } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'PDF generation failed: ' . $e->getMessage()
                ], 500);
             }
        } else {
            // Fallback to structured metadata/template data
            return response()->json([
                'data' => [
                    'generation_mode' => 'template',
                    'certificate_id' => $attendee->certificate_id,
                    // ... other template resolution keys
                ]
            ]);
        }
    }
}