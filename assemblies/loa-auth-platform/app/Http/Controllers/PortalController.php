<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdentityService;
use App\Services\PortalRouter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Post-login portal surface (dashboard-account.md): the console dashboard at
 * `/` for every authenticated user, tile entry into tenant apps with
 * server-side membership re-checks, and a minimal account page.
 */
class PortalController extends Controller
{
    public function __construct(
        private readonly IdentityService $identity,
        private readonly PortalRouter $router,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Default page (dashboard-account.md v1.1): the console-styled dashboard
     * for EVERY authenticated user — enrolled app tiles + account summary.
     * Only a validated ?redirect= intent routes away (straight into that
     * tenant app); guests fall through to login / sso-login. There is no
     * auto-enter: membership count never skips the dashboard.
     */
    public function home(Request $request): View|RedirectResponse
    {
        if (!Auth::guard('web')->check()) {
            // Preserve the full query string (?redirect= etc.) so the login
            // pipeline's own safeRedirectUrl() validation still applies.
            $query = $request->query();

            if ($query !== []) {
                return redirect()->to('/sso/login?'.http_build_query($query));
            }

            return redirect()->route('login');
        }

        $user = $this->authUser();

        $target = $this->safeRedirectUrl($request->query('redirect'));

        if ($target !== null) {
            return $this->router->enterForTarget($request, $user, $target);
        }

        return view('dashboard', [
            'tenants' => $this->router->activeMemberships($user),
            'isAdmin' => $this->router->isAdmin($user),
            'portalUser' => $user,
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
                ->route('home')
                ->with('error', 'You do not have access to that application.');
        }

        $url = $tenant->effectiveAppUrl();

        if (!$url) {
            return redirect()
                ->route('home')
                ->with('error', 'That application is not available right now.');
        }

        return $this->router->enterTenant($request, $user, $tenant, (string) $url, null, 'portal');
    }

    public function account(Request $request): View
    {
        return view('account', [
            'portalUser' => $this->authUser(),
            'editName' => $request->query('edit') === 'name',
        ]);
    }

    /**
     * Self-service name update (dashboard-account.md §5): the only editable
     * profile field on /account; email and status remain read-only.
     */
    public function updateName(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('portal.account', ['edit' => 'name'])
                ->withErrors($validator)
                ->withInput();
        }

        $user = $this->authUser();
        $oldName = $user->name;
        $name = trim($request->string('name')->toString());

        if ($name === '') {
            return redirect()
                ->route('portal.account', ['edit' => 'name'])
                ->withErrors(['name' => 'The name field is required.'])
                ->withInput();
        }

        $user->update(['name' => $name]);

        $this->audit->recordSafe(
            'auth.profile.name_update',
            'user',
            $user->id,
            ['from' => $oldName, 'to' => $name],
        );

        return redirect()->route('portal.account')->with('status', 'Name updated.');
    }

    /**
     * Standalone change-password page (dashboard-account.md §5): linked from
     * /account instead of embedding the form, and deep-linkable from tenant
     * apps (capture.return preserves the intent across an expired session).
     */
    public function showPasswordForm(): View
    {
        return view('account-password');
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
