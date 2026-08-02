<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [App\Http\Controllers\AuthController::class, 'register']);
        Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);
        Route::post('/refresh', [App\Http\Controllers\AuthController::class, 'refresh']);
        Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout']);
        Route::get('/me', [App\Http\Controllers\AuthController::class, 'me'])->middleware('jwt.auth');
        Route::put('/password', [App\Http\Controllers\AuthController::class, 'updatePassword'])->middleware('jwt.auth');
        Route::post('/password/forgot', [App\Http\Controllers\AuthController::class, 'forgotPassword'])
            ->middleware('password.reset.throttle');
        Route::post('/password/change-request', [App\Http\Controllers\AuthController::class, 'changePasswordRequest'])
            ->middleware('jwt.auth');
        Route::post('/password/reset', [App\Http\Controllers\AuthController::class, 'resetPassword']);
        Route::get('/verify', [App\Http\Controllers\AuthController::class, 'verify']);
    });

    Route::prefix('users')->middleware(['jwt.auth', 'jwt.permission:users.view'])->group(function () {
        Route::get('/', [App\Http\Controllers\UserController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\UserController::class, 'show']);
    });

    Route::prefix('users')->middleware(['jwt.auth', 'jwt.permission:users.manage'])->group(function () {
        Route::patch('/{id}/status', [App\Http\Controllers\UserController::class, 'updateStatus']);
    });

    Route::middleware(['jwt.auth', 'jwt.permission:users.manage'])->group(function () {
        Route::get('/groups', [App\Http\Controllers\GroupController::class, 'index']);
        Route::post('/groups', [App\Http\Controllers\GroupController::class, 'store']);
        Route::delete('/groups/{id}', [App\Http\Controllers\GroupController::class, 'destroy']);
        Route::get('/groups/{id}/permissions', [App\Http\Controllers\GroupController::class, 'showPermissions']);
        Route::post('/groups/{id}/permissions', [App\Http\Controllers\GroupController::class, 'syncPermissions']);

        Route::get('/users/{id}/groups', [App\Http\Controllers\UserGroupController::class, 'indexGroups']);
        Route::post('/users/{id}/groups', [App\Http\Controllers\UserGroupController::class, 'addGroup']);
        Route::delete('/users/{id}/groups/{groupId}', [App\Http\Controllers\UserGroupController::class, 'removeGroup']);
        Route::get('/users/{id}/permissions', [App\Http\Controllers\UserGroupController::class, 'showPermissions']);
        Route::post('/users/{id}/permissions', [App\Http\Controllers\UserGroupController::class, 'grantPermission']);
        Route::delete('/users/{id}/permissions/{permissionKey}', [App\Http\Controllers\UserGroupController::class, 'revokePermission']);
    });

    Route::prefix('admin/permissions')->middleware(['jwt.auth', 'jwt.permission:users.manage'])->group(function () {
        Route::get('/claims', [App\Http\Controllers\PermissionPolicyController::class, 'claimsIndex']);
        Route::post('/claims', [App\Http\Controllers\PermissionPolicyController::class, 'claimsStore']);
        Route::put('/claims/{claim}', [App\Http\Controllers\PermissionPolicyController::class, 'claimsUpdate']);
        Route::delete('/claims/{claim}', [App\Http\Controllers\PermissionPolicyController::class, 'claimsDestroy']);

        Route::get('/policies', [App\Http\Controllers\PermissionPolicyController::class, 'routePoliciesIndex']);
        Route::post('/policies', [App\Http\Controllers\PermissionPolicyController::class, 'routePoliciesStore']);
        Route::put('/policies/{policy}', [App\Http\Controllers\PermissionPolicyController::class, 'routePoliciesUpdate']);
        Route::delete('/policies/{policy}', [App\Http\Controllers\PermissionPolicyController::class, 'routePoliciesDestroy']);

        Route::get('/group-claims', [App\Http\Controllers\PermissionPolicyController::class, 'groupClaimsIndex']);
        Route::post('/group-claims', [App\Http\Controllers\PermissionPolicyController::class, 'groupClaimsStore']);
        Route::delete('/group-claims/{groupClaim}', [App\Http\Controllers\PermissionPolicyController::class, 'groupClaimsDestroy']);

        Route::get('/user-overrides', [App\Http\Controllers\PermissionPolicyController::class, 'userOverridesIndex']);
        Route::post('/user-overrides', [App\Http\Controllers\PermissionPolicyController::class, 'userOverridesStore']);
        Route::delete('/user-overrides/{override}', [App\Http\Controllers\PermissionPolicyController::class, 'userOverridesDestroy']);

        Route::post('/authorize', [App\Http\Controllers\PermissionPolicyController::class, 'authorize']);
    });
});
