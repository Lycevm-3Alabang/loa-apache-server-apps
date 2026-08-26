<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Smart-routing primitives shared by the login pipeline
 * (unified-auth-flow.md §5) and the portal dashboard root router
 * (dashboard-account.md §1.1): a validated redirect intent hands members
 * straight into the tenant app; every other path lands on the console
 * dashboard at `/`. Handoff minting + /redirect queueing lives here so every
 * entry path shares one implementation.
 */
class PortalRouter
{
    public function __construct(
        private readonly IdentityService $identity,
        private readonly EncryptionService $encryption,
        private readonly TenantService $tenants,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Destination resolver for authenticated users: validated intent →
     * straight handoff for members; otherwise the dashboard at `/`
     * (dashboard-account.md v1.1: auto-enter removed — no membership count
     * ever skips the dashboard).
     */
    public function route(
        Request $request,
        User $user,
        ?string $target,
        ?array $tokens,
    ): RedirectResponse {
        if ($target !== null) {
            return $this->enterForTarget($request, $user, $target, $tokens);
        }

        $this->revokeTokens($tokens);

        return redirect()->route('home');
    }

    /**
     * Explicit-redirect branch (dashboard-account.md §3): members enter the
     * tenant app; non-members land on the dashboard with a denial flash and
     * any login-time token pair revoked.
     */
    public function enterForTarget(
        Request $request,
        User $user,
        string $target,
        ?array $tokens = null,
    ): RedirectResponse {
        $intentTenant = $this->resolveTenant($target);

        if ($intentTenant && $this->tenants->isMember($user->id, $intentTenant->id)) {
            return $this->enterTenant($request, $user, $intentTenant, $target, $tokens, 'sso');
        }

        $this->revokeTokens($tokens);

        return redirect()
            ->route('home')
            ->with('error', 'You do not have access to that application.');
    }

    /**
     * Mints a tenant-scoped token pair from the portal session and queues the
     * /redirect interstitial (unified-auth-flow.md §3 tail). Any login-time
     * pair minted without the target tenant's claims is revoked first.
     */
    public function enterTenant(
        Request $request,
        User $user,
        Tenant $tenant,
        string $url,
        ?array $previousTokens,
        string $via,
    ): RedirectResponse {
        $this->revokeTokens($previousTokens);

        $tokens = $this->identity->issueForUser($user, $tenant);

        $payload = [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => $tokens['token_type'],
            'expires_in' => $tokens['expires_in'],
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
            ],
            'iat' => time(),
            'exp' => time() + $tokens['expires_in'],
        ];

        if ($this->encryption->isConfigured()) {
            $request->session()->put('redirect_payload', $this->encryption->encrypt($payload));
        } else {
            $request->session()->put(
                'redirect_fragment',
                http_build_query($tokens, '', '&', PHP_QUERY_RFC3986),
            );
        }

        $request->session()->put('redirect_url', $url);

        // admin-audit-log.md §5: admin entries into tenant apps are evidence.
        if ($this->isAdmin($user)) {
            $this->audit->recordSafe(
                'auth.tenant_entry',
                'tenant',
                $tenant->id,
                ['tenant' => $tenant->slug, 'via' => $via],
            );
        }

        return redirect()->route('auth.redirect');
    }

    public function revokeTokens(?array $tokens): void
    {
        if ($tokens) {
            $this->identity->logout($tokens['refresh_token']);
        }
    }

    public function activeMemberships(User $user): Collection
    {
        return $user->tenants()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function isAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->inGroup((string) config('auth-web.admin_group'));
    }

    public function resolveTenant(?string $target): ?Tenant
    {
        if (!$target) {
            return null;
        }

        $parts = parse_url($target);

        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = strtolower($parts['scheme']).'://'.strtolower($parts['host']);

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $this->tenants->resolveTenantByRedirectOrigin($origin);
    }
}
