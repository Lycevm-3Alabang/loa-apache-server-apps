<?php

use App\Http\Controllers\AttendeeController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateTemplateController;
use App\Http\Controllers\EventController;
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

    Route::prefix('events')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::post('/', [EventController::class, 'store']);
        Route::get('/{id}', [EventController::class, 'show']);
        Route::patch('/{id}', [EventController::class, 'update']);
        Route::delete('/{id}', [EventController::class, 'destroy']);
        Route::get('/{id}/stats', [EventController::class, 'stats']);
        Route::get('/{id}/revoke-expired', [EventController::class, 'revokeExpiredCount']);
        Route::post('/{id}/revoke-expired', [EventController::class, 'revokeExpired']);
        Route::post('/{id}/clone-template', [EventController::class, 'cloneTemplate']);
        Route::post('/{id}/clone-email-template', [EventController::class, 'cloneEmailTemplate']);
        Route::post('/{id}/bulk-issue', [EventController::class, 'bulkIssue']);
        Route::post('/{id}/reissue', [EventController::class, 'reissue']);
        Route::post('/{id}/issue-completed', [EventController::class, 'issueCompleted']);

        Route::prefix('{eventId}/attendees')->group(function () {
            Route::get('/', [AttendeeController::class, 'index']);
            Route::post('/', [AttendeeController::class, 'store']);
            Route::post('/import', [AttendeeController::class, 'import']);
        });
    });

    Route::prefix('attendees')->group(function () {
        Route::patch('/{id}', [AttendeeController::class, 'update']);
        Route::delete('/{id}', [AttendeeController::class, 'destroy']);
        Route::delete('/{id}/with-cert', [AttendeeController::class, 'destroyWithCert']);
        Route::get('/{id}/delete-preview', [AttendeeController::class, 'deletePreview']);
        Route::get('/{id}/file-data', [AttendeeController::class, 'fileData']);
    });
});
