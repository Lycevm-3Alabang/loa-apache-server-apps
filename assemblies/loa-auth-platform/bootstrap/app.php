<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        web: __DIR__.'/../routes/web.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtMiddleware::class,
            'jwt.permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'jwt.claim-policy' => \App\Http\Middleware\ClaimPolicyMiddleware::class,
            'jwt.tenant' => \App\Http\Middleware\JwtTenantMiddleware::class,
            'password.reset.throttle' => \App\Http\Middleware\PasswordResetThrottle::class,
            'web.admin' => \App\Http\Middleware\WebAdminMiddleware::class,
            'capture.return' => \App\Http\Middleware\CaptureReturnIntent::class,
        ]);

        // dashboard-account.md §6: the return-intent capture must observe the
        // guest request BEFORE auth:web throws its AuthenticationException
        // (an exception unwinds past StartSession and the captured session
        // write would be discarded). Authenticate sits on the framework's
        // middleware-priority list, so the capture has to be prioritized too.
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\CaptureReturnIntent::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Auth forms never show the raw 419 page (web-ui.md §4.0): re-render
        // the originating form with a fresh CSRF token instead. The framework
        // converts TokenMismatchException to HttpException(419) before render
        // callbacks run, so we must match on the converted exception.
        $exceptions->render(function (Symfony\Component\HttpKernel\Exception\HttpException $e, Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'CSRF token mismatch.'], 419);
            }

            $target = match (true) {
                $request->is('login') => '/login',
                $request->is('sso/*') => '/sso/login',
                $request->is('forgot-password') => '/forgot-password',
                $request->is('reset-password') => '/reset-password?'.http_build_query(array_filter([
                    'token' => (string) $request->input('token'),
                    'email' => (string) $request->input('email'),
                ])),
                default => null,
            };

            $redirect = $target !== null ? redirect()->to($target) : redirect()->back();

            return $redirect
                ->withInput($request->except('password', 'password_confirmation', '_token'))
                ->withErrors(['session' => 'Your session has expired. Please try again.']);
        });
    })->create();
