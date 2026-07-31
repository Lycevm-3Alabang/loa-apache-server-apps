<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class PasswordResetThrottle
{
    private const MESSAGE = 'If the email exists, a reset link has been sent.';

    public function handle(Request $request, Closure $next): Response
    {
        $email = strtolower(trim((string) $request->input('email')));
        $key = 'password-reset:'.hash('sha256', $email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 1)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => self::MESSAGE]);
            }

            return redirect()
                ->route('password.forgot')
                ->with('status', self::MESSAGE);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
