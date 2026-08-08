<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\CertificateEmail;
use App\Models\CertificateSequence;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Certificates", description: "Certificate management and issuance")]
#[OA\Schema(schema: "Certificate", properties: [
    new OA\Property(property: "id", type: "string", format: "uuid"),
    new OA\Property(property: "certificate_number", type: "string"),
    new OA\Property(property: "recipient_name", type: "string"),
    new OA\Property(property: "recipient_email", type: "string", format: "email"),
    new OA\Property(property: "issued_at", type: "string", format: "date-time"),
    new OA\Property(property: "expires_at", type: "string", format: "date-time", nullable: true),
    new OA\Property(property: "revoked_at", type: "string", format: "date-time", nullable: true),
    new OA\Property(property: "revoke_reason", type: "string", nullable: true),
    new OA\Property(property: "status", type: "string", enum: ["active", "revoked", "expired"]),
    new OA\Property(property: "event_id", type: "string", format: "uuid", nullable: true),
    new OA\Property(property: "template_id", type: "string", format: "uuid", nullable: true),
    new OA\Property(property: "file_path", type: "string", nullable: true),
    new OA\Property(property: "created_at", type: "string", format: "date-time"),
])]
#[OA\Schema(schema: "CertificateListResponse", properties: [
    new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Certificate")),
    new OA\Property(property: "meta", type: "object", properties: [
        new OA\Property(property: "limit", type: "integer"),
        new OA\Property(property: "offset", type: "integer"),
        new OA\Property(property: "total", type: "integer"),
        new OA\Property(property: "has_more", type: "boolean"),
    ]),
])]
#[OA\Schema(schema: "CertificateSingleResponse", properties: [
    new OA\Property(property: "data", ref: "#/components/schemas/Certificate"),
])]
#[OA\Schema(schema: "CertificateIssueRequest", required: ["recipient_name", "recipient_email"], properties: [
    new OA\Property(property: "event_id", type: "string", format: "uuid"),
    new OA\Property(property: "template_id", type: "string", format: "uuid"),
    new OA\Property(property: "recipient_name", type: "string"),
    new OA\Property(property: "recipient_email", type: "string", format: "email"),
    new OA\Property(property: "expires_at", type: "string", format: "date-time"),
    new OA\Property(property: "send_email", type: "boolean", default: false),
])]
#[OA\Schema(schema: "CertificateBulkRequest", required: ["event_id", "recipients"], properties: [
    new OA\Property(property: "event_id", type: "string", format: "uuid"),
    new OA\Property(property: "recipients", type: "array", items: new OA\Items(properties: [
        new OA\Property(property: "name", type: "string"),
        new OA\Property(property: "email", type: "string", format: "email"),
    ])),
    new OA\Property(property: "send_email", type: "boolean", default: false),
])]
#[OA\Schema(schema: "BulkResult", properties: [
    new OA\Property(property: "success", type: "integer"),
    new OA\Property(property: "failed", type: "integer"),
    new OA\Property(property: "errors", type: "array", items: new OA\Items(properties: [
        new OA\Property(property: "index", type: "integer"),
        new OA\Property(property: "email", type: "string"),
        new OA\Property(property: "reason", type: "string"),
    ])),
    new OA\Property(property: "certificates", type: "array", items: new OA\Items(type: "string", format: "uuid")),
])]
#[OA\Schema(schema: "RevokeRequest", required: ["reason"], properties: [
    new OA\Property(property: "reason", type: "string"),
])]
#[OA\Schema(schema: "ReissueRequest", required: ["reason"], properties: [
    new OA\Property(property: "reason", type: "string"),
])]
#[OA\Schema(schema: "ExpireResponse", properties: [
    new OA\Property(property: "revoked", type: "integer"),
    new OA\Property(property: "expiring_count", type: "integer"),
    new OA\Property(property: "error", type: "string", nullable: true),
])]
class CertificateController extends Controller
{
    public function __construct(
        private readonly PdfService $pdfService,
    ) {
    }

