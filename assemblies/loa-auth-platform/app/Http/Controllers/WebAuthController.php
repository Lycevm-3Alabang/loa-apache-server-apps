<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivationService;
use App\Services\IdentityService;
use App\Services\PasswordResetNotificationService;
use App\Services\PortalRouter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class WebAuthController extends Controller
{
    public function __construct(
        private readonly IdentityService $identity,
        private readonly PasswordResetNotificationService $passwordResetNotifications,
        private readonly PortalRouter $router,
        private readonly ActivationService $activation,
    ) {
    }

    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return $this->router->route(
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
                $this->router->resolveTenant($target),
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

        // dashboard-account.md §6: an explicit tenant intent outranks a
        // captured return path (which is discarded); otherwise deliver the
        // user to the internal portal URL they were bounced from (e.g.
        // tenant-app change-password deep link across an expired session).
        $returnTo = $this->consumeReturnIntent($request);

        if ($target === null && $returnTo !== null) {
            return redirect()->to($returnTo);
        }

        return $this->router->route($request, $user, $target, $tokens);
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

            if (($returnTo = $this->consumeReturnIntent($request))) {
                return redirect()->to($returnTo);
            }

            return $this->router->route($request, $user, null, null);
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
                return $this->router->route(
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
     * Destination resolver shared by login, activation and authenticated
     * GETs lives in PortalRouter (unified-auth-flow.md §5,
     * dashboard-account.md §3).
     */

    private function isAllowedRegistrationDomain(string $email): bool
    {
        $domain = strtolower(substr($email, strrpos($email, '@') + 1));

        return in_array($domain, ['lyceumalabang.edu.ph', 'itmlyceumalabang.onmicrosoft.com'], true);
    }

    /**
     * dashboard-account.md §6: consumes the pending internal-path return
     * intent captured for guests bounced off protected portal URLs. Only
     * same-app relative paths (single leading slash, never another slash or
     * backslash) ever qualify — open-redirect proof.
     */
    private function consumeReturnIntent(Request $request): ?string
    {
        $path = $request->session()->pull('return_to');

        return is_string($path) && preg_match('#^/[^/\\\\]#', $path) ? $path : null;
    }

    private function resolveRedirect(?string $candidate): ?string
    {
        return $this->safeRedirectUrl($candidate);
    }

    private function removeFragment(string $url): string
    {
        return explode('#', $url, 2)[0];
    }
}
