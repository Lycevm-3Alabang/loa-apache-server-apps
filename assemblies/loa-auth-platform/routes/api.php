<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [App\Http\Controllers\AuthController::class, 'register']);
        Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);
        Route::post('/refresh', [App\Http\Controllers\AuthController::class, 'refresh']);
        Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout']);
        Route::get('/me', [App\Http\Controllers\AuthController::class, 'me'])->middleware('jwt.auth');
        Route::put('/password', [App\Http\Controllers\AuthController::class, 'updatePassword'])->middleware('jwt.auth');
        Route::post('/password/forgot', [App\Http\Controllers\AuthController::class, 'forgotPassword'])
            ->middleware('password.reset.throttle');
        Route::post('/password/change-request', [App\Http\Controllers\AuthController::class, 'changePasswordRequest'])
            ->middleware('jwt.auth');
        Route::post('/password/reset', [App\Http\Controllers\AuthController::class, 'resetPassword']);
        Route::get('/verify', [App\Http\Controllers\AuthController::class, 'verify']);
    });

    Route::prefix('users')->middleware(['jwt.auth', 'jwt.permission:users.view'])->group(function () {
        Route::get('/', [App\Http\Controllers\UserController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\UserController::class, 'show']);
    });

    Route::prefix('users')->middleware(['jwt.auth', 'jwt.permission:users.manage'])->group(function () {
        Route::patch('/{id}/status', [App\Http\Controllers\UserController::class, 'updateStatus']);
    });
});
