<?php

namespace App\Http\Controllers;

use App\Services\PasswordResetNotificationService;
use App\Services\IdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class WebAuthController extends Controller
{
    public function __construct(
        private readonly IdentityService $identity,
        private readonly PasswordResetNotificationService $passwordResetNotifications,
    ) {
    }

    public function showLogin(Request $request): View
    {
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

        try {
            $tokens = $this->identity->login(
                $request->string('email')->toString(),
                $request->string('password')->toString(),
                $request->ip(),
            );
        } catch (\Throwable) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['credentials' => 'Invalid credentials']);
        }

        $target = $this->resolveRedirect($request->input('redirect'));
        $fragment = http_build_query($tokens, '', '&', PHP_QUERY_RFC3986);

        return redirect()->away($this->removeFragment($target).'#'.$fragment);
    }

    public function showRegister(): View
    {
        return view('register');
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

    private function resolveRedirect(?string $candidate): string
    {
        $default = (string) config('auth-web.redirect_url');
        $candidate = is_string($candidate) ? trim($candidate) : '';

        if ($candidate === '' || !filter_var($candidate, FILTER_VALIDATE_URL)) {
            return $default;
        }

        $parts = parse_url($candidate);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return $default;
        }

        $origin = strtolower($parts['scheme']).'://'.strtolower($parts['host']);
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        $allowed = array_map(
            static fn (string $url): string => rtrim(strtolower($url), '/'),
            (array) config('auth-web.allowed_redirects', []),
        );

        if (!in_array($origin, $allowed, true)) {
            return $default;
        }

        return $this->removeFragment($candidate);
    }

    private function removeFragment(string $url): string
    {
        return explode('#', $url, 2)[0];
    }
}
