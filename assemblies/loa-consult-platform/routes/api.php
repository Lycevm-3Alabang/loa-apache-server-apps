<?php

use App\Http\Controllers\AcademicController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\EvaluationPeriodController;
use App\Http\Controllers\EvaluationResultController;
use App\Http\Controllers\AuthCallbackController;
use App\Http\Controllers\AuthLogoutController;
use App\Http\Controllers\AuthRefreshController;
use App\Http\Controllers\AvailabilityRuleController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\RubricGroupController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\UserLinkController;
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

        // Evaluations disabled set (static BEFORE {id} — else 'disabled' matches {id})
        Route::get('/evaluations/disabled', [EvaluationResultController::class, 'disabled']);
        Route::delete('/evaluations/disabled', [EvaluationResultController::class, 'deleteDisabled']);
        Route::post('/evaluations/disabled/restore', [EvaluationResultController::class, 'restore']);

        // Evaluations (static paths before {id})
        Route::get('/evaluations/pending', [EvaluationController::class, 'pending']);
        Route::get('/evaluations/bootstrap', [EvaluationController::class, 'bootstrap']);
        Route::get('/evaluations', [EvaluationController::class, 'index']);
        Route::post('/evaluations', [EvaluationController::class, 'store']);
        Route::post('/evaluations/dispute', [EvaluationController::class, 'dispute']);
        Route::get('/evaluations/{id}', [EvaluationController::class, 'show']);
        Route::get('/evaluations/{id}/ratings', [EvaluationController::class, 'ratings']);
        Route::put('/evaluations/{id}/ratings', [EvaluationController::class, 'saveRatings']);
        Route::get('/evaluations/{id}/comments', [EvaluationController::class, 'comment']);
        Route::post('/evaluations/{id}/comments', [EvaluationController::class, 'storeComment']);
        Route::post('/evaluations/{id}/submit', [EvaluationController::class, 'submit']);
        Route::get('/evaluation-comments', [EvaluationController::class, 'allComments']);

        // Evaluation results — single scoped surface (flat; group-driven per URL flattening Final v1.0)
        Route::get('/evaluation-results', [EvaluationResultController::class, 'index']);
        Route::get('/evaluation-results/department', [EvaluationResultController::class, 'deanDepartment']);
        Route::get('/evaluation-results/details', [EvaluationResultController::class, 'breakdowns']);
        Route::get('/evaluation-results/subjects', [EvaluationResultController::class, 'facultySubjects']);
        Route::get('/evaluation-results/subjects/{facultySubjectId}', [EvaluationResultController::class, 'facultySubjectShow']);
        Route::get('/evaluation-results/departments/{departmentId}', [EvaluationResultController::class, 'department']);
        Route::get('/evaluation-results/faculty/{facultyId}', [EvaluationResultController::class, 'faculty']);
        Route::get('/evaluation-results/groups/{facultySubjectId}', [EvaluationResultController::class, 'group']);
        Route::post('/evaluation-results/invalidate', [EvaluationResultController::class, 'invalidate']);
        Route::post('/evaluation-results/visibility', [EvaluationResultController::class, 'visibility']);

        // Single-evaluation assembly (2-segment, no {id} collision)
        Route::get('/evaluations/{evaluationId}/details', [EvaluationResultController::class, 'details']);
        Route::post('/evaluations/{evaluationId}/invalidate', [EvaluationResultController::class, 'invalidateOne']);

        // User link reads (Phase D DEC-1: Auth-owned users, consult link only)
        Route::get('/users/primary', [UserLinkController::class, 'primary']);
        Route::get('/users/attendees', [UserLinkController::class, 'attendees']);

        // Import domain-only (Phase D DEC-2: users/reference lives in Auth, absent here)
        Route::post('/import/preview', [ImportController::class, 'preview']);
        Route::get('/import/departments-courses/reference', [ImportController::class, 'referenceCourses']);
        Route::get('/import/faculties/reference', [ImportController::class, 'referenceFaculties']);
        Route::get('/import/students/reference', [ImportController::class, 'referenceStudents']);
        Route::get('/import/students', [ImportController::class, 'listStudents']);
        Route::get('/import/subjects/reference', [ImportController::class, 'referenceSubjects']);
        Route::get('/import/sections/reference', [ImportController::class, 'referenceSections']);
        Route::post('/import/departments-courses', [ImportController::class, 'importDomain'])->defaults('domain', 'departments-courses');
        Route::post('/import/faculties', [ImportController::class, 'importDomain'])->defaults('domain', 'faculties');
        Route::post('/import/students', [ImportController::class, 'importDomain'])->defaults('domain', 'students');

        // Data & audit (Phase D DEC-3: audit-logs absent — no table yet, data-model gate holds)
        Route::post('/data/delete-students', [DataController::class, 'deleteStudents']);
        Route::post('/data/export-consultations', [DataController::class, 'exportConsultations']);
        Route::post('/data/reset-db', [DataController::class, 'resetDb']);
        Route::get('/data/evaluation-mappings', [DataController::class, 'evaluationMappings']);

        // Semesters
        Route::get('/semesters', [SemesterController::class, 'index']);
        Route::post('/semesters', [SemesterController::class, 'store']);
        Route::get('/semesters/{id}', [SemesterController::class, 'show']);
        Route::patch('/semesters/{id}', [SemesterController::class, 'update']);
        Route::delete('/semesters/{id}', [SemesterController::class, 'destroy']);
        Route::post('/semesters/{id}', [SemesterController::class, 'activate']);
        Route::get('/semesters/{id}/impacts', [SemesterController::class, 'impacts']);

        // Academic (flat resources — URL flattening Final v1.0)
        // User link read (Phase D DEC-1: related-data only, no user writes)
        Route::get('/users/{id}/related-data', [UserLinkController::class, 'relatedData']);

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
