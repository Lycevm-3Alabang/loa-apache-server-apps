<?php

use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\WebResetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'LOA Auth Platform',
        'version' => '1.0.0',
        'status' => 'running',
    ]);
});

Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [WebAuthController::class, 'login']);

Route::get('/forgot-password', [WebAuthController::class, 'showForgotPassword'])
    ->name('password.forgot');
Route::post('/forgot-password', [WebAuthController::class, 'sendResetLinkEmail'])
    ->middleware('password.reset.throttle');

Route::get('/reset-password', [WebResetController::class, 'showResetForm'])
    ->name('password.reset');
Route::post('/reset-password', [WebResetController::class, 'reset']);
