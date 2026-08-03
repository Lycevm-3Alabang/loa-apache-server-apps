<?php

namespace Database\Factories;

use App\Models\TenantEndpointGrant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantEndpointGrantFactory extends Factory
{
    protected $model = TenantEndpointGrant::class;

    public function definition(): array
    {
        return [
            'group_id' => \App\Models\UserGroup::factory(),
            'method' => 'GET',
            'path' => '/api/v1/resource',
            'tenant_id' => \App\Models\Tenant::factory(),
            'level' => 'read',
        ];
    }
}
