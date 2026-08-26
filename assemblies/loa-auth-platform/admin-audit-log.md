# Admin Audit Log

## Product Assembly Component Specification

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-auth-platform`) — admin surface
**Audience:** Architects, Engineers, AI Development Agents

> Adds an append-only audit trail for privileged web-admin actions and closes
> the P3 hygiene item of `unified-auth-flow.md` §12: every grant/revoke of the
> platform-admin group (`loa-auth-admin`) becomes immutable evidence.

### Access model invariant (user-confirmed 2026-08-25)

Membership in `loa-auth-admin` grants **all access to the auth-app itself**
(the admin console) and nothing else. A platform-admin has that auth-app
access inherently, but it does **not** confer access to any tenant app:
tenant entry always requires explicit `user_tenants` membership
(`unified-auth-flow.md` D1, enforced without bypass). Admin∩tenant overlap is
therefore legitimate by design and carries no warning.

---

## 0. Ownership & reuse (scope guardrails)

| Concept | Owner | This spec |
|---|---|---|
| Audit Record / Actor / Context | **`kernels/audit.md` v1.0 (Approved)** | consumes; never redefines |
| Group membership changes | Identity kernel events (`user-added-to-group`, `user-removed-from-group`) | instruments the assembly write-points that trigger them |
| Concrete storage/logger pattern | `loa-cert-platform` (`AuditLog`, `AuditLogger`, `audit_logs`) | composed analogously — adapted to this assembly, not copied blindly |

**Principles (inherited from the kernel):** records are immutable and
append-only; no update/delete paths exist anywhere; retrieval never mutates;
audit data is evidence, not business data.

---

## 1. Purpose

Answers:

> **"Who granted platform-admin power to whom, when, and from where?"**

and, secondarily, gives operators one place to see what platform
administrators did inside the auth console itself.

---

## 2. Problems being removed

| Today | Consequence |
|---|---|
| Adding/removing a user from `loa-auth-admin` leaves zero trace | Privilege escalation (intentional or accidental) is undetectable |
| The pre-portal workaround "put the tenant member in the admin group" left no mark | Historical misuse is undiscoverable; the audit trail is the remedy |
| No record of admin-console security actions at all | Compliance/investigations start from nothing |

---

## 3. Data model

```
audit_logs (new table)
├─ id            uuid PK
├─ actor_id      uuid nullable   — web-session admin (auth guard) or JWT sub
├─ actor_email   string nullable — denormalized for readability after deletion
├─ action        string          — dotted catalog key (§5)
├─ source        string default 'web'  — 'web' | 'api' | 'system'
├─ entity_type   string nullable — e.g. 'user', 'user_group', 'tenant'
├─ entity_id     string nullable
├─ details       json nullable   — action-specific payload (§5)
├─ ip_address    string nullable
├─ user_agent    string nullable (255)
└─ created_at    timestamp
INDEX (action, created_at) · INDEX (entity_type, entity_id)
```

Deltas vs. cert-platform's table: **no `organization_id`** (auth platform has
no organization concept) and `source` defaults to `'web'`.

No model update/delete methods are generated. Retention/archival is out of
scope (kernel §13 future work).

---

## 4. Logger service

```
App\Services\AuditLogger (new)
├─ __construct(Request $request)
└─ record(string $action,
          ?string $entityType = null,
          ?string $entityId = null,
          ?array $details = null,
          ?string $actorId = null,
          ?string $actorEmail = null): AuditLog
