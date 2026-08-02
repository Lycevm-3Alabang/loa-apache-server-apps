<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserGroupController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {
    }

    public function indexGroups(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
            ], 404);
        }

        $groups = $user->userGroups->map(fn (UserGroup $group) => [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'tenant_id' => $group->tenant_id,
        ]);

        return response()->json([
            'user_id' => $user->id,
            'groups' => $groups,
        ]);
    }

    public function addGroup(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:user_groups,id',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $groupId = $request->input('group_id');

        if ($user->userGroups()->where('user_groups.id', $groupId)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'User is already in this group.',
            ], 409);
        }

        $this->authorization->addToGroup($user->id, $groupId);

        return response()->json([
            'status' => 'success',
            'user_id' => $user->id,
            'group_id' => $groupId,
        ], 201);
    }

    public function removeGroup(string $id, string $groupId): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
            ], 404);
        }

        $group = UserGroup::find($groupId);

        if (!$group) {
            return response()->json([
                'status' => 'error',
                'message' => 'Group not found.',
            ], 404);
        }

        $this->authorization->removeFromGroup($user->id, $groupId);

        return response()->json(null, 204);
    }

    public function showPermissions(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
            ], 404);
        }

        $groupNames = $this->authorization->getGroups($user->id);
        $effectivePermissions = $this->authorization->getPermissions($user->id);

        $overrides = $user->userPermissions()
            ->get()
            ->map(fn ($perm) => [
                'permission_key' => $perm->key,
                'granted' => (bool) $perm->pivot->granted,
                'source' => 'user_override',
            ]);

        return response()->json([
            'user_id' => $user->id,
            'permissions' => $effectivePermissions,
            'groups' => $groupNames,
            'overrides' => $overrides,
        ]);
    }

    public function grantPermission(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'permission_key' => 'required|string|exists:permissions,key',
            'granted' => 'required|boolean',
            'tenant_id' => 'nullable|string|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $permission = Permission::where('key', $request->input('permission_key'))->first();
        $tenantId = $request->input('tenant_id');

        DB::table('user_permission')->updateOrInsert(
            [
                'user_id' => $user->id,
                'permission_id' => $permission->id,
                'tenant_id' => $tenantId,
            ],
            [
                'granted' => $request->boolean('granted'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return response()->json([
            'status' => 'success',
            'user_id' => $user->id,
            'permission_key' => $permission->key,
            'granted' => $request->boolean('granted'),
        ]);
    }

    public function revokePermission(string $id, string $permissionKey): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
            ], 404);
        }

        $permission = Permission::where('key', $permissionKey)->first();

        if (!$permission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission not found.',
            ], 404);
        }

        DB::table('user_permission')
            ->where('user_id', $user->id)
            ->where('permission_id', $permission->id)
            ->delete();

        return response()->json(null, 204);
    }
}
