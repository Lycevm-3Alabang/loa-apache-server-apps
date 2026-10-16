<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RelaySecretMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->bearerToken();
        $expected = config('mail-relay.secret');

        if ($provided === '' || ! is_string($expected) || ! hash_equals($expected, $provided)) {
            Log::channel('mail-relay')->warning('relay outcome=unauthorized', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
