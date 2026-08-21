<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\GroupClaim;
use App\Models\RoutePolicy;
use App\Models\TenantAppEndpoint;
use App\Models\TenantEndpointGrant;
use App\Models\TenantEndpointOverride;
use App\Models\User;
use App\Models\UserClaimOverride;
use App\Models\UserGroup;

class PermissionPolicyService
{
    public const LEVEL_ORDINAL = [
        'deny' => -1,
        'read' => 1,
        'write' => 2,
        'admin' => 3,
    ];
    public function resolveUserClaims(string $userId, ?string $tenantId = null): array
    {
        $user = User::find($userId);

        if (!$user) {
            return [];
        }

        $groupClaimKeys = $user->userGroups()
            ->where(function ($q) use ($tenantId) {
                $this->scopeTenant($q, $tenantId, 'user_groups.tenant_id');
            })
            ->with(['groupClaims' => function ($q) {
                $q->with('claim');
            }])
            ->get()
            ->flatMap(fn ($group) => $group->groupClaims)
            ->map(fn (GroupClaim $gc) => $gc->claim_key)
            ->unique();

        $overrideKeys = UserClaimOverride::where('user_id', $userId)
            ->get()
            ->map(fn (UserClaimOverride $uo) => [
                'key' => $uo->claim_key,
                'granted' => $uo->granted,
            ]);

        $overrideMap = [];
        foreach ($overrideKeys as $override) {
            $overrideMap[$override['key']] = $override['granted'];
        }

        $resolved = [];
        foreach ($groupClaimKeys as $key) {
            if (isset($overrideMap[$key])) {
                if ($overrideMap[$key]) {
                    $resolved[$key] = true;
                } else {
                    unset($resolved[$key]);
                }
            } else {
                $resolved[$key] = true;
            }
        }

        foreach ($overrideMap as $key => $granted) {
            if ($granted) {
                $resolved[$key] = true;
            }
        }

        return array_keys($resolved);
    }

    public function resolveUserScopes(string $userId, ?string $tenantId = null): array
    {
        $user = User::find($userId);

        if (!$user) {
            return [];
        }

        $scopes = $user->userGroups()
            ->where(function ($q) use ($tenantId) {
                $this->scopeTenant($q, $tenantId, 'user_groups.tenant_id');
            })
            ->with(['groupClaims' => function ($q) {
                $q->where('scope_type', '!=', 'none');
            }])
            ->get()
            ->flatMap(fn ($group) => $group->groupClaims)
            ->map(fn (GroupClaim $gc) => $gc->scope_type . ':' . ($gc->scope_id ?? 'all'))
            ->unique()
            ->values()
            ->toArray();

        return $scopes;
    }

    public function authorize(string $userId, ?string $tenantId, string $app, string $method, string $path): array
    {
        $userClaims = $this->resolveUserClaims($userId, $tenantId);
        $userScopes = $this->resolveUserScopes($userId, $tenantId);

        $policies = RoutePolicy::where('app', $app)
            ->where('method', strtoupper($method))
            ->where('path', $path)
            ->with('claim')
            ->get();

        if ($policies->isEmpty()) {
            return ['allowed' => true, 'policies' => [], 'claims' => $userClaims, 'scopes' => $userScopes];
        }

        foreach ($policies as $policy) {
            if (!in_array($policy->claim_key, $userClaims, true)) {
                return [
                    'allowed' => false,
                    'policy' => $policy->toArray(),
                    'claims' => $userClaims,
                    'scopes' => $userScopes,
                    'reason' => 'missing_claim',
                ];
            }

            $filterResult = $this->applyFilter($policy->filter, $userId, $tenantId, $userScopes);

            if (!$filterResult['allowed']) {
                return [
                    'allowed' => false,
                    'policy' => $policy->toArray(),
                    'claims' => $userClaims,
                    'scopes' => $userScopes,
                    'reason' => 'filter_denied',
                    'filter' => $policy->filter,
                ];
            }
        }

        return ['allowed' => true, 'policies' => $policies->toArray(), 'claims' => $userClaims, 'scopes' => $userScopes];
    }

    private function applyFilter(string $filter, string $userId, ?string $tenantId, array $userScopes): array
    {
        return match ($filter) {
            'none' => ['allowed' => true],
            'all' => ['allowed' => true],
            'author' => ['allowed' => true],
            'scope' => ['allowed' => !empty($userScopes)],
            default => ['allowed' => true],
        };
    }

