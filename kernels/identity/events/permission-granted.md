# PermissionGranted

## Identity Kernel — Domain Event

### Purpose

Published when a permission is granted to a user group (or directly to a user as an override).

### When It Fires

After `AuthorizationService.grantGroupPermission()` or a user-level permission override is created.

### Payload

| Field | Type | Description |
|-------|------|-------------|
| permissionKey | string | The granted permission key |
| targetType | string | `"group"` or `"user"` |
| targetId | integer/UUID | Group ID or User ID |
| grantedBy | UUID | Admin who granted the permission |
| occurredAt | DateTimeImmutable | When grant occurred |

### Raised By

- `AuthorizationService.grantGroupPermission()`
- User permission override creation

### Consumed By

- Audit log
