# UserGroupCreated

## Identity Kernel — Domain Event

### Purpose

Published when a new user group is created.

### When It Fires

After `UserGroupService.createGroup()` persists the group.

### Payload

| Field | Type | Description |
|-------|------|-------------|
| groupId | integer | New group identifier |
| name | string | Group name |
| createdBy | UUID | Admin who created the group |
| occurredAt | DateTimeImmutable | When creation occurred |

### Raised By

- `UserGroupService.createGroup()`

### Consumed By

- Audit log
