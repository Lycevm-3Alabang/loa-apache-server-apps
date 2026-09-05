<?php

use App\Http\Controllers\LogViewerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'LOA Cert Platform',
        'status' => 'running',
    ]);
});

Route::prefix('logs')->middleware('log.viewer')->group(function () {
    Route::get('/', [LogViewerController::class, 'index'])->name('logs.index');
    Route::get('/download', [LogViewerController::class, 'download'])->name('logs.download');
    Route::post('/logout', [LogViewerController::class, 'logout'])->name('logs.logout');
});

Route::get('/logs/login', [LogViewerController::class, 'showLogin'])->name('logs.login');
Route::post('/logs/login', [LogViewerController::class, 'postLogin'])->name('logs.login.post');
