<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtMiddleware::class,
            'jwt.endpoint' => \App\Http\Middleware\EndpointPolicyMiddleware::class,
            'log.viewer' => \App\Http\Middleware\LogViewerAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($e->getStatusCode() === 413) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Request body exceeds size limit of 10MB. Please compress images or reduce template content size.',
                ], 413);
            }
        });
    })->create();
