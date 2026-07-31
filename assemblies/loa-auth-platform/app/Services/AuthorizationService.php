<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserGroup;

class AuthorizationService
{
    public function hasPermission(string $userId, string $permissionKey): bool
    {
        $user = User::find($userId);

        if (!$user) {
            return false;
        }

        $permission = Permission::where('key', $permissionKey)->first();

        if (!$permission) {
            return false;
        }

        $userPermission = $user->userPermissions()
            ->where('permission_id', $permission->id)
            ->first();

        if ($userPermission) {
            return $userPermission->pivot->granted;
        }

        return $user->userGroups()
            ->whereHas('permissions', function ($q) use ($permission) {
                $q->where('permission_id', $permission->id)
                  ->wherePivot('granted', true);
            })
            ->exists();
    }

    public function getPermissions(string $userId): array
    {
        $user = User::find($userId);

        if (!$user) {
            return [];
        }

        $groupPermissions = $user->userGroups()
            ->with('permissions')
            ->get()
            ->flatMap->permissions
            ->filter(fn ($p) => $p->pivot->granted)
            ->pluck('key')
            ->unique();

        $userPermissions = $user->userPermissions()
            ->where('granted', true)
            ->pluck('permissions.key');

        return $groupPermissions->merge($userPermissions)->unique()->toArray();
    }

    public function getGroups(string $userId): array
    {
        $user = User::find($userId);

        if (!$user) {
            return [];
        }

        return $user->userGroups()->pluck('name')->toArray();
    }

    public function addToGroup(string $userId, string $groupId): void
    {
        $user = User::find($userId);
        $group = UserGroup::find($groupId);

        if (!$user || !$group) {
            throw new \Exception('User or group not found');
        }

        $user->userGroups()->syncWithoutDetaching([$groupId]);
    }

    public function removeFromGroup(string $userId, string $groupId): void
    {
        $user = User::find($userId);
        $group = UserGroup::find($groupId);

        if (!$user || !$group) {
            return;
        }

        $user->userGroups()->detach($groupId);
    }

    public function grantGroupPermission(string $groupId, string $permissionKey): void
    {
        $group = UserGroup::find($groupId);
        $permission = Permission::where('key', $permissionKey)->first();

        if (!$group || !$permission) {
            throw new \Exception('Group or permission not found');
        }

        $group->permissions()->syncWithoutDetaching([
            $permission->id => ['granted' => true],
        ]);
    }

    public function revokeGroupPermission(string $groupId, string $permissionKey): void
    {
        $group = UserGroup::find($groupId);
        $permission = Permission::where('key', $permissionKey)->first();

        if (!$group || !$permission) {
            return;
        }

        $group->permissions()->detach($permission->id);
    }
}
