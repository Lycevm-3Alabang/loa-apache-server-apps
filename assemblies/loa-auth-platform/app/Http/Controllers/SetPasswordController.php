<?php

namespace App\Http\Controllers;

use App\Models\PasswordSetToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SetPasswordController extends Controller
{
    public function show(Request $request): View|string
    {
        $token = $request->query('token', '');

        if ($token === '') {
            return response('Invalid link.', 400);
        }

        $hashed = hash('sha256', $token);

        $record = PasswordSetToken::where('token', $hashed)
            ->whereNull('used_at')
            ->first();

        if (!$record || $record->isExpired()) {
            return response('This link has expired or has already been used.', 410);
        }

        return view('auth.set-password', [
            'token' => $token,
            'email' => $record->user->email,
        ]);
    }

    public function set(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $hashed = hash('sha256', $request->input('token'));

        $record = PasswordSetToken::where('token', $hashed)
            ->whereNull('used_at')
            ->first();

        if (!$record || $record->isExpired()) {
            return back()->withErrors(['token' => 'This link has expired or has already been used.']);
        }

        $user = $record->user;

        $user->update([
            'password' => Hash::make($request->input('password')),
            'status' => 'active',
        ]);

        $record->update(['used_at' => now()]);

        return redirect()->route('login')->with('status', 'Your password has been set. You can now sign in.');
    }
}
