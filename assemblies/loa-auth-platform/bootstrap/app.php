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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
