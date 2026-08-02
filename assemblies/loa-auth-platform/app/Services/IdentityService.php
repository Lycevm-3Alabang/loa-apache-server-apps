<?php

namespace App\Services;

use App\Models\LoginAttempt;
use App\Models\PasswordResetToken;
use App\Models\RefreshToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class IdentityService
{
    private JWTService $jwt;
    private AuthorizationService $authorization;
    private PermissionPolicyService $policy;
    private int $maxAttempts = 5;
    private int $lockoutMinutes = 30;

    public function __construct(JWTService $jwt, AuthorizationService $authorization, PermissionPolicyService $policy)
    {
        $this->jwt = $jwt;
        $this->authorization = $authorization;
        $this->policy = $policy;
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

    public function login(string $email, string $password, string $ipAddress, ?Tenant $tenant = null): array
    {
        $user = User::where('email', $email)->first();

        if ($user && $user->isLocked()) {
            $this->recordAttempt(null, $email, $ipAddress, false);
            throw new \Exception('Account is locked');
        }

        if ($user && $user->status === 'disabled') {
            $this->recordAttempt(null, $email, $ipAddress, false);
            throw new \Exception('Account is disabled');
        }

        if (!$user || !Hash::check($password, $user->password)) {
            $this->recordAttempt($user?->id, $email, $ipAddress, false);

            if ($user) {
                $this->handleFailedAttempt($user);
            }

            throw new \Exception('Invalid credentials');
        }

        if ($tenant && !$tenant->isActive()) {
            $this->recordAttempt($user->id, $email, $ipAddress, false);
            throw new \Exception('Invalid credentials');
        }

        $this->recordAttempt($user->id, $email, $ipAddress, true);
        $this->resetFailedAttempts($user);

        return $this->generateTokenPair($user, null, $tenant);
    }

    public function refresh(string $refreshToken): array
    {
        $claims = $this->jwt->validate($refreshToken);

        if (!$claims || ($claims['type'] ?? '') !== 'refresh') {
            throw new \Exception('Invalid refresh token');
        }

        $record = RefreshToken::where('jti', hash('sha256', $claims['jti'] ?? ''))->first();

        if (!$record || !$record->isValid()) {
            throw new \Exception('Invalid refresh token');
        }

        $user = User::find($claims['sub']);

        if (!$user || !$user->isActive()) {
            throw new \Exception('User not found or inactive');
        }

        $tenant = null;

        if (isset($claims['tenant']['id'])) {
            $tenant = Tenant::find($claims['tenant']['id']);

            if (!$tenant || !$tenant->isActive()) {
                throw new \Exception('Tenant unavailable');
            }
        }

        return $this->generateTokenPair($user, $record, $tenant);
    }

    public function logout(string $refreshToken): void
    {
        $this->revokeRefreshToken($refreshToken);
    }

    public function getUser(string $userId): User
    {
        $user = User::find($userId);

        if (!$user) {
            throw new \Exception('User not found');
        }

        return $user;
    }

    public function setUserStatus(string $userId, string $status): void
    {
        $user = User::find($userId);

        if (!$user) {
            throw new \Exception('User not found');
        }

        if (!in_array($status, ['active', 'disabled'], true)) {
            throw new \InvalidArgumentException('Invalid status');
        }

        if ($status === 'disabled') {
            $user->update(['status' => 'disabled']);
            $this->revokeAllRefreshTokens($user->id);

            return;
        }

        $user->update([
            'status' => 'active',
            'failed_attempts' => 0,
            'locked_until' => null,
        ]);
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

        $this->revokeAllRefreshTokens($user->id);
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

        $this->revokeAllRefreshTokens($user->id);
    }

    private function generateTokenPair(User $user, ?RefreshToken $previous = null, ?Tenant $tenant = null): array
    {
        $claims = [
            'sub' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'groups' => $this->authorization->getGroups($user->id, $tenant?->id),
            'permissions' => array_merge(
                $this->policy->resolveUserClaims($user->id, $tenant?->id),
                $this->policy->resolveUserEndpointPermissions($user->id, $tenant?->id)
            ),
            'scopes' => $this->policy->resolveUserScopes($user->id, $tenant?->id),
        ];

        if ($tenant) {
            $claims['tenant'] = [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
            ];
        }

        $accessToken = $this->jwt->generateAccessToken($claims);
        $refreshToken = $this->jwt->generateRefreshToken($claims);

        $refreshClaims = $this->jwt->validate($refreshToken);

        $record = RefreshToken::create([
            'user_id' => $user->id,
            'jti' => hash('sha256', $refreshClaims['jti'] ?? ''),
            'expires_at' => now()->addMinutes(config('jwt.refresh_ttl', 10080)),
        ]);

        if ($previous) {
            $previous->update([
                'revoked_at' => now(),
                'replaced_by' => $record->id,
            ]);
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.access_ttl', 15) * 60,
        ];
    }

    private function revokeRefreshToken(string $refreshToken): void
    {
        $claims = $this->jwt->validate($refreshToken);

        if (!$claims || ($claims['type'] ?? '') !== 'refresh') {
            return;
        }

        $record = RefreshToken::where('jti', hash('sha256', $claims['jti'] ?? ''))
            ->whereNull('revoked_at')
            ->first();

        if ($record) {
            $record->update(['revoked_at' => now()]);
        }
    }

    private function revokeAllRefreshTokens(string $userId): void
    {
        RefreshToken::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
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

            $this->revokeAllRefreshTokens($user->id);
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
