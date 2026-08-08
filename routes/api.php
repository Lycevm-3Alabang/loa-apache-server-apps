use App\Http\Controllers\EventController;
use App\Http\Controllers\AttendeeController;

// Existing middleware should be defined here (e.g., Middleware::group(['middleware' => 'jwt.auth', ...]))
Route::prefix('api/v1')->group(function () {
    // --- Events Resource Group (Handling EventCRUD and advanced features) ---
    Route::controller(EventController::class)->prefix('events')->name('events.')->group(function () {
        Route::get('/', 'index'); // GET /api/v1/events (List)
        Route::post('/', 'store'); // POST /api/v1/events (Create)
        Route::get('/{id}', 'show'); // GET /api/v1/events/{id} (Show single)
        Route::patch('/{id}', 'update'); // PATCH /api/v1/events/{id} (Update)
        Route::delete('/{id}', 'destroy'); // DELETE /api/v1/events/{id} (Delete)
        
        // Advanced Endpoints
        Route::get('/{id}/stats', 'stats'); // GET /api/v1/events/{id}/stats
        Route::post('/{id}/clone-template', 'cloneTemplate'); // POST §5.1.7
        Route::post('/{id}/clone-email-template', 'cloneEmailTemplate'); // POST §5.1.8
        Route::post('/{id}/bulk-issue', 'bulkIssue'); // POST §5.1.9
        Route::post('/{id}/reissue', 'reissue'); // POST §5.1.10
        Route::get('/{id}/revoke-expired', 'revokeExpiredCount'); // GET §5.1.11
        Route::post('/{id}/revoke-expired', 'revokeExpired'); // POST §5.1.12
        Route::post('/{id}/issue-completed', 'issueCompleted'); // POST §5.1.13

        // Nested Attendees Route Group (All endpoints under events/{event_id}/attendees)
        Route::prefix('{event_id}/attendees')->controller(AttendeeController::class)->name('events.attendees.')->group(function () {
            Route::get('/', 'index'); // GET §5.2.1
            Route::post('/', 'store'); // POST §5.2.2 (Upsert)
            Route::post('/import', 'import'); // POST §5.2.3
        });
    });

    // --- Attendees Resource Group (Standalone by attendee id / DELETE, PATCH, GET) ---
    Route::controller(AttendeeController::class)->prefix('attendees')->name('attendee.')->group(function () {
        Route::patch('/{id}', 'update'); // PATCH §5.2.4
        Route::delete('/{id}', 'destroy'); // DELETE §5.2.5 (Simple delete)
        Route::delete('/{id}/with-cert', 'destroyWithCertificate'); // DELETE §5.2.6 (Delete with cert)
        Route::get('/{id}/delete-preview', 'deletePreview'); // GET §5.2.7
        Route::get('/{id}/file-data', 'fileData'); // GET §5.2.8 (File/Metadata reader)
    });

    // Add other resource routes here...
});