    public function resolveUserEndpointPermissions(string $userId, ?string $tenantId): array
    {
        $user = User::find($userId);

        if (!$user) {
            return [];
        }

        if ($tenantId === null) {
            $catalogEntries = TenantAppEndpoint::whereNull('tenant_id')->get();
        } else {
            $catalogEntries = TenantAppEndpoint::whereNull('tenant_id')
                ->orWhere('tenant_id', $tenantId)
                ->get();
        }

        $groups = $user->userGroups()
            ->where(fn ($q) => $q->whereNull('user_groups.tenant_id')->orWhere('user_groups.tenant_id', $tenantId))
            ->get();

        $groupIds = $groups->pluck('id')->all();

        $permissions = [];

        foreach ($catalogEntries as $endpoint) {
            $effectiveLevel = $this->resolveEffectiveLevelForEndpoint($userId, $endpoint, $tenantId, $groupIds);

            if ($effectiveLevel !== 'deny') {
                $permissions[] = $effectiveLevel . ':' . $endpoint->path;
            }
        }

        return array_values(array_unique($permissions));
    }

    public function resolveEffectiveLevel(string $userId, ?string $tenantId, string $method, string $path): ?string
    {
        $user = User::find($userId);

        if (!$user) {
            return null;
        }

        $endpoint = TenantAppEndpoint::matchPath($method, $path, $tenantId);

        if (!$endpoint) {
            return null;
        }

        $groups = $user->userGroups()
            ->where(fn ($q) => $q->whereNull('user_groups.tenant_id')->orWhere('user_groups.tenant_id', $tenantId))
            ->get();

        return $this->resolveEffectiveLevelForEndpoint($userId, $endpoint, $tenantId, $groups->pluck('id')->all());
    }

    public function authorizeEndpoint(string $userId, ?string $tenantId, string $method, string $path): array
    {
        $endpoint = TenantAppEndpoint::matchPath($method, $path, $tenantId);

        if (!$endpoint) {
            return [
                'allowed' => false,
                'reason' => 'no_catalog_entry',
                'message' => 'Endpoint not in catalog (closed-by-default)',
            ];
        }

        $requiredLevel = $endpoint->required_level;
        $effectiveLevel = $this->resolveEffectiveLevel($userId, $tenantId, $method, $path);

        if ($effectiveLevel === null) {
            return [
                'allowed' => false,
                'reason' => 'no_access',
                'required_level' => $requiredLevel,
                'effective_level' => 'deny',
            ];
        }

        if ($effectiveLevel === 'deny') {
            return [
                'allowed' => false,
                'reason' => 'denied',
                'required_level' => $requiredLevel,
                'effective_level' => 'deny',
            ];
        }

        $requiredOrdinal = self::LEVEL_ORDINAL[$requiredLevel] ?? 0;
        $effectiveOrdinal = self::LEVEL_ORDINAL[$effectiveLevel] ?? 0;

        if ($effectiveOrdinal < $requiredOrdinal) {
            return [
                'allowed' => false,
                'reason' => 'insufficient_level',
                'required_level' => $requiredLevel,
                'effective_level' => $effectiveLevel,
            ];
        }

        return [
            'allowed' => true,
            'required_level' => $requiredLevel,
            'effective_level' => $effectiveLevel,
        ];
    }

    private function resolveEffectiveLevelForEndpoint(string $userId, TenantAppEndpoint $endpoint, ?string $tenantId, array $groupIds): string
    {
        $effectiveLevel = 'deny';

        if (!empty($groupIds)) {
            $grants = TenantEndpointGrant::whereIn('group_id', $groupIds)
                ->where('method', $endpoint->method)
                ->where('path', $endpoint->path)
                ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
                ->get()
                ->map(function (TenantEndpointGrant $grant) {
                    $group = $grant->group;
                    return [
                        'level' => $grant->level,
                        'priority' => $group ? $group->priority : 10,
                    ];
                })
                ->sortBy('priority')
                ->values();

            if ($grants->isNotEmpty()) {
                $lowestPriority = $grants->first()['priority'];

                $topGrants = $grants->filter(fn ($g) => $g['priority'] === $lowestPriority);

                $hasDeny = $topGrants->contains('level', 'deny');

                if ($hasDeny && $topGrants->count() > 1) {
                    $effectiveLevel = 'deny';
                } else {
                    $effectiveLevel = $topGrants->first()['level'];
                }
            }
        }

        $override = TenantEndpointOverride::where('user_id', $userId)
            ->where('method', $endpoint->method)
            ->where('path', $endpoint->path)
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->first();

        if ($override !== null) {
            $effectiveLevel = $override->level;
        }

        return $effectiveLevel;
    }

    public function levelOrdinal(string $level): int
    {
        return self::LEVEL_ORDINAL[$level] ?? 0;
    }

    public function isPlatformAdmin(string $userId): bool
    {
        $user = User::find($userId);

        if (!$user) {
            return false;
        }

        $adminGroup = $user->userGroups()
            ->whereNull('tenant_id')
            ->where('name', config('auth-web.admin_group'))
            ->exists();

        return $adminGroup;
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