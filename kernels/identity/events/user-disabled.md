# UserDisabled

## Identity Kernel — Domain Event

### Purpose

Published when an admin disables a user account.

### When It Fires

After `User.status` is changed from `active` to `disabled`.

### Payload

| Field | Type | Description |
|-------|------|-------------|
| userId | UUID | Disabled user identifier |
| disabledBy | UUID | Admin who performed the action |
| reason | string? | Optional reason for disable |
| occurredAt | DateTimeImmutable | When disable occurred |

### Raised By

- Admin user management operation

### Consumed By

- Audit log
- Notification Service (email to user)
