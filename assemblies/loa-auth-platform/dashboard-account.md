# Auth App Dashboard & Account Rework

## Product Assembly Component Specification

**Version:** 1.1
**Status:** Final — v1.1 supersedes v1.0 hub-location + auto-enter decisions (console consolidation)
**Layer:** Product Assembly (`loa-auth-platform`) — web portal + console surface
**Audience:** Architects, Engineers, AI Development Agents
**Depends on:** `unified-auth-flow.md` Final v1.0 (§3 pipeline, §5 smart router), `admin-dashboard.md` (console chrome, §2 session model)

> Gives the auth platform a real default landing page (`/`) instead of a JSON
> health dump, makes that page the **universal console dashboard** — rendered
> inside the auth admin console chrome for every signed-in user — reworks
> **`/account`** into a clean readout (name self-service editable; password
> change behind a link), and makes **change-password deep-linkable from tenant
> apps** via a return intent.

---

## 0. Locked decisions

### v1.1 additions

| # | Decision | Choice |
|---|---|---|
| D8 | Dashboard location | The dashboard at `/` renders **inside the auth admin console layout** (`layouts/admin`). Non-platform-admins never use the `/admin` URL space — the console *experience* is shared, the admin *namespace* is not |
| D9 | Admin area boundary | `/admin/**` remains exclusively platform-admin (member of `loa-auth-admin`) with the existing `web.admin` 403. Tenant-level roles (e.g. a tenant's `cert-admin`) carry **no weight** on the auth platform — only the platform admin group does |
| D10 | Console nav visibility | Users / Tenants / Audit-log topbar links render **only for platform-admins** (`@if ($isAdmin)`); the sections stay server-enforced regardless of what the nav shows |
| D11 | Auto-enter removed | **Supersedes v1.0 D2 and `unified-auth-flow.md` D3/D4-fallback.** Direct login, activation, or any authenticated visit lands **everyone** on the dashboard. Only a validated `?redirect=` payload enters a tenant app directly. Membership count never skips the dashboard |
| D12 | Shared logout | `POST /logout` (`console.logout`, `auth:web`) serves every console user — sign-out cannot sit behind the `web.admin` gate once non-admins render the console chrome. `admin.logout` kept as compatible alias |

### v1.0 decisions (still in force except where superseded)

| # | Decision | Choice |
|---|---|---|
| D1 | Root route | `/` becomes the dashboard entry point (auth-aware router); JSON health check moves to `/health` |
| D2 | ~~Single-app users~~ | ~~Auto-enter preserved~~ — **reversed by D11 (v1.1)** |
| D3 | Dashboard content | Enrolled-app tiles + compact **account summary strip**; `/launcher` remains as redirecting alias |
| D4 | `/account` fields | Email + status **read-only**; name **editable** via Edit-reveals-input pattern; **no password fields on the page** |
| D5 | Change password | Link-only from `/account` → dedicated `GET /account/password` page; form **keeps current-password verification**; shows global-sign-out warning |
| D6 | Tenant-app deep link | Expired portal session stores a session **return intent** (`return_to`, internal paths only); post-login returns the user to the form |
| D7 | Name validation | `required|string|max:255`, trimmed — matches admin user-management rules |

### Related specs

| Concept | Owner |
|---|---|
| Login pipeline, smart router, portal session, handoff tail | `unified-auth-flow.md` §3–§5 (D3/D4 superseded — see D11) |
| Console chrome, admin session/logout semantics | `admin-dashboard.md` §2/§3 |
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
| `/` | GET | `home` | none | **Replaced**: dashboard router closure → `PortalController::home()` |
| `/health` | GET | — | none | **New**: relocated JSON health payload (same body as old `/`) |
| `/launcher` | GET | `portal.launcher` | `auth:web` | **Changed**: 302 redirect to `/` (name preserved — callers/bookmarks unaffected) |
| `/logout` | POST | `console.logout` | `auth:web` | **New** (v1.1): shared sign-out for all console users |
| `/admin/logout` | POST | `admin.logout` | `auth:web` | **Moved** out of the `web.admin` group — same handler, now reachable by every signed-in user |
| `/account` | GET | `portal.account` | `capture.return`, `auth:web` | **Reworked view** (§5) |
| `/account/name` | POST | `portal.account.name` | `auth:web`, `throttle:10,60` | **New**: self-service name update |
| `/account/password` | GET | `portal.account.password.show` | `capture.return`, `auth:web` | **New**: standalone change-password page |
| `/account/password` | POST | `portal.account.password` | `auth:web`, `throttle:10,60` | Unchanged handler (`PortalController::updatePassword`) |
| `/admin/**` (rest) | any | `admin.*` | `auth:web`, `web.admin` | **Unchanged** — platform-admin only, 403 otherwise (D9) |

Router logic for `GET /` (reuses existing primitives):

```
guest + ?redirect=<any>                   -> redirect sso.login WITH query string intact
guest, plain                              -> redirect route('login')
authenticated + validated ?redirect=      -> enterForTarget() handoff (or denial flash)
authenticated, plain                      -> view('dashboard')  — EVERYONE (D11)
```

The `?redirect=` passthrough must preserve the **entire query string**, since
validation stays where it lives today (`safeRedirectUrl()` inside the login
pipeline). The router performs no origin checks of its own. Login/activation
fallbacks and tile-denial flashes point at `route('home')` via
`PortalRouter`; there is no membership-count shortcut anywhere.

---

## 4. Dashboard view (console consolidation)

`resources/views/dashboard.blade.php` **extends `layouts/admin`** (v1.1) so
every signed-in user gets the console chrome — dark topbar with the LOA
lockup, their name chip, and sign-out. Content:

1. **Account summary strip** — name, email, status badge; link → `portal.account`.
2. **Tenant tiles** — one POST-CSRF form per active membership → `portal.go`;
   host shown under tenant name. This is how everyone reaches their apps.
3. **Empty state** — *"You don't have access to any applications yet. Contact
   your administrator."* when a non-admin holds zero memberships.

Topbar rules (D10):

- Brand lockup links to `/` (the dashboard), no longer to `admin.users`.
- **Dashboard** link always visible.
- **Users / Tenants / Audit log** links wrapped in `@if ($isAdmin)`; `$isAdmin`
  is computed in the layout itself from the web-guard user's group membership,
  so admin pages need no per-view plumbing.
- Sign-out posts to `console.logout`.

Zero-JS, server-rendered. Tokens are never embedded in dashboard HTML
(`unified-auth-flow.md` invariant 2). `/admin/**` pages keep rendering the
same layout unchanged — admins simply see the extra links everywhere.

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
| Single-tenant member logs in directly | Dashboard (v1.1 D11 — no auto-enter); they click the tile to enter |
| Tenant-app login with payload | Straight into that app, dashboard bypassed exactly once (payload present) |
| Non-admin opens `/admin/users` directly | 403 via `web.admin` — unchanged; tenant-level admin roles confer nothing (D9) |
| Admin opens `/admin/users` | Unchanged; topbar additionally shows Users/Tenants/Audit log on every console page |
| Bookmarked `/launcher` | 302 → `/` |
| Uptime monitor polling `/` for JSON | Must be repointed to `/health` (§10) |
| `return_to` = `//evil.com` or `https://...` or `/\evil.com` | Ignored → normal routing |
| Name submit 256+ chars / empty after trim | Validation error, editor stays open |
| User disabled between page load and Save | Disable revokes sessions; `auth:web` bounces to login |
| Password change while holding multiple tenant sessions | All refresh tokens revoked; warning text already shown (§5) |
| Concurrent tabs submit different names | Last write wins; acceptable for self-service display name |
| Non-admin POSTs `/admin/logout` | Allowed (sign-out is self-service, not privileged) — same handler as `/logout` |

---

## 8. Security invariants

1. `return_to` accepts **only** same-app relative paths (`^/[^/\\]`, enforced at capture AND consumption) — open-redirect proof.
2. `?redirect=` passthrough adds **no new origin acceptance**; `safeRedirectUrl()` remains the sole validator.
3. CSRF on all new POSTs (`portal.account.name`, `console.logout`; existing password POST already covered).
4. Current-password verification mandatory for password change — unchanged service path.
5. Membership re-checked server-side in `go()` and in every handoff — dashboard tiles remain untrusted hints.
6. No JWT material rendered into dashboard/account HTML.
7. Name change is audit-logged (`auth.profile.name_update`).
8. Nav visibility (`$isAdmin`) is presentation only — **authorization stays in `WebAdminMiddleware`**; hiding a link never grants a route.
9. Only membership in `config('auth-web.admin_group')` confers platform-admin; no tenant role mapping exists on this app (D9).

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
| `routes/web.php` | Root closure → `PortalController::home()`; add `/health`, `/account/name`, GET `/account/password`, `POST /logout`; `/launcher` becomes redirect (name kept); `/admin/logout` moved out of the `web.admin` group |
| `app/Services/PortalRouter.php` | **New** — shared smart-router primitives (`route()`, `enterForTarget()`, `enterTenant()` + handoff queueing, membership/admin helpers) extracted from `WebAuthController`; fallback + denial flash target `route('home')`; auto-enter deleted (v1.1) |
| `app/Http/Controllers/WebAuthController.php` | Delegates routing to `PortalRouter`; consumes return intent in `handleWebLogin()` + `activate()` |
| `app/Http/Middleware/CaptureReturnIntent.php` | **New** — captures guest intent, bounces guests itself |
| `bootstrap/app.php` | `capture.return` alias + priority prepend ahead of `Authenticate` |
| `app/Http/Controllers/PortalController.php` | New `home()` (renders dashboard for every authed user), `updateName()` (audited), `showPasswordForm()`; `go()` delegates to `PortalRouter::enterTenant()`; `launcher()` removed |
| `resources/views/dashboard.blade.php` | New — console-styled dashboard extending `layouts/admin` |
| `resources/views/layouts/admin.blade.php` | Brand lockup → `/`; Dashboard link always; Users/Tenants/Audit log behind `@if ($isAdmin)` computed in-layout; sign-out posts `console.logout` |
| `resources/views/launcher.blade.php` | Deleted |
| `resources/views/account.blade.php` | Reworked readout + zero-JS Edit-name (`?edit=name`) + password link |
| `resources/views/account-password.blade.php` | New standalone change-password page (+ sign-out warning) |

Rollout order:

1. Add `/health`; repoint any uptime monitors (cPanel deployment notes).
2. Ship dashboard router + view; convert `/launcher` to redirect.
3. Console consolidation: layout conditionals + shared logout routes.
4. Rework `/account`; add name endpoint + audit event.
5. Split out change-password page + warning text.
6. Return-intent capture (middleware + priority) + post-login consumption.
7. Feature tests: `PortalDashboardTest` (root router, health, dashboard-for-all,
   nav visibility, 403 boundary, return-intent incl. malicious-path rejection,
   name validation, password page) and updated `PortalLauncherTest`,
   `SsoAuthTest`, `ActivationTest`.

---

## 11. Test checklist

- [ ] `/` guest plain → `/login`
- [ ] `/` guest with `?redirect=<tenant origin>` → `sso/login` with query intact
- [ ] `/` single-tenant member → dashboard renders (v1.1: no auto-enter)
- [ ] `/` multi-tenant member → dashboard tiles render
- [ ] `/` platform-admin → Users/Tenants/Audit log nav visible
- [ ] `/` non-admin → those nav links absent
- [ ] Non-admin direct GET `/admin/users` → 403
- [ ] Tenant-scoped admin role name (e.g. cert-admin group) confers nothing on `/admin/**`
- [ ] `/health` returns the old JSON payload
- [ ] `/launcher` → 302 `/` (route name still resolves)
- [ ] `console.logout` signs out non-admins; `admin.logout` still resolves for admins
- [ ] `/account` shows email/status read-only; no password fields
- [ ] Edit-name happy path persists + flash + audit row
- [ ] Edit-name rejects empty / >255 chars
- [ ] Change-password link → form page; wrong current password rejected
- [ ] Warning text visible on password page
- [ ] Expired session deep link: login → lands back on `/account/password`
- [ ] `return_to=//evil.com` / `/\evil.com` ignored

---

## 12. Changelog

| Version | Change |
|---|---|
| v1.0 | Initial Final: root router, health relocation, account rework, password split, return intent |
| v1.1 | Console consolidation — dashboard rendered in admin console chrome at `/` for all users; D8–D12 added; v1.0 D2 reversed (auto-enter removed, supersedes `unified-auth-flow.md` D3/D4-fallback); shared logout outside `web.admin`; `/admin/**` boundary restated (D9) |

