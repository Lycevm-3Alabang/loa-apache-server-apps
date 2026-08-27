# LOA Auth Platform — Admin Console Home (Platform-Admin Zone)

## Product Assembly Component Specification

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-auth-platform`) — web portal + console surface
**Audience:** Architects, Engineers, AI Development Agents
**Depends on:** `dashboard-account.md` v1.2–v1.3 (D15 defers the admin dashboard here), `admin-dashboard.md` v3.0 (console chrome/session model), `admin-audit-log.md` (evidence trail)

> Fills the gap left by `dashboard-account.md` D15: the dashboard at `/`
> currently renders the same apps-first launcher for everyone. This spec
> defines the **platform-admin-only zone** rendered beneath the apps grid —
> turning `/` into the platform-admin's daily operational home without
> touching what non-admins see.

---

# 1. Purpose

Answers:

> **"When a platform-admin opens `/`, what should they see besides their own
> tenant apps — and can it replace a manual sweep of Users / Tenants /
> Audit-log every morning?"**

Non-admins keep today's launcher untouched (D13/D15 hold). Everything below
is additive, `$isAdmin`-gated presentation on an existing authorized page.

---

# 2. Ownership

## Owns

- The admin-only zone markup/styles inside `resources/views/dashboard.blade.php`
- Aggregation queries backing the stat strip and attention queue

## Does Not Own

- The apps grid / greeting / empty state (`dashboard-account.md` §4)
- Any CRUD operation — every action links out to existing `/admin/**` pages
- Authorization — `web.admin` remains the sole gate for admin namespaces;
  this zone is presentation only (invariant 8, `dashboard-account.md` §8)

---

# 3. Locked decisions

| # | Decision | Choice |
|---|---|---|
| H1 | Placement | One **"Platform administration"** section below the apps grid, rendered only when layout `$isAdmin` is true. Non-admin HTML output is byte-identical to today |
| H2 | Zero-JS | Stat cards, feeds, and quick actions are plain server-rendered Blade + POST forms. No charts, no fetch, no inline script |
| H3 | Link-out only | Every actionable item navigates to or posts to an **existing** `/admin/**` route. No new mutations are invented on `/` |
| H4 | Fail-degrade rendering | If an aggregation query throws, the zone collapses to a one-line "Admin metrics temporarily unavailable." notice — never a silent full-hide (which reads as breakage), and the apps grid must always render |
| H5 | Read scope | Counts/feed read primary tables (`users`, `tenants`, `user_tenants`, `refresh_tokens`, `audit_logs`) directly via models — no new summary/cache tables in v1 |

---

# 4. Zone composition (top → bottom)

## 4.1 Platform pulse — stat strip

Four cards in one row (wraps on narrow viewports):

| Card | Primary figure | Sub-line | Links to |
|---|---|---|---|
| Users | total count | `N pending · N disabled` | `/admin/users` |
| Tenants | active count | `N inactive` | `admin.tenants` |
| Active sessions | distinct users holding a non-revoked `refresh_tokens` row | "N users with valid tokens" | — (intentionally read-only) |
| Memberships | `user_tenants` pivot rows | — | `admin.tenants` |

Cards are anchors; only the figures are dynamic. ("Active sessions" counts
distinct users with a non-revoked refresh token — not concurrent browser
sessions. The sub-line clarifies this for scanning admins.)

## 4.2 Needs attention (work queue)

Rendered only when at least one item exists; otherwise omitted entirely.
Each item: one-line description + single action whose destination MUST arrive
**pre-scoped** (filtered list, specific record, or specific export) — never a
generic landing page. The one-line-one-action contract holds through the final
hop.

Display order is fixed by the priority below (data-integrity/security →
blocked users → housekeeping); at most **5** items render, remainder
aggregated into a non-interactive line listing the hidden categories
(e.g., *"2 more: import failures, empty tenants"*).

| Priority | Trigger (query) | Copy | Action (pre-scoped) |
|---|---|---|---|
| 1 | Group membership violating invariant I1* | "Group {name} maps non-members" | → `admin.tenants.{tid}.groups.{gid}.members` (row-level **Remove from group** resolves it; the pivot-grant alternative is gated on `group-permission-management.md` §12.8 Q3) |
| 2 | `users.status = 'pending'` count > 0 | "{n} users awaiting activation" | → `/admin/users?status=pending` |
| 3 | Failed user-import rows exist (not discarded) | "Last user import had failures" | → `admin.users.import.failed` |
| 4 | Failed tenant-member-import rows exist | "Tenant member import has failures" | → tenant members import failed page |
| 5 | Active tenant with zero members | "{tenant} has no members" | → `admin.tenants.{id}` |
| 6 | Production env where `tenants.dev_app_url IS NOT NULL AND dev_app_url != app_url` | "{tenant} has dev URL configured in production" | → `admin.tenants.{id}/edit` |

\* see `group-permission-management.md` v3.0 invariant I1 — this item is
**conditionally rendered**: omitted entirely if GPM v3.0 invariant I1 is not
yet enforced. Once the restructure ships and backfill completes, the item
disappears permanently.

## 4.3 Recent platform activity

Last 10 rows, **weighted toward high-signal actions** — a raw last-10 slice is
dominated by high-volume `auth.tenant_entry` handoffs and drowns the events an
admin actually needs to notice. Composition rule: exclude `auth.tenant_entry`
from the home feed entirely (it is routine traffic, visible in the browser);
fill from the remaining action classes, newest first:
`created_at · actor name · action badge · subject`.

Each row deep-links into the audit browser **pre-filtered by that row's
action** (`admin.audit-logs?action=…`, using the browser's existing query
params). Footer link: **View all → `admin.audit-logs`**. Reuses the exact read
path of the audit browser (same ordering/scoping rules as `admin-audit-log.md`
§6).

## 4.4 Quick actions

Inline button row (all existing GET routes): **New user** (`admin.users.create`)
· **New tenant** (`admin.tenants.create`) · **Import users**
(`admin.users.import`) · **Audit log** (`admin.audit-logs`).

---

# 5. Explicitly excluded

- Charts/graphs of any kind (need JS — violates H2)
- Inline CRUD forms; token/JWT material anywhere in the zone
- Per-user session listing, MFA state (out of scope, `dashboard-account.md` §9)
- Dismissible/acked attention items (no persistence invented in v1 — items
  persist until the underlying data changes; dismissal deferred to v2)

---

# 6. Performance & safety notes

- All aggregations are single-purpose indexed count queries executed only for
  admins on `GET /`; expected added latency budget < 50ms on current data sizes
- Attention-queue queries run lazily and short-circuit on first hit where possible
- H4 try/catch wraps the whole zone render; failures logged, never surfaced
- No new middleware, routes, or controllers — all additions live in
  `PortalController::home()` data assembly + the Blade partial
  (`resources/views/admin/partials/` or inline section)

## Enabling prerequisites (verified)

Both targets already honor GET query params:
- `GET /admin/users` honors `?status=` (including `pending` — added as part of this promotion)
- `GET /admin/audit-logs` honors `?action=`, `?actor=`, `?entity=`, `?from=`, `?to=`

---

# 7. Test checklist

- [ ] Non-admin `/`: byte-for-byte identical DOM to pre-change baseline
- [ ] Admin `/`: zone renders below apps grid; apps grid still first
- [ ] Each stat card figure matches direct DB counts in test fixtures
- [ ] Empty DB / fresh seed: zone renders with zeros, queue absent, feed empty-state text
- [ ] Every attention destination arrives pre-scoped (filtered list / specific record) — asserted per item
- [ ] Attention queue respects priority order and the 5-item cap + category-listing aggregate
- [ ] Home feed excludes `auth.tenant_entry`; rows deep-link into audit browser pre-filtered by action
- [ ] Aggregation failure → one-line notice renders; apps grid intact; no silent hide (H4)
- [ ] Zero inline `<script>` in served HTML (zero-JS guard)

---

# 8. Open questions (resolved at promotion to Final)

| # | Question | Resolution |
|---|---|---|
| Q1 | Which stat cards survive into v1? | All four as specified |
| Q2 | Freshness: live queries vs cached (60s) | Live queries — simplest correct v1; revisit caching only if p95 latency regresses |
| Q3 | Should attention items be dismissible (persisted ack)? | No — v1 always shows until resolved; dismissal deferred to v2 |
| Q4 | Activity feed depth (5 vs 10 vs 20) | 10 |

---

# 9. Changelog

| Version | Change |
|---|---|
| 0.1 | Initial Draft: pulse strip, attention queue, activity feed, quick actions; H1–H5 locked; Q1–Q4 open |
| 0.2 | SME flow review folded in: pre-scoped destinations mandated for every queue item; priority ordering + 5-item cap; feed re-weighted (tenant-entry excluded, per-row filtered deep-links); I1 item's resolution surface clarified against GPM §12.8 Q3; "Active sessions" rename; H4 degrades to visible notice instead of silent hide; §6 enabling-prerequisites table (GET filter support on users list + audit browser); checklist expanded |
| 1.0 | Promoted to Final: Q1–Q4 resolved (all defaults accepted). Blockers fixed: `?status=pending` now honored by `WebAdminController::index()`; I1 attention item conditionally rendered (omitted if GPM v3.0 not enforced). Recommendations applied: "Active sessions" sub-line clarified ("N users with valid tokens"); attention aggregate lists hidden categories; dismissal explicitly deferred to v2; dev_app_url trigger condition precision added |
