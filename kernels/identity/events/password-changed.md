# PasswordChanged

## Identity Kernel — Domain Event

### Purpose

Published when a user successfully changes their password (authenticated change, not reset).

### When It Fires

After `IdentityService.updatePassword()` successfully updates the password hash.

### Payload

| Field | Type | Description |
|-------|------|-------------|
| userId | UUID | User who changed password |
| occurredAt | DateTimeImmutable | When change occurred |

### Raised By

- `IdentityService.updatePassword()`

### Consumed By

- Audit log
- Notification Service (confirmation email)
