# UserGroup Uniqueness Rule

## Identity Kernel — Business Rule

### Purpose

Ensures every user group has a unique name and groups remain universal (not app-specific).

### Rules

1. **Name uniqueness:** No two groups may share the same name
2. **Universality:** Groups are not prefixed with app names (no `consult-faculty`, `cert-admin`)
3. **Multi-app membership:** A group can contain users from any app

### Enforcement Point

- `UserGroupService.createGroup()` — before insert, check name uniqueness
- `UserGroupService.updateGroup()` — before update, check name not taken

### Naming Convention

| Correct | Incorrect |
|---------|-----------|
| Faculty | consult-faculty |
| CCS | cert-ccs |
| Students | auth-students |
| Program Chair CS | consult-program-chair |

### Anti-Patterns

- Do not create app-scoped groups (e.g., `consult-admin`)
- Do not allow duplicate group names
- Do not treat groups as roles (groups are for organization, not permission level)
