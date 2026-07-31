# LoginSucceeded

## Identity Kernel — Domain Event

### Purpose

Published when a user successfully authenticates.

### When It Fires

After email + password are validated and tokens are issued.

### Payload

| Field | Type | Description |
|-------|------|-------------|
| userId | UUID | Authenticated user identifier |
| ipAddress | string | Client IP address |
| userAgent | string? | Client user agent string |
| occurredAt | DateTimeImmutable | When login occurred |

### Raised By

- `IdentityService.login()`

### Consumed By

- Audit log
- Security monitoring
