# Tenant Member Search Picker

## Product Assembly Component Specification

**Version:** 0.3 (Draft)
**Status:** Proposed
**Layer:** Product Assembly (`loa-auth-platform`) — admin surface, tenant context
**Audience:** Architects, Engineers, AI Development Agents

> Replaces the unbounded "select a user" dropdown on `admin/tenants/{tenant}`
> with a debounced search-and-stage picker, and adds per-group unenrolment
> controls. Complements [tenant-member-import.md](tenant-member-import.md)
> (bulk path stays authoritative for mass adds).

---

## 0. Unenrolment & removal semantics (scope guardrails)

**Principle: members are unenrolled, never deleted.** No action on this page may
delete a `users` row, alter a password/status, or touch any group whose
`tenant_id` is NULL (platform-admin groups) or belongs to another tenant.

| Action | Mechanism | Group cleanup |
|---|---|---|
| Remove from tenant (existing row ✕) | `admin.tenants.members` `action=remove` | **FIX**: must now also detach ALL groups where `user_groups.tenant_id = {tenant}` — today `TenantService::removeUserFromTenant` detaches only the pivot, leaving orphaned memberships |
| Unenroll from ONE group (new per-group ✕ chip) | reuses existing `POST /admin/groups/{group}/members/{userId}/remove` (`admin.groups.members.remove`) | detaches that single group only |
| Delete user | ❌ out of scope, permanently | — |

Removing the last tenant-scoped group is allowed and leaves a plain tenant
member. Platform-admin groups are never rendered as removable chips (they are
not tenant-scoped), giving structural protection on top of the rule above.

---

## 1. Purpose

Answers:

> **"With 1,000+ platform users, how do I find and add ONE user to this tenant
> without scrolling a dropdown or loading every account into the page?"**

---

## 2. Problems being removed

| Today | Consequence |
|---|---|
| `tenantsShow()` eager-loads ALL non-members | Page payload grows linearly with platform size |
| Native `<select>` of every user | Unusable beyond a few hundred entries |
| Member exclusion via `pluck()` + `whereNotIn(id[])` | Degrades as both arrays grow |

---

## 3. Search endpoint

```
GET /admin/tenants/{tenant}/members/search?q=<term>    name: admin.tenants.members.search
```

- Placed INSIDE the existing `Route::prefix('admin')` group so `auth:web` +
  `web.admin` are inherited; additionally throttled `->middleware('throttle:120,1')`.
- Response: `application/json`

```json
{ "data": [ { "id": "uuid", "name": "...", "email": "...", "status": "active|pending|disabled" } ] }
```

### Query semantics

