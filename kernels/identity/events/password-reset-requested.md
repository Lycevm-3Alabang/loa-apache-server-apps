# PasswordResetRequested

## Identity Kernel — Domain Event

### Purpose

Published when a user requests a password reset (forgot password flow).

### When It Fires

After `IdentityService.requestPasswordReset()` generates a reset token.

### Payload

| Field | Type | Description |
|-------|------|-------------|
| userId | UUID | User requesting reset |
| email | string | User's email address |
| ipAddress | string | Request origin IP |
| occurredAt | DateTimeImmutable | When request was made |

### Raised By

- `IdentityService.requestPasswordReset()`

### Consumed By

- Notification Service (sends reset link email)
- Security monitoring
