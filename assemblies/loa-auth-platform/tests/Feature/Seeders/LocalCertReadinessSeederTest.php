<?php

namespace Tests\Feature\Seeders;

use App\Models\GroupClaim;
use App\Models\Tenant;
use App\Models\UserGroup;
use Database\Seeders\LocalCertReadinessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalCertReadinessSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_local_cert_tenant_and_groups(): void
    {
        putenv('APP_ENV=local');
        putenv('CERT_APP_URL=http://localhost:9001');

        $seeder = new LocalCertReadinessSeeder();
        $seeder->run();

        $tenant = Tenant::where('slug', 'loa-e-cert')->first();

        $this->assertNotNull($tenant);
        $this->assertSame('91128f0a-df85-47a9-ae1d-5298904dacd5', $tenant->id);
        $this->assertSame(['http://localhost:3000'], $tenant->redirect_origins);

        $groupNames = UserGroup::where('tenant_id', $tenant->id)->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['cert-admin', 'cert-staff', 'cert-user'], $groupNames);

        $adminGroup = UserGroup::where('tenant_id', $tenant->id)->where('name', 'cert-admin')->first();
        $claimKeys = GroupClaim::where('group_id', $adminGroup->id)->pluck('claim_key')->all();
        $this->assertEqualsCanonicalizing(['users.view', 'users.manage'], $claimKeys);
    }

    public function test_it_preserves_existing_redirect_origins_on_rerun(): void
    {
        putenv('APP_ENV=local');

        $seeder = new LocalCertReadinessSeeder();
        $seeder->run();

        $tenant = Tenant::where('slug', 'loa-e-cert')->first();
        $tenant->update(['redirect_origins' => ['http://localhost:9999']]);

        $seeder->run();

        $this->assertSame(['http://localhost:9999'], $tenant->fresh()->redirect_origins);
    }
}
