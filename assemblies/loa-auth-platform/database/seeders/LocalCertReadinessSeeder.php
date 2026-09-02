<?php

namespace Database\Seeders;

use App\Models\GroupClaim;
use App\Models\Tenant;
use App\Models\UserGroup;
use Illuminate\Database\Seeder;

class LocalCertReadinessSeeder extends Seeder
{
    /**
     * Canonical cert tenant. The slug is immutable after issuance and must
     * match e-cert's NEXT_PUBLIC_CERT_TENANT_SLUG.
     */
    private const TENANT_ID = '91128f0a-df85-47a9-ae1d-5298904dacd5';
    private const TENANT_SLUG = 'loa-e-cert';
    private const TENANT_NAME = 'Vericert';
    private const PROD_URL = 'https://staging-loa-vericert.vercel.app';
    private const DEV_URL = 'http://localhost:3000';

    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $tenant = Tenant::find(self::TENANT_ID);

        if (!$tenant) {
            $tenant = Tenant::create([
                'id' => self::TENANT_ID,
                'slug' => self::TENANT_SLUG,
                'name' => self::TENANT_NAME,
                'status' => 'active',
                'app_url' => self::PROD_URL,
                'dev_app_url' => self::DEV_URL,
                'redirect_origins' => [self::PROD_URL],
                'dev_redirect_origins' => [self::DEV_URL],
            ]);
        } else {
            // Never clobber redirect origins: they carry the e-cert dev
            // origin used for SSO tenant resolution.
            $tenant->update([
                'name' => self::TENANT_NAME,
                'status' => 'active',
                'app_url' => self::PROD_URL,
                'dev_app_url' => self::DEV_URL,
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

        // JWT permission-key claims consumed by jwt.permission:* middleware.
        $adminGroup = UserGroup::where('tenant_id', $tenant->id)
            ->where('name', 'cert-admin')
            ->first();

        if ($adminGroup) {
            foreach (['users.view', 'users.manage'] as $claimKey) {
                GroupClaim::updateOrCreate(
                    ['group_id' => $adminGroup->id, 'claim_key' => $claimKey],
                    ['scope_type' => 'none', 'scope_id' => null],
                );
            }
        }
    }
}
