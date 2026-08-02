<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\GroupClaim;
use App\Models\RoutePolicy;
use App\Models\UserClaimOverride;
use App\Models\User;

class PermissionPolicyService
{
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

    private function scopeTenant($query, ?string $tenantId, string $column): void
    {
        if ($tenantId === null) {
            $query->whereNull($column);
        } else {
            $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, $tenantId));
        }
    }
}