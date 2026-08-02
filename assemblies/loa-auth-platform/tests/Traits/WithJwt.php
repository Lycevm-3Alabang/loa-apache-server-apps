<?php

namespace Tests\Traits;

use App\Models\User;
use App\Models\RefreshToken;
use App\Services\JWTService;
use App\Services\AuthorizationService;

trait WithJwt
{
    private function generateToken(User $user): string
    {
        $jwt = app(JWTService::class);

        $authorization = app(AuthorizationService::class);
        $groups = $authorization->getGroups($user->id);
        $permissions = $authorization->getPermissions($user->id);

        return $jwt->generateAccessToken([
            'sub' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'groups' => $groups,
            'permissions' => $permissions,
        ]);
    }

    private function jwtHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $this->generateToken($user)];
    }

    private function createRefreshToken(User $user): string
    {
        $jwt = app(JWTService::class);

        $authorization = app(AuthorizationService::class);
        $groups = $authorization->getGroups($user->id);
        $permissions = $authorization->getPermissions($user->id);

        $refreshJwt = $jwt->generateRefreshToken([
            'sub' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'groups' => $groups,
            'permissions' => $permissions,
        ]);

        $claims = $jwt->validate($refreshJwt);

        RefreshToken::create([
            'user_id' => $user->id,
            'jti' => hash('sha256', $claims['jti'] ?? ''),
            'expires_at' => now()->addDays(7),
        ]);

        return $refreshJwt;
    }

    private function createAndLoginAdmin(): User
    {
        $admin = User::factory()->create();
        $adminGroup = \App\Models\UserGroup::firstOrCreate(
            ['name' => config('auth-web.admin_group')],
            ['description' => 'Platform administrators']
        );
        $admin->userGroups()->syncWithoutDetaching([$adminGroup->id]);

        $managePerm = \App\Models\Permission::firstOrCreate(
            ['key' => 'users.manage'],
            ['description' => 'Manage users']
        );
        $viewPerm = \App\Models\Permission::firstOrCreate(
            ['key' => 'users.view'],
            ['description' => 'View users']
        );

        $adminGroup->permissions()->syncWithoutDetaching([
            $managePerm->id => ['granted' => true],
            $viewPerm->id => ['granted' => true],
        ]);

        return $admin;
    }

    private function createPermission(string $key): \App\Models\Permission
    {
        return \App\Models\Permission::firstOrCreate(
            ['key' => $key],
            ['description' => "Permission: {$key}"]
        );
    }
}
