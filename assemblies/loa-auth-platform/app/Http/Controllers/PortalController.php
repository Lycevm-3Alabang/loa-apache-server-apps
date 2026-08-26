<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EncryptionService;
use App\Services\IdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Post-login portal surface (unified-auth-flow.md §6): launcher tiles for
 * every active tenant membership, admin console entry for platform admins,
 * and a minimal account page. Entry into tenant apps re-checks membership
 * server-side before minting a scoped token pair.
 */
class PortalController extends Controller
{
    public function __construct(
        private readonly IdentityService $identity,
        private readonly EncryptionService $encryption,
        private readonly AuditLogger $audit,
    ) {
    }

    public function launcher(): View
    {
        $user = $this->authUser();

        return view('launcher', [
            'tenants' => $user->tenants()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
            'isAdmin' => $user->inGroup((string) config('auth-web.admin_group')),
        ]);
    }

    public function go(Request $request, string $tenant): RedirectResponse
    {
        $user = $this->authUser();

        // Pivot-scoped lookup doubles as the server-side membership check.
        $tenant = $user->tenants()
            ->where('status', 'active')
            ->where('tenants.id', $tenant)
            ->first();

        if (!$tenant) {
            return redirect()
                ->route('portal.launcher')
                ->with('error', 'You do not have access to that application.');
        }

        $url = $tenant->effectiveAppUrl();

        if (!$url) {
            return redirect()
                ->route('portal.launcher')
                ->with('error', 'That application is not available right now.');
        }

        $tokens = $this->identity->issueForUser($user, $tenant);

        $this->queueHandoff($request, $this->encryption, $user, $url, $tokens, $tenant);

        // admin-audit-log.md §5: admin entries into tenant apps are evidence.
        if ($user->inGroup((string) config('auth-web.admin_group'))) {
            $this->audit->recordSafe(
                'auth.tenant_entry',
                'tenant',
                $tenant->id,
                ['tenant' => $tenant->slug, 'via' => 'portal'],
            );
        }

        return redirect()->route('auth.redirect');
    }

    public function account(): View
    {
        return view('account', [
            'portalUser' => $this->authUser(),
        ]);
    }

    /**
     * Change-password for the portal session (unified-auth-flow.md §9).
     * Reuses the platform password policy and IdentityService, which revokes
     * every refresh token on success; the web session itself is kept.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $this->authUser();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'confirmed',
            ],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $this->identity->updatePassword(
                $user->id,
                $request->string('current_password')->toString(),
                $request->string('password')->toString(),
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['current_password' => $e->getMessage()]);
        }

        return back()->with('status', 'Password updated.');
    }

    private function authUser(): User
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        return $user;
    }
}
