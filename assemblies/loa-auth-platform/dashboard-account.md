# Auth App Dashboard & Account Rework

## Product Assembly Component Specification

**Version:** 1.0
**Status:** Final — implemented; covered by `PortalDashboardTest` + `PortalLauncherTest` (308 passing)
**Layer:** Product Assembly (`loa-auth-platform`) — web portal surface
**Audience:** Architects, Engineers, AI Development Agents
**Depends on:** `unified-auth-flow.md` Final v1.0 (§3 pipeline, §5 smart router, §6 launcher, §9 account)

> Gives the auth platform a real default landing page (`/`) instead of a JSON
> health dump, turns the launcher into a proper **dashboard**, reworks
> **`/account`** into a clean readout (name self-service editable; password
> change behind a link), and makes **change-password deep-linkable from tenant
> apps** — including surviving an expired portal session via a return intent.

---

## 0. Locked decisions

| # | Decision | Choice |
|---|---|---|
| D1 | Root route | `/` becomes the dashboard entry point (auth-aware router); JSON health check moves to `/health` |
| D2 | Single-app users | **Auto-enter preserved** — exactly-one-membership non-admins skip the dashboard straight into their app (upholds `unified-auth-flow.md` D3/D4) |
| D3 | Dashboard content | Tenant tiles + compact **account summary strip**; `/launcher` remains as redirecting alias |
| D4 | `/account` fields | Email + status **read-only**; name **editable** via Edit-reveals-input pattern; **no password fields on the page** |
| D5 | Change password | Link-only from `/account` → dedicated `GET /account/password` page; form **keeps current-password verification**; shows global-sign-out warning |
| D6 | Tenant-app deep link | Expired portal session stores a session **return intent** (`return_to`, internal paths only); post-login returns the user to the form |
| D7 | Name validation | `required|string|max:255`, trimmed — matches admin user-management rules |

### Related specs

| Concept | Owner |
|---|---|
| Login pipeline, smart router, portal session, launcher tiles | `unified-auth-flow.md` §3–§6 |
| Admin audit trail (`recordSafe`) | `admin-audit-log.md` |
| Base auth layout / styling conventions | `web-ui.md` |

---

## 1. Purpose

Answers:

> **"What does a signed-in user land on when they open the auth platform — and
> how can a tenant app send its user to change their password without losing
> them mid-journey?"**

Today `/` answers nothing (raw JSON), the account page front-loads a password
form, names can only be changed by admins, and a tenant-app "Change Password"
link strands the user on the launcher whenever their portal session lapsed.

---

## 2. Problems being removed

| Problem | Today | After |
|---|---|---|
| Default page | `/` returns `{"service": ...}` JSON | Auth-aware dashboard router |
| Password UX | Full current/new/confirm form always visible on `/account` | Details-only readout; password form behind a link on its own page |
| Name editing | Self-service impossible (admin-only via WebAdminController) | Inline Edit on `/account` |
| Tenant deep links | Expired portal session → login → dumped on launcher/auto-enter | Return intent restores the user to `/account/password` |
| Health endpoint | Occupies `/` | Dedicated `/health` |

---

## 3. Route changes

| Route | Method | Name | Middleware | Change |
|---|---|---|---|---|
| `/` | GET | — | none | **Replaced**: dashboard router closure → `PortalController::home()` |
| `/health` | GET | — | none | **New**: relocated JSON health payload (same body as old `/`) |
| `/launcher` | GET | `portal.launcher` | `auth:web` | **Changed**: 302 redirect to `/` (name preserved — callers/bookmarks unaffected) |
| `/account` | GET | `portal.account` | `auth:web` | **Reworked view** (§5) |
| `/account/name` | POST | `portal.account.name` | `auth:web`, `throttle:10,60` | **New**: self-service name update |
| `/account/password` | GET | `portal.account.password.show` | `auth:web` | **New**: standalone change-password page |
| `/account/password` | POST | `portal.account.password` | `auth:web`, `throttle:10,60` | Unchanged handler (`PortalController::updatePassword`) |

Router logic for `GET /` (reuses existing primitives):

```
guest + ?redirect=<any>                   -> redirect sso.login WITH query string intact
guest, plain                              -> redirect route('login')
authenticated                             -> routeAuthenticatedUser()
    ├─ explicit target + member           -> enterTenant (unchanged handoff tail)
    ├─ non-admin + exactly 1 membership   -> enterTenant (auto-enter, D2)
    └─ otherwise                          -> view('dashboard')
```

