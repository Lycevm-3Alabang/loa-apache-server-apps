# UserGroupDeleted

## Identity Kernel — Domain Event

### Purpose

Published when a user group is deleted.

### When It Fires

After `UserGroupService.deleteGroup()` removes the group and its pivot records.

### Payload

| Field | Type | Description |
|-------|------|-------------|
| groupId | integer | Deleted group identifier |
| name | string | Group name (for audit trail) |
| deletedBy | UUID | Admin who deleted the group |
| occurredAt | DateTimeImmutable | When deletion occurred |

### Raised By

- `UserGroupService.deleteGroup()`

### Consumed By

- Audit log
