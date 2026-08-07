<?php

namespace App\Http\Controllers\Api;

use App\Models\CertificateTemplate;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class CertificateTemplateController extends Controller
{
    /**
     * Display a listing of the certificate templates.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CertificateTemplate::query();

        // Apply type filter
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Apply search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply pagination
        $limit = min($request->input('limit', 25), 100);
        $offset = $request->input('offset', 0);
        
        $templates = $query->skip($offset)
                          ->take($limit)
                          ->get();

        $total = $query->count();

        // Add lock information to each template 
        foreach ($templates as $template) {
            $template->is_locked = $this->isLocked($template);
            if ($template->is_locked) {
                $template->locked_reason = $this->lockedReason($template);
            }
        }

        return response()->json([
            'data' => $templates,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'has_more' => $total > ($offset + $limit)
            ]
        ]);
    }

    /**
     * Store a newly created certificate template in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:certificate_templates,organization_id,NULL,id,organization_id,' . auth()->user()->tenant->id,
            'type' => 'required|in:certificate,email',
            'html_content' => 'required|string',
            'description' => 'nullable|string',
            'css_content' => 'nullable|string'
        ]);

        $template = CertificateTemplate::create([
            'id' => (string) Str::uuid(),
            'organization_id' => auth()->user()->tenant->id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'type' => $request->input('type'),
            'html_content' => $request->input('html_content'),
            'css_content' => $request->input('css_content', ''),
            'created_by' => auth()->user()->sub,
        ]);

        return response()->json([
            'data' => $template
        ], 201);
    }

    /**
     * Display the specified certificate template.
     */
    public function show(string $id): JsonResponse
    {
        $template = CertificateTemplate::findOrFail($id);
        
        return response()->json([
            'data' => $template
        ]);
    }

    /**
     * Update the specified certificate template in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $template = CertificateTemplate::findOrFail($id);

        // Check if template is locked 
        if ($this->isLocked($template)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template is locked and cannot be updated',
                'errors' => [
                    'name' => ['Template is referenced by events or certificates']
                ]
            ], 409);
        }

        $request->validate([
            'name' => 'nullable|string|unique:certificate_templates,organization_id,NULL,id,organization_id,' . auth()->user()->tenant->id,
            'type' => 'nullable|in:certificate,email',
            'html_content' => 'nullable|string',
            'description' => 'nullable|string',
            'css_content' => 'nullable|string'
        ]);

        // If name is being updated, validate it doesn't duplicate
        if ($request->has('name') && $request->input('name') !== $template->name) {
            $duplicate = CertificateTemplate::where('organization_id', auth()->user()->tenant->id)
                ->where('name', $request->input('name'))
                ->first();
            if ($duplicate) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Name already exists in this organization',
                    'errors' => [
                        'name' => ['Name already exists in this organization']
                    ]
                ], 409);
            }
        }

        $template->update($request->all());

        return response()->json([
            'data' => $template
        ]);
    }

    /**
     * Remove the specified certificate template from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $template = CertificateTemplate::findOrFail($id);

        // Check if template is referenced by certificates (never allow delete)
        if ($template->certificates()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template is referenced by issued certificates and cannot be deleted',
                'errors' => [
                    'name' => ['Template is referenced by issued certificates']
                ]
            ], 409);
        }

        // Check if template is referenced by events (allow delete only with force parameter)
        $isReferencedByEvents = $template->events()->exists();
        
        if ($isReferencedByEvents) {
            // If force parameter is not true, reject the request
            if (!$request->has('force') || !$request->input('force')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Template is referenced by events and cannot be deleted without force=true',
                    'errors' => [
                        'name' => ['Template is referenced by events']
                    ]
                ], 409);
            }
        }

        $template->delete();

        return response()->noContent();
    }

    /**
     * Check if a template is locked (referenced by events or certificates)
     */
    private function isLocked(CertificateTemplate $template): bool
    {
        return $template->events()->exists() || $template->certificates()->exists();
    }

    /**
     * Get the reason why a template is locked
     */
    private function lockedReason(CertificateTemplate $template): string
    {
        if ($template->certificates()->exists()) {
            return 'Referenced by issued certificates';
        }
        
        $event = $template->events()->first();
        if ($event) {
            return 'Referenced by event ' . $event->name;
        }
        
        return 'Unknown reason';
    }
}