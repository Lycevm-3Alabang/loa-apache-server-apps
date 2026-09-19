<?php

use App\Http\Controllers\AcademicController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\SemesterController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', [HealthController::class, 'show']);

    // Semesters (public read, admin write)
    Route::get('/semesters/count-active', [SemesterController::class, 'countActive']);
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
