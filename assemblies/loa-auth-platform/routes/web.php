<?php

use App\Http\Controllers\AccessConfigController;
use App\Http\Controllers\EndpointGrantController;
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

Route::get('/sso/login', [WebAuthController::class, 'showSSOLogin'])->name('sso.login');
Route::post('/sso/login', [WebAuthController::class, 'ssoLogin'])
    ->middleware('throttle:10,60');

Route::get('/sso/register', [WebAuthController::class, 'showSSORegister'])->name('sso.register');
Route::post('/sso/register', [WebAuthController::class, 'ssoRegister'])
    ->middleware('throttle:5,60');

Route::get('/activate', [WebAuthController::class, 'showActivate'])->name('activate');
Route::post('/activate', [WebAuthController::class, 'activate'])
    ->middleware('throttle:5,60');

Route::get('/forgot-password', [WebAuthController::class, 'showForgotPassword'])
    ->name('password.forgot');
Route::post('/forgot-password', [WebAuthController::class, 'sendResetLinkEmail'])
    ->middleware('password.reset.throttle');

Route::get('/reset-password', [WebResetController::class, 'showResetForm'])
    ->name('password.reset');
Route::post('/reset-password', [WebResetController::class, 'reset']);

Route::get('/redirect', [WebAuthController::class, 'showRedirect'])
    ->name('auth.redirect');

