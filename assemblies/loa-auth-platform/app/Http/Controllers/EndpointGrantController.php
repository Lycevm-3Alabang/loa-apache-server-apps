<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantAppEndpoint;
use App\Models\TenantEndpointGrant;
use App\Models\TenantEndpointOverride;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\PermissionPolicyService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EndpointGrantController extends Controller
{
    public function __construct(
        private readonly PermissionPolicyService $policy,
        private readonly TenantService $tenantService,
    ) {
    }

    // ===== Catalog Endpoints =====

    public function catalogIndex(Request $request, string $tenantId): JsonResponse
    {
        $this->authorizeAdmin($request);
        $tenant = $this->getTenant($tenantId);

        $endpoints = TenantAppEndpoint::where(function ($q) use ($tenant) {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
        })->orderBy('method')->orderBy('path')->get();

        return response()->json(['endpoints' => $endpoints]);
    }

    public function catalogStore(Request $request, string $tenantId): JsonResponse
    {
        $this->authorizeAdmin($request);
        $tenant = $this->getTenant($tenantId);

        $validated = $request->validate([
            'method' => 'required|string|max:10',
            'path' => 'required|string|max:512',
            'label' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'required_level' => 'required|in:read,write,admin',
        ]);

        if ($tenant->id !== null && !$this->policy->isPlatformAdmin($request->user()->id)) {
            $tenantId = $tenant->id;
        } else {
            $tenantId = $validated['tenant_id'] ?? null;
        }

        if ($tenantId === null && !$this->policy->isPlatformAdmin($request->user()->id)) {
            return response()->json(['message' => 'Platform admin required for global endpoints'], 403);
        }

        $path = TenantAppEndpoint::normalizePath($validated['path']);
        $method = strtoupper($validated['method']);

        $existing = TenantAppEndpoint::where('tenant_id', $tenantId)
            ->where('method', $method)
            ->where('path', $path)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Endpoint already exists', 'endpoint' => $existing], 409);
        }

        $endpoint = TenantAppEndpoint::create([
            'tenant_id' => $tenantId,
            'method' => $method,
            'path' => $path,
            'label' => $validated['label'] ?? null,
            'description' => $validated['description'] ?? null,
            'required_level' => $validated['required_level'],
        ]);

        return response()->json(['endpoint' => $endpoint], 201);
    }

    public function catalogUpdate(Request $request, string $tenantId): JsonResponse
    {
        $this->authorizeAdmin($request);
        $tenant = $this->getTenant($tenantId);

        $validated = $request->validate([
            'method' => 'required|string|max:10',
            'path' => 'required|string|max:512',
            'label' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'required_level' => 'sometimes|in:read,write,admin',
        ]);

        $path =TenantAppEndpoint::normalizePath($validated['path']);
        $method = strtoupper($validated['method']);

        $endpoint = TenantAppEndpoint::where(function ($q) use ($tenant) {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
        })
            ->where('method', $method)
            ->where('path', $path)
            ->first();

        if (!$endpoint) {
            return response()->json(['message' => 'Endpoint not found'], 404);
        }

        $endpoint->update([
            'label' => $validated['label'] ?? $endpoint->label,
            'description' => $validated['description'] ?? $endpoint->description,
            'required_level' => $validated['required_level'] ?? $endpoint->required_level,
        ]);

        return response()->json(['endpoint' => $endpoint]);
    }

    public function catalogDestroy(Request $request, string $tenantId): JsonResponse
    {
        $this->authorizeAdmin($request);
        $tenant = $this->getTenant($tenantId);

        $validated = $request->validate([
            'method' => 'required|string|max:10',
            'path' => 'required|string|max:512',
        ]);

        $path = TenantAppEndpoint::normalizePath($validated['path']);
        $method = strtoupper($validated['method']);

        $endpoint = TenantAppEndpoint::where(function ($q) use ($tenant) {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
        })
            ->where('method', $method)
            ->where('path', $path)
            ->first();

        if (!$endpoint) {
            return response()->json(['message' => 'Endpoint not found'], 404);
        }

        $force = $request->validate(['force' => 'sometimes|boolean'])['force'] ?? false;

        $hasGrants = TenantEndpointGrant::where('method', $method)
            ->where('path', $path)
            ->where(function ($q) use ($tenant) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
            })->exists();

        $hasOverrides = TenantEndpointOverride::where('method', $method)
            ->where('path', $path)
            ->where(function ($q) use ($tenant) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
            })->exists();

        if (($hasGrants || $hasOverrides) && !$force) {
            return response()->json([
                'message' => 'Endpoint has existing grants or overrides',
                'has_grants' => $hasGrants,
                'has_overrides' => $hasOverrides,
            ], 409);
        }

        $endpoint->delete();

        return response()->json(['message' => 'Endpoint deleted']);
    }

    public function catalogBulk(Request $request, string $tenantId): JsonResponse
    {
        $this->authorizeAdmin($request);
        $tenant = $this->getTenant($tenantId);

        $validated = $request->validate([
            'endpoints' => 'required|array',
            'endpoints.*.method' => 'required|string|max:10',
            'endpoints.*.path' => 'required|string|max:512',
            'endpoints.*.label' => 'nullable|string|max:255',
            'endpoints.*.description' => 'nullable|string',
            'endpoints.*.required_level' => 'required|in:read,write,admin',
            'replace' => 'sometimes|boolean',
        ]);

        $replace = $request->input('replace', false);

        if ($replace) {
            TenantAppEndpoint::where('tenant_id', $tenant->id)->delete();
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($validated, $tenant, &$inserted, &$updated, &$skipped, &$errors) {
            foreach ($validated['endpoints'] as $ep) {
                $path = TenantAppEndpoint::normalizePath($ep['path']);
                $method = strtoupper($ep['method']);

                $existing = TenantAppEndpoint::where('tenant_id', $tenant->id)
                    ->where('method', $method)
                    ->where('path', $path)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'label' => $ep['label'] ?? null,
                        'description' => $ep['description'] ?? null,
                        'required_level' => $ep['required_level'],
                    ]);
                    $updated++;
                } else {
                    TenantAppEndpoint::create([
                        'tenant_id' => $tenant->id,
                        'method' => $method,
                        'path' => $path,
                        'label' => $ep['label'] ?? null,
                        'description' => $ep['description'] ?? null,
                        'required_level' => $ep['required_level'],
                    ]);
                    $inserted++;
                }
            }
        });

        return response()->json([
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    public function catalogValidate(Request $request, string $tenantId): JsonResponse
    {
        $this->authorizeAdmin($request);
        $tenant = $this->getTenant($tenantId);

        $errors = [];
        $warnings = [];

        $catalogEndpoints = TenantAppEndpoint::where(function ($q) use ($tenant) {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
        })->get();

        foreach ($catalogEndpoints as $endpoint) {
            if (!in_array($endpoint->required_level, ['read', 'write', 'admin'], true)) {
                $errors[] = "Invalid required_level '{$endpoint->required_level}' for {$endpoint->method} {$endpoint->path}";
            }

            $hasGrants = TenantEndpointGrant::where('method', $endpoint->method)
                ->where('path', $endpoint->path)
                ->where(function ($q) use ($tenant) {
                    $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
                })->exists();

            if (!$hasGrants) {
                $warnings[] = "Endpoint {$endpoint->method} {$endpoint->path} has no group grants";
            }
        }

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ]);
    }

    // ===== Group Endpoint Grants =====

    public function groupGrantsIndex(string $tenantId, string $groupId): JsonResponse
    {
        $group = UserGroup::findOrFail($groupId);

        $catalogEndpoints = TenantAppEndpoint::where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
        })->orderBy('method')->orderBy('path')->get();

        $grants = TenantEndpointGrant::where('group_id', $group->id)
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            })->get();

        $grantMap = [];
        foreach ($grants as $grant) {
            $key = $grant->method . '|' . $grant->path;
            $grantMap[$key] = $grant->level;
        }

        $result = [];
        foreach ($catalogEndpoints as $endpoint) {
            $key = $endpoint->method . '|' . $endpoint->path;
            $result[] = [
                'method' => $endpoint->method,
                'path' => $endpoint->path,
                'label' => $endpoint->label,
                'required_level' => $endpoint->required_level,
                'granted_level' => $grantMap[$key] ?? null,
            ];
        }

        return response()->json([
            'group_id' => $group->id,
            'group_name' => $group->name,
            'tenant_id' => $group->tenant_id,
            'grants' => $result,
        ]);
    }

    public function groupGrantStore(Request $request, string $tenantId, string $groupId): JsonResponse
    {
        $this->authorizeAdmin($request);

        $group = UserGroup::findOrFail($groupId);

        $validated = $request->validate([
            'method' => 'required|string|max:10',
            'path' => 'required|string|max:512',
            'level' => 'required|in:read,write,admin,deny',
        ]);

        $method = strtoupper($validated['method']);
        $path = TenantAppEndpoint::normalizePath($validated['path']);

        $catalogExists = TenantAppEndpoint::where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
        })->where('method', $method)->where('path', $path)->exists();

        if (!$catalogExists) {
            return response()->json(['message' => 'Endpoint not found in catalog'], 404);
        }

        if ($group->tenant_id !== null && $group->tenant_id !== $tenantId) {
            return response()->json(['message' => 'Group is not scoped to this tenant'], 403);
        }

        if ($group->tenant_id !== null && $group->tenant_id !== $tenantId) {
            return response()->json(['message' => 'Tenant-scoped groups can only be granted on their own tenant endpoints'], 403);
        }

        TenantEndpointGrant::updateOrCreate(
            [
                'group_id' => $group->id,
                'tenant_id' => $tenantId,
                'method' => $method,
                'path' => $path,
            ],
            ['level' => $validated['level']]
        );

        return response()->json([
            'status' => 'success',
            'group_id' => $group->id,
            'method' => $method,
            'path' => $path,
            'level' => $validated['level'],
            'tenant_id' => $tenantId,
        ], 201);
    }

    public function groupGrantDestroy(Request $request, string $tenantId, string $groupId): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'method' => 'required|string|max:10',
            'path' => 'required|string|max:512',
        ]);

        $method = strtoupper($validated['method']);
        $path = TenantAppEndpoint::normalizePath($validated['path']);

        $grant = TenantEndpointGrant::where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('method', $method)
            ->where('path', $path)
            ->first();

        if (!$grant) {
            return response()->json(['message' => 'Grant not found'], 404);
        }

        $grant->delete();

        return response()->json(['message' => 'Grant removed'], 204);
    }

    // ===== User Endpoint Overrides =====

    public function userOverridesIndex(string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        $overrides = TenantEndpointOverride::where('user_id', $user->id)
            ->orderBy('tenant_id')
            ->orderBy('method')
            ->orderBy('path')
            ->get();

        return response()->json([
            'user_id' => $user->id,
            'overrides' => $overrides,
        ]);
    }

    public function userOverrideStore(Request $request, string $userId): JsonResponse
    {
        $this->authorizeAdmin($request);

        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'method' => 'required|string|max:10',
            'path' => 'required|string|max:512',
            'level' => 'required|in:read,write,admin,deny',
            'tenant_id' => 'nullable|exists:tenants,id',
        ]);

        $method = strtoupper($validated['method']);
        $path = TenantAppEndpoint::normalizePath($validated['path']);
        $tenantId = $validated['tenant_id'] ?? null;

        $catalogExists = TenantAppEndpoint::where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
        })->where('method', $method)->where('path', $path)->exists();

        if (!$catalogExists) {
            return response()->json(['message' => 'Endpoint not found in catalog'], 404);
        }

        TenantEndpointOverride::updateOrCreate(
            [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'method' => $method,
                'path' => $path,
            ],
            ['level' => $validated['level']]
        );

        return response()->json([
            'status' => 'success',
            'user_id' => $user->id,
            'method' => $method,
            'path' => $path,
            'level' => $validated['level'],
            'tenant_id' => $tenantId,
        ]);
    }

    public function userOverrideDestroy(Request $request, string $userId): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'method' => 'required|string|max:10',
            'path' => 'required|string|max:512',
        ]);

        $method = strtoupper($validated['method']);
        $path = TenantAppEndpoint::normalizePath($validated['path']);

        $override = TenantEndpointOverride::where('user_id', $userId)
            ->where('method', $method)
            ->where('path', $path)
            ->first();

        if (!$override) {
            return response()->json(['message' => 'Override not found'], 404);
        }

        $override->delete();

        return response()->json(['message' => 'Override removed'], 204);
    }

    // ===== Helpers =====

    private function authorizeAdmin(Request $request): void
    {
        $userId = $request->user()?->id;
        if (!$userId || !$this->policy->isPlatformAdmin($userId)) {
            abort(403, 'Platform admin access required');
        }
    }

    private function getTenant(string $tenantId): Tenant
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            abort(404, 'Tenant not found');
        }

        if (!$tenant->isActive()) {
            abort(403, 'Tenant is suspended');
        }

        return $tenant;
    }
}
