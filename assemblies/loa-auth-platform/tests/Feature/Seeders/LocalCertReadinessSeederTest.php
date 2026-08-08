<?php

namespace Tests\Feature\Seeders;

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

        $tenant = Tenant::where('slug', 'cert-app')->first();

        $this->assertNotNull($tenant);
        $this->assertSame('cert-app', $tenant->slug);
        $this->assertSame(['http://localhost:9001'], $tenant->redirect_origins);

        $groupNames = UserGroup::where('tenant_id', $tenant->id)->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['cert-admin', 'cert-staff', 'cert-user'], $groupNames);
    }
}
