<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\UserGroup;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = UserGroup::withCount('users')->orderBy('name');

        $tenantId = $request->query('tenant_id');

        if ($tenantId === 'null' || $tenantId === '') {
            $query->whereNull('tenant_id');
        } elseif ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $groups = $query->get()->map(fn (UserGroup $group) => [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'tenant_id' => $group->tenant_id,
            'members_count' => $group->users_count,
            'created_at' => $group->created_at?->toIso8601String(),
        ]);

        return response()->json(['data' => $groups]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'tenant_id' => 'nullable|string|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $tenantId = $request->input('tenant_id');

        $existing = UserGroup::where('name', $request->input('name'))
            ->where(fn ($q) => $tenantId === null
                ? $q->whereNull('tenant_id')
                : $q->where('tenant_id', $tenantId))
            ->exists();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Group name already exists for the given scope.',
            ], 409);
        }

        $group = UserGroup::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'tenant_id' => $tenantId,
        ]);

        return response()->json([
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'tenant_id' => $group->tenant_id,
            'created_at' => $group->created_at?->toIso8601String(),
        ], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $group = UserGroup::find($id);

        if (!$group) {
            return response()->json([
                'status' => 'error',
                'message' => 'Group not found.',
            ], 404);
        }

        $group->users()->detach();
        $group->permissions()->detach();
        $group->delete();

        return response()->json(null, 204);
    }

    public function showPermissions(string $id): JsonResponse
    {
        $group = UserGroup::with('permissions')->find($id);

        if (!$group) {
            return response()->json([
                'status' => 'error',
                'message' => 'Group not found.',
            ], 404);
        }

        $permissions = $group->permissions->map(fn ($perm) => [
            'id' => $perm->id,
            'key' => $perm->key,
            'description' => $perm->description,
            'granted' => (bool) $perm->pivot->granted,
            'tenant_id' => $perm->pivot->tenant_id,
        ]);

        return response()->json([
            'group_id' => $group->id,
            'group_name' => $group->name,
            'permissions' => $permissions,
        ]);
    }

    public function syncPermissions(Request $request, string $id): JsonResponse
    {
        $group = UserGroup::find($id);

        if (!$group) {
            return response()->json([
                'status' => 'error',
                'message' => 'Group not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*.permission_key' => 'required|string|exists:permissions,key',
            'permissions.*.granted' => 'required|boolean',
            'tenant_id' => 'nullable|string|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $tenantId = $request->input('tenant_id');

        foreach ($request->input('permissions') as $item) {
            $permission = Permission::where('key', $item['permission_key'])->first();

            if ($item['granted']) {
                $this->authorization->grantGroupPermission($group->id, $permission->key, $tenantId);
            } else {
                $this->authorization->revokeGroupPermission($group->id, $permission->key, $tenantId);
            }
        }

        return response()->json([
            'status' => 'success',
            'group_id' => $group->id,
            'permissions_count' => count($request->input('permissions')),
        ]);
    }
}
