<?php

namespace Database\Factories;

use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class PasswordResetTokenFactory extends Factory
{
    protected $model = PasswordResetToken::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'token' => hash('sha256', 'raw-test-token'),
            'expires_at' => now()->addHours(1),
            'used_at' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn () => ['used_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subHour()]);
    }
}
