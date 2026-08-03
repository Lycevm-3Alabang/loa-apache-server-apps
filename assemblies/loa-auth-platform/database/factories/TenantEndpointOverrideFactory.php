<?php

namespace Database\Factories;

use App\Models\TenantEndpointOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantEndpointOverrideFactory extends Factory
{
    protected $model = TenantEndpointOverride::class;

    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'method' => 'GET',
            'path' => '/api/v1/resource',
            'tenant_id' => \App\Models\Tenant::factory(),
            'level' => 'read',
        ];
    }
}
