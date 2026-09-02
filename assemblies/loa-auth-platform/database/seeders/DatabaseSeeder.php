<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Tenants that are seeded by the platform. Any other slugs are
     * considered stale (added manually) and removed on each seed.
     */
    private const CANONICAL_SLUGS = ['auth', 'loa-e-cert'];

    public function run(): void
    {
        $this->call(AdminSeeder::class);

        if (!app()->environment('production')) {
            // Remove tenants not in the canonical set (aces-api, e-cert, etc.)
            Tenant::whereNotIn('slug', self::CANONICAL_SLUGS)->delete();

            $this->call(LocalCertReadinessSeeder::class);
            $this->call(LocalAuthTenantSeeder::class);
        }
    }
}