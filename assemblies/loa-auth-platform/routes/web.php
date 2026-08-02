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

Route::get('/redirect', [WebAuthController::class, 'showRedirect'])
    ->name('auth.redirect')
    ->middleware('auth:web');

Route::prefix('admin')->middleware('auth:web', 'web.admin')->group(function () {
    Route::get('/users', [WebAdminController::class, 'index'])->name('admin.users');
    Route::get('/users/create', [WebAdminController::class, 'create'])->name('admin.users.create');
    Route::post('/users', [WebAdminController::class, 'store'])->name('admin.users.store');
    Route::get('/users/{id}', [WebAdminController::class, 'showUser'])->name('admin.users.show');
    Route::post('/users/{id}/groups', [WebAdminController::class, 'storeUserGroup'])->name('admin.users.groups.store');
    Route::post('/users/{id}/groups/{groupId}/remove', [WebAdminController::class, 'removeUserGroup'])->name('admin.users.groups.remove');
    Route::post('/users/{id}/permissions', [WebAdminController::class, 'storeUserPermission'])->name('admin.users.permissions.store');
    Route::post('/users/{id}/permissions/{key}/remove', [WebAdminController::class, 'removeUserPermission'])->name('admin.users.permissions.remove');
    Route::post('/users/{id}/status', [WebAdminController::class, 'updateStatus'])->name('admin.users.status');
    Route::post('/logout', [WebAdminController::class, 'logout'])->name('admin.logout');

    Route::get('/groups', [WebAdminController::class, 'groupsIndex'])->name('admin.groups');
    Route::get('/groups/create', [WebAdminController::class, 'groupsCreate'])->name('admin.groups.create');
    Route::post('/groups', [WebAdminController::class, 'groupsStore'])->name('admin.groups.store');
    Route::get('/groups/{group}', [WebAdminController::class, 'groupsShow'])->name('admin.groups.show');
    Route::post('/groups/{group}/permissions', [WebAdminController::class, 'groupsPermissions'])->name('admin.groups.permissions');
    Route::post('/groups/{group}/members', [WebAdminController::class, 'groupsMembersStore'])->name('admin.groups.members.store');
    Route::post('/groups/{group}/members/{userId}/remove', [WebAdminController::class, 'groupsMembersRemove'])->name('admin.groups.members.remove');

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
