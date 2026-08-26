# Auth App Dashboard & Account Rework

## Product Assembly Component Specification

**Version:** 1.3
**Status:** Final — v1.3 supersedes v1.2 account-page chrome + change-password mechanism (console `/account`, emailed reset link, post-reset sign-out)
**Layer:** Product Assembly (`loa-auth-platform`) — web portal + console surface
**Audience:** Architects, Engineers, AI Development Agents
**Depends on:** `unified-auth-flow.md` Final v1.0 (§3 pipeline, §5 smart router), `admin-dashboard.md` (console chrome, §2 session model)

> Gives the auth platform a real default landing page (`/`) instead of a JSON
> health dump, makes that page the **universal console dashboard** — rendered
> inside the auth admin console chrome for every signed-in user — and reworks
> **`/account`** onto the same console chrome (inline name edit; change-password
> emails a reset link). Tenant-app deep links land on `/account` via a session
> return intent.

---

## 0. Locked decisions

### v1.3 additions

| # | Decision | Choice |
|---|---|---|
| D16 | `/account` in console chrome | `account.blade.php` extends `layouts/admin` — topbar (brand, Dashboard link, admin links when applicable), account menu (avatar / Manage account / Sign out) and design tokens identical to every other console surface. The split-screen login-style layout is gone from the signed-in experience |
| D17 | Change password = emailed reset link | The inline current/new/confirm form and its page (`/account/password`) are **removed**. `/account` shows one **"Change password"** button that POSTs `portal.account.password.email`, reuses the forgot-password service path to email a reset link to the session user's own address, flashes *"Reset link sent to {email}."*, and never navigates away. Throttled by `password.reset.throttle`. Possession of the emailed token **is** the verification |
| D18 | Post-reset sign-out everywhere | On successful reset via the emailed link, `WebResetController::reset()` also logs out the web guard, invalidates the session, and regenerates the token before redirecting (tenant allowlist target or login). Combined with existing refresh-token revocation in `IdentityService::resetPassword`, every LOA surface requires fresh authentication |

### v1.2 additions

| # | Decision | Choice |
|---|---|---|
| D13 | Console tile removed | The dashboard body never renders an **Auth Admin Console** tile — including for platform-admins. Admin entry lives solely in the topbar nav links (D10). The dashboard is an **application launcher**, nothing else |
| D14 | Topbar account menu | The bare name chip + standalone Sign-out button are replaced by a **user dropdown** in the topbar: the trigger shows the signed-in user's name; the open menu lists **Manage account** (`portal.account`) and **Sign out** (`POST console.logout`, CSRF). Implemented with `<details>/<summary>` — the zero-JS invariant holds. Renders for every console user on every page using `layouts/admin` |
| D15 | Role-split dashboard | Non-admins get the **apps-first launcher** (§4). The dedicated admin dashboard is **deferred to a future spec**; until then admins render the same launcher for their own memberships, with console access via the topbar nav |

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
| D5 | ~~Change password~~ | ~~Link-only from `/account` → dedicated `GET /account/password` page; form keeps current-password verification; shows global-sign-out warning~~ — **reversed by D17 (v1.3)**: the form and its page are removed; the emailed reset link is the only self-service path |
| D6 | Tenant-app deep link | Expired portal session stores a session **return intent** (`return_to`, internal paths only); post-login returns the user there. **Amended by v1.3:** the target is `/account` — the former `/account/password` target is deleted |
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
> how can a tenant app send its user to account self-service without losing
> them mid-journey?"**

Deliverables across v1.0–v1.3: an apps-first launcher at `/` inside shared
console chrome, a topbar account menu (Manage account / Sign out), a
console-chrome `/account` (readout + inline name edit; change-password emails
a reset link), and a return intent that restores lapsed-session visitors to
`/account`.

---

## 2. Problems being removed

| Problem | Today | After |
|---|---|---|
| Default page | `/` returns `{"service": ...}` JSON | Auth-aware dashboard router |
| Password UX | Full current/new/confirm form always visible on `/account` | Readout-only page; one button emails a reset link (v1.3 D17) |
| Name editing | Self-service impossible (admin-only via WebAdminController) | Inline Edit on `/account` |
| Tenant deep links | Expired portal session → login → dumped on launcher/auto-enter | Return intent restores the user to `/account` (target repointed by v1.3) |
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
| `/account` | GET | `portal.account` | `capture.return`, `auth:web` | **Reworked view** (§5); v1.3 — rendered in console chrome (D16) |
| `/account/name` | POST | `portal.account.name` | `auth:web`, `throttle:10,60` | **New**: self-service name update |
| `/account/password/email` | POST | `portal.account.password.email` | `auth:web`, `password.reset.throttle` | **New** (v1.3, D17): emails a reset link to the session user's address; flash + no navigation |
| ~~`/account/password`~~ | GET/POST | ~~`portal.account.password.*`~~ | — | **Removed** (v1.3, D17): inline form page deleted; tenant deep links repoint to `/account`; no alias by design |
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

