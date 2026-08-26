<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivationService;
use App\Services\AuditLogger;
use App\Services\EncryptionService;
use App\Services\IdentityService;
use App\Services\PasswordResetNotificationService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class WebAuthController extends Controller
{
    public function __construct(
        private readonly IdentityService $identity,
        private readonly PasswordResetNotificationService $passwordResetNotifications,
        private readonly TenantService $tenants,
        private readonly EncryptionService $encryption,
        private readonly ActivationService $activation,
        private readonly AuditLogger $audit,
    ) {
    }

    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return $this->routeAuthenticatedUser(
                $request,
                Auth::guard('web')->user(),
                $this->resolveRedirect($request->query('redirect')),
                null,
            );
        }

        return view('login', [
            'redirect' => $this->resolveRedirect($request->query('redirect')),
            'context' => 'portal',
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        return $this->handleWebLogin($request, false);
    }

    /**
     * Unified credential pipeline for POST /login and POST /sso/login
     * (unified-auth-flow.md §3). SSO mode requires a validated redirect intent;
     * every authenticated user leaves with a portal session (§4).
     */
    private function handleWebLogin(Request $request, bool $requireTenantIntent): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'redirect' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors($validator);
        }

        $target = $this->resolveRedirect($request->input('redirect'));

        if ($requireTenantIntent && !$target) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['credentials' => 'Invalid credentials']);
        }

        try {
            $tokens = $this->identity->login(
                $request->string('email')->toString(),
                $request->string('password')->toString(),
                $request->ip(),
                $this->resolveTenant($target),
            );
        } catch (\Throwable) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['credentials' => 'Invalid credentials']);
        }

        $user = User::where('email', $request->string('email')->toString())->first();

        if (!$user) {
            return back()
                ->withErrors(['credentials' => 'Invalid credentials']);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return $this->routeAuthenticatedUser($request, $user, $target, $tokens);
    }

    public function showRegister(): View
    {
        return view('register');
    }
    
    public function showActivate(Request $request): View|RedirectResponse
    {
        $token = $request->query('token');
        
        if (!$token) {
            return redirect()->route('login');
        }
        
        try {
            // Validate token (lookup hashed version in database)
            $hashedToken = hash('sha256', $token);
            $activation = \App\Models\Activation::where('token', $hashedToken)
                ->whereNull('activated_at')
                ->first();
                
            if (!$activation || $activation->isExpired()) {
                return redirect()->route('login')->with('error', 'Invalid or expired activation token.');
            }
            
            // Get the user from activation
            $user = \App\Models\User::find($activation->user_id);
            if (!$user) {
                return redirect()->route('login')->with('error', 'User not found');
            }
            
            return view('activate', [
                'email' => $user->email,
                'token' => $token
            ]);
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Invalid or expired activation token.');
        }
    }

    public function activate(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => [
                'required', 
                'string', 
                'min:8',
                'regex:/[A-Z]/', 
                'regex:/[a-z]/', 
                'regex:/[0-9]/',
            ],
            'password_confirmation' => 'required|string|same:password',
        ]);

        if ($validator->fails()) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors($validator);
        }

        try {
            $user = $this->activation->activate($request->input('token'), $request->input('password'));

            // Portal session + smart routing (unified-auth-flow.md §7): the new
            // user lands on the launcher, or straight into their only app.
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            return $this->routeAuthenticatedUser($request, $user, null, null);
        } catch (\Exception $e) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['token' => $e->getMessage()]);
        }
    }

    public function register(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => [
                'required', 'string', 'min:8',
                'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/',
            ],
            'password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors($validator);
        }

        try {
            $this->identity->register(
                $request->string('email')->toString(),
                $request->string('password')->toString(),
                $request->string('name')->toString(),
            );
        } catch (\Throwable) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'An account with this email already exists.']);
        }

        return redirect()->route('login')->with('status', 'Account created. Please sign in.');
    }

    public function showForgotPassword(Request $request): View
    {
        // Resolution order: explicit ?redirect= → HTTP referrer origin (both
        // validated against the allowlist); null falls back to the sign-in link.
        $returnUrl = $this->safeRedirectUrl($request->query('redirect'))
            ?? $this->referrerReturnUrl($request);

        return view('forgot-password', [
            'redirect' => $returnUrl,
        ]);
    }

    private function referrerReturnUrl(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (!is_string($referer) || $referer === '') {
            return null;
        }

        return $this->safeRedirectUrl($referer);
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'redirect' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            // Re-render via GET with the sanitized redirect so the
            // Return-to-app link survives validation errors (§4.2 UI).
            $safe = $this->safeRedirectUrl($request->input('redirect'));
            $query = $safe !== null ? ['redirect' => $safe] : [];

            return redirect()
                ->to('/forgot-password'.($query !== [] ? '?'.http_build_query($query) : ''))
                ->withErrors($validator)
                ->withInput();
        }

        $this->passwordResetNotifications->sendForgotPasswordLink(
            $request->string('email')->toString(),
            $this->safeRedirectUrl($request->input('redirect')),
        );

        return back()->with('status', 'If the email exists, a reset link has been sent.');
    }

    public function showSSOLogin(Request $request): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            $target = $this->resolveRedirect($request->query('redirect'));

            if ($target) {
                return $this->routeAuthenticatedUser(
                    $request,
                    Auth::guard('web')->user(),
                    $target,
                    null,
                );
            }

            return redirect()->route('portal.launcher');
        }

        return view('login', [
            'redirect' => $this->resolveRedirect($request->query('redirect')),
            'context' => 'sso',
        ]);
    }

    public function ssoLogin(Request $request): RedirectResponse
    {
        return $this->handleWebLogin($request, true);
    }

    public function showSSORegister(): View
    {
        return view('sso-register');
    }

    public function ssoRegister(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => [
                'required', 'string', 'min:8',
                'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/',
            ],
            'password_confirmation' => 'required|string|same:password',
        ]);

        if ($validator->fails()) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors($validator);
        }

        $email = $request->string('email')->toString();

        if (!$this->isAllowedRegistrationDomain($email)) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Registration is restricted to LOA email addresses.']);
        }

        try {
            $this->identity->register(
                $email,
                $request->string('password')->toString(),
                $request->string('name')->toString(),
            );
        } catch (\Throwable) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'An account with this email already exists.']);
        }

        return redirect()->route('sso.login')->with('status', 'Account created. Please sign in.');
    }

    public function showRedirect(Request $request): View|RedirectResponse
    {
        $url = $request->session()->get('redirect_url');

        if (!$url) {
            return redirect()->route('login');
        }

        $payload = $request->session()->get('redirect_payload');
        $fragment = $request->session()->get('redirect_fragment');

        $targetUrl = $this->removeFragment($url);
        $fullUrl = $payload
            ? $targetUrl . '#payload=' . $payload
            : $targetUrl . '#' . $fragment;

        $request->session()->forget(['redirect_url', 'redirect_payload', 'redirect_fragment']);

        return view('redirect', [
            'url' => $targetUrl,
            'full_url' => $fullUrl,
        ]);
    }

    /**
     * Destination resolver shared by login, activation and authenticated GETs
     * (unified-auth-flow.md §5): validated intent → straight handoff for
     * members; single tenant membership → auto-enter; otherwise the launcher.
     */
    private function routeAuthenticatedUser(
        Request $request,
        User $user,
        ?string $target,
        ?array $tokens,
    ): RedirectResponse {
        if ($target !== null) {
            $intentTenant = $this->resolveTenant($target);

            if ($intentTenant && $this->tenants->isMember($user->id, $intentTenant->id)) {
                return $this->enterTenant($request, $user, $intentTenant, $target, $tokens);
            }

            $this->revokeTokens($tokens);

            return redirect()
                ->route('portal.launcher')
                ->with('error', 'You do not have access to that application.');
        }

        $memberships = $this->activeMemberships($user);

        if (!$this->isAdmin($user) && $memberships->count() === 1) {
            $tenant = $memberships->first();
            $url = $tenant->effectiveAppUrl();

            if ($url) {
                return $this->enterTenant($request, $user, $tenant, $url, $tokens);
            }
        }

        $this->revokeTokens($tokens);

        return redirect()->route('portal.launcher');
    }

    /**
     * Mints a tenant-scoped token pair from the portal session and queues the
     * /redirect interstitial (unified-auth-flow.md §3 tail). Any login-time
     * pair minted without the target tenant's claims is revoked first.
     */
    private function enterTenant(
        Request $request,
        User $user,
        Tenant $tenant,
        string $url,
        ?array $previousTokens,
    ): RedirectResponse {
        $this->revokeTokens($previousTokens);

        $tokens = $this->identity->issueForUser($user, $tenant);

        $this->queueHandoff($request, $this->encryption, $user, $url, $tokens, $tenant);

        // admin-audit-log.md §5: admin entries into tenant apps are evidence.
        if ($this->isAdmin($user)) {
            $this->audit->recordSafe(
                'auth.tenant_entry',
                'tenant',
                $tenant->id,
                ['tenant' => $tenant->slug, 'via' => 'sso'],
            );
        }

        return redirect()->route('auth.redirect');
    }

    private function revokeTokens(?array $tokens): void
    {
        if ($tokens) {
            $this->identity->logout($tokens['refresh_token']);
        }
    }

    private function activeMemberships(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->tenants()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function isAllowedRegistrationDomain(string $email): bool
    {
        $domain = strtolower(substr($email, strrpos($email, '@') + 1));

        return in_array($domain, ['lyceumalabang.edu.ph', 'itmlyceumalabang.onmicrosoft.com'], true);
    }

    private function isAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->inGroup((string) config('auth-web.admin_group'));
    }

    private function resolveRedirect(?string $candidate): ?string
    {
        return $this->safeRedirectUrl($candidate);
    }

    private function resolveTenant(?string $target): ?Tenant
    {
        if (!$target) {
            return null;
        }

        return $this->tenants->resolveTenantByRedirectOrigin($this->extractOrigin($target) ?? '');
    }

    private function extractOrigin(string $url): ?string
    {
        $parts = parse_url($url);

        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = strtolower($parts['scheme']).'://'.strtolower($parts['host']);

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    private function removeFragment(string $url): string
    {
        return explode('#', $url, 2)[0];
    }
}
