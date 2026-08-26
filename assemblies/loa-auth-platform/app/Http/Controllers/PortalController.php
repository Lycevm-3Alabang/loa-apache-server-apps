<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PasswordResetNotificationService;
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
        private readonly PortalRouter $router,
        private readonly AuditLogger $audit,
        private readonly PasswordResetNotificationService $passwordResets,
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
     * Change-password = emailed reset link (dashboard-account.md v1.3 D17).
     * Reuses the platform's change-request notification path so token TTL and
     * email template match the rest of the identity surface; possession of
     * the emailed signed token replaces current-password verification. No
     * navigation away — flash + stay. Using the link signs the user out of
     * every LOA application (WebResetController + refresh-token revocation).
     */
    public function emailResetLink(Request $request): RedirectResponse
    {
        $user = $this->authUser();

        $this->passwordResets->sendChangePasswordLink($user);

        $this->audit->recordSafe(
            'auth.profile.password_reset_request',
            'user',
            $user->id,
            ['email' => $user->email],
        );

        // Authenticated context: no anti-enumeration copy needed — the user
        // is signed in with this address.
        return back()->with('status', "Reset link sent to {$user->email}.");
    }

    private function authUser(): User
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        return $user;
    }
}
