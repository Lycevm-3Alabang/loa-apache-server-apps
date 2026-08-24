<?php

namespace App\Http\Controllers;

use App\Services\IdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        // Return the user to the tenant app that started the flow when the
        // origin is allowlisted; otherwise fall back to the login page (§4.3a).
        $target = $this->safeRedirectUrl($request->input('redirect'));

        if ($target !== null) {
            return redirect()->away($target);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Password updated. Please sign in.');
    }
}