The `?redirect=` passthrough must preserve the **entire query string**, since
validation stays where it lives today (`safeRedirectUrl()` inside the login
pipeline). The router performs no origin checks of its own.

---

## 4. Dashboard view

New `resources/views/dashboard.blade.php`, extends `layouts.auth`.
`resources/views/launcher.blade.php` is deleted (the redirect happens before
view resolution).

Content:

1. **Account summary strip** — name, email, status badge; link → `portal.account`.
2. **Tenant tiles** — identical mechanics to the current launcher: one POST-CSRF
   form per active membership → `portal.go`; host shown under tenant name.
3. **Auth Admin Console tile** — when `isAdmin` (unchanged gate).
4. **Empty state** — *"You don't have access to any applications yet. Contact
   your administrator."* (admins always see ≥ Console tile).

Zero-JS, server-rendered, matching auth-surface conventions. Tokens are never
embedded in dashboard HTML (`unified-auth-flow.md` invariant 2).

---

## 5. `/account` page behavior

### Readout

| Field | Presentation |
|---|---|
| Email | Plain text, never editable here |
| Status | Plain text (`ucfirst`), never editable |
| Name | Text + **Edit button**; clicking swaps to `<input>` (pre-filled) + Save / Cancel |

Name save posts to `portal.account.name`; on success redirect back with
status flash; on failure re-render with validation errors, input still open.

### Name endpoint contract

```
POST /account/name        name: portal.account.name
  name: required|string|max:255 (trimmed)
```

- Updates `users.name` for the session user **only**.
- Emits audit event via `AuditLogger::recordSafe('auth.profile.name_update',
  'user', $user->id, [...])` — consistent with `admin-audit-log.md` evidence trail.

### Change password

- `/account` shows a single **"Change password" link** → `portal.account.password.show`.
- The standalone page renders the existing three-field form
  (current / new / confirm) — **current-password verification retained**
  (existing `updatePassword()` + `IdentityService::updatePassword()` untouched).
- Required hint text on the form:
  *"Changing your password signs you out of all LOA applications."*
  (reflects global refresh-token revocation — tenant apps lose sessions when
  their access token expires).
- Success → back to the password page with status flash; web session kept.

---

## 6. Return intent (tenant-app deep links)

Tenant apps may link users directly to `https://<auth-host>/account/password`.
Portal-session cookies survive normal handoff (SameSite=Lax permits top-level
GET navigation), so this usually just works. For an **expired** session:

1. Before `auth:web` bounces the guest to `/login`, store
   `session(['return_to' => '/account/password'])` (or whatever internal path
   was requested).
2. After successful login **or** activation, if `return_to` exists and passes
   validation → redirect there and forget the key.
3. If absent/invalid → normal routing (smart router: auto-enter or launcher).

**Validation rule (hard):** `return_to` must match `^/[^/\\]` — a single leading
slash followed by a character that is neither another slash nor a backslash.
Rejects protocol-relative URLs (`//`), schemes, and backslash tricks. Only
same-app relative paths ever qualify. Both the capture (middleware) and the
consumer (`WebAuthController::consumeReturnIntent()`) enforce it.

**As-built mechanics:** a dedicated `capture.return` middleware handles guest
visits on `/account` + `/account/password` — it stores the intent AND returns
the login redirect itself. It must NOT let the request reach `auth:web`
unchecked, because an `AuthenticationException` unwinds past
`StartSession::saveSession()` and the session write would be silently lost.
Since framework `Authenticate` sits on the middleware-priority list,
`bootstrap/app.php` registers the capture via `prependToPriorityList(...,
AuthenticatesRequests)` so it always sorts ahead of `auth:web`. The intent is
consumed unconditionally after successful login/activation (stale intents are
discarded even when an explicit tenant target outranks them).

---

## 7. Edge cases

