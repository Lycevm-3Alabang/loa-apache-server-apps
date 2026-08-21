<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $slug = strtolower(fake()->unique()->bothify('####'));

        return [
            'id' => (string) Str::uuid(),
            'slug' => $slug,
            'name' => ucfirst($slug) . ' Tenant',
            'status' => 'active',
            'app_url' => null,
            'dev_app_url' => null,
            'redirect_origins' => [],
            'dev_redirect_origins' => [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }

    public function withSlug(string $slug): static
    {
        return $this->state(fn () => ['slug' => $slug]);
    }
}
