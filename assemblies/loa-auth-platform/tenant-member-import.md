# Tenant Member Bulk Import

## Product Assembly Component Specification

**Version:** 0.1 (Draft)
**Status:** Proposed
**Layer:** Product Assembly (`loa-auth-platform`) — admin surface, tenant context
**Audience:** Architects, Engineers, AI Development Agents

> Specialises [bulk-user-import.md](bulk-user-import.md) for a single-tenant context.
> Reuses its wizard UX and `UserImportController` pipeline; shrinks the CSV contract.

---

## 1. Purpose

Answers:

> **"How do I bulk-add members to THIS tenant app, each into one of ITS groups,
> without repeating the tenant slug on every row?"**

Entry point: **`admin/tenants/{tenant}`** page ("Import members" action).

---

## 2. Differences vs. platform-wide import

| Aspect | bulk-user-import.md | This spec |
|---|---|---|
| Scope | Any tenant, any group | One fixed tenant |
| CSV headers | `name,email,tenant_app,user_group` | `name,email,user_group` |
| Group validation | Group must exist under row's tenant | Group must exist under **page's tenant** only |
| Group picker | Free text from file | Dropdown pre-filtered to `$tenant->userGroups()` (optional aid) |
| Entry point | `admin/users/import` | `admin/tenants/{tenant}` page |

Everything else (parsing, preview wizard, batch size 50, activation emails, upsert
semantics, failed-row export) is inherited unchanged from the base spec.

---

## 3. Routes

### Web (session admin)

```
GET  /admin/tenants/{tenant}/members/import           form (wizard step 1)
POST /admin/tenants/{tenant}/members/import/preview   dry-run validation
POST /admin/tenants/{tenant}/members/import/process   execute
GET  /admin/tenants/{tenant}/members/import/failed    failed-rows CSV download
```

All behind existing `auth:web` + `web.admin` middleware group.
Route names: `admin.tenants.members.import`, `.preview`, `.process`, `.failed`.

### API parity (optional phase 2)

```
POST /api/v1/admin/tenants/{tenant}/members/import/preview
POST /api/v1/admin/tenants/{tenant}/members/import/process
GET  /api/v1/admin/tenants/{tenant}/members/import/failed
```

Middleware: `jwt.auth` + `jwt.permission:users.manage`.
Until built, integrations use the existing `/api/v1/admin/users/import/*`
with `tenant_app` repeated per row — behaviourally equivalent.

---

## 4. CSV Schema

### Required headers (exactly, no extras)

```
name,email,user_group
```

### Field rules

| Field | Required | Format | Notes |
|-------|----------|--------|-------|
| `name` | Yes | Non-empty, ≤255 chars | Display name |
| `email` | Yes | Valid email, ≤255 chars | Natural upsert key (case-insensitive) |
| `user_group` | Yes | Non-empty | Must match a group where `user_groups.tenant_id = {tenant}.id` |

Parser injects the page tenant as `tenant_app = {tenant}->slug` before handing rows to the
shared pipeline, so downstream code stays identical.

### Parser tolerance (implemented)

- RFC-4180 quoting: `"ALamo, Nino Francisco","alamoninofrancisco@gmail.com","cert-admin"` parses correctly.
- `user-group` / `user group` header variants are accepted and normalized.
- Trailing commas on data rows are dropped; blank lines are skipped silently.
- UTF-8 BOM stripped; whitespace around fields trimmed; internal whitespace in group collapsed.

---

## 5. Row validation (preview stage)

Inherited from base spec, plus tenant-scoping changes:

| Check | Failure remark |
|---|---|
| Valid email format | `email is invalid` |
| Email ≤255 chars | `email is too long` |
| Duplicate email **within file** (first wins) | `duplicate email in file` |
| Existing user with status `disabled` | `account is disabled` |
| Group exists **under this tenant** | `user_group does not exist in this tenant` |
| User already member of tenant | informational remark, see §6 |

---

## 6. Processing semantics

Per ready row (same order as base implementation):

1. Resolve `$group = UserGroup::where('name', …)->where('tenant_id', $tenant->id)` — fail row if absent.
2. Find user by email.
   - Missing → create via `IdentityService::register(email, '', name)`,
     set `status = 'pending'`, create activation token, send activation email.
   - Existing → reuse.
3. `TenantService::addUserToTenant(user, tenant)` — idempotent attach.
4. `AuthorizationService::addToGroup(user, group)`.

### Existing-member behaviour (decision)

Default: if the user is already attached to the tenant **and** already in the target
group → mark row `skipped (already a member)` and change nothing.
If attached but in a *different* group of this tenant → move them: remove old
tenant-scoped memberships, add target group, remark `group updated`.
Platform-admin groups are never touched by this rule.

---

## 7. UI

- Button on `admin/tenants/show` members card: **Import members** → wizard step 1.
- Step 1 additionally shows the tenant name/slug and lists its valid groups
  (from `$tenant->userGroups()->orderBy('name')`) so admins know what the CSV may contain.
- Steps 2–4 (preview table, confirm summary, results + failed download) identical to
  the base wizard.

---

## 8. Failure handling

- Batch size 50 per transaction chunk (inherited).
- A thrown error on one row fails only that row → captured with remarks, surfaced in the
  results table, downloadable as `tenant-{slug}-members-import-failed-{timestamp}.csv`
  with headers `name,email,user_group,REMARKS`.

---

## 9. Edge cases & limits

| Case | Behaviour |
|---|---|
| Tenant inactive (`status != active`) | Block process step with banner; preview allowed |
| Group renamed between preview and process | Row fails at process with `user_group does not exist` (re-validate against fresh map, never trust preview cache) |
| User pending activation, re-imported | No duplicate email; no second activation token |
| File >5 MB / wrong mime | Same rejection as base spec |
| **Row count > 5000** | Rejected at upload (422): "split into smaller files" |
| **Field >255 chars** (name / email / user_group) | Row rejected in preview: `... is too long (max 255)` — matches DB column widths, never silently truncated |
| Quoted CSV (`"Doe, John"`) / hyphenated headers / trailing commas / blank lines / BOM | Parsed correctly (RFC-4180) |

### Performance envelope

- Reads are preloaded once per run (users by email, existing memberships, current
  tenant-scoped groups, group-id map); per-row work is writes only.
- Activation emails are queued (`Mail::queue`; synchronous fallback when
  `QUEUE_CONNECTION=sync`).
- Dominant cost is **bcrypt** inside `IdentityService::register()` (~100-150 ms per NEW
  user on shared hosting). Practical ceiling for a single request ≈ 1000-2000 new users;
  `set_time_limit(0)` is attempted but not guaranteed on shared hosts.
- Beyond that, processing must move to a queued job (future work — see §3 phase 3).

---

## 10. Testing checklist

- [ ] Happy path: 3 new users across 2 valid groups → created pending, emailed, attached, grouped
- [ ] Mixed file: new + existing members + already-in-group skips
- [ ] Group-from-another-tenant rejected in preview
- [ ] Duplicate email inside one file → first processed, rest remarked
- [ ] Disabled user rejected
- [ ] Failed-row CSV downloads and round-trips through a re-import
- [ ] Non-admin session cannot reach any route (403)
- [ ] API parity routes enforce `users.manage`
