<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $group = UserGroup::updateOrCreate(
            ['name' => 'loa-auth-admin'],
            ['description' => 'Platform administrator'],
        );

        $permissions = [
            ['key' => 'users.view', 'description' => 'View user list and details'],
            ['key' => 'users.manage', 'description' => 'Enable/disable users, manage status'],
            ['key' => 'groups.view', 'description' => 'View groups'],
            ['key' => 'groups.manage', 'description' => 'Create, edit, delete groups'],
            ['key' => 'permissions.view', 'description' => 'View permissions'],
            ['key' => 'permissions.manage', 'description' => 'Assign permissions to groups'],
            ['key' => 'auth.verify', 'description' => 'Validate tokens (internal)'],
        ];

        foreach ($permissions as $perm) {
            $permission = Permission::updateOrCreate(
                ['key' => $perm['key']],
                $perm,
            );

            $group->permissions()->syncWithoutDetaching([
                $permission->id => ['granted' => true],
            ]);
        }

        $admin = User::where('email', env('ADMIN_EMAIL'))->first();

        if (!$admin) {
            $admin = User::create([
                'email' => env('ADMIN_EMAIL'),
                'password' => Hash::make(env('ADMIN_PASSWORD')),
                'name' => env('ADMIN_NAME'),
                'status' => 'active',
            ]);
        }

        $admin->userGroups()->syncWithoutDetaching($group->id);
    }
}