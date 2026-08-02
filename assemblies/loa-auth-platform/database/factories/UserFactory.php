<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'password' => Hash::make('Test1234'),
            'status' => 'active',
            'failed_attempts' => 0,
            'locked_until' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => 'disabled']);
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'status' => 'locked',
            'locked_until' => now()->addMinutes(30),
        ]);
    }

    public function withPassword(string $password): static
    {
        return $this->state(fn () => ['password' => Hash::make($password)]);
    }
}
