# PasswordResetToken Entity

## Identity Kernel

### Purpose

Secure tokens for password reset flow.

### Attributes

| Attribute | Type | Constraints | Description |
|-----------|------|-------------|-------------|
| id | UUID | PK | Unique identifier |
| user_id | UUID | FK, required | Associated user |
| token | string | required | Hashed token value |
| expires_at | timestamp | required | Token expiry |
| used_at | timestamp | nullable | When token was used |
| created_at | timestamp | auto | Creation time |

### Invariants

1. Token is hashed (never stored raw)
2. Token expires after 60 minutes
3. Token can only be used once
4. Old tokens are invalidated when new one is generated

### Relationships

- belongsTo User

### Business Rules

1. Request password reset:
   - Invalidate any existing tokens for user
   - Generate new token
   - Store hashed token
   - Return raw token (to send via email)
2. Reset password:
   - Validate token exists and not expired
   - Validate token not already used
   - Update user password
   - Mark token as used
   - Invalidate all other tokens for user

### Token Generation

```php
$rawToken = bin2hex(random_bytes(32));
$hashedToken = hash('sha256', $rawToken);
```

### Factory

```php
PasswordResetToken::create([
    'user_id' => $user->id,
    'token' => hash('sha256', $rawToken),
    'expires_at' => now()->addMinutes(60),
]);
```
