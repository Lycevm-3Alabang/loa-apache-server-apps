<?php

namespace Database\Factories;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RefreshTokenFactory extends Factory
{
    protected $model = RefreshToken::class;

    public function definition(): array
    {
        $rawJti = bin2hex(random_bytes(16));

        return [
            'id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'jti' => hash('sha256', $rawJti),
            'expires_at' => now()->addDays(7),
            'revoked_at' => null,
            'replaced_by' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
