<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$admin = App\Models\User::factory()->create([
    'email' => 'admin@test.com',
    'name' => 'Admin',
    'status' => 'active'
]);
$group = App\Models\UserGroup::firstOrCreate(
    ['name' => config('auth-web.admin_group')],
    ['description' => 'x', 'priority' => 1]
);
$admin->userGroups()->attach($group);
$perm = App\Models\Permission::firstOrCreate(['key' => 'users.manage'], ['description' => 'x']);
$group->permissions()->syncWithoutDetaching($perm->id);

echo 'Admin: ' . $admin->email . PHP_EOL;
echo 'Has users.manage: ' . ($admin->hasPermission('users.manage') ? 'true' : 'false') . PHP_EOL;
echo 'Web admin check: ' . ($admin->hasRole(config('auth-web.admin_group')) ? 'true' : 'false') . PHP_EOL;

try {
    $response = $app->make('Illuminate\Support\Facades\Http')->get('/admin/users/import');
    echo 'Response: ' . $response->status() . PHP_EOL;
} catch (\Throwable $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
