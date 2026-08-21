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
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessConfigController extends Controller
{
    public function __construct(
        private readonly PermissionPolicyService $policy,
        private readonly TenantService $tenantService,
    ) {
    }

    // ===== Web Routes (Blade) =====

    public function importForm(string $tenant): \Illuminate\View\View
    {
        $admin = \Auth::guard('web')->user();
        $tenantModel = $this->getTenant($tenant);

        return view('admin.tenants.access-config-import', [
            'tenant' => $tenantModel,
        ]);
    }

    // ===== Shared Logic =====

    public function template(string $tenant): StreamedResponse
    {
        $tenantModel = $this->getTenant($tenant);

        $template = [
            'version' => '1.0',
            'groups' => [
                [
                    'name' => 'Faculty',
                    'description' => 'Teaching staff — full access to appointments and certificates',
                    'priority' => 5,
                    '_comment' => 'priority: 1=highest, 100=lowest, default=10. Lower value = higher precedence.',
                    'grants' => [
                        ['method' => 'GET', 'path' => '/api/v1/appointments', 'level' => 'read'],
                        ['method' => 'POST', 'path' => '/api/v1/appointments', 'level' => 'write'],
                        ['method' => 'PUT', 'path' => '/api/v1/appointments/{id}', 'level' => 'write'],
                        ['method' => 'DELETE', 'path' => '/api/v1/appointments/{id}', 'level' => 'admin'],
                        ['method' => 'GET', 'path' => '/api/v1/certificates', 'level' => 'read'],
                        ['method' => 'POST', 'path' => '/api/v1/certificates/{id}/sign', 'level' => 'admin'],
                    ],
                ],
                [
                    'name' => 'Students',
                    'description' => 'Students — read-only access to appointments and certificates',
                    'priority' => 20,
                    'grants' => [
                        ['method' => 'GET', 'path' => '/api/v1/appointments', 'level' => 'read'],
                        ['method' => 'GET', 'path' => '/api/v1/certificates', 'level' => 'read'],
                    ],
                ],
                [
                    'name' => 'Registrar-Staff',
                    'description' => 'Registrar — can manage certificates but not appointments',
                    'priority' => 10,
                    '_comment' => 'This group has higher priority than Students (10 < 20). If both grants conflict, this group wins.',
                    'grants' => [
                        ['method' => 'GET', 'path' => '/api/v1/certificates', 'level' => 'read'],
                        ['method' => 'POST', 'path' => '/api/v1/certificates', 'level' => 'write'],
                        ['method' => 'PUT', 'path' => '/api/v1/certificates/{id}', 'level' => 'write'],
                        ['method' => 'DELETE', 'path' => '/api/v1/certificates/{id}', 'level' => 'admin'],
                    ],
                ],
            ],
            'user_overrides' => [
                [
                    'email' => 'dean@lyceumalabang.edu.ph',
                    '_comment' => 'User must already exist in the system. Overrides replace group-resolution for that endpoint.',
                    'overrides' => [
                        ['method' => 'DELETE', 'path' => '/api/v1/appointments/{id}', 'level' => 'write'],
                    ],
                ],
            ],
        ];

        $filename = 'access-config-template.json';

        return response()->streamDownload(function () use ($template) {
            echo json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function export(string $tenant): StreamedResponse
    {
        $tenantModel = $this->getTenant($tenant);
        $tenantId = $tenantModel->id;

        $groups = UserGroup::where(function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
        })->get();

        $groupsData = [];
        foreach ($groups as $group) {
            $grants = TenantEndpointGrant::where('group_id', $group->id)
                ->where(function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                })
                ->get()
                ->map(fn ($g) => [
                    'method' => $g->method,
                    'path' => $g->path,
                    'level' => $g->level,
                ])
                ->toArray();

            $groupData = [
                'name' => $group->name,
                'description' => $group->description,
                'priority' => $group->priority,
                'tenant_id' => $group->tenant_id,
            ];

            if (!empty($grants)) {
                $groupData['grants'] = $grants;
            }

            $groupsData[] = $groupData;
        }

        $overrides = TenantEndpointOverride::where('tenant_id', $tenantId)->get();
        $overridesData = [];
        foreach ($overrides as $override) {
            $user = User::find($override->user_id);
            if (!$user) {
                continue;
            }

            $existing = collect($overridesData)->firstWhere('email', $user->email);
            if ($existing) {
                $existing['overrides'][] = [
                    'method' => $override->method,
                    'path' => $override->path,
                    'level' => $override->level,
                ];
            } else {
                $overridesData[] = [
                    'email' => $user->email,
                    'overrides' => [
                        [
                            'method' => $override->method,
                            'path' => $override->path,
                            'level' => $override->level,
                        ],
                    ],
                ];
            }
        }

        $payload = [
            'version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'tenant_slug' => $tenantModel->slug,
            'groups' => $groupsData,
            'user_overrides' => $overridesData,
        ];

        $filename = 'access-config-' . $tenantModel->slug . '-' . now()->format('Y-m-d') . '.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function import(Request $request, string $tenant): JsonResponse
    {
        $tenantModel = $this->getTenant($tenant);
        $tenantId = $tenantModel->id;

        // Handle file upload or JSON body
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            if (!$file->isValid() || $file->getMimeType() !== 'application/json') {
                return response()->json(['status' => 'error', 'message' => 'Invalid JSON file'], 422);
            }
            $content = file_get_contents($file->getRealPath());
        } else {
            $content = $request->input('payload') ?? $request->getContent();
        }

        $data = json_decode($content, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'status' => 'error',
                'message' => 'Malformed JSON: ' . json_last_error_msg(),
            ], 422);
        }

        // Schema validation
        $validator = Validator::make($data, [
            'version' => 'required|string|in:1.0',
            'groups' => 'nullable|array',
            'groups.*.name' => 'required|string|max:255',
            'groups.*.description' => 'nullable|string|max:255',
            'groups.*.priority' => 'nullable|integer|min:1|max:100',
            'groups.*.grants' => 'nullable|array',
            'groups.*.grants.*.method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE,*',
            'groups.*.grants.*.path' => 'required|string|max:512',
            'groups.*.grants.*.level' => 'required|string|in:read,write,admin,deny',
            'user_overrides' => 'nullable|array',
            'user_overrides.*.email' => 'required|email',
            'user_overrides.*.overrides' => 'required|array',
            'user_overrides.*.overrides.*.method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE,*',
            'user_overrides.*.overrides.*.path' => 'required|string|max:512',
            'user_overrides.*.overrides.*.level' => 'required|string|in:read,write,admin,deny',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $isDryRun = $request->boolean('dry_run') || !$request->boolean('confirm');
        $groupsInput = $data['groups'] ?? [];
        $overridesInput = $data['user_overrides'] ?? [];

        // Business validation
        $preview = [
            'groups' => ['create' => [], 'update' => [], 'skip' => [], 'errors' => []],
            'grants' => ['upsert' => 0, 'skip' => 0, 'errors' => []],
            'user_overrides' => ['upsert' => 0, 'skip' => 0, 'errors' => []],
            'endpoint_validation' => ['valid' => true, 'missing_endpoints' => []],
        ];

        // Validate endpoints exist
        foreach ($groupsInput as $groupData) {
            foreach ($groupData['grants'] ?? [] as $grant) {
                if ($grant['level'] === 'deny') {
                    continue;
                }
                $exists = TenantAppEndpoint::where(function ($q) use ($tenantId, $grant) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                })->where('method', $grant['method'])
                    ->where('path', $grant['path'])
                    ->exists();

                if (!$exists && $grant['method'] !== '*') {
                    $exists = TenantAppEndpoint::where(function ($q) use ($tenantId, $grant) {
                        $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                    })->where('method', '*')
                        ->where('path', $grant['path'])
                        ->exists();
                }

                if (!$exists) {
                    $preview['endpoint_validation']['valid'] = false;
                    $preview['endpoint_validation']['missing_endpoints'][] = $grant['method'] . ' ' . $grant['path'];
                }
            }
        }

        foreach ($overridesInput as $overrideData) {
            foreach ($overrideData['overrides'] ?? [] as $override) {
                if ($override['level'] === 'deny') {
                    continue;
                }
                $exists = TenantAppEndpoint::where(function ($q) use ($tenantId, $override) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                })->where('method', $override['method'])
                    ->where('path', $override['path'])
                    ->exists();

                if (!$exists && $override['method'] !== '*') {
                    $exists = TenantAppEndpoint::where(function ($q) use ($tenantId, $override) {
                        $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                    })->where('method', '*')
                        ->where('path', $override['path'])
                        ->exists();
                }

                if (!$exists) {
                    $preview['endpoint_validation']['valid'] = false;
                    $preview['endpoint_validation']['missing_endpoints'][] = $override['method'] . ' ' . $override['path'];
                }
            }
        }

        // Compute group preview
        foreach ($groupsInput as $groupData) {
            $existing = UserGroup::where('name', $groupData['name'])
                ->where('tenant_id', $tenantId)
                ->first();

            if ($existing) {
                $preview['groups']['update'][] = $groupData['name'];
            } else {
                $preview['groups']['create'][] = $groupData['name'];
            }

            $preview['grants']['upsert'] += count($groupData['grants'] ?? []);
        }

        // Compute overrides preview
        foreach ($overridesInput as $overrideData) {
            $user = User::where('email', $overrideData['email'])->first();
            if (!$user) {
                $preview['user_overrides']['errors'][] = 'User not found: ' . $overrideData['email'];
                $preview['user_overrides']['skip'] += count($overrideData['overrides'] ?? []);
            } else {
                $preview['user_overrides']['upsert'] += count($overrideData['overrides'] ?? []);
            }
        }

        if ($isDryRun) {
            return response()->json(['status' => 'preview'] + $preview);
        }

        // Apply changes in a transaction
        $result = DB::transaction(function () use (
            $tenantId,
            $groupsInput,
            $overridesInput,
            $preview
        ) {
            $created = 0;
            $updated = 0;
            $grantsUpserted = 0;
            $overridesUpserted = 0;
            $overrideErrors = [];

            foreach ($groupsInput as $groupData) {
                $existing = UserGroup::where('name', $groupData['name'])
                    ->where('tenant_id', $tenantId)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'description' => $groupData['description'] ?? $existing->description,
                        'priority' => $groupData['priority'] ?? $existing->priority,
                    ]);
                    $group = $existing;
                    $updated++;
                } else {
                    $group = UserGroup::create([
                        'name' => $groupData['name'],
                        'description' => $groupData['description'] ?? null,
                        'priority' => $groupData['priority'] ?? 10,
                        'tenant_id' => $tenantId,
                    ]);
                    $created++;
                }

                foreach ($groupData['grants'] ?? [] as $grant) {
                    if ($grant['level'] === 'deny') {
                        TenantEndpointGrant::where('group_id', $group->id)
                            ->where('tenant_id', $tenantId)
                            ->where('method', $grant['method'])
                            ->where('path', $grant['path'])
                            ->delete();
                        continue;
                    }

                    $this->upsertGrant($group->id, $tenantId, $grant['method'], $grant['path'], $grant['level']);
                    $grantsUpserted++;
                }
            }

            foreach ($overridesInput as $overrideData) {
                $user = User::where('email', $overrideData['email'])->first();
                if (!$user) {
                    $overrideErrors[] = 'User not found: ' . $overrideData['email'];
                    continue;
                }

                foreach ($overrideData['overrides'] ?? [] as $override) {
                    if ($override['level'] === 'deny') {
                        TenantEndpointOverride::where('user_id', $user->id)
                            ->where('tenant_id', $tenantId)
                            ->where('method', $override['method'])
                            ->where('path', $override['path'])
                            ->delete();
                        continue;
                    }

                    $this->upsertOverride($user->id, $tenantId, $override['method'], $override['path'], $override['level']);
                    $overridesUpserted++;
                }
            }

            return [
                'groups' => ['created' => $created, 'updated' => $updated, 'skipped' => 0],
                'grants' => ['upserted' => $grantsUpserted, 'skipped' => 0],
                'user_overrides' => ['upserted' => $overridesUpserted, 'skipped' => 0, 'errors' => $overrideErrors],
            ];
        });

        return response()->json(['status' => 'applied'] + $result);
    }

    // ===== Helpers =====

    private function upsertGrant(int $groupId, string $tenantId, string $method, string $path, string $level): void
    {
        $existing = TenantEndpointGrant::where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('method', $method)
            ->where('path', $path)
            ->first();

        if ($existing) {
            $existing->update(['level' => $level]);
        } else {
            TenantEndpointGrant::create([
                'group_id' => $groupId,
                'tenant_id' => $tenantId,
                'method' => $method,
                'path' => $path,
                'level' => $level,
            ]);
        }
    }

    private function upsertOverride(string $userId, string $tenantId, string $method, string $path, string $level): void
    {
        $existing = TenantEndpointOverride::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('method', $method)
            ->where('path', $path)
            ->first();

        if ($existing) {
            $existing->update(['level' => $level]);
        } else {
            TenantEndpointOverride::create([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'method' => $method,
                'path' => $path,
                'level' => $level,
            ]);
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
