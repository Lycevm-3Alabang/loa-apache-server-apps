<?php

namespace App\Services;

use App\Models\LoginAttempt;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class IdentityService
{
    private JWTService $jwt;
    private AuthorizationService $authorization;
    private int $maxAttempts = 5;
    private int $lockoutMinutes = 30;

    public function __construct(JWTService $jwt, AuthorizationService $authorization)
    {
        $this->jwt = $jwt;
        $this->authorization = $authorization;
    }

    public function register(string $email, string $password, string $name): User
    {
        $existing = User::where('email', $email)->first();

        if ($existing) {
            throw new \Exception('Email already registered');
        }

        return User::create([
            'email' => $email,
            'password' => Hash::make($password),
            'name' => $name,
            'status' => 'active',
        ]);
    }

    public function login(string $email, string $password, string $ipAddress): array
    {
        $user = User::where('email', $email)->first();

        if ($user && $user->isLocked()) {
            $this->recordAttempt(null, $email, $ipAddress, false);
            throw new \Exception('Account is locked');
        }

        if (!$user || !Hash::check($password, $user->password)) {
            $this->recordAttempt($user?->id, $email, $ipAddress, false);

            if ($user) {
                $this->handleFailedAttempt($user);
            }

            throw new \Exception('Invalid credentials');
        }

        $this->recordAttempt($user->id, $email, $ipAddress, true);
        $this->resetFailedAttempts($user);

        return $this->generateTokenPair($user);
    }

    public function refresh(string $refreshToken): array
    {
        $claims = $this->jwt->validate($refreshToken);

        if (!$claims || ($claims['type'] ?? '') !== 'refresh') {
            throw new \Exception('Invalid refresh token');
        }

        $user = User::find($claims['sub']);

        if (!$user || !$user->isActive()) {
            throw new \Exception('User not found or inactive');
        }

        return $this->generateTokenPair($user);
    }

    public function logout(string $refreshToken): void
    {
        $this->jwt->validate($refreshToken);
    }

    public function getUser(string $userId): User
    {
        $user = User::find($userId);

        if (!$user) {
            throw new \Exception('User not found');
        }

        return $user;
    }

    public function updatePassword(string $userId, string $oldPassword, string $newPassword): void
    {
        $user = User::find($userId);

        if (!$user) {
            throw new \Exception('User not found');
        }

        if (!Hash::check($oldPassword, $user->password)) {
            throw new \Exception('Current password is incorrect');
        }

        $user->update([
            'password' => Hash::make($newPassword),
        ]);
    }

    public function requestPasswordReset(string $email): ?string
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return null;
        }

        PasswordResetToken::where('user_id', $user->id)->delete();

        $rawToken = bin2hex(random_bytes(32));

        PasswordResetToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $rawToken),
            'expires_at' => now()->addMinutes(60),
        ]);

        return $rawToken;
    }

    public function resetPassword(string $rawToken, string $newPassword): void
    {
        $hashedToken = hash('sha256', $rawToken);

        $token = PasswordResetToken::where('token', $hashedToken)
            ->where('used_at', null)
            ->first();

        if (!$token || $token->isExpired()) {
            throw new \Exception('Invalid or expired token');
        }

        $user = User::find($token->user_id);

        if (!$user) {
            throw new \Exception('User not found');
        }

        $user->update([
            'password' => Hash::make($newPassword),
            'failed_attempts' => 0,
            'locked_until' => null,
            'status' => 'active',
        ]);

        $token->update(['used_at' => now()]);

        PasswordResetToken::where('user_id', $user->id)
            ->where('id', '!=', $token->id)
            ->update(['used_at' => now()]);
    }

    private function generateTokenPair(User $user): array
    {
        $claims = [
            'sub' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'groups' => $this->authorization->getGroups($user->id),
            'permissions' => $this->authorization->getPermissions($user->id),
        ];

        return [
            'access_token' => $this->jwt->generateAccessToken($claims),
            'refresh_token' => $this->jwt->generateRefreshToken($claims),
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.access_ttl', 15) * 60,
        ];
    }

    private function recordAttempt(?string $userId, string $email, string $ipAddress, bool $success): void
    {
        LoginAttempt::create([
            'user_id' => $userId,
            'email_attempted' => $email,
            'ip_address' => $ipAddress,
            'success' => $success,
            'attempted_at' => now(),
        ]);
    }

    private function handleFailedAttempt(User $user): void
    {
        $attempts = $user->failed_attempts + 1;

        if ($attempts >= $this->maxAttempts) {
            $user->update([
                'failed_attempts' => $attempts,
                'status' => 'locked',
                'locked_until' => now()->addMinutes($this->lockoutMinutes),
            ]);
        } else {
            $user->update(['failed_attempts' => $attempts]);
        }
    }

    private function resetFailedAttempts(User $user): void
    {
        if ($user->failed_attempts > 0) {
            $user->update(['failed_attempts' => 0]);
        }
    }
}