## 4. Dashboard view (v1.2 — apps-first launcher)

`resources/views/dashboard.blade.php` continues to **extend `layouts/admin`**
so every signed-in user gets the console chrome. v1.2 changes the body
composition: the in-body **account summary strip is removed** (identity and
account actions consolidated into the topbar account menu — D14), and the
**Auth Admin Console tile is gone for everyone** (D13). What remains is a pure
application launcher:

1. **Greeting header** — "Welcome back, {first name}" with a one-line helper
   ("Choose an application to open.").
2. **Apps grid** — one POST-CSRF form per active membership → `portal.go`
   (mechanics unchanged, presentation upgraded). Each card shows:
   - a brand-tinted initial block (first character of the tenant name),
   - the tenant name,
   - the target host from `effectiveAppUrl()`.
   The entire card is one click target; hover lifts it (brand border + soft
   shadow); `:focus-visible` ring for keyboard users.
3. **Single-app emphasis** — exactly one active membership renders as a single
   full-width emphasized card ("Continue to {app}") instead of a grid. This
   reduces friction **without reintroducing auto-enter** (v1.1 D11 stands —
   the click always happens; membership count never skips the dashboard).
4. **Empty state** (zero memberships) — heading + reassurance copy:
   *"You don't have access to any applications yet. Once your administrator
   enrolls you, your apps will appear here."* Route behavior unchanged.
   The gate simplifies to plain `$tenants->isEmpty()` — the legacy
   `&& !$isAdmin` guard existed only to steer admins toward the console tile
   that v1.2 D13 deletes; kept as-is, zero-membership admins would render an
   empty grid instead of this panel.

### Topbar account menu (D14)

Replaces the `.user-chip` span and the ghost Sign-out button:

```blade
<details class="user-menu">
    <summary class="user-menu-trigger">
        <span class="user-menu-avatar">{{ mb_substr($name, 0, 1) }}</span>
        <span class="user-menu-name">{{ $name }}</span>
        <span class="user-menu-caret" aria-hidden="true"></span>
    </summary>
    <div class="user-menu-panel">
        <div class="user-menu-header">
            <span class="user-menu-title">{{ $name }}</span>
            <span class="user-menu-email">{{ $email }}</span>
        </div>
        <a href="{{ route('portal.account') }}">Manage account</a>
        <form method="post" action="{{ route('console.logout') }}">
            @csrf
            <button type="submit">Sign out</button>
        </form>
    </div>
</details>
```

Rules:

- Trigger = avatar initial + truncated display name (CSS ellipsis) + caret.
  Long names never widen the topbar.
- Panel header repeats full name + email; items: **Manage account**
  (`GET portal.account`), divider, **Sign out** (`POST console.logout`, CSRF).
- Zero-JS: `<details>/<summary>` toggles natively and is keyboard-accessible.
  Clicking outside does not close the panel (accepted trade-off — the next
  interaction navigates or re-toggles; no JS is introduced).
- The menu renders on **every** console page (layout-level) for both admins
  and non-admins — no per-view plumbing.
- All other topbar rules stand (D10): Users / Tenants / Audit log links stay
  behind `@if ($isAdmin)`; authorization stays in `WebAdminMiddleware`.
- **No `role="menu"` / `role="menuitem"`**: those ARIA roles promise arrow-key
  handling that zero-JS cannot deliver. Plain disclosure semantics —
  `<details>/<summary>` with an ordinary link and button — are the correct,
  honest pattern.

Zero-JS, server-rendered throughout. Tokens are never embedded in dashboard HTML
(`unified-auth-flow.md` invariant 2). `/admin/**` pages keep rendering the
same layout unchanged.

---

## 5. `/account` page behavior

### Chrome (v1.3 D16)

`account.blade.php` extends `layouts/admin`. The signed-in user sees the same
topbar as everywhere else in the console — brand lockup, Dashboard link,
Users/Tenants/Audit log when platform-admin, and the account menu
(avatar / name / Manage account / Sign out) from D14. Session flashes render
in the shared alert slots. The split-screen auth layout used by
login/register/activate is no longer part of any signed-in page.

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

### Change password = emailed reset link (v1.3 D17)

- `/account` shows a single **"Change password"** button posting to
  `portal.account.password.email` (CSRF). No navigation away.
