<?php

use App\Http\Controllers\WebAdminController;
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

Route::get('/register', [WebAuthController::class, 'showRegister'])->name('register');
Route::post('/register', [WebAuthController::class, 'register'])
    ->middleware('throttle:5,60');

Route::get('/forgot-password', [WebAuthController::class, 'showForgotPassword'])
    ->name('password.forgot');
Route::post('/forgot-password', [WebAuthController::class, 'sendResetLinkEmail'])
    ->middleware('password.reset.throttle');

Route::get('/reset-password', [WebResetController::class, 'showResetForm'])
    ->name('password.reset');
Route::post('/reset-password', [WebResetController::class, 'reset']);

Route::prefix('admin')->middleware('auth:web', 'web.admin')->group(function () {
    Route::get('/users', [WebAdminController::class, 'index'])->name('admin.users');
    Route::get('/users/create', [WebAdminController::class, 'create'])->name('admin.users.create');
    Route::post('/users', [WebAdminController::class, 'store'])->name('admin.users.store');
    Route::post('/users/{id}/status', [WebAdminController::class, 'updateStatus'])->name('admin.users.status');
    Route::post('/logout', [WebAdminController::class, 'logout'])->name('admin.logout');

    // Tenant management (v2)
    Route::get('/tenants', [WebAdminController::class, 'tenantsIndex'])->name('admin.tenants');
    Route::get('/tenants/create', [WebAdminController::class, 'tenantsCreate'])->name('admin.tenants.create');
    Route::post('/tenants', [WebAdminController::class, 'tenantsStore'])->name('admin.tenants.store');
    Route::get('/tenants/{tenant}', [WebAdminController::class, 'tenantsShow'])->name('admin.tenants.show');
    Route::post('/tenants/{tenant}/status', [WebAdminController::class, 'tenantsStatus'])->name('admin.tenants.status');
    Route::get('/tenants/{tenant}/groups', [WebAdminController::class, 'tenantsGroups'])->name('admin.tenants.groups');
    Route::post('/tenants/{tenant}/groups', [WebAdminController::class, 'tenantsGroupsStore'])->name('admin.tenants.groups.store');
    Route::post('/tenants/{tenant}/groups/{group}/permissions', [WebAdminController::class, 'tenantsGroupsPermissions'])->name('admin.tenants.groups.permissions');
    Route::post('/tenants/{tenant}/members', [WebAdminController::class, 'tenantsMembersStore'])->name('admin.tenants.members');
});