| Case | Behaviour |
|---|---|
| Guest opens `/` with `?redirect=` for unknown/inactive origin | Redirected to `sso/login?...` unchanged; existing denial flash path handles it post-submit |
| Guest opens `/` plain | `/login` |
| Single-tenant member opens `/` while logged in | Straight into their app (D2) — dashboard never blocks |
| Admin with zero memberships opens `/` | Dashboard with Console tile (+ empty tenant area) |
| Bookmarked `/launcher` | 302 → `/` |
| Uptime monitor polling `/` for JSON | Must be repointed to `/health` (§10) |
| `return_to` = `//evil.com` or `https://...` | Ignored → normal routing |
| Name submit 256+ chars / empty after trim | Validation error, editor stays open |
| User disabled between page load and Save | Disable revokes sessions; `auth:web` bounces to login |
| Password change while holding multiple tenant sessions | All refresh tokens revoked; warning text already shown (§5) |
| Concurrent tabs submit different names | Last write wins; acceptable for self-service display name |

---

## 8. Security invariants

1. `return_to` accepts **only** same-app relative paths (`^/[^/\\]`, enforced at capture AND consumption) — open-redirect proof.
2. `?redirect=` passthrough adds **no new origin acceptance**; `safeRedirectUrl()` remains the sole validator.
3. CSRF on both new POSTs (`portal.account.name`, existing password POST already covered).
4. Current-password verification mandatory for password change — unchanged service path.
5. Membership re-checked server-side in `go()` — dashboard tiles remain untrusted hints.
6. No JWT material rendered into dashboard/account HTML.
7. Name change is audit-logged (`auth.profile.name_update`).

---

## 9. Out of scope

- Sessions listing, MFA, avatar, email-change flows (`unified-auth-flow.md` §9 exclusions stand).
- Editing status/email anywhere in the portal surface.
- Tenant-app-side UI changes (they only gain a URL to link to).
- API (`routes/api.php`) equivalents of profile self-service.

---

## 10. Implementation & rollout notes

| File | Change |
|---|---|
| `routes/web.php` | Root closure → `PortalController::home()`; add `/health`, `/account/name`, GET `/account/password`; `/launcher` becomes redirect (name kept) |
| `app/Services/PortalRouter.php` | **New** — shared smart-router primitives (`route()`, `enterForTarget()`, `autoEnterTenant()`, `enterTenant()` + handoff queueing, membership/admin helpers) extracted from `WebAuthController` so login pipeline and dashboard share one implementation |
| `app/Http/Controllers/WebAuthController.php` | Delegates routing to `PortalRouter`; consumes return intent in `handleWebLogin()` + `activate()` |
| `app/Http/Middleware/CaptureReturnIntent.php` | **New** — captures guest intent, bounces guests itself |
| `bootstrap/app.php` | `capture.return` alias + priority prepend ahead of `Authenticate` |
| `app/Http/Controllers/PortalController.php` | New `home()`, `updateName()` (audited), `showPasswordForm()`; `go()` delegates to `PortalRouter::enterTenant()`; `launcher()` removed |
| `resources/views/dashboard.blade.php` | New (launcher tiles + account summary strip) |
| `resources/views/launcher.blade.php` | Deleted |
| `resources/views/account.blade.php` | Reworked readout + zero-JS Edit-name (`?edit=name`) + password link |
| `resources/views/account-password.blade.php` | New standalone change-password page (+ sign-out warning) |

Rollout order:

1. Add `/health`; repoint any uptime monitors (cPanel deployment notes).
2. Ship dashboard router + view; convert `/launcher` to redirect.
3. Rework `/account`; add name endpoint + audit event.
4. Split out change-password page + warning text.
5. Return-intent capture (middleware + priority) + post-login consumption.
6. Feature tests: `PortalDashboardTest` (root router, health, auto-enter,
   return-intent incl. malicious-path rejection, name validation, password
   page) and updated `PortalLauncherTest`.

---

## 11. Test checklist

- [ ] `/` guest plain → `/login`
- [ ] `/` guest with `?redirect=<tenant origin>` → `sso/login` with query intact
- [ ] `/` single-tenant member → auto-enter handoff unchanged
- [ ] `/` multi-tenant member → dashboard tiles render
- [ ] `/` admin → Console tile present
- [ ] `/health` returns the old JSON payload
- [ ] `/launcher` → 302 `/` (route name still resolves)
- [ ] `/account` shows email/status read-only; no password fields
- [ ] Edit-name happy path persists + flash + audit row
- [ ] Edit-name rejects empty / >255 chars
- [ ] Change-password link → form page; wrong current password rejected
- [ ] Warning text visible on password page
- [ ] Expired session deep link: login → lands back on `/account/password`
- [ ] `return_to=//evil.com` ignored