```

- Actor defaults to `Auth::guard('web')->user()` (id + email); explicit
  overrides exist for system/JWT contexts.
- `ip_address` / `user_agent` captured from the current request.
- Constructor-injected wherever used (AI-RULES §3); never static.
- Logging failures MUST NOT break the primary write path: wrap callers'
  `record()` in try/catch `\Throwable` + `report()` — audit loss is logged,
  user-facing operations still succeed.

---

## 5. Instrumented actions (v1 catalog)

| Action key | Write point (`WebAdminController`) | Details payload |
|---|---|---|
| `admin_group.granted` | `storeUserGroup` (:696) when group name = `auth-web.admin_group` | `{group: name}` |
| `admin_group.revoked` | `removeUserGroup` (:718) when group name = `auth-web.admin_group` | `{group: name}` |
| `group.member_added` | `groupsMembersStore` (:642) | `{group: name, member_email}` |
| `group.member_removed` | `groupsMembersRemove` (:663) | `{group: name, member_email}` |
| `tenant.member_added` | `tenantsMembersStore` | `{tenant: slug, member_email}` |
| `tenant.member_removed` | tenant remove path in `TenantService::removeUserFromTenant` callers | `{tenant: slug, member_email}` |
| `user.status_changed` | `updateStatus` | `{from, to}` |
| `auth.tenant_entry` | `PortalController::go` + admin handoffs via `enterTenant` | `{tenant: slug, via: 'portal'\|'sso'}` |

`entity_type/entity_id` = the acted-on subject (user, group, tenant).
Non-admin-group grants flow through `group.member_*`; only the
`loa-auth-admin` group additionally emits the dedicated `admin_group.*` keys.

Catalog is versioned: new keys append, existing keys never change meaning.

---

## 6. Admin UI

Audit log browser:

```
GET /admin/audit-logs        name: admin.audit-logs        (inside admin route group)
GET /admin/audit-logs/export name: admin.audit-logs.export
```

- Newest-first table: timestamp, actor email, action badge, entity, details
  (pretty-printed JSON), IP. Paginated 50/page (`->paginate(50)`).
- Filters (query string, combinable): `action=` prefix match, `actor=`
  email LIKE, `entity=` `type:id`, date `from`/`to`.
- Export streams identical rows as CSV (same filters), filename
  `audit-logs-YYYYMMDD-HHmm.csv`.
- Entry point: sidebar link "Audit log" (permission-gated like other admin
  pages — inherits group middleware).

No dual-hat warning UI: per the access model invariant above, admin∩tenant
overlap is legitimate and the audit trail is the sole hygiene instrument.

---

## 7. Out of scope (tracked separately)

- API/JWT-surface audit parity (cert-style `fromClaims`) — add when auth API
  gains privileged mutations.
- Retention policy, tamper detection, field masking — kernel §13 futures.
- Login attempt auditing — already covered by `login_attempts` table.

---

## 8. Edge cases

| Case | Behaviour |
|---|---|
| Actor deleted after the fact | `actor_id` dangles; `actor_email` preserves attribution |
| Grant + revoke races from two admins | Two independent append-only rows; order shown by `created_at` |
| `AuditLog::create` fails mid-request | Caught, reported, primary action still succeeds |
| CSV export with 100k rows | Streamed via lazy cursor (`->lazy(500)`), no array materialization |
| Filter with `%` wildcards | Treated literally via escaping (same rule as member picker search) |
| Tenant removed while its entries exist | Rows persist; entity reference becomes historical |

---

## 9. Testing checklist

- [ ] Granting `loa-auth-admin` writes exactly one `admin_group.granted` row with actor + IP
- [ ] Revoking writes `admin_group.revoked`; both rows survive user deletion (email retained)
- [ ] Non-admin group adds emit `group.member_added`, NOT `admin_group.*`
- [ ] Portal `go()` by an admin-member emits `auth.tenant_entry` with `via:'portal'`
- [ ] Browser lists newest-first with all columns; filters combine correctly
- [ ] Wildcards in filters match literally
- [ ] CSV export matches filtered set; streams without memory blowup (large fixture)
- [ ] No update/delete endpoints or model methods exist for audit rows
- [ ] Primary action still succeeds when the logger throws (fault-injection test)

---

## 10. Doc control

| Version | Date | Change |
|---|---|---|
| 0.1 Draft | 2026-08-25 | Initial draft; dual-hat warning chip proposed |
| 1.0 Final | 2026-08-25 | Access-model invariant codified (admin group ⇒ auth-app console only; tenant access strictly explicit); warning chip removed per user decision; promoted to Final. Unblocks unified-auth-flow.md P3 hygiene item |
