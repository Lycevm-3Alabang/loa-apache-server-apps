# Permission Resolution Rule

## Identity Kernel — Business Rule

### Purpose

Defines how a user's effective permissions are computed from their group memberships and individual overrides.

### Resolution Order

```
User permissions =
    (Group A permissions)
  ∪ (Group B permissions)
  ∪ ...
  ± (User-level overrides)
```

### Rules

1. **Union of groups:** Permissions from all groups the user belongs to are merged (OR logic)
2. **Deny wins across groups:** A deny for a permission in any of the user's groups removes it from the effective set, even when another group grants it (matches `AuthorizationService::getPermissions()`: granted keys minus denied keys)
3. **User override:** A user-level explicit grant can override a group-level deny. A user-level explicit deny can override a group-level grant
4. **Final decision:** If any effective rule grants the permission after applying overrides, access is allowed

### Specialization: Tenant-Endpoint Level Model

The tenant-endpoint level model (`assemblies/loa-auth-platform/tenant-group-endpoint-grants.md`) replaces union resolution with **group-priority precedence** for tenant app endpoints:

- The grant from the user's **highest-precedence** group (`user_groups.priority`, **1 = highest**, lower value wins) applies.
- Different priorities: the higher-precedence group wins — a lower-precedence `deny` does not beat a higher-precedence grant.
- Equal priorities: `deny` wins, else `admin`/`write` > `read`.
- User overrides still apply last.

The claims-based model above keeps union/OR resolution; only the endpoint-level model uses priority.

### Example

| Source | Permission | Value |
|--------|-----------|-------|
| Group: Faculty | consult.appointments.create | Grant |
| Group: CCS | consult.appointments.create | Grant |
| User override | consult.appointments.create | Deny |

**Result:** Deny (user override wins).

### Enforcement Point

- `AuthorizationService.hasPermission()` — called by middleware on every protected request

### Anti-Patterns

- Do not hardcode permission checks (`if (user.role === 'admin')`)
- Do not skip permission resolution for any protected endpoint
- Do not cache permissions for longer than the access token lifetime (15 min)
