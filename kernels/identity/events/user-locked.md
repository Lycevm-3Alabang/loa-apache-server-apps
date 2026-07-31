# UserLocked

## Identity Kernel — Domain Event

### Purpose

Published when a user account is automatically locked due to excessive failed login attempts.

### When It Fires

After `failed_attempts` reaches 5 and `status` is set to `locked`, `locked_until` is set.

### Payload

| Field | Type | Description |
|-------|------|-------------|
| userId | UUID | Locked user identifier |
| failedAttempts | int | Number of consecutive failures |
| lockedUntil | DateTimeImmutable | When lock automatically expires |
| ipAddress | string | IP of the last failed attempt |
| occurredAt | DateTimeImmutable | When lock occurred |

### Raised By

- `LoginAttemptThresholdRule` check during `IdentityService.login()`

### Consumed By

- Security monitoring
- Audit log
