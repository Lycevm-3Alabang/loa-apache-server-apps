# Identity Kernel Contracts

## Public Interfaces

### UserRepository

```php
interface UserRepository
{
    public function findById(string $id): ?User;
    public function findByEmail(string $email): ?User;
    public function create(array $data): User;
    public function update(string $id, array $data): User;
    public function incrementFailedAttempts(string $id): void;
    public function resetFailedAttempts(string $id): void;
    public function lock(string $id, int $minutes): void;
    public function unlock(string $id): void;
}
```

### UserGroupRepository

```php
interface UserGroupRepository
{
    public function findById(string $id): ?UserGroup;
    public function findByName(string $name): ?UserGroup;
    public function create(array $data): UserGroup;
    public function update(string $id, array $data): UserGroup;
    public function delete(string $id): void;
    public function getMembers(string $groupId): array;
    public function getPermissions(string $groupId): array;
}
```

### PermissionRepository

```php
interface PermissionRepository
{
    public function findByKey(string $key): ?Permission;
    public function create(array $data): Permission;
    public function findByEndpoint(string $method, string $path): ?Permission;
}
```

### LoginAttemptRepository

```php
interface LoginAttemptRepository
{
    public function record(array $data): void;
    public function getConsecutiveFailures(string $userId): int;
    public function getFailuresByIp(string $ipAddress, int $minutes): int;
    public function prune(int $days): void;
}
```

### PasswordResetTokenRepository

```php
interface PasswordResetTokenRepository
{
    public function create(string $userId): string;
    public function findValid(string $token): ?PasswordResetToken;
    public function markUsed(string $tokenId): void;
    public function invalidateForUser(string $userId): void;
}
```

### TokenService

```php
interface TokenService
{
    public function generateTokenPair(User $user): TokenPair;
    public function validateToken(string $token): ?TokenClaims;
    public function revokeRefreshToken(string $token): void;
}
```

### IdentityService

```php
interface IdentityService
{
    public function register(string $email, string $password, string $name): User;
    public function login(string $email, string $password): TokenPair;
    public function refresh(string $refreshToken): TokenPair;
    public function logout(string $refreshToken): void;
    public function getUser(string $userId): User;
    public function updatePassword(string $userId, string $oldPassword, string $newPassword): void;
    public function requestPasswordReset(string $email): void;
    public function resetPassword(string $token, string $newPassword): void;
}
```

### AuthorizationService

```php
interface AuthorizationService
{
    public function hasPermission(string $userId, string $permissionKey): bool;
    public function getPermissions(string $userId): array;
    public function getGroups(string $userId): array;
    public function addToGroup(string $userId, string $groupId): void;
    public function removeFromGroup(string $userId, string $groupId): void;
    public function grantGroupPermission(string $groupId, string $permissionKey): void;
    public function revokeGroupPermission(string $groupId, string $permissionKey): void;
}
```

### UserGroupService

```php
interface UserGroupService
{
    public function getGroup(string $groupId): UserGroup;
    public function getGroupByName(string $name): UserGroup;
    public function createGroup(array $data): UserGroup;
    public function updateGroup(string $groupId, array $data): UserGroup;
    public function deleteGroup(string $groupId): void;
    public function getMembers(string $groupId): array;
    public function getPermissions(string $groupId): array;
}
```
