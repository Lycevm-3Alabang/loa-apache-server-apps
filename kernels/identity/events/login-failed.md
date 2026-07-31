# LoginFailed

## Identity Kernel — Domain Event

### Purpose

Published when an authentication attempt fails.

### When It Fires

After email exists but password does not match, or email not found.

### Payload

| Field | Type | Description |
|-------|------|-------------|
| userId | UUID? | User identifier if email exists |
| email | string | Email used in attempt |
| ipAddress | string | Client IP address |
| reason | string | Failure reason (wrong_password, account_locked, account_disabled) |
| occurredAt | DateTimeImmutable | When failure occurred |

### Raised By

- `IdentityService.login()`

### Consumed By

- `LoginAttemptThresholdRule` (triggers lock after threshold)
- Security monitoring
- Audit log
