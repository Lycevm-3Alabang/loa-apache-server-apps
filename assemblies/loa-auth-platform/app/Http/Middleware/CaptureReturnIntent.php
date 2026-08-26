<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * dashboard-account.md §6: remembers the requested portal URL when a guest
 * hits an auth-guarded surface, so post-login returns them there instead of
 * the launcher. This is what keeps tenant-app "Change password" deep links
 * working across an expired portal session.
 *
 * Guests are bounced straight from here (not via the auth:web exception)
 * because an AuthenticationException unwinds past StartSession and the
 * captured session write would be discarded. Only same-app relative paths
 * (single leading slash, never another slash or backslash) are ever
 * captured — the consumer in WebAuthController re-validates before
 * redirecting.
 */
class CaptureReturnIntent
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('web')->check()) {
            $path = $request->getPathInfo();

            if (
                $request->isMethod('GET')
                && preg_match('#^/[^/\\\\]#', $path)
            ) {
                $request->session()->put('return_to', $path);
            }

            return redirect()->route('login');
        }

        return $next($request);
    }
}
