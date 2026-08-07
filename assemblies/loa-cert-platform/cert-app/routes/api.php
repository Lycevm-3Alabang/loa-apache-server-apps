<?php

use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('templates')->group(function () {
        Route::get('/', [CertificateTemplateController::class, 'index']);
        Route::post('/', [CertificateTemplateController::class, 'store']);
        Route::get('/{id}', [CertificateTemplateController::class, 'show']);
        Route::patch('/{id}', [CertificateTemplateController::class, 'update']);
        Route::delete('/{id}', [CertificateTemplateController::class, 'destroy']);
    });

    Route::prefix('certificates')->group(function () {
        Route::get('/', [CertificateController::class, 'index']);
        Route::post('/', [CertificateController::class, 'store']);
        Route::post('/bulk', [CertificateController::class, 'bulk']);
        Route::post('/upload', [CertificateController::class, 'upload']);
        Route::get('/qr', [CertificateController::class, 'qr']);
        Route::post('/expire', [CertificateController::class, 'expire']);
        Route::get('/{id}', [CertificateController::class, 'show']);
        Route::get('/{id}/pdf', [CertificateController::class, 'pdf']);
        Route::get('/{id}/download', [CertificateController::class, 'download']);
        Route::post('/{id}/revoke', [CertificateController::class, 'revoke']);
        Route::delete('/{id}', [CertificateController::class, 'destroy']);
        Route::post('/{id}/email', [CertificateController::class, 'email']);
        Route::get('/{id}/email-logs', [CertificateController::class, 'emailLogs']);
        Route::post('/{id}/reissue', [CertificateController::class, 'reissue']);
    });
});
