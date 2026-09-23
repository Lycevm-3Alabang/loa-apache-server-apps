<?php

use App\Http\Controllers\AcademicController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\EvaluationPeriodController;
use App\Http\Controllers\AuthCallbackController;
use App\Http\Controllers\AuthLogoutController;
use App\Http\Controllers\AuthRefreshController;
use App\Http\Controllers\AvailabilityRuleController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RubricGroupController;
use App\Http\Controllers\SemesterController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', [HealthController::class, 'show']);

    // Auth SSO trio (public; callback/refresh throttled 10/min)
    Route::prefix('auth')->group(function () {
        Route::post('/callback', [AuthCallbackController::class, '__invoke'])
            ->middleware('throttle:10,1');
        Route::post('/refresh', [AuthRefreshController::class, '__invoke'])
            ->middleware('throttle:10,1');
        Route::post('/logout', [AuthLogoutController::class, '__invoke']);
    });

    // Public semester read (catalog public)
    Route::get('/semesters/count-active', [SemesterController::class, 'countActive']);
    // Everything below requires a valid JWT + endpoint level (§11 Step 7)
    Route::middleware(['jwt.auth', 'jwt.endpoint'])->group(function () {
        // Availability
        Route::get('/availability-rules', [AvailabilityRuleController::class, 'index']);
        Route::post('/availability-rules', [AvailabilityRuleController::class, 'store']);

        // Appointments (static paths before {id})
        Route::get('/appointments/faculty-booked', [AppointmentController::class, 'facultyBooked']);
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::post('/appointments/batch', [AppointmentController::class, 'batch']);
        Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
        Route::post('/appointments/{id}/files', [AppointmentController::class, 'files']);
        Route::post('/appointments/{id}/retry-sync', [AppointmentController::class, 'retrySync']);
        Route::post('/appointments/{id}/student-cancel', [AppointmentController::class, 'studentCancel']);
        Route::post('/appointments/{id}/{action}', [AppointmentController::class, 'action']);
        Route::post('/appointments/slots/{slotId}/teams-link', [AppointmentController::class, 'slotTeamsLink']);

        // Evaluation periods
        Route::get('/evaluation-periods', [EvaluationPeriodController::class, 'index']);
        Route::post('/evaluation-periods', [EvaluationPeriodController::class, 'store']);
        Route::get('/evaluation-periods/{id}', [EvaluationPeriodController::class, 'show']);
        Route::put('/evaluation-periods/{id}', [EvaluationPeriodController::class, 'update']);
        Route::delete('/evaluation-periods/{id}', [EvaluationPeriodController::class, 'destroy']);
        Route::post('/evaluation-periods/{id}/activate', [EvaluationPeriodController::class, 'activate']);
        Route::post('/evaluation-periods/{id}/reset', [EvaluationPeriodController::class, 'reset']);
        Route::get('/evaluation-periods/{id}/rubric', [EvaluationPeriodController::class, 'rubric']);
        Route::post('/evaluation-periods/{id}/rubric/copy', [EvaluationPeriodController::class, 'rubricCopy']);
        Route::post('/evaluation-periods/{id}/rubrics/items', [EvaluationPeriodController::class, 'storeItem']);
        Route::patch('/evaluation-periods/{id}/rubrics/items/{itemId}', [EvaluationPeriodController::class, 'updateItem']);
        Route::delete('/evaluation-periods/{id}/rubrics/items/{itemId}', [EvaluationPeriodController::class, 'destroyItem']);

        // Rubric groups
        Route::get('/rubric-groups', [RubricGroupController::class, 'index']);
        Route::post('/rubric-groups', [RubricGroupController::class, 'store']);
        Route::get('/rubric-groups/{id}', [RubricGroupController::class, 'show']);
        Route::patch('/rubric-groups/{id}', [RubricGroupController::class, 'update']);
        Route::delete('/rubric-groups/{id}', [RubricGroupController::class, 'destroy']);
        Route::post('/rubric-groups/{id}/items', [RubricGroupController::class, 'storeItem']);
        Route::patch('/rubric-groups/{id}/items/{itemId}', [RubricGroupController::class, 'updateItem']);
        Route::delete('/rubric-groups/{id}/items/{itemId}', [RubricGroupController::class, 'destroyItem']);
        Route::post('/rubric-groups/{id}/duplicate', [RubricGroupController::class, 'duplicate']);
        Route::get('/rubric-groups/{id}/snapshot', [RubricGroupController::class, 'snapshot']);
        Route::post('/rubric-groups/{id}/categories', [RubricGroupController::class, 'storeCategory']);
        Route::delete('/rubric-groups/{id}/categories', [RubricGroupController::class, 'destroyCategory']);

        // Semesters
        Route::get('/semesters', [SemesterController::class, 'index']);
        Route::post('/semesters', [SemesterController::class, 'store']);
        Route::get('/semesters/{id}', [SemesterController::class, 'show']);
        Route::patch('/semesters/{id}', [SemesterController::class, 'update']);
        Route::delete('/semesters/{id}', [SemesterController::class, 'destroy']);
        Route::post('/semesters/{id}', [SemesterController::class, 'activate']);
        Route::get('/semesters/{id}/impacts', [SemesterController::class, 'impacts']);

        // Academic admin
        Route::prefix('admin')->group(function () {
            // Departments
            Route::get('/departments', [AcademicController::class, 'index']);
            Route::post('/departments', [AcademicController::class, 'store']);
            Route::patch('/departments/{id}', [AcademicController::class, 'update']);

            // Department Courses
            Route::get('/department-courses', [AcademicController::class, 'indexCourses']);
            Route::post('/department-courses', [AcademicController::class, 'storeCourse']);
            Route::delete('/department-courses/{id}', [AcademicController::class, 'destroyCourse']);

            // Subjects
            Route::post('/subjects', [AcademicController::class, 'storeSubject']);
            Route::patch('/subjects/{id}', [AcademicController::class, 'updateSubject']);

            // Sections
            Route::post('/sections', [AcademicController::class, 'storeSection']);
            Route::patch('/sections/{id}', [AcademicController::class, 'updateSection']);
            Route::post('/sections/fix-names', [AcademicController::class, 'fixNames']);

            // Faculty-Subject Mappings
            Route::post('/faculty-subjects', [AcademicController::class, 'storeFacultySubject']);
            Route::post('/faculty-subjects/reassign', [AcademicController::class, 'reassignFacultySubject']);

            // Student Enrollments
            Route::post('/student-enrollments', [AcademicController::class, 'storeEnrollment']);
            Route::delete('/student-enrollments/{id}', [AcademicController::class, 'destroyEnrollment']);
        });
    });
});
