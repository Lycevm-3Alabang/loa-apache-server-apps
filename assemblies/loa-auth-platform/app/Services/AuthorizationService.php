<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthorizationService
{
    public function hasPermission(string $userId, string $permissionKey, ?string $tenantId = null): bool
    {
        $user = User::find($userId);

        if (!$user) {
            return false;
        }

        $permission = Permission::where('key', $permissionKey)->first();

        if (!$permission) {
            return false;
        }

        $override = $this->findUserOverride($user, $permission, $tenantId);

        if ($override !== null) {
            return $override;
        }

        $grants = $this->applicableGroupGrants($user, $permission, $tenantId);

        if (in_array(false, $grants, true)) {
            return false;
        }

        return in_array(true, $grants, true);
    }

    public function getPermissions(string $userId, ?string $tenantId = null): array
    {
        $user = User::find($userId);

        if (!$user) {
            return [];
        }

        $groupPermissions = $user->userGroups()
            ->where(function ($q) use ($tenantId) {
                $this->scopeTenant($q, $tenantId, 'user_groups.tenant_id');
            })
            ->with(['permissions' => function ($q) use ($tenantId) {
                $q->where(function ($q2) use ($tenantId) {
                    $this->scopeTenant($q2, $tenantId, 'user_group_permission.tenant_id');
                });
            }])
            ->get()
            ->flatMap(fn (UserGroup $group) => $group->permissions);

        $granted = $groupPermissions
            ->filter(fn ($p) => $p->pivot->granted)
            ->pluck('key')
            ->unique();

        $denied = $groupPermissions
            ->filter(fn ($p) => !$p->pivot->granted)
            ->pluck('key')
            ->unique();

        $keys = $granted->diff($denied);

        $userPermissions = $user->userPermissions()
            ->where(function ($q) use ($tenantId) {
                $this->scopeTenant($q, $tenantId, 'user_permission.tenant_id');
            })
            ->get();

        foreach ($userPermissions as $userPermission) {
            if ($userPermission->pivot->granted) {
                $keys->push($userPermission->key);
            } else {
                $keys = $keys->reject(fn ($key) => $key === $userPermission->key);
            }
        }

        return $keys->unique()->values()->toArray();
    }

    public function getGroups(string $userId, ?string $tenantId = null): array
    {
        $user = User::find($userId);

        if (!$user) {
            return [];
        }

        return $user->userGroups()
            ->where(function ($q) use ($tenantId) {
                $this->scopeTenant($q, $tenantId, 'user_groups.tenant_id');
            })
            ->pluck('name')
            ->toArray();
    }

    public function addToGroup(string $userId, string $groupId): void
    {
        $user = User::find($userId);
        $group = UserGroup::find($groupId);

        if (!$user || !$group) {
            throw new \Exception('User or group not found');
        }

        // I1 invariant: user in tenant-scoped group ⇒ user must have that tenant's pivot
        if ($group->tenant_id !== null) {
            $hasPivot = DB::table('user_tenants')
                ->where('user_id', $userId)
                ->where('tenant_id', $group->tenant_id)
                ->exists();

            if (!$hasPivot) {
                throw new HttpException(422, 'User must belong to the tenant before being added to a tenant-scoped group.');
            }
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

        // M8: prevent self-revocation of platform admin group
        $adminGroup = config('auth-web.admin_group', 'loa-auth-admin');
        if (auth()->check() && auth()->id() === $userId && $group->name === $adminGroup) {
            throw new HttpException(422, 'You cannot revoke your own platform admin membership.');
        }

        $user->userGroups()->detach($groupId);
    }

    public function addToGroupTransactional(string $userId, string $groupId): void
    {
        DB::transaction(function () use ($userId, $groupId) {
            $group = UserGroup::find($groupId);

            if (!$group) {
                throw new \Exception('Group not found');
            }

            // If group is tenant-scoped and user lacks pivot, create it
            if ($group->tenant_id !== null) {
                $hasPivot = DB::table('user_tenants')
                    ->where('user_id', $userId)
                    ->where('tenant_id', $group->tenant_id)
                    ->exists();

                if (!$hasPivot) {
                    $user = User::find($userId);
                    if (!$user) {
                        throw new \Exception('User not found');
                    }
                    $user->tenants()->attach($group->tenant_id);
                }
            }

            $this->addToGroup($userId, $groupId);
        });
    }

    public function grantGroupPermission(string $groupId, string $permissionKey, ?string $tenantId = null): void
    {
        $group = UserGroup::find($groupId);
        $permission = Permission::where('key', $permissionKey)->first();

        if (!$group || !$permission) {
            throw new \Exception('Group or permission not found');
        }

        $group->permissions()->syncWithoutDetaching([
            $permission->id => [
                'granted' => true,
                'tenant_id' => $tenantId,
            ],
        ]);
    }

    public function revokeGroupPermission(string $groupId, string $permissionKey, ?string $tenantId = null): void
    {
        $group = UserGroup::find($groupId);
        $permission = Permission::where('key', $permissionKey)->first();

        if (!$group || !$permission) {
            return;
        }

        $group->permissions()->syncWithoutDetaching([
            $permission->id => [
                'granted' => false,
                'tenant_id' => $tenantId,
            ],
        ]);
    }

    private function findUserOverride(User $user, Permission $permission, ?string $tenantId): ?bool
    {
        $override = $user->userPermissions()
            ->where('permission_id', $permission->id)
            ->where(fn ($q) => $this->scopeTenant($q, $tenantId, 'user_permission.tenant_id'))
            ->first();

        if (!$override) {
            return null;
        }

        return (bool) $override->pivot->granted;
    }

    private function applicableGroupGrants(User $user, Permission $permission, ?string $tenantId): array
    {
        return $user->userGroups()
            ->where(function ($q) use ($tenantId) {
                $this->scopeTenant($q, $tenantId, 'user_groups.tenant_id');
            })
            ->with(['permissions' => function ($q) use ($permission, $tenantId) {
                $q->where('permission_id', $permission->id)
                    ->where(function ($q2) use ($tenantId) {
                        $this->scopeTenant($q2, $tenantId, 'user_group_permission.tenant_id');
                    });
            }])
            ->get()
            ->flatMap(fn (UserGroup $group) => $group->permissions)
            ->map(fn ($p) => (bool) $p->pivot->granted)
            ->all();
    }

    private function scopeTenant($query, ?string $tenantId, string $column): void
    {
        if ($tenantId === null) {
            $query->whereNull($column);
        } else {
            $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, $tenantId));
        }
    }
}
