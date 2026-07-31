# PasswordResetCompleted

## Identity Kernel — Domain Event

### Purpose

Published when a password reset flow is successfully completed.

### When It Fires

After `IdentityService.resetPassword()` validates the token and updates the password hash.

### Payload

| Field | Type | Description |
|-------|------|-------------|
| userId | UUID | User who reset password |
| occurredAt | DateTimeImmutable | When reset completed |

### Raised By

- `IdentityService.resetPassword()`

### Consumed By

- Audit log
- Notification Service (confirmation email)
