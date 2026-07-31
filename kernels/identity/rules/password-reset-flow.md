# Password Reset Flow Rule

## Identity Kernel — Business Rule

### Purpose

Defines the secure process for password reset via email token.

### Flow

```
1. User requests reset
   ├── Lookup user by email
   ├── Invalidate all existing tokens for user
   ├── Generate raw token (random 32 bytes → hex)
   ├── Store hashed token (SHA-256)
   ├── Set expires_at = now + 60 minutes
   └── Publish PasswordResetRequested

2. User submits token + new password
   ├── Hash submitted token
   ├── Find matching hashed token in database
   ├── Validate: exists, not expired, not used
   ├── Update user password (bcrypt)
   ├── Mark token as used (used_at = now)
   ├── Invalidate all other tokens for user
   ├── Revoke all refresh tokens for user
   └── Publish PasswordResetCompleted
```

### Unified Forgot / Change Flow

Forget-password and change-password are the **same flow** with different triggers and email copy.

| Aspect | Forgot Password | Change Password |
|--------|-----------------|-----------------|
| Trigger | unauthenticated | authenticated |
| Entry | `/forgot-password` form | `POST /api/v1/auth/password/change-request` |
| Email template | reset-password | change-password |
| Token + consumer form | same | same |

Both produce a `PasswordResetToken` consumed by the same `/reset-password` form. No duplicated logic.

### Token Properties

| Property | Value |
|----------|-------|
| Raw token length | 64 hex characters (32 random bytes) |
| Storage | SHA-256 hash |
| Expiry | 60 minutes from creation |
| Usage | Single-use only |

### Enforcement Points

- `IdentityService.requestPasswordReset()` — token generation
- `IdentityService.resetPassword()` — token validation and password update

### Edge Cases

- If email doesn't exist, return success anyway (prevent email enumeration)
- If token is expired, return generic "invalid or expired" error
- If token was already used, return generic "invalid or expired" error
- Rate-limit reset requests to 1 per 60 seconds per email

### Anti-Patterns

- Do not store raw tokens in the database
- Do not reveal whether an email is registered
- Do not allow expired or used tokens to reset a password
- Do not allow password reset without token validation
