<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class LocalAuthTenantSeeder extends Seeder
{
    /**
     * The auth tenant is the container for users who log in to the platform.
     * slug 'auth' is immutable — used for badge display and tenant resolution.
     * redirect_origins must be empty to prevent SSO redirect loops.
     */
    private const TENANT_SLUG = 'auth';

    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        Tenant::updateOrCreate(
            ['slug' => self::TENANT_SLUG],
            [
                'name' => 'LOA Auth Platform',
                'status' => 'active',
                'app_url' => null,
                'dev_app_url' => null,
                'redirect_origins' => [],
                'dev_redirect_origins' => [],
            ],
        );
    }
}
