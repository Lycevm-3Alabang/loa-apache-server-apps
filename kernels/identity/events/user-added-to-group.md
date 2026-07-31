# UserAddedToGroup

## Identity Kernel — Domain Event

### Purpose

Published when a user is added to a user group.

### When It Fires

After `AuthorizationService.addToGroup()` creates the pivot record.

### Payload

| Field | Type | Description |
|-------|------|-------------|
| userId | UUID | User being added |
| groupId | integer | Target group |
| groupName | string | Group name for context |
| addedBy | UUID | Admin who performed the action |
| occurredAt | DateTimeImmutable | When addition occurred |

### Raised By

- `AuthorizationService.addToGroup()`

### Consumed By

- Audit log
