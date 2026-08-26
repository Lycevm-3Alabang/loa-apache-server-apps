# Unified Login Pipeline & Portal Launcher

## Product Assembly Component Specification

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-auth-platform`) — web auth surface
**Audience:** Architects, Engineers, AI Development Agents

> Replaces the divergent `/login` and `/sso/login` pipelines with ONE login
> handler, grants every successful web login a persistent **portal session**,
> and adds a post-login **launcher page** so members (and platform admins)
> can reach every application they are explicitly entitled to. Fixes the
> dead-end activation flow (activate → nowhere useful) and removes the
> "add them to `loa-auth-admin`" workaround for tenant-member access.

---

## 0. Locked decisions

| # | Decision | Choice |
|---|---|---|
| D1 | Admin access to tenant apps | **Explicit membership only** — admins enter tenant apps solely via `user_tenants`; no implicit super-access |
| D2 | When launcher appears | **Smart routing** — login initiated from a tenant app (`redirect=` present) goes straight there; login started at the auth domain lands on the launcher |
| D3 | Single-app users | **Skip launcher** — exactly one tile (no admin console) → auto-enter |
| D4 | Post-activation destination | **Launcher** (then D3 shortcut applies) |
| D5 | Portal session | **Yes** — persistent web-guard session on the auth domain for ALL users, members included |
| D6 | Member self-service | **Minimal `/account` page** (profile + change password) — P2 |
| D7 | Redirect policy | **Tenant rows only** — `safeRedirectUrl()` accepts exclusively active-tenant origins (`app_url`/`dev_app_url`, `redirect_origins`/`dev_redirect_origins`); `AUTH_ALLOWED_REDIRECTS` is retired from the validation path (config key removed in P3 cleanup) |
| D8 | Public endpoints | **Both URLs permanent** — `/login` primary; `/sso/login` kept indefinitely as functional alias, marked deprecated in docs only |
| D9 | Error vocabulary & audit | **Single string** `"Invalid credentials"` on every login failure class. Platform-admin **audit log is a SEPARATE spec** (not yet written) — this spec references it by name, never implements it |

### Related specs

| Concept | Owner |
|---|---|
| Platform-admin audit trail (who granted/revoked `loa-auth-admin`, admin entries into tenant apps, management actions) | Future `admin-audit-log.md` — out of scope here; P3 items in §12 that depend on it are gated on that spec existing |

**D7 migration ordering (hard dependency):** `safeRedirectUrl()` (base
`Controller.php`) is shared by the login pipeline AND the forgot/reset-password
redirect flow (`WebResetController`, API forgot-password — see
`PostResetRedirectTest`). Before the allowlist fallback is removed, every
currently allowlisted origin (`aces-api…`, `e-cert.vercel.app`) MUST exist as
an active Tenant row with matching `redirect_origins`, or emailed reset links
silently lose their `&redirect=` target. Removal of the fallback + deletion of
the config key happens in P3; until then the allowlist remains as legacy
fallback for ALL flows (login included).

---

## 1. Purpose

Answers:

> **"After signing in once, how does any user reach every app they belong to —
> and why did activation previously dump me on a login form I can't use?"**

---

## 2. Problems being removed

| Today | Location | Consequence |
|---|---|---|
| Admins hard-denied from SSO | `ssoLogin()` WebAuthController.php:328-334 | Platform admins cannot enter ANY tenant app |
| Bare `/login` rejects all non-admins (`$tenant = null`) | WebAuthController.php:55-56,82 | Operators add members to `loa-auth-admin` to make login "work" → unintended admin-console access |
| Two near-duplicate login handlers drifting apart | `login()` :41-123 vs `ssoLogin()` :283-371 | Payload drift (SSO payload missing `iat`/`exp`, :344-360 vs :107-108); two error vocabularies; ~30 duplicated handoff lines |
| `with('error',…)` flashes never rendered on auth pages | layouts/auth.blade.php (FIXED in working tree) | Invalid/expired activation tokens silently dumped on /login |
| Activation success redirects to bare `/login` | `activate()` WebAuthController.php:187 | New member lands where they cannot sign in |
| Debug `<script>` logs email + redirect target to console | sso-login.blade.php:28-37 | Information disclosure; must be deleted |

---

## 3. Unified login pipeline

```
POST /login      ──┐
                   ├─► handleWebLogin(Request, bool $requireTenantIntent)