- Handler reuses the forgot-password service path but **not its copy**: it
  mints a reset token, queues the standard reset email to the session user's
  own address, then flashes *"Reset link sent to {email}."* The guest flow's
  anti-enumeration wording ("If the email exists…") is pointless here — the
  user is signed in with that address.
- Throttled by `password.reset.throttle` (same limiter as the guest flow).
- Hint under the button: *"The reset link signs you out of all LOA
  applications — including this one — once you use it."* (D18)
- Audit: `AuditLogger::recordSafe('auth.profile.password_reset_request',
  'user', $user->id, [...])`.
- **Deleted:** routes `portal.account.password.show` / `portal.account.password`,
  `PortalController::showPasswordForm()` / `updatePassword()`, and view
  `account-password.blade.php`. Current-password verification disappears with
  them — the emailed signed token is the proof of identity.

### Post-reset sign-out (v1.3 D18)

On successful reset (`WebResetController::reset()` →
`IdentityService::resetPassword()` revokes all refresh tokens), the controller
additionally ends the completing browser session:

```php
Auth::guard('web')->logout();
$request->session()->invalidate();
$request->session()->regenerateToken();
```

Then: allowlisted tenant `redirect=` → redirect away; otherwise the login page
with *"Password updated. Please sign in."*

### Deep links

Tenant apps that deep-linked users to `/account/password` repoint to
`/account` (`capture.return` remains mounted there). The old path 404s by
design — no alias; `/account` is the single self-service surface.

Known limitation (accepted): other browsers' portal cookies are not
blocklisted (no AuthenticateSession); only the completing browser's session is
killed synchronously plus every refresh token. Tenant apps are fully signed
out on all devices.

Second accepted limitation (v1.3): a user who cannot receive email (dead
mailbox, swallowed sends) has no remaining password-change path — the inline
form is gone. Recovery falls back to administrator assistance via the console;
no portal-side fallback exists by design.

---

## 6. Return intent (tenant-app deep links)

Tenant apps may link users directly to `https://<auth-host>/account` — the
single self-service surface since v1.3 (the former `/account/password` target
is deleted, D17). Portal-session cookies survive normal handoff
(SameSite=Lax permits top-level GET navigation), so this usually just works.
For an **expired** session:

1. Before `auth:web` bounces the guest to `/login`, store
   `session(['return_to' => '/account'])` (or whatever internal path was
   requested).
2. After successful login **or** activation, if `return_to` exists and passes
   validation → redirect there and forget the key.
3. If absent/invalid → normal routing (dashboard; no auto-enter per D11).

**Validation rule (hard):** `return_to` must match `^/[^/\\]` — a single leading
slash followed by a character that is neither another slash nor a backslash.
Rejects protocol-relative URLs (`//`), schemes, and backslash tricks. Only
same-app relative paths ever qualify. Both the capture (middleware) and the
consumer (`WebAuthController::consumeReturnIntent()`) enforce it.

**As-built mechanics:** a dedicated `capture.return` middleware handles guest
visits on `/account` — it stores the intent AND returns the login redirect
itself. It must NOT let the request reach `auth:web`
unchecked, because an `AuthenticationException` unwinds past
`StartSession::saveSession()` and the session write would be silently lost.
Since framework `Authenticate` sits on the middleware-priority list,
`bootstrap/app.php` registers the capture via `prependToPriorityList(...,
AuthenticatesRequests)` so it always sorts ahead of `auth:web`. The intent is
consumed unconditionally after successful login/activation (stale intents are
discarded even when an explicit tenant target outranks them).

*v1.3 note:* the capture previously also mounted on `/account/password`; that
mount point is gone with the page (D17).

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
| Password change while holding multiple tenant sessions | Reset link used → all refresh tokens revoked AND completing browser session force-signed-out (D18); user lands on login |
| Authenticated "Change password" clicked repeatedly | `password.reset.throttle` caps emails; flash copy identical every time (anti-enumeration) |
| Guest hits removed `GET /account/password` | 404 — no alias by design (v1.3 D17) |
| Expired-session deep link to `/account` | `capture.return` stores intent; post-login lands on `/account` |
| Reset completed in a different browser than an open portal session | Completing browser signed out + all refresh tokens revoked; other portal cookies survive until natural expiry (accepted limitation, §5) |
| Concurrent tabs submit different names | Last write wins; acceptable for self-service display name |
| Non-admin POSTs `/admin/logout` | Allowed (sign-out is self-service, not privileged) — same handler as `/logout` |
| Platform-admin opens `/` (v1.2 D13) | Launcher shows only their tenant memberships; console reachable via Users/Tenants/Audit log nav links |
| Admin with zero memberships (v1.2) | Standard empty-state panel; dedicated admin dashboard deferred (D15) |
| Very long display name in topbar (v1.2) | Trigger truncates with ellipsis; full name + email visible in the open menu header |
| Keyboard-only user opens account menu (v1.2) | `<summary>` is natively focusable/activatable; menu items are tabbable in DOM order |
| Click outside the open account menu (v1.2) | Panel stays open (zero-JS trade-off, D14); harmless — next interaction navigates or re-toggles |
| Status badge location after v1.2 | Dashboard no longer shows it; account status remains on `/account` readout |

