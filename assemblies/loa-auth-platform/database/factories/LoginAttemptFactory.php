<?php

namespace Database\Factories;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LoginAttemptFactory extends Factory
{
    protected $model = LoginAttempt::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => null,
            'email_attempted' => fake()->safeEmail(),
            'ip_address' => '127.0.0.1',
            'success' => false,
            'attempted_at' => now(),
        ];
    }

    public function success(): static
    {
        return $this->state(fn () => ['success' => true]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'email_attempted' => $user->email,
        ]);
    }
}
