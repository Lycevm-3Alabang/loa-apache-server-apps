<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivationService;
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
    ) {
    }

    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('admin.users');
        }

        return view('login', [
            'redirect' => $this->resolveRedirect($request->query('redirect')),
        ]);
    }

    public function login(Request $request): RedirectResponse
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
        $tenant = $this->resolveTenant($target);

        try {
            $tokens = $this->identity->login(
                $request->string('email')->toString(),
                $request->string('password')->toString(),
                $request->ip(),
                $tenant,
            );
        } catch (\Throwable) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['credentials' => 'Invalid credentials']);
        }

        $user = User::where('email', $request->string('email')->toString())->first();

        if ($this->isAdmin($user)) {
            $this->identity->logout($tokens['refresh_token']);

            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            return redirect()->route('admin.users');
        }

        if (!$tenant || !$this->tenants->isMember($user->id, $tenant->id)) {
            $this->identity->logout($tokens['refresh_token']);

            return back()
                ->withInput($request->except('password'))
                ->withErrors(['credentials' => 'Invalid credentials']);
        }

        $fragment = http_build_query($tokens, '', '&', PHP_QUERY_RFC3986);

        if ($this->encryption->isConfigured()) {
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
                'tenant' => $tenant ? [
                    'id' => $tenant->id,
                    'slug' => $tenant->slug,
                ] : null,
                'iat' => time(),
                'exp' => time() + $tokens['expires_in'],
            ];

            $encrypted = $this->encryption->encrypt($payload);

            $request->session()->put('redirect_payload', $encrypted);
        } else {
            $fragment = http_build_query($tokens, '', '&', PHP_QUERY_RFC3986);

            $request->session()->put('redirect_fragment', $fragment);
        }

        $request->session()->put('redirect_url', $target);

        return redirect()->route('auth.redirect');
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
            $user = $this->activation->activate($request->input('token'));
            
            // Redirect to login with success message
            return redirect()->route('login')->with('status', 'Account activated. Please sign in.');
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

    public function showForgotPassword(): View
    {
        return view('forgot-password');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $this->passwordResetNotifications->sendForgotPasswordLink(
            $request->string('email')->toString(),
        );

        return back()->with('status', 'If the email exists, a reset link has been sent.');
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
        $candidate = is_string($candidate) ? trim($candidate) : '';

        if ($candidate === '' || !filter_var($candidate, FILTER_VALIDATE_URL)) {
            return null;
        }

        $origin = $this->extractOrigin($candidate);

        if ($origin === null) {
            return null;
        }

        if ($this->tenants->resolveTenantByRedirectOrigin($origin)) {
            return $this->removeFragment($candidate);
        }

        if (in_array($origin, $this->allowedOrigins(), true)) {
            return $this->removeFragment($candidate);
        }

        return null;
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

    private function allowedOrigins(): array
    {
        return array_map(
            static fn (string $url): string => rtrim(strtolower($url), '/'),
            (array) config('auth-web.allowed_redirects', []),
        );
    }

    private function removeFragment(string $url): string
    {
        return explode('#', $url, 2)[0];
    }
}
