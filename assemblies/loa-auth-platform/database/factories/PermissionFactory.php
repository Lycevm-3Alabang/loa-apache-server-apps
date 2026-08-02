<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $key = fake()->unique()->bothify('##.###.####');

        return [
            'key' => $key,
            'description' => fake()->sentence(),
            'endpoint_pattern' => null,
        ];
    }

    public function withKey(string $key): static
    {
        return $this->state(fn () => ['key' => $key]);
    }
}
