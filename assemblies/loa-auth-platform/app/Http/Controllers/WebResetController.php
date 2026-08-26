<?php

namespace App\Http\Controllers;

use App\Services\IdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class WebResetController extends Controller
{
    public function __construct(private readonly IdentityService $identity)
    {
    }

    public function showResetForm(Request $request): View
    {
        return view('reset-password', [
            'token' => (string) $request->query('token', ''),
            'email' => (string) $request->query('email', ''),
            'redirect' => (string) $request->query('redirect', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
            'redirect' => 'nullable|string',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
            ],
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors($validator);
        }

        try {
            $this->identity->resetPassword(
                $request->string('token')->toString(),
                $request->string('password')->toString(),
            );
        } catch (\Throwable) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['token' => 'Invalid or expired token.']);
        }

        // D18 (dashboard-account.md v1.3): a completed reset signs the user
        // out of every LOA surface — resetPassword() already revoked all
        // refresh tokens; end this browser's portal session too. Harmless
        // no-op for the guest forgot-password flow (no session to kill).
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Return the user to the tenant app that started the flow when the
        // origin is allowlisted; otherwise fall back to the login page (§4.3a).
        // `redirect` is request input, so reading it after invalidation is safe.
        $target = $this->safeRedirectUrl($request->input('redirect'));

        if ($target !== null) {
            return redirect()->away($target);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Password updated. Please sign in.');
    }
}
