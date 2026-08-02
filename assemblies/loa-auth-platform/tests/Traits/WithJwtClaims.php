<?php

namespace Tests\Traits;

use App\Models\Claim;
use App\Models\GroupClaim;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\JWTService;
use App\Services\PermissionPolicyService;

trait WithJwtClaims
{
    private function generateTokenWithClaims(User $user, array $claimKeys = [], array $scopes = []): string
    {
        $jwt = app(JWTService::class);

        return $jwt->generateAccessToken([
            'sub' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'groups' => [],
            'permissions' => $claimKeys,
            'scopes' => $scopes,
        ]);
    }

    private function generateTokenWithResolvedClaims(User $user): string
    {
        $policy = app(PermissionPolicyService::class);
        $claimKeys = $policy->resolveUserClaims($user->id);
        $scopes = $policy->resolveUserScopes($user->id);

        return $this->generateTokenWithClaims($user, $claimKeys, $scopes);
    }

    private function jwtHeadersWithClaims(User $user, array $claimKeys = [], array $scopes = []): array
    {
        return ['Authorization' => 'Bearer ' . $this->generateTokenWithClaims($user, $claimKeys, $scopes)];
    }

    private function createClaim(string $key, ?string $description = null): Claim
    {
        return Claim::firstOrCreate(
            ['key' => $key],
            ['description' => $description ?? "Claim: {$key}"]
        );
    }

    private function createRoutePolicy(string $app, string $method, string $path, string $claimKey, string $filter = 'all'): \App\Models\RoutePolicy
    {
        return \App\Models\RoutePolicy::create([
            'app' => $app,
            'method' => strtoupper($method),
            'path' => $path,
            'claim_key' => $claimKey,
            'filter' => $filter,
        ]);
    }

    private function createGroupClaim(UserGroup $group, string $claimKey, string $scopeType = 'none', ?string $scopeId = null): GroupClaim
    {
        return GroupClaim::create([
            'group_id' => $group->id,
            'claim_key' => $claimKey,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
        ]);
    }
}