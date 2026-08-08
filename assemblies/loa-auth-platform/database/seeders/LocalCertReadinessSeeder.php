<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\UserGroup;
use Illuminate\Database\Seeder;

class LocalCertReadinessSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $defaultRedirect = 'http://localhost:9001';

        $tenant = Tenant::where('slug', 'cert-app')->first();

        if (!$tenant) {
            $tenant = Tenant::create([
                'slug' => 'cert-app',
                'name' => 'Local Cert App',
                'status' => 'active',
                'app_url' => $defaultRedirect,
                'redirect_origins' => [$defaultRedirect],
            ]);
        } else {
            $tenant->update([
                'name' => 'Local Cert App',
                'status' => 'active',
                'app_url' => $defaultRedirect,
                'redirect_origins' => [$defaultRedirect],
            ]);
        }

        $groupNames = ['cert-admin', 'cert-staff', 'cert-user'];

        foreach ($groupNames as $index => $name) {
            $priority = [2, 3, 4][$index] ?? 10;

            UserGroup::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                ],
                [
                    'description' => match ($name) {
                        'cert-admin' => 'Local certificate administrator',
                        'cert-staff' => 'Local certificate staff',
                        'cert-user' => 'Local certificate user',
                        default => 'Local certificate group',
                    },
                    'priority' => $priority,
                ],
            );
        }
    }
}