| Rule | Detail |
|---|---|
| Minimum length | `< 2` chars → `{data: []}` without hitting DB |
| Match | case-insensitive `LIKE %term%` on `name` OR `email`; **`%`, `_`, `\` escaped** via `addcslashes($term, '%_\\')` (MySQL's default LIKE escape — no `ESCAPE` clause needed) |
| Exclusion | `whereNotExists` subquery on `user_tenants` pivot for this tenant (never pluck+whereNotIn) |
| Status | `disabled` accounts excluded entirely |
| Ordering | exact-email match first (`ORDER BY CASE WHEN email = ? THEN 0 ELSE 1 END, email` — bind the lowercased term; do not re-sort in PHP), then `email ASC` |
| Limit | hard `LIMIT 20` |

---

## 4. UI behaviour (`admin/tenants/show`)

- Dropdown removed; replaced by text input placeholder *"Search by name or email…"*.
- Debounce **250 ms**; abort in-flight request when a newer keystroke lands.
- Suggestions panel lists ≤20 candidates: name, email, status badge
  (`pending` shown amber; `disabled` never appears).
- Click candidate → stages exactly one user (name + email shown beside input,
  ✕ to unstage). Staging replaces any previously staged user.
- **Add to tenant** button enabled only while staged; POSTs unchanged to
  `admin.tenants.members` (`user_id`, `action=add`) → server redirect/flash as today.
- States: `Searching…` while in flight · `No matches for "<q>"` on empty result ·
  silent no-op on network error (input keeps value).
- After successful add: staged user cleared, members list refreshes (full page
  reload via redirect is acceptable — matches current post/redirect/get flow).
- **Group chips on member rows**: each tenant-scoped group tag renders a small
  **server-rendered inline `<form>`** per chip (same pattern as the existing
  Remove-from-tenant button — no JS required for unenrolment) posting to
  `admin.groups.members.remove` with confirm dialog ("Unenroll from <group>?").
  Row keeps its existing Remove-from-tenant button, whose semantics now include
  the §0 group cleanup.
  > Known pre-existing exposure, unchanged by this spec: a crafted POST can hit
  > `admin.groups.members.remove` with a GLOBAL group id. Chips only ever render
  > tenant-scoped groups; hardening that endpoint against global ids is declared
  > out of scope here and tracked separately.

Non-JavaScript fallback is explicitly out of scope: admins of this platform are
assumed JS-capable (wizard already requires it).

### Action toolbar layout

All member-add affordances live in ONE aligned toolbar directly under the
`Members (n)` heading — no floating buttons in the card header, no orphaned
form row:

```
┌─ Members (1,247) ─────────────────────────────────────────────────────┐
│                                                                       │
│ [ ⌕ Search by name or email…            ] [John Doe ✕] [Add to tenant]│
│                                                             ┌────────┐│
│                                             [⇪ Import members]        │
│                                                                       │
│ ── member table ──────────────────────────────────────────────────────│
```

Rules:

| Element | Treatment |
|---|---|
| Toolbar container | one `display:flex; gap:.5rem; flex-wrap:wrap` row; search input `flex:1 1 16rem` |
| Control heights | uniform `2.5rem`; border-radius `var(--radius-xl)`; font-size `.875rem` |
| `Add to tenant` | primary (`.button`); disabled until a candidate is staged; label constant — never changes to spinner (progress is the page reload) |
| Staged pill | appears between input and Add: name + email truncated, ✕ unstages (also bound to Escape per keyboard section) |
| `Import members` | ghost (`.button-ghost`) anchored to the toolbar's END (`margin-left:auto` on desktop), drops to its own wrapped line on narrow screens — visually secondary to Add |
| Suggestion panel | absolutely positioned under the input, full input width, above table z-index |
| Empty state | *"No members yet. Search for a user above or import a CSV."* (position references removed) |

### Keyboard accessibility (minimum viable)

- Staged user removable via `Escape` when input focused.
- `Enter` in the search input submits the staged user if one is staged.
- Arrow-key navigation of the suggestion list is declared out of scope.

---

## 5. Controller changes

| Location | Change |
|---|---|
| NEW `searchMembers(Request, Tenant)` on `WebAdminController` | implements §3 |
| `TenantService::removeUserFromTenant()` | **FIX**: after pivot detach, also detach `userGroups()` where `tenant_id = $tenantId` (§0 hygiene) |
| `tenantsShow()` | delete `$nonMembers` query entirely |
| `routes/web.php` | one GET search route (named, throttled) before the `/members/import` group |
| `show.blade.php` | replace select form with picker markup + ~60 LOC vanilla JS; unified action toolbar (§4); server-rendered per-group ✕ chip forms on member rows; fixed empty-state copy |

Write path (`tenantsMembersStore`) untouched — validation, permissions,
flash messaging all inherited. Group-unenroll reuses the existing
`admin.groups.members.remove` endpoint verbatim.

---

## 6. Edge cases

| Case | Behaviour |
|---|---|
| Search term contains `%` / `_` / `\` | Treated literally (escaped), e.g. searching `100%` finds literal "100%" |
| User already member types exact email | Not returned (excluded); UI shows "No matches" — correct, use Remove instead |
| Two admins remove the same user concurrently | Both pivots detach idempotently; second sees success, one outcome |
| Two admins add the same user concurrently | `syncWithoutDetaching` idempotent → one membership, both see success |
| Unenroll from last tenant-scoped group | Allowed; user remains a plain tenant member |
| Remove-from-tenant with multiple groups | All tenant-scoped groups detached atomically in the same request; platform-admin + other-tenant groups untouched |
| Tenant page open in two tabs | Harmless: staging is per-tab DOM state; write path idempotent |
| Very long names/emails | CSS ellipsis truncation, full value in `title` attribute |
| Slow DB (>1s) | Previous results stay visible until replaced (no flicker-to-empty) |

---

## 6.5 Scaling notes

`LIKE %term%` cannot use ordinary indexes. Acceptable to ~tens of thousands of
users on this hosting tier. Beyond that, add a FULLTEXT index on
`users(name, email)` or a generated trigram column — do not shard the endpoint.

---

## 7. API parity (phase 2, out of scope here)

Tenant apps may later need the same lookup:
`GET /api/v1/admin/tenants/{tenant}/members/search?q=` under
`jwt.auth` + `users.view`. The web endpoint's query builder should be written so
the JSON shape ports directly.

---

## 8. Testing checklist

- [ ] Search returns matching users by partial name and partial email
- [ ] Existing tenant members never appear in results
- [ ] Disabled users never appear in results
- [ ] SQL wildcard input (`%`, `_`) matches literally
- [ ] Exact-email match ranks first
- [ ] Unauthenticated search redirects to login (group middleware)
- [ ] Response JSON shape: `data[].{id,name,email,status}` only
- [ ] Staging replaces prior selection; Add posts correct uuid; success flash shown
- [ ] Added user disappears from subsequent searches; members count increments
- [ ] `< 2` chars issues no request (network tab assertion)
- [ ] Per-group ✕ chip detaches only that group; other memberships intact
- [ ] Remove-from-tenant also detaches ALL tenant-scoped groups of that user
- [ ] Remove-from-tenant leaves platform-admin group and other-tenant groups untouched
- [ ] Group chips rendered only for groups where `tenant_id` matches the page tenant
- [ ] Group chips work without JavaScript (server-rendered forms)
- [ ] Users row still exists after every removal action (no deletion anywhere)
- [ ] `$nonMembers` code path gone from `tenantsShow()`
- [ ] Existing member-add flow covered by tests after blade rewrite