POST /sso/login ──┘
```

One private method on `WebAuthController`; both public endpoints become thin
wrappers. `requireTenantIntent` = true only for `POST /sso/login`.

### Pipeline stages

1. Validate `email`, `password`, optional `redirect` (unchanged rules).
2. Resolve intent: `$target = safeRedirectUrl(redirect)`.
   - `requireTenantIntent && $target === null` → back with error (D9 string).
3. Authenticate via `IdentityService::login()` (tenant may be null when no intent).
4. Establish **portal session** for EVERY authenticated user (§4).
5. Route via **smart-routing helper** (§5).

### Normalisation

| Aspect | Rule |
|---|---|
| Redirect validation | `safeRedirectUrl()` — tenant origins first; `AUTH_ALLOWED_REDIRECTS` remains legacy fallback until P3 (D7 migration ordering, §0) |
| Encrypted payload keys | always `access_token, refresh_token, token_type, expires_in, user{id,email,name}, tenant{id,slug}|null, iat, exp` — SSO gains `iat/exp`; `/login` unchanged |
| Fragment fallback | tokens-only querystring (both modes, unchanged) |
| Errors | one string (D9); throttling unchanged (`throttle:10,60` on `/sso/login`; consider same for `/login` — out of scope here) |
| Handoff tail | encrypt-or-fragment → session → `auth.redirect` interstitial extracted into one private method shared by pipeline AND launcher entry (§7) |

### Routes

```php
Route::get('/login',  …)->name('login');            // unchanged names/URLs
Route::post('/login', …);
Route::get('/sso/login',  …)->name('sso.login');    // permanent functional alias (D8), deprecated in docs
Route::post('/sso/login', …);
```

GET pages collapse to one view: `showSSOLogin()` renders the SAME `login`
blade with SSO eyebrow/intro copy (hidden `redirect` field preserved).
`sso-login.blade.php` is deleted along with its debug script block.

---

## 4. Portal session

On step 4 above, for admins AND members:

```php
Auth::guard('web')->login($user);
$request->session()->regenerate();
```

- Portal session ≠ API credentials. Tenant tokens remain per-entry handoffs;
  nothing token-bearing is stored client-side by the launcher.
- Existing `auth:web` middleware protects the new surfaces; `web.admin`
  middleware continues to guard `admin.*` exclusively (non-admin members get
  403 there, unchanged).
- `admin.logout` (routes/web.php:62) becomes the universal logout — renamed
  conceptually to "sign out", reachable from launcher/account.

---

## 5. Smart-routing helper

Single private resolver, reused by pipeline, `activate()`,
`showLogin()`/`showSSOLogin()` (when already portal-authenticated):

```
routeAuthenticatedUser(Request, ?string $intent):
  ├─ $intent valid AND membership($user, tenantOf($intent)) → handoff($intent)   [§3 tail]
  ├─ $intent valid AND NOT member → launcher with denial flash for that app
  ├─ tiles = tenants(isMember) ∪ isAdmin ? {console} ; ∪ {account}
  ├─ |tiles| == 1 (and it's a tenant app, no console) → auto-enter that app
  └─ otherwise → GET /launcher
```

Membership check: `TenantService::isMember()` (:130) — admins have NO bypass (D1).

---

## 6. Launcher

```
GET  /launcher            name: portal.launcher    middleware: auth:web
POST /launcher/go/{tenant} name: portal.go         middleware: auth:web + throttle:30,60
```

- New `PortalController` (constructor-injected `TenantService`,
  `EncryptionService`, `ActivationService` as needed) — no logic added to
  `WebAdminController`.
- View `resources/views/launcher.blade.php` extends `layouts.auth`;
  card lists tiles:
  - one per ACTIVE tenant where `isMember($user)` → label = tenant name;
    posts CSRF form to `portal.go`.
  - `isAdmin($user)` → additional **Auth Admin Console** tile → `admin.users`.
  - always an **Account** tile → `/account` (P2 placeholder until §9 ships).
- `PortalController::go()`: re-validates membership server-side, mints fresh
  tokens via `IdentityService::login`-issued pair ONLY from the just-used
  session — implementation MUST reuse the existing handoff tail (§3); no
  credential re-prompt while portal session valid.
- Empty state: *"You don't have access to any applications yet. Contact your
  administrator."* (admins always see ≥ Console + Account).
- Zero-JS: plain forms + server rendering, matching auth-surface conventions.

---

## 7. Activation reroute

`activate()` success path (WebAuthController.php:184-187):

1. Password set (unchanged validation/service).
2. Establish portal session (§4).
3. `routeAuthenticatedUser($request, null)` → typically launcher (D4), or
   straight into the single tenant app (D3).

Failure paths unchanged (redirect `/login` + error flash — now visible).

---

## 8. Admin × tenant apps (D1)

| Change | Detail |
|---|---|
| DELETE deny block | `ssoLogin()` WebAuthController.php:328-334 removed entirely |
| Membership gate | admins pass the SAME `isMember()` check; adding an admin to a tenant pivot grants entry, nothing else changes |
| Admin-group hygiene (P3) | admin UI warning when a user without platform staff permissions sits in `loa-auth-admin`; audit-log entry on grant/revoke of that group |

---

## 9. Account page (P2)

```
GET  /account     name: portal.account     middleware: auth:web
POST /account/password  name: portal.account.password
```

Profile readout (name/email/status) + change-password form reusing existing
password policy + current-password verification. Out of scope: sessions
listing, MFA, profile edits.

---

## 10. Edge cases

| Case | Behaviour |
|---|---|
| Member hits `/sso/login` intent for app they're NOT in | launcher + denial flash (not generic error) |
| Admin with zero tenant memberships, bare `/login` | launcher: Console + Account tiles only (no skip — D3 requires exactly one TOTAL tile) |
| Member with zero memberships | launcher empty state (§6) |
| Multi-tenant member from tenant-app redirect | straight handoff, launcher never blocks |
| Already portal-authenticated visits `/login` | smart router runs immediately (no form) |
| Tenant deactivated between tile render and click | `resolveTenantByRedirectOrigin`/status check fails → launcher + denial flash |
| Portal session expires mid-launcher | `auth:web` bounces to `/login` (guest flow unchanged) |
| Encrypted-payload mode off | fragment fallback identical to today (§3 normalisation) |
| Concurrent logins different apps | independent handoffs; portal session regenerate only at authentication step, not per-entry |

---

## 11. Security invariants

1. Every `redirect` passes `safeRedirectUrl()` — launcher introduces NO new
   origin acceptance.
2. Tokens minted only at authentication or explicit tile click — never
   embedded in launcher HTML.
3. Membership re-checked server-side inside `go()` (never trust rendered tile).
4. Session regenerate at login; CSRF on all POSTs (`portal.go`,
   `portal.account.password`).
5. One error string (D9) — no oracle distinguishing unknown-email /
   bad-password / not-a-member.
6. Debug script removal is mandatory before merge (§2 row 6).

---

## 12. Phasing

| Phase | Items |
|---|---|
| P1 | §3 unify pipeline · §4 portal session · §5 router · §6 launcher · §7 activation reroute · §8 deny-block removal · §2 debug-script deletion |
| P2 | §9 account page (+ real Account tile target) |
| P3 | admin-group hygiene (§8 row 3 — gated on future `admin-audit-log.md`) · D7 allowlist retirement: seed tenant rows for aces/e-cert → drop `AUTH_ALLOWED_REDIRECTS` fallback from `Controller::safeRedirectUrl()` + `config/auth-web.php` → update `PostResetRedirectTest` + `web-ui.md`/`cert-readiness.md` docs |

---

## 13. Testing checklist

- [ ] `POST /login` and `POST /sso/login` share assertions: valid creds → expected destination per D2/D3 matrix
- [ ] Admin denied at bare `/login` pre-change test UPDATED: admin now reaches launcher (Console+Account)
- [ ] Admin WITH tenant membership enters that app via `/sso/login?redirect=…` (deny block gone)
- [ ] Non-member SSO attempt → launcher + denial flash; tokens revoked
- [ ] Single-app member skips launcher (straight handoff)
- [ ] Multi-app member sees N tenant tiles + Account (+Console iff admin)
- [ ] `portal.go` mints handoff for member; 403/redirect for non-member; CSRF enforced
- [ ] Activation success → portal session → launcher (or single-app direct)
- [ ] Invalid/expired token GET → `/login` with VISIBLE error alert (regression for layout fix)
- [ ] SSO encrypted payload now contains `iat`/`exp`; `/login` payload byte-compatible keys
- [ ] Both POST endpoints return identical single error string on every failure class
- [ ] `sso-login.blade.php` deleted; no console.log remains in auth views
- [ ] Non-admin hitting `admin.*` still 403 (web.admin intact)
- [ ] Logout clears portal session AND admin session

---

## 14. Doc control

| Version | Date | Change |
|---|---|---|
| 0.1 Draft | 2026-08-25 | Initial draft; D1-D6 locked via user Q&A |
| 1.0 Final | 2026-08-25 | Q1-Q3 resolved as D7/D8/D9 (audit log deferred to future dmin-audit-log.md; D7 migration ordering documented). Promoted to Final by user. P1 implementation authorized per AI-RULES Rule 0 |