---

## 8. Security invariants

1. `return_to` accepts **only** same-app relative paths (`^/[^/\\]`, enforced at capture AND consumption) — open-redirect proof.
2. `?redirect=` passthrough adds **no new origin acceptance**; `safeRedirectUrl()` remains the sole validator.
3. CSRF on all new POSTs (`portal.account.name`, `console.logout`; existing password POST already covered).
4. Password self-service uses the **emailed reset-token path** (v1.3 D17) — the signed emailed link replaces current-password verification; requests throttled by `password.reset.throttle`.
5. Membership re-checked server-side in `go()` and in every handoff — dashboard tiles remain untrusted hints.
6. No JWT material rendered into dashboard/account HTML.
7. Name change and password-reset requests are audit-logged (`auth.profile.name_update`, `auth.profile.password_reset_request`).
8. Nav visibility (`$isAdmin`) is presentation only — **authorization stays in `WebAdminMiddleware`**; hiding a link never grants a route.
9. Only membership in `config('auth-web.admin_group')` confers platform-admin; no tenant role mapping exists on this app (D9).
10. Reset completion terminates the completing browser session (logout + session invalidate + token regenerate) and all refresh tokens (D18).

---

## 9. Out of scope

- Sessions listing, MFA, avatar, email-change flows (`unified-auth-flow.md` §9 exclusions stand).
- Editing status/email anywhere in the portal surface.
- Tenant-app-side UI changes (they only gain a URL to link to).
- API (`routes/api.php`) equivalents of profile self-service.
- Blocklisting portal cookies across *other* browsers (AuthenticateSession-style) — accepted limitation (§5).
- A portal-side fallback when reset-email delivery fails (dead mailbox); admin-assisted recovery covers it (§5).
- A replacement for the deleted current/new/confirm self-service form; emailed reset link is the only path.

---

## 10. Implementation & rollout notes

| File | Change |
|---|---|
| `routes/web.php` | Root closure → `PortalController::home()`; add `/health`, `/account/name`, `POST /logout`; `/launcher` becomes redirect (name kept); `/admin/logout` moved out of the `web.admin` group. **v1.3:** swap GET+POST `/account/password` for POST `/account/password/email` |
| `app/Services/PortalRouter.php` | **New** — shared smart-router primitives (`route()`, `enterForTarget()`, `enterTenant()` + handoff queueing, membership/admin helpers) extracted from `WebAuthController`; fallback + denial flash target `route('home')`; auto-enter deleted (v1.1) |
| `app/Http/Controllers/WebAuthController.php` | Delegates routing to `PortalRouter`; consumes return intent in `handleWebLogin()` + `activate()` |
| `app/Http/Middleware/CaptureReturnIntent.php` | **New** — captures guest intent, bounces guests itself |
| `bootstrap/app.php` | `capture.return` alias + priority prepend ahead of `Authenticate` |
| `app/Http/Controllers/PortalController.php` | New `home()` (renders dashboard for every authed user), `updateName()` (audited); `go()` delegates to `PortalRouter::enterTenant()`; `launcher()` removed. **v1.3:** `showPasswordForm()` / `updatePassword()` deleted; new `emailResetLink()` (reuses forgot-password service path, audited) |
| `app/Http/Controllers/WebResetController.php` | **v1.3 (D18):** on successful reset — `Auth::guard('web')->logout()`, session invalidate + token regenerate — before tenant/login redirect |
| `resources/views/dashboard.blade.php` | New — console-styled dashboard extending `layouts/admin`. **v1.2:** apps-first launcher — greeting header, initial-block cards, single-app emphasis, reworked empty state; profile strip removed; Auth Admin Console tile removed |
| `resources/views/layouts/admin.blade.php` | Brand lockup → `/`; Dashboard link always; Users/Tenants/Audit log behind `@if ($isAdmin)` computed in-layout. **v1.2:** name chip + ghost Sign-out button replaced by `<details class="user-menu">` account menu (Manage account / Sign out) with scoped CSS |
| `resources/views/launcher.blade.php` | Deleted |
| `resources/views/account.blade.php` | Reworked readout + zero-JS Edit-name (`?edit=name`) + password link. **v1.3:** extends `layouts/admin` (console chrome, D16); "Change password" becomes a POST button → `portal.account.password.email` with sign-out-everywhere hint |
| `resources/views/account-password.blade.php` | ~~New standalone change-password page (+ sign-out warning)~~ — **Deleted** (v1.3 D17) |

