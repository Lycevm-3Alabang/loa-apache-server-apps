<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogViewerAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session('log_viewer_authed')) {
            return $next($request);
        }

        if ($request->is('logs/login')) {
            return $next($request);
        }

        return redirect()->route('logs.login');
    }
}
