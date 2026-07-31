# UserRemovedFromGroup

## Identity Kernel — Domain Event

### Purpose

Published when a user is removed from a user group.

### When It Fires

After `AuthorizationService.removeFromGroup()` deletes the pivot record.

### Payload

| Field | Type | Description |
|-------|------|-------------|
| userId | UUID | User being removed |
| groupId | integer | Group left |
| groupName | string | Group name for audit trail |
| removedBy | UUID | Admin who performed the action |
| occurredAt | DateTimeImmutable | When removal occurred |

### Raised By

- `AuthorizationService.removeFromGroup()`

### Consumed By

- Audit log
