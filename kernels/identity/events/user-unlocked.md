# UserUnlocked

## Identity Kernel — Domain Event

### Purpose

Published when a locked user account is unlocked (either automatically after lockout expiry, or manually by an admin).

### When It Fires

- Automatically when login occurs after `locked_until` has passed
- Manually when an admin unlocks the account

### Payload

| Field | Type | Description |
|-------|------|-------------|
| userId | UUID | Unlocked user identifier |
| unlockedBy | string | `"auto"` or admin userId |
| occurredAt | DateTimeImmutable | When unlock occurred |

### Raised By

- `IdentityService.login()` (auto-unlock)
- Admin user management operation

### Consumed By

- Audit log
- Security monitoring
