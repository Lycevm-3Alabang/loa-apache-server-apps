<?php

namespace Database\Factories;

use App\Models\UserGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserGroupFactory extends Factory
{
    protected $model = UserGroup::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'priority' => fake()->numberBetween(1, 100),
            'tenant_id' => null,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => ['tenant_id' => $tenantId]);
    }
}
