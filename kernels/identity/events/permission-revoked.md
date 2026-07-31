# PermissionRevoked

## Identity Kernel — Domain Event

### Purpose

Published when a permission is revoked from a user group (or from a user override).

### When It Fires

After `AuthorizationService.revokeGroupPermission()` or a user-level permission override is removed.

### Payload

| Field | Type | Description |
|-------|------|-------------|
| permissionKey | string | The revoked permission key |
| targetType | string | `"group"` or `"user"` |
| targetId | integer/UUID | Group ID or User ID |
| revokedBy | UUID | Admin who revoked the permission |
| occurredAt | DateTimeImmutable | When revocation occurred |

### Raised By

- `AuthorizationService.revokeGroupPermission()`
- User permission override removal

### Consumed By

- Audit log
