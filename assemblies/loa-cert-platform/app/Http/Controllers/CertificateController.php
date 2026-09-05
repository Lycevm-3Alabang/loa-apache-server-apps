<?php

namespace App\Http\Controllers;

use App\Mail\CertificateEmail;
use App\Models\Certificate;
use App\Models\CertificateEmail as CertificateEmailModel;
use App\Models\CertificateSequence;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Services\AuditLogger;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
    new OA\Property(property: "issued", type: "integer"),
    new OA\Property(property: "emailed", type: "integer"),
    new OA\Property(property: "results", type: "array", items: new OA\Items(properties: [
        new OA\Property(property: "name", type: "string"),
        new OA\Property(property: "email", type: "string"),
        new OA\Property(property: "success", type: "boolean"),
        new OA\Property(property: "emailed", type: "boolean"),
        new OA\Property(property: "certNumber", type: "string"),
        new OA\Property(property: "error", type: "string", nullable: true),
    ])),
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
        private readonly AuditLogger $auditLogger,
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

        $certificateNumber = $this->generateCertificateNumber($organizationId, $eventId ? ($event->certificate_number_pattern ?? 'CERT-####') : 'CERT-####');

        $certificate = Certificate::create([
            'organization_id' => $organizationId,
            'event_id' => $eventId,
            'template_id' => $templateId,
            'recipient_name' => $request->input('recipient_name'),
            'recipient_email' => $request->input('recipient_email'),
            'certificate_number' => $certificateNumber,
            'expires_at' => $request->input('expires_at') ?? ($eventId ? ($event->valid_until ?? null) : null),
            'metadata' => $request->input('metadata'),
        ]);

        if ($eventId) {
            $attendee = EventAttendee::firstOrCreate(
                ['event_id' => $eventId, 'email' => $request->input('recipient_email')],
                ['name' => $request->input('recipient_name'), 'organization_id' => $organizationId]
            );
            $attendee->update(['certificate_id' => $certificate->id, 'certificate_number' => $certificateNumber]);
        }

        try {
            $this->pdfService->generateCertificatePdf($certificate->fresh(['event', 'template', 'organization']));
        } catch (\Exception $e) {
            // PDF generation failure is non-fatal; certificate is still created
        }

        $this->auditLogger->record('certificate.issued', 'api', 'certificate', $certificate->id, [
            'certificate_number' => $certificateNumber,
            'event_id' => $eventId,
            'recipient_email' => $request->input('recipient_email'),
            'channel' => 'single',
        ]);

        $emailSent = false;
        if ($request->boolean('send_email')) {
            try {
                $certificate->load(['event', 'template', 'organization']);
                $pdfPath = $certificate->file_path;

                $appUrl = config('app.url');
                $downloadUrl = $appUrl ? $appUrl . '/api/v1/certificates/' . $certificate->id . '/download' : null;
                $verifyUrl = $appUrl ? $appUrl . '/api/v1/verify/' . $certificate->certificate_number : null;

                Mail::to($certificate->recipient_email)->queue(new CertificateEmail(
                    recipientName: $certificate->recipient_name,
                    recipientEmail: $certificate->recipient_email,
                    certificateNumber: $certificate->certificate_number,
                    eventName: $certificate->event?->name,
                    issuedDate: $certificate->issued_at?->format('F d, Y') ?? now()->format('F d, Y'),
                    pdfPath: $pdfPath,
                    downloadUrl: $downloadUrl,
                    verifyUrl: $verifyUrl,
                ));

                CertificateEmailModel::create([
                    'certificate_id' => $certificate->id,
                    'sent_to' => $certificate->recipient_email,
                    'subject' => 'Your Certificate: ' . $certificate->certificate_number,
                    'sent_at' => now(),
                    'sent_by' => auth()->id(),
                    'status' => 'sent',
                ]);

                $emailSent = true;
            } catch (\Exception $e) {
                CertificateEmailModel::create([
                    'certificate_id' => $certificate->id,
                    'sent_to' => $certificate->recipient_email,
                    'subject' => 'Your Certificate: ' . $certificateNumber,
                    'sent_at' => now(),
                    'sent_by' => auth()->id(),
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        $formatted = $this->formatCertificate($certificate->fresh(['event', 'template']));
        $formatted['email_sent'] = $emailSent;

        return response()->json([
            'data' => $formatted,
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
                new OA\Property(property: "data", type: "object", properties: [
                    new OA\Property(property: "issued", type: "integer"),
                    new OA\Property(property: "emailed", type: "integer"),
                    new OA\Property(property: "results", type: "array", items: new OA\Items(properties: [
                        new OA\Property(property: "name", type: "string"),
                        new OA\Property(property: "email", type: "string"),
                        new OA\Property(property: "success", type: "boolean"),
                        new OA\Property(property: "emailed", type: "boolean"),
                        new OA\Property(property: "certNumber", type: "string"),
                        new OA\Property(property: "error", type: "string", nullable: true),
                    ])),
                ]),
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
        $sendEmail = (bool) $request->input('send_email', false);
        $issued = 0;
        $emailed = 0;
        $results = [];

        foreach ($request->input('recipients') as $index => $recipient) {
            $success = false;
            $certificateNumber = null;
            $error = null;
            $emailSent = false;

            try {
                $existing = Certificate::where('event_id', $event->id)
                    ->where('recipient_email', $recipient['email'])
                    ->whereNull('revoked_at')
                    ->exists();

                if ($existing) {
                    $error = 'Active certificate already exists';
                } else {
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

                    $this->auditLogger->record('certificate.issued', 'api', 'certificate', $certificate->id, [
                        'certificate_number' => $certificateNumber,
                        'event_id' => $event->id,
                        'recipient_email' => $recipient['email'],
                        'channel' => 'bulk',
                    ]);

                    try {
                        $this->pdfService->generateCertificatePdf($certificate->fresh(['event', 'template', 'organization']));
                    } catch (\Exception $e) {
                        // PDF generation failure is non-fatal; certificate is still created
                    }

                    $success = true;
                    $issued++;
                }

                if ($success && $sendEmail) {
                    try {
                        $certificate = Certificate::where('event_id', $event->id)
                            ->where('recipient_email', $recipient['email'])
                            ->whereNull('revoked_at')
                            ->first();

                        if ($certificate) {
                            $certificate->load(['event', 'template', 'organization']);
                            $pdfPath = $certificate->file_path;

                            $appUrl = config('app.url');
                            $downloadUrl = $appUrl ? $appUrl . '/api/v1/certificates/' . $certificate->id . '/download' : null;
                            $verifyUrl = $appUrl ? $appUrl . '/api/v1/verify/' . $certificate->certificate_number : null;

                            Mail::to($recipient['email'])->queue(new CertificateEmail(
                                recipientName: $recipient['name'],
                                recipientEmail: $recipient['email'],
                                certificateNumber: $certificate->certificate_number,
                                eventName: $certificate->event?->name,
                                issuedDate: $certificate->issued_at?->format('F d, Y') ?? now()->format('F d, Y'),
                                pdfPath: $pdfPath,
                                downloadUrl: $downloadUrl,
                                verifyUrl: $verifyUrl,
                            ));

                            CertificateEmailModel::create([
                                'certificate_id' => $certificate->id,
                                'sent_to' => $recipient['email'],
                                'subject' => 'Your Certificate: ' . $certificate->certificate_number,
                                'sent_at' => now(),
                                'sent_by' => auth()->id(),
                                'status' => 'sent',
                            ]);

                            $emailSent = true;
                            $emailed++;
                        }
                    } catch (\Exception $e) {
                        if (isset($certificate)) {
                            CertificateEmailModel::create([
                                'certificate_id' => $certificate->id,
                                'sent_to' => $recipient['email'],
                                'subject' => 'Your Certificate: ' . ($certificateNumber ?? ''),
                                'sent_at' => now(),
                                'sent_by' => auth()->id(),
                                'status' => 'failed',
                                'error_message' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }

            $results[] = [
                'name' => $recipient['name'],
                'email' => $recipient['email'],
                'success' => $success,
                'emailed' => $emailSent,
                'certNumber' => $certificateNumber,
                'error' => $error,
            ];
        }

        return response()->json([
            'data' => [
                'issued' => $issued,
                'emailed' => $emailed,
                'results' => $results,
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

        $this->auditLogger->record('certificate.uploaded', 'api', 'certificate', $certificate->id, [
            'certificate_number' => $certificate->certificate_number,
        ]);

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

        $this->auditLogger->record('certificate.revoked', 'api', 'certificate', $certificate->id, [
            'certificate_number' => $certificate->certificate_number,
            'reason' => $request->input('reason'),
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

        $this->auditLogger->record('certificate.deleted', 'api', 'certificate', $certificate->id, [
            'certificate_number' => $certificate->certificate_number,
        ]);

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

            $this->auditLogger->record('certificate.revoked', 'api', 'certificate', $certificate->id, [
                'certificate_number' => $certificate->certificate_number,
                'reason' => $request->input('reason'),
                'channel' => 'reissue',
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

            $this->auditLogger->record('certificate.reissued', 'api', 'certificate', $newCertificate->id, [
                'certificate_number' => $certificateNumber,
                'previous_certificate_id' => $certificate->id,
                'recipient_email' => $newCertificate->recipient_email,
            ]);

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

        $query = CertificateEmailModel::where('certificate_id', $id);
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
        path: "/api/v1/certificates/{id}/email",
        summary: "Send certificate email to recipient",
        tags: ["Certificates"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(properties: [
                new OA\Property(property: "data", type: "object", properties: [
                    new OA\Property(property: "sent", type: "boolean"),
                ]),
            ])),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Certificate not found"),
            new OA\Response(response: 500, description: "Failed to send email"),
        ]
    )]
    public function email(Request $request, string $id): JsonResponse
    {
        $certificate = Certificate::with(['event', 'template', 'organization'])->find($id);

        if (!$certificate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate not found.',
            ], 404);
        }

        if ($certificate->revoked_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot send email for revoked certificate.',
            ], 422);
        }

        try {
            $pdfPath = $certificate->file_path;

            $appUrl = config('app.url');
            $downloadUrl = $appUrl ? $appUrl . '/api/v1/certificates/' . $certificate->id . '/download' : null;
            $verifyUrl = $appUrl ? $appUrl . '/api/v1/verify/' . $certificate->certificate_number : null;

            Mail::to($certificate->recipient_email)->queue(new CertificateEmail(
                recipientName: $certificate->recipient_name,
                recipientEmail: $certificate->recipient_email,
                certificateNumber: $certificate->certificate_number,
                eventName: $certificate->event?->name,
                issuedDate: $certificate->issued_at?->format('F d, Y') ?? now()->format('F d, Y'),
                pdfPath: $pdfPath,
                downloadUrl: $downloadUrl,
                verifyUrl: $verifyUrl,
            ));

            CertificateEmailModel::create([
                'certificate_id' => $certificate->id,
                'sent_to' => $certificate->recipient_email,
                'subject' => 'Your Certificate: ' . $certificate->certificate_number,
                'sent_at' => now(),
                'sent_by' => auth()->id(),
                'status' => 'sent',
            ]);

            return response()->json([
                'data' => [
                    'sent' => true,
                ],
            ]);
        } catch (\Exception $e) {
            CertificateEmailModel::create([
                'certificate_id' => $certificate->id,
                'sent_to' => $certificate->recipient_email,
                'subject' => 'Your Certificate: ' . $certificate->certificate_number,
                'sent_at' => now(),
                'sent_by' => auth()->id(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send email: ' . $e->getMessage(),
            ], 500);
        }
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

        $revokedCount = $expiredQuery->count();

        if (!$dryRun) {
            $expiredQuery->update([
                'revoked_at' => now(),
                'revoke_reason' => 'Auto-expired',
            ]);

            if ($revokedCount > 0) {
                $this->auditLogger->record('certificate.expired', 'api', 'certificate', null, [
                    'revoked' => $revokedCount,
                    'channel' => 'auto_expire',
                ]);
            }
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

            CertificateSequence::where('organization_id', $organizationId)
                ->where('pattern', $pattern)
                ->update(['next_value' => $value + 1]);

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
