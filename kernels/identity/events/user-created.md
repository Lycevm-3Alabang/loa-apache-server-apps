# UserCreated

## Identity Kernel — Domain Event

### Purpose

Published when a new user account is successfully registered.

### When It Fires

After `IdentityService.register()` creates a User record.

### Payload

| Field | Type | Description |
|-------|------|-------------|
| userId | UUID | New user identifier |
| email | string | User's email address |
| name | string | User's display name |
| occurredAt | DateTimeImmutable | When registration completed |

### Raised By

- `IdentityService.register()`

### Consumed By

- Notification Service (welcome email)
- Audit log
