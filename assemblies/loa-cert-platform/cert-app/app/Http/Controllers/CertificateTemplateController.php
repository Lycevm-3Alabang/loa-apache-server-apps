<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Templates", description: "Certificate template management")]
#[OA\Schema(schema: "Template", properties: [
    new OA\Property(property: "id", type: "string", format: "uuid"),
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "description", type: "string", nullable: true),
    new OA\Property(property: "type", type: "string", enum: ["certificate", "email"]),
    new OA\Property(property: "html_content", type: "string"),
    new OA\Property(property: "css_content", type: "string", nullable: true),
    new OA\Property(property: "is_locked", type: "boolean"),
    new OA\Property(property: "locked_reason", type: "string", nullable: true),
    new OA\Property(property: "created_at", type: "string", format: "date-time"),
    new OA\Property(property: "updated_at", type: "string", format: "date-time"),
])]
#[OA\Schema(schema: "TemplateListResponse", properties: [
    new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Template")),
    new OA\Property(property: "meta", type: "object", properties: [
        new OA\Property(property: "limit", type: "integer"),
        new OA\Property(property: "offset", type: "integer"),
        new OA\Property(property: "total", type: "integer"),
        new OA\Property(property: "has_more", type: "boolean"),
    ]),
])]
#[OA\Schema(schema: "TemplateSingleResponse", properties: [
    new OA\Property(property: "data", ref: "#/components/schemas/Template"),
])]
#[OA\Schema(schema: "TemplateCreateRequest", required: ["name", "type", "html_content"], properties: [
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "description", type: "string"),
    new OA\Property(property: "type", type: "string", enum: ["certificate", "email"]),
    new OA\Property(property: "html_content", type: "string"),
    new OA\Property(property: "css_content", type: "string"),
])]
#[OA\Schema(schema: "TemplateUpdateRequest", properties: [
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "description", type: "string"),
    new OA\Property(property: "type", type: "string", enum: ["certificate", "email"]),
    new OA\Property(property: "html_content", type: "string"),
    new OA\Property(property: "css_content", type: "string"),
])]
class CertificateTemplateController extends Controller
{
    #[OA\Get(
        path: "/api/v1/templates",
        summary: "List templates",
        tags: ["Templates"],
        parameters: [
            new OA\Parameter(name: "type", in: "query", schema: new OA\Schema(type: "string", enum: ["certificate", "email"])),
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer", default: 25)),
            new OA\Parameter(name: "offset", in: "query", schema: new OA\Schema(type: "integer", default: 0)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/TemplateListResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = CertificateTemplate::query();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $limit = min((int) $request->query('limit', 25), 100);
        $offset = (int) $request->query('offset', 0);

        $total = $query->count();
        $templates = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (CertificateTemplate $template) => $this->formatTemplate($template));

        return response()->json([
            'data' => $templates,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'has_more' => ($offset + $limit) < $total,
            ],
        ]);
    }

    #[OA\Post(
        path: "/api/v1/templates",
        summary: "Create a template",
        tags: ["Templates"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/TemplateCreateRequest")
        ),
        responses: [
            new OA\Response(response: 201, description: "Created", content: new OA\JsonContent(ref: "#/components/schemas/TemplateSingleResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 409, description: "Conflict - name already exists"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:certificate,email',
            'html_content' => 'required|string',
            'css_content' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $organizationId = $this->resolveOrganizationId();

        $existing = CertificateTemplate::where('organization_id', $organizationId)
            ->where('name', $request->input('name'))
            ->exists();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template name already exists for this organization.',
            ], 409);
        }

        $template = CertificateTemplate::create([
            'organization_id' => $organizationId,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'type' => $request->input('type'),
            'html_content' => $request->input('html_content'),
            'css_content' => $request->input('css_content'),
            'created_by' => $request->attributes->get('jwt_claims.sub'),
        ]);

        return response()->json([
            'data' => $this->formatTemplate($template),
        ], 201);
    }

    #[OA\Get(
        path: "/api/v1/templates/{id}",
        summary: "Get a template",
        tags: ["Templates"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/TemplateSingleResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $template = CertificateTemplate::find($id);

        if (!$template) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template not found.',
            ], 404);
        }

        return response()->json([
            'data' => $this->formatTemplate($template),
        ]);
    }

    #[OA\Patch(
        path: "/api/v1/templates/{id}",
        summary: "Update a template",
        tags: ["Templates"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: "#/components/schemas/TemplateUpdateRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/TemplateSingleResponse")),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
            new OA\Response(response: 409, description: "Conflict - locked or name exists"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $template = CertificateTemplate::find($id);

        if (!$template) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template not found.',
            ], 404);
        }

        if ($this->isTemplateLocked($template)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template is locked and cannot be updated.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:certificate,email',
            'html_content' => 'sometimes|string',
            'css_content' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        if ($request->has('name') && $request->input('name') !== $template->name) {
            $existing = CertificateTemplate::where('organization_id', $template->organization_id)
                ->where('name', $request->input('name'))
                ->where('id', '!=', $id)
                ->exists();

            if ($existing) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Template name already exists for this organization.',
                ], 409);
            }
        }

        $template->update($request->only([
            'name',
            'description',
            'type',
            'html_content',
            'css_content',
        ]));

        return response()->json([
            'data' => $this->formatTemplate($template->fresh()),
        ]);
    }

    #[OA\Delete(
        path: "/api/v1/templates/{id}",
        summary: "Delete a template",
        tags: ["Templates"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: "force", type: "boolean", default: false),
            ])
        ),
        responses: [
            new OA\Response(response: 204, description: "Deleted"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Not found"),
            new OA\Response(response: 409, description: "Conflict - locked or in use"),
        ]
    )]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $template = CertificateTemplate::find($id);

        if (!$template) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template not found.',
            ], 404);
        }

        if ($this->isTemplateInUseByCertificate($template)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template is referenced by issued certificates and cannot be deleted.',
            ], 409);
        }

        if ($this->isTemplateLocked($template) && !$request->input('force')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template is referenced by events. Use force=true to delete.',
            ], 409);
        }

        $template->delete();

        return response()->json(null, 204);
    }

    private function isTemplateLocked(CertificateTemplate $template): bool
    {
        return Event::where('template_id', $template->id)
            ->orWhere('email_template_id', $template->id)
            ->exists();
    }

    private function isTemplateInUseByCertificate(CertificateTemplate $template): bool
    {
        return Certificate::where('template_id', $template->id)->exists();
    }

    private function formatTemplate(CertificateTemplate $template): array
    {
        $locked = $this->isTemplateLocked($template);
        $lockedReason = null;

        if ($locked) {
            $referencingEvent = Event::where('template_id', $template->id)
                ->orWhere('email_template_id', $template->id)
                ->first();

            $lockedReason = $referencingEvent
                ? "Referenced by event {$referencingEvent->name}"
                : 'Referenced by an event';
        }

        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'type' => $template->type,
            'html_content' => $template->html_content,
            'css_content' => $template->css_content,
            'is_locked' => $locked,
            'locked_reason' => $lockedReason,
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    private function resolveOrganizationId(): string
    {
        return config('cert-platform.organization_id', '00000000-0000-0000-0000-000000000001');
    }
}