    #[OA\Get(
        path: "/api/v1/certificates",
        summary: "List certificates",
        tags: ["Certificates"],
        parameters: [
            new OA\Parameter(name: "event_id", in: "query", schema: new OA\Schema(type: "string", format: "uuid")),
            new OA\Parameter(name: "recipient_email", in: "query", schema: new OA\Schema(type: "string", format: "email")),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["active", "revoked", "expired"])),
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "from", in: "query", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "to", in: "query", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer", default: 25)),
            new OA\Parameter(name: "offset", in: "query", schema: new OA\Schema(type: "integer", default: 0)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/CertificateListResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Certificate::with(['event', 'template']);

        if ($eventId = $request->query('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($email = $request->query('recipient_email')) {
            $query->where('recipient_email', $email);
        }

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

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('recipient_name', 'like', "%{$search}%")
                  ->orWhere('certificate_number', 'like', "%{$search}%");
            });
        }

        if ($from = $request->query('from')) {
            $query->where('issued_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('issued_at', '<=', $to);
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

    #[OA\Post(
        path: "/api/v1/certificates",
        summary: "Issue a certificate",
        tags: ["Certificates"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/CertificateIssueRequest")
        ),
        responses: [
            new OA\Response(response: 201, description: "Created", content: new OA\JsonContent(ref: "#/components/schemas/CertificateSingleResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Event or template not found"),
            new OA\Response(response: 409, description: "Active certificate already exists"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'nullable|uuid|exists:events,id',
            'template_id' => 'nullable|uuid|exists:certificate_templates,id',
            'recipient_name' => 'required|string',
            'recipient_email' => 'required|email',
            'expires_at' => 'nullable|date',
            'send_email' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $organizationId = $this->resolveOrganizationId();
        $eventId = $request->input('event_id');
        $templateId = $request->input('template_id');

        if ($eventId) {
            $event = Event::find($eventId);
            if (!$event) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Event not found.',
                ], 404);
            }

            $templateId = $templateId ?: $event->template_id;

            if (!$templateId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Template is required when event has no default template.',
                ], 422);
            }
        }

        if ($templateId) {
            $template = \App\Models\CertificateTemplate::find($templateId);
            if (!$template) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Template not found.',
                ], 404);
            }
        }

        if ($eventId && $request->input('recipient_email')) {
            $existing = Certificate::where('event_id', $eventId)
                ->where('recipient_email', $request->input('recipient_email'))
                ->whereNull('revoked_at')
                ->exists();

            if ($existing) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'An active certificate already exists for this event and email.',
                ], 409);
            }
        }

        $certificateNumber = $this->generateCertificateNumber($organizationId, $event->certificate_number_pattern ?? 'CERT-####');

        $certificate = Certificate::create([
            'organization_id' => $organizationId,
            'event_id' => $eventId,
            'template_id' => $templateId,
            'recipient_name' => $request->input('recipient_name'),
            'recipient_email' => $request->input('recipient_email'),
            'certificate_number' => $certificateNumber,
            'expires_at' => $request->input('expires_at') ?? ($event->valid_until ?? null),
            'metadata' => $request->input('metadata'),
        ]);

        if ($eventId) {
            $attendee = EventAttendee::firstOrCreate(
                ['event_id' => $eventId, 'email' => $request->input('recipient_email')],
                ['name' => $request->input('recipient_name')]
            );
            $attendee->update(['certificate_id' => $certificate->id, 'certificate_number' => $certificateNumber]);
        }

        try {
            $this->pdfService->generateCertificatePdf($certificate->fresh(['event', 'template', 'organization']));
        } catch (\Exception $e) {
            // PDF generation failure is non-fatal; certificate is still created
        }

        return response()->json([
            'data' => $this->formatCertificate($certificate->fresh(['event', 'template'])),
        ], 201);
    }

    #[OA\Post(
        path: "/api/v1/certificates/bulk",
        summary: "Bulk issue certificates",
        tags: ["Certificates"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/CertificateBulkRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(properties: [
                new OA\Property(property: "data", ref: "#/components/schemas/BulkResult"),
            ])),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function bulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|uuid|exists:events,id',
            'recipients' => 'required|array|min:1',
            'recipients.*.name' => 'required|string',
            'recipients.*.email' => 'required|email',
            'send_email' => 'boolean',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $event = Event::find($request->input('event_id'));
        $success = 0;
        $failed = 0;
        $errors = [];
        $certificateIds = [];

        foreach ($request->input('recipients') as $index => $recipient) {
            try {
                $existing = Certificate::where('event_id', $event->id)
                    ->where('recipient_email', $recipient['email'])
                    ->whereNull('revoked_at')
                    ->exists();

                if ($existing) {
                    $failed++;
                    $errors[] = [
                        'index' => $index,
                        'email' => $recipient['email'],
                        'reason' => 'Active certificate already exists',
                    ];
                    continue;
                }

                $certificateNumber = $this->generateCertificateNumber(
                    $event->organization_id,
                    $event->certificate_number_pattern
                );

                $certificate = Certificate::create([
                    'organization_id' => $event->organization_id,
                    'event_id' => $event->id,
                    'template_id' => $event->template_id,
                    'recipient_name' => $recipient['name'],
                    'recipient_email' => $recipient['email'],
                    'certificate_number' => $certificateNumber,
                    'expires_at' => $event->valid_until,
                ]);

                $attendee = EventAttendee::firstOrCreate(
                    ['event_id' => $event->id, 'email' => $recipient['email']],
                    ['name' => $recipient['name']]
                );
                $attendee->update(['certificate_id' => $certificate->id, 'certificate_number' => $certificateNumber]);

                $success++;
                $certificateIds[] = $certificate->id;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'index' => $index,
                    'email' => $recipient['email'],
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

    #[OA\Post(
        path: "/api/v1/certificates/upload",
        summary: "Upload a certificate PDF",
        tags: ["Certificates"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(mediaType: "multipart/form-data", schema: new OA\Schema(
                required: ["certificate_number", "file"],
                properties: [
                    new OA\Property(property: "certificate_number", type: "string"),
                    new OA\Property(property: "file", type: "string", format: "binary"),
                ]
            ))
        ),
        responses: [
            new OA\Response(response: 200, description: "Success"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Certificate not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'certificate_number' => 'required|string|exists:certificates,certificate_number',
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $certificate = Certificate::where('certificate_number', $request->input('certificate_number'))->first();
        $file = $request->file('file');
        $filePath = 'certificates/' . $certificate->certificate_number . '.pdf';
        $file->storeAs('public', $filePath);

        $certificate->update(['file_path' => $filePath]);

        return response()->json([
            'data' => [
                'certificate_id' => $certificate->id,
                'file_path' => $filePath,
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/v1/certificates/{id}",
        summary: "Get a certificate",
        tags: ["Certificates"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/CertificateSingleResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $certificate = Certificate::with(['event', 'template', 'emails'])->find($id);

        if (!$certificate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate not found.',
            ], 404);
        }

        return response()->json([
            'data' => $this->formatCertificate($certificate),
        ]);
    }

    #[OA\Get(
        path: "/api/v1/certificates/{id}/pdf",
        summary: "Stream certificate PDF",
        tags: ["Certificates"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "PDF binary stream"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
            new OA\Response(response: 410, description: "Certificate revoked or expired"),
        ]
    )]
    public function pdf(string $id)
    {
        $certificate = Certificate::with(['event', 'template', 'organization'])->find($id);

        if (!$certificate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate not found.',
            ], 404);
        }

        if (in_array($certificate->status, ['revoked', 'expired'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate is ' . $certificate->status . '.',
            ], 410);
        }

        try {
            return $this->pdfService->streamCertificatePdf($certificate);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/v1/certificates/{id}/download",
        summary: "Download certificate PDF",
        tags: ["Certificates"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "PDF file download"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
            new OA\Response(response: 410, description: "Certificate revoked or expired"),
        ]
    )]
    public function download(string $id)
    {
        $certificate = Certificate::with(['event', 'template', 'organization'])->find($id);

        if (!$certificate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate not found.',
            ], 404);
        }

        if (in_array($certificate->status, ['revoked', 'expired'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate is ' . $certificate->status . '.',
            ], 410);
        }

        try {
            return $this->pdfService->downloadCertificatePdf($certificate);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: "/api/v1/certificates/{id}/revoke",
        summary: "Revoke a certificate",
        tags: ["Certificates"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/RevokeRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/CertificateSingleResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
            new OA\Response(response: 409, description: "Already revoked"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function revoke(Request $request, string $id): JsonResponse
    {
        $certificate = Certificate::find($id);

        if (!$certificate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate not found.',
            ], 404);
        }

        if ($certificate->revoked_at !== null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate is already revoked.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $certificate->update([
            'revoked_at' => now(),
            'revoke_reason' => $request->input('reason'),
        ]);

        return response()->json([
            'data' => $this->formatCertificate($certificate->fresh(['event', 'template'])),
        ]);
    }

    #[OA\Delete(
        path: "/api/v1/certificates/{id}",
        summary: "Delete a certificate",
        tags: ["Certificates"],
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
        $certificate = Certificate::find($id);

        if (!$certificate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate not found.',
            ], 404);
        }

        $certificate->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: "/api/v1/certificates/{id}/reissue",
        summary: "Reissue a certificate",
        tags: ["Certificates"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/ReissueRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/CertificateSingleResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function reissue(Request $request, string $id): JsonResponse
    {
        $certificate = Certificate::find($id);

        if (!$certificate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        DB::beginTransaction();

        try {
            $certificate->update([
                'revoked_at' => now(),
                'revoke_reason' => $request->input('reason'),
            ]);

            $event = $certificate->event;
            $certificateNumber = $this->generateCertificateNumber(
                $certificate->organization_id,
                $event->certificate_number_pattern ?? 'CERT-####'
            );

            $newCertificate = Certificate::create([
                'organization_id' => $certificate->organization_id,
                'event_id' => $certificate->event_id,
                'template_id' => $certificate->template_id,
                'recipient_name' => $certificate->recipient_name,
                'recipient_email' => $certificate->recipient_email,
                'certificate_number' => $certificateNumber,
                'expires_at' => $certificate->expires_at,
            ]);

            if ($certificate->event_id) {
                EventAttendee::where('certificate_id', $certificate->id)
                    ->update([
                        'certificate_id' => $newCertificate->id,
                        'certificate_number' => $certificateNumber,
                    ]);
            }

            DB::commit();

            return response()->json([
                'data' => $this->formatCertificate($newCertificate->fresh(['event', 'template'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    #[OA\Get(
        path: "/api/v1/certificates/{id}/email-logs",
        summary: "List email logs for a certificate",
        tags: ["Certificates"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer", default: 25)),
            new OA\Parameter(name: "offset", in: "query", schema: new OA\Schema(type: "integer", default: 0)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function emailLogs(string $id): JsonResponse
    {
        $certificate = Certificate::find($id);

        if (!$certificate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate not found.',
            ], 404);
        }

        $limit = min((int) request()->query('limit', 25), 100);
        $offset = (int) request()->query('offset', 0);

        $query = CertificateEmail::where('certificate_id', $id);
        $total = $query->count();
        $emails = $query->orderBy('sent_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (CertificateEmail $email) => [
                'id' => $email->id,
                'sent_to' => $email->sent_to,
                'subject' => $email->subject,
                'sent_at' => $email->sent_at?->toIso8601String(),
                'status' => $email->status,
                'error_message' => $email->error_message,
            ]);

        return response()->json([
            'data' => $emails,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'has_more' => ($offset + $limit) < $total,
            ],
        ]);
    }

    #[OA\Post(
        path: "/api/v1/certificates/expire",
        summary: "Auto-expire certificates",
        tags: ["Certificates"],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: "dry_run", type: "boolean", default: false),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/ExpireResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
        ]
    )]
    public function expire(Request $request): JsonResponse
    {
        $dryRun = $request->input('dry_run', false);

        $expiredQuery = Certificate::whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());

        $revokedCount = $dryRun ? $expiredQuery->count() : 0;

        if (!$dryRun) {
            $expiredQuery->update([
                'revoked_at' => now(),
                'revoke_reason' => 'Auto-expired',
            ]);
            $revokedCount = $expiredQuery->count();
        }

        $expiringCount = Certificate::whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', now())
            ->where('expires_at', '<=', now()->addDays(30))
            ->count();

        return response()->json([
            'data' => [
                'revoked' => $revokedCount,
                'expiring_count' => $expiringCount,
                'error' => null,
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/v1/certificates/qr",
        summary: "Generate QR code for a certificate",
        tags: ["Certificates"],
        parameters: [
            new OA\Parameter(name: "certificate_number", in: "query", required: true, schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
            new OA\Response(response: 422, description: "Missing certificate number"),
        ]
    )]
    public function qr(Request $request): JsonResponse
    {
        $certificateNumber = $request->query('certificate_number');

        if (!$certificateNumber) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate number is required.',
            ], 422);
        }

        $certificate = Certificate::where('certificate_number', $certificateNumber)->first();

        if (!$certificate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'certificate_number' => $certificate->certificate_number,
                'qr_data_url' => 'data:image/png;base64,QR_CODE_NOT_IMPLEMENTED',
            ],
        ]);
    }

    private function generateCertificateNumber(string $organizationId, string $pattern): string
    {
        return DB::transaction(function () use ($organizationId, $pattern) {
            $sequence = CertificateSequence::lockForUpdate()
                ->firstOrCreate(
                    ['organization_id' => $organizationId, 'pattern' => $pattern],
                    ['next_value' => 1]
                );

            $value = $sequence->next_value;
            $sequence->increment('next_value');

            $width = substr_count($pattern, '#');
            $paddedValue = str_pad($value, $width, '0', STR_PAD_LEFT);

            $number = str_replace('####', $paddedValue, $pattern);
            $number = str_replace('YYYY', date('Y'), $number);

            return $number;
        });
    }

    private function formatCertificate(Certificate $certificate): array
    {
        return [
            'id' => $certificate->id,
            'certificate_number' => $certificate->certificate_number,
            'recipient_name' => $certificate->recipient_name,
            'recipient_email' => $certificate->recipient_email,
            'issued_at' => $certificate->issued_at?->toIso8601String(),
            'expires_at' => $certificate->expires_at?->toIso8601String(),
            'revoked_at' => $certificate->revoked_at?->toIso8601String(),
            'revoke_reason' => $certificate->revoke_reason,
            'status' => $certificate->status,
            'event_id' => $certificate->event_id,
            'template_id' => $certificate->template_id,
            'file_path' => $certificate->file_path,
            'created_at' => $certificate->created_at?->toIso8601String(),
        ];
    }

    private function resolveOrganizationId(): string
    {
        return config('cert-platform.organization_id', '00000000-0000-0000-0000-000000000001');
    }
}