Rollout order (final state; do not build superseded intermediates):

1. Add `/health`; repoint any uptime monitors (cPanel deployment notes).
2. Ship dashboard router + view; convert `/launcher` to redirect.
3. Console consolidation: layout conditionals + shared logout routes.
4. Rework `/account`; add name endpoint + audit event.
5. Return-intent capture (middleware + priority) + post-login consumption,
   targeting `/account`.
6. Feature tests: `PortalDashboardTest` (root router, health, dashboard-for-all,
   nav visibility, 403 boundary, return-intent incl. malicious-path rejection,
   name validation) and updated `PortalLauncherTest`,
   `SsoAuthTest`, `ActivationTest`.
7. v1.2 chrome: topbar account menu in the layout; apps-first dashboard body;
   Auth Admin Console tile deleted; `PortalLauncherTest` admin-tile assertions
   inverted (admin now asserts the tile is **absent** and nav links present).
8. v1.3: `/account` onto console chrome; change-password → emailed reset link
   (`POST /account/password/email`). Do **not** build the interim
   `/account/password` form pair — on upgrades from an earlier deploy, delete
   its routes, handlers, and `account-password.blade.php`. Force sign-out in
   `WebResetController`; replace password-form tests with email-send,
   throttle, post-reset sign-out, and guest-flow regression coverage;
   repoint return-intent coverage to `/account`.

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
- [ ] `/account` renders console chrome — topbar with brand, Dashboard link, account menu (v1.3 D16); no split-screen auth layout
- [ ] "Change password" POST → flash *"Reset link sent to {email}."*, no navigation, reset email queued to own address (v1.3 D17)
- [ ] Repeat sends capped by `password.reset.throttle`
- [ ] `GET /account/password` → 404 (removed, no alias)
- [ ] Completing reset via emailed link: refresh tokens revoked AND portal session force-signed-out → login page flash (v1.3 D18)
- [ ] Guest forgot-password flow unaffected by D18 — no session to kill; logout() is a harmless no-op; completes to login flash as before
- [ ] Expired-session deep link to `/account`: login → lands back on `/account`
- [ ] `return_to=//evil.com` / `/\evil.com` ignored
- [ ] `/` non-admin → "Auth Admin Console" absent (v1.2 D13)
- [ ] `/` platform-admin → "Auth Admin Console" absent; Users/Tenants/Audit log nav still present
- [ ] Account menu trigger shows the session user's name on `/` and on `/admin/users`
- [ ] Menu exposes Manage account → 200 on `/account`; Sign out posts `console.logout` and terminates session (both roles)
- [ ] Single-membership user → one emphasized full-width card (no grid)
- [ ] Multi-membership user → all membership cards render, each POSTing `portal.go`
- [ ] Zero-membership user (incl. platform-admin) → empty-state panel; never an empty grid (`$tenants->isEmpty()` gate)
- [ ] Layout contains no inline JavaScript (zero-JS guard)

---

## 12. Changelog

| Version | Change |
|---|---|
| v1.0 | Initial Final: root router, health relocation, account rework, password split, return intent |
| v1.1 | Console consolidation — dashboard rendered in admin console chrome at `/` for all users; D8–D12 added; v1.0 D2 reversed (auto-enter removed, supersedes `unified-auth-flow.md` D3/D4-fallback); shared logout outside `web.admin`; `/admin/**` boundary restated (D9) |
| v1.2 | Apps-first launcher + topbar account menu — D13–D15 added: Auth Admin Console tile removed from dashboard body (admin entry is nav-only, D13); name chip + ghost sign-out replaced by a `<details>`-based account menu exposing Manage account + Sign out (D14); profile strip removed from dashboard body; single-app emphasis card; empty-state copy reworked; dedicated admin dashboard explicitly deferred (D15) |
| v1.3 | Account surface rework — D16–D18 added: `/account` rendered in console chrome with full topbar/account menu (D16); change-password becomes a single POST that emails the standard reset link (inline form + `/account/password` page deleted, D17); completing a reset now force-signs-out the portal session on top of refresh-token revocation (D18); return-intent deep links repoint to `/account` |

