<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGroup;

class TenantService
{
    public function createTenant(array $data): Tenant
    {
        $slug = strtolower(trim((string) ($data['slug'] ?? '')));

        if ($slug === '' || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
            throw new \InvalidArgumentException('Invalid slug');
        }

        if (Tenant::where('slug', $slug)->exists()) {
            throw new \Exception('Slug already in use');
        }

        return Tenant::create([
            'slug' => $slug,
            'name' => trim((string) ($data['name'] ?? '')),
            'status' => $data['status'] ?? 'active',
            'app_url' => $data['app_url'] ?? null,
            'dev_app_url' => $data['dev_app_url'] ?? null,
            'redirect_origins' => $this->normalizeOrigins($data['redirect_origins'] ?? []),
            'dev_redirect_origins' => $this->normalizeOrigins($data['dev_redirect_origins'] ?? []),
        ]);
    }

    public function updateTenant(string $tenantId, array $data): Tenant
    {
        $tenant = $this->getTenant($tenantId);

        if (isset($data['slug']) && strtolower($data['slug']) !== $tenant->slug) {
            throw new \InvalidArgumentException('Slug is immutable after issuance');
        }

        $update = array_filter([
            'name' => isset($data['name']) ? trim((string) $data['name']) : null,
            'status' => $data['status'] ?? null,
            'app_url' => $data['app_url'] ?? null,
            'dev_app_url' => $data['dev_app_url'] ?? null,
            'redirect_origins' => isset($data['redirect_origins'])
                ? $this->normalizeOrigins($data['redirect_origins'])
                : null,
            'dev_redirect_origins' => isset($data['dev_redirect_origins'])
                ? $this->normalizeOrigins($data['dev_redirect_origins'])
                : null,
        ], fn ($value) => $value !== null);

        $tenant->update($update);

        return $tenant;
    }

    public function suspendTenant(string $tenantId): void
    {
        $this->getTenant($tenantId)->update(['status' => 'suspended']);
    }

    public function activateTenant(string $tenantId): void
    {
        $this->getTenant($tenantId)->update(['status' => 'active']);
    }

    public function getTenant(string $tenantId): Tenant
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            throw new \Exception('Tenant not found');
        }

        return $tenant;
    }

    public function getTenantBySlug(string $slug): ?Tenant
    {
        return Tenant::where('slug', $slug)->first();
    }

    public function resolveTenantByRedirectOrigin(string $origin): ?Tenant
    {
        $origin = $this->normalizeOrigin($origin);

        return Tenant::where('status', 'active')
            ->get()
            ->first(function (Tenant $tenant) use ($origin): bool {
                return in_array(
                    $origin,
                    $this->normalizeOrigins($tenant->effectiveRedirectOrigins()),
                    true,
                );
            });
    }

    public function addUserToTenant(string $userId, string $tenantId): void
    {
        $user = User::find($userId);
        $this->getTenant($tenantId);

        if (!$user) {
            throw new \Exception('User not found');
        }

        $user->tenants()->syncWithoutDetaching([$tenantId]);
    }

    public function removeUserFromTenant(string $userId, string $tenantId): void
    {
        $user = User::find($userId);

        if (!$user) {
            return;
        }

        $user->tenants()->detach($tenantId);

        $tenantScopedGroupIds = UserGroup::where('tenant_id', $tenantId)->pluck('id')->all();

        if (!empty($tenantScopedGroupIds)) {
            $user->userGroups()->detach($tenantScopedGroupIds);
        }
    }

    public function isMember(string $userId, string $tenantId): bool
    {
        $user = User::find($userId);

        if (!$user) {
            return false;
        }

        return $user->tenants()->where('tenant_id', $tenantId)->exists();
    }

    public function normalizeOrigin(string $origin): string
    {
        $origin = strtolower(trim($origin));

        if (!filter_var($origin, FILTER_VALIDATE_URL)) {
            return $origin;
        }

        $parts = parse_url($origin);

        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return $origin;
        }

        $normalized = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }

        return $normalized;
    }

    private function normalizeOrigins(array $origins): array
    {
        return array_values(array_unique(array_map(
            fn ($origin) => $this->normalizeOrigin((string) $origin),
            $origins,
        )));
    }
}
