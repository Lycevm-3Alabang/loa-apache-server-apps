<?php

namespace Database\Factories;

use App\Models\TenantAppEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantAppEndpointFactory extends Factory
{
    protected $model = TenantAppEndpoint::class;

    public function definition(): array
    {
        return [
            'tenant_id' => \App\Models\Tenant::factory(),
            'method' => 'GET',
            'path' => '/api/v1/resource',
            'label' => fake()->sentence(3),
            'description' => null,
            'required_level' => 'read',
        ];
    }
}