Route::prefix('admin')->middleware('auth:web', 'web.admin')->group(function () {
    // Bulk User Import (must come before /users/{id} route)
    Route::get('/users/import', [App\Http\Controllers\UserImportController::class, 'showForm'])->name('admin.users.import');
    Route::post('/users/import/preview', [App\Http\Controllers\UserImportController::class, 'preview'])->name('admin.users.import.preview');
    Route::post('/users/import/process', [App\Http\Controllers\UserImportController::class, 'process'])->name('admin.users.import.process');
    Route::get('/users/import/failed', [App\Http\Controllers\UserImportController::class, 'downloadFailed'])->name('admin.users.import.failed');

    Route::post('/users/{id}/resend-activation', [WebAdminController::class, 'resendActivation'])->name('admin.users.resend-activation');
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
    Route::get('/tenants/{tenant}/edit', [WebAdminController::class, 'tenantsEdit'])->name('admin.tenants.edit');
    Route::put('/tenants/{tenant}', [WebAdminController::class, 'tenantsUpdate'])->name('admin.tenants.update');
    Route::post('/tenants/{tenant}/status', [WebAdminController::class, 'tenantsStatus'])->name('admin.tenants.status');
    Route::get('/tenants/{tenant}/groups', [WebAdminController::class, 'tenantsGroups'])->name('admin.tenants.groups');
    Route::post('/tenants/{tenant}/groups', [WebAdminController::class, 'tenantsGroupsStore'])->name('admin.tenants.groups.store');
    
    // NEW: Group detail landing page
    Route::get('/tenants/{tenant}/groups/{group}', [WebAdminController::class, 'tenantsGroupShow'])
        ->name('admin.tenants.group.show');
    
    // NEW: Group endpoints / permissions page (replaces inline)
    Route::get('/tenants/{tenant}/groups/{group}/endpoints', [WebAdminController::class, 'tenantsGroupEndpoints'])
        ->name('admin.tenants.group.endpoints');
    Route::post('/tenants/{tenant}/groups/{group}/endpoints', [WebAdminController::class, 'tenantsGroupsEndpointsStore'])
        ->name('admin.tenants.group.endpoints.save');
    
    // NEW: Group members page
    Route::get('/tenants/{tenant}/groups/{group}/members', [WebAdminController::class, 'tenantsGroupMembers'])
        ->name('admin.tenants.group.members');
    Route::post('/tenants/{tenant}/members', [WebAdminController::class, 'tenantsMembersStore'])->name('admin.tenants.members');

    // Tenant endpoint catalog
    Route::get('/tenants/{tenant}/endpoints', [EndpointGrantController::class, 'catalogIndex'])->name('admin.tenants.endpoints');
    Route::post('/tenants/{tenant}/endpoints', [EndpointGrantController::class, 'catalogStore'])->name('admin.tenants.endpoints.store');
    Route::post('/tenants/{tenant}/endpoints/bulk', [EndpointGrantController::class, 'catalogBulk'])->name('admin.tenants.endpoints.import');
    Route::patch('/tenants/{tenant}/endpoints', [EndpointGrantController::class, 'catalogUpdate'])->name('admin.tenants.endpoints.update');
    Route::delete('/tenants/{tenant}/endpoints', [EndpointGrantController::class, 'catalogDestroy'])->name('admin.tenants.endpoints.destroy');
    Route::get('/tenants/{tenant}/endpoints/validate', [EndpointGrantController::class, 'catalogValidate'])->name('admin.tenants.endpoints.validate');

    // User endpoint overrides
    Route::get('/users/{id}/endpoint-overrides', [EndpointGrantController::class, 'userOverridesIndex'])->name('admin.users.endpoint-overrides');
    Route::post('/users/{id}/endpoint-overrides', [EndpointGrantController::class, 'userOverrideStore'])->name('admin.users.endpoint-overrides.upsert');
    Route::delete('/users/{id}/endpoint-overrides', [EndpointGrantController::class, 'userOverrideDestroy'])->name('admin.users.endpoint-overrides.delete');

    // Blade views for endpoint catalog and grants
    Route::get('/tenants/{tenant}/endpoints/manage', [WebAdminController::class, 'tenantsEndpoints'])->name('admin.tenants.endpoints.manage');
    Route::post('/tenants/{tenant}/endpoints/manage', [WebAdminController::class, 'tenantsEndpointsStore'])->name('admin.tenants.endpoints.manage.store');
    Route::delete('/tenants/{tenant}/endpoints/manage', [WebAdminController::class, 'tenantsEndpointsDestroy'])->name('admin.tenants.endpoints.manage.destroy');
    Route::get('/tenants/{tenant}/endpoints/export', [WebAdminController::class, 'tenantsEndpointsExport'])->name('admin.tenants.endpoints.export');
    Route::get('/tenants/{tenant}/endpoints/import', [WebAdminController::class, 'tenantsEndpointsImportForm'])->name('admin.tenants.endpoints.import.manage');
    Route::post('/tenants/{tenant}/endpoints/import', [WebAdminController::class, 'tenantsEndpointsImport'])->name('admin.tenants.endpoints.import.manage.store');

    Route::get('/tenants/{tenant}/groups/{group}/endpoints/manage', [WebAdminController::class, 'tenantsGroupsEndpoints'])->name('admin.tenants.groups.endpoints.manage');
    Route::post('/tenants/{tenant}/groups/{group}/endpoints/manage', [WebAdminController::class, 'tenantsGroupsEndpointsStore'])->name('admin.tenants.groups.endpoints.manage.store');

    Route::get('/users/{id}/endpoint-overrides/manage', [WebAdminController::class, 'usersEndpointOverrides'])->name('admin.users.endpoint-overrides.manage');
    Route::post('/users/{id}/endpoint-overrides/manage', [WebAdminController::class, 'usersEndpointOverridesStore'])->name('admin.users.endpoint-overrides.manage.store');

    // Access config import/export
    Route::get('/tenants/{tenant}/access-config/template', [AccessConfigController::class, 'template'])->name('admin.tenants.access-config.template');
    Route::get('/tenants/{tenant}/access-config/export', [AccessConfigController::class, 'export'])->name('admin.tenants.access-config.export');
    Route::get('/tenants/{tenant}/access-config/import', [AccessConfigController::class, 'importForm'])->name('admin.tenants.access-config.import');
    Route::post('/tenants/{tenant}/access-config/import', [AccessConfigController::class, 'import'])->name('admin.tenants.access-config.import.store');
});
