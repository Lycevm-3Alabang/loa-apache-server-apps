# SSO Logout for Tenant Apps

## Product Assembly Component Specification

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-auth-platform`) — web auth surface
**Audience:** Architects, Engineers, AI Development Agents
**Depends on:** `unified-auth-flow.md` Final v1.0 (§4 portal session), `dashboard-account.md` Final v1.3 (D12 shared logout)

> Adds a GET-redirect logout endpoint so tenant apps (e-cert) can invalidate
> the auth platform's portal session without needing to POST with CSRF.

---

## 0. Locked decisions

| # | Decision | Choice |
|---|---|---|
| D1 | Endpoint method | **GET** — tenant apps redirect the browser; no CSRF needed |
| D2 | Session invalidation | **Same as POST /logout** — Auth::guard('web')->logout(), session invalidate, regenerate token |
| D3 | Redirect target | **`?redirect=` query param** — validated via `safeRedirectUrl()`; defaults to `/login` if missing/invalid |
| D4 | Route location | **`routes/web.php`** — public GET route, no middleware (guest or authenticated) |

### Related specs

| Concept | Owner |
|---|---|
| Portal session lifecycle | `unified-auth-flow.md` §4 |
| Shared logout (POST) | `dashboard-account.md` D12, §3 |
| Redirect validation | `unified-auth-flow.md` D7 |

---

## 1. Purpose

Answers:

> **"How does a tenant app (e-cert) log the user out of the auth platform's
> portal session when the apps are on different domains?"**

---

## 2. Problem being removed

| Today | Consequence |
|---|---|
| Tenant app clears local tokens only | Auth platform session remains active |
| User redirected to `/sso/login` after logout | Auth platform sees active session, immediately re-authenticates |
| User appears "logged out" then instantly "logged in" | Broken logout UX |

---

## 3. Route

```php
Route::get('/sso/logout', [WebAuthController::class, 'ssoLogout'])
    ->name('sso.logout');
```

No middleware — the endpoint works for both authenticated guests (clears session if present) and guests (no-op redirect).

---

## 4. Handler behavior

```
GET /sso/logout?redirect=https://staging-loa-vericert.vercel.app/
```

1. If portal session exists:
   - `Auth::guard('web')->logout()`
   - `$request->session()->invalidate()`
   - `$request->session()->regenerateToken()`
2. Resolve redirect: `safeRedirectUrl($request->query('redirect'))`
   - If valid → redirect there
   - If missing/invalid → redirect to `route('login')`

---

## 5. Security invariants

1. Redirect URL validated via `safeRedirectUrl()` — no open redirect
2. No CSRF required (GET, stateless redirect, session already cleared)
3. Session invalidation identical to `POST /logout` (D2)

---

## 6. Tenant app integration

### e-cert frontend changes

**Files:** `src/components/logout-button.tsx`, `src/components/user-menu.tsx`

Change:
```ts
window.location.href = "/";
```

To:
```ts
window.location.href = `${process.env.NEXT_PUBLIC_AUTH_BASE_URL}/sso/logout?redirect=${encodeURIComponent(window.location.origin)}`;
```

### Flow

1. User clicks Logout
2. Local tokens cleared from sessionStorage
3. Browser → `auth.lyceumalabang.edu.ph/sso/logout?redirect=https://staging-loa-vericert.vercel.app/`
4. Auth platform clears portal session
5. Auth platform redirects to `https://staging-loa-vericert.vercel.app/`
6. e-cert root page sees no session, stays logged out

---

## 7. Edge cases

| Case | Behavior |
|---|---|
| No session exists | No-op clear, redirect anyway |
| Invalid redirect URL | Falls back to `/login` |
| No redirect param | Falls back to `/login` |
| User has multiple tabs open | Other tabs' tokens remain valid until expiry (accepted limitation) |

---

## 8. Testing checklist

- [ ] `GET /sso/logout` with valid session → session cleared, redirected to target
- [ ] `GET /sso/logout` without session → no error, redirected to target
- [ ] `GET /sso/logout` with invalid redirect → redirected to `/login`
- [ ] `GET /sso/logout` without redirect param → redirected to `/login`
- [ ] e-cert logout button → auth session cleared → user stays logged out
- [ ] Existing `POST /logout` still works for console users

---

## 9. Doc control

| Version | Date | Change |
|---|---|---|
| 1.0 Final | 2026-08-28 | Initial Final: GET /sso/logout for tenant apps |
