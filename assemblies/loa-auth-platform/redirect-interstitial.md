# Redirect Interstitial — Manual Redirect + Admin Gold Flag

## Product Assembly Component Specification

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-auth-platform`) — web auth surface
**Audience:** Architects, Engineers, AI Development Agents
**Depends on:** `unified-auth-flow.md` Final v1.0 (§3 handoff tail, §4 portal session), `sso-logout.md` Final v1.0

> Makes the `/redirect` interstitial page wait for an explicit user click
> before navigating to the tenant app, and adds a "gold flag" link back to
> the admin console for platform admins.

---

## 0. Locked decisions

| # | Decision | Choice |
|---|---|---|
| D1 | Redirect behavior | **Manual only** — no auto-redirect; user clicks "Continue to application" button |
| D2 | Admin indicator | **Gold flag link** — platform admins see "Back to Admin Console" below the continue button |
| D3 | Admin link destination | **`config('app.url')`** — the auth platform's own origin (e.g., `auth.lyceumalabang.edu.ph` or `localhost:8080`) |
| D4 | Admin flag source | **Encrypted payload** — `is_admin` boolean added to the payload built by `PortalRouter::enterTenant()`, decrypted in `showRedirect()` |

---

## 1. Purpose

Answers:

> **"How does the user confirm they want to leave the auth platform and enter
> a tenant app — and how do platform admins get back to the console?"**

---

## 2. Problem being removed

| Today | Consequence |
|---|---|
| `/redirect` auto-navigates via `requestAnimationFrame` + `window.location.replace()` | User never sees the page; no opportunity to cancel or navigate elsewhere |
| No admin link on redirect page | Platform admins entering a tenant app have no quick path back to the admin console |

---

## 3. View changes

### 3.1 `resources/views/redirect.blade.php`

| Action | Detail |
|--------|--------|
| **Remove** | `<script>` block (lines 29–37) — `requestAnimationFrame`, `window.location.replace()`, `console.log` statements |
| **Update** | Heading: `"Redirecting..."` → `"Ready to redirect"` |
| **Update** | Intro: `"You are being redirected to the application."` → `"Click the button below to continue to the application."` |
| **Remove** | `"If you are not redirected automatically,"` text (line 21–23) — no longer relevant |
| **Keep** | `<a class="button" href="{{ $full_url }}">` — sole navigation mechanism |
| **Add** | Conditional admin link (§4 below) |

### 3.2 Resulting view structure

```blade
@extends('layouts.auth')

@section('title', 'Redirecting | LOA Platform')
@section('eyebrow', 'Leaving LOA Platform')
@section('heading', 'Ready to redirect')
@section('intro', 'Click the button below to continue to the application.')

@section('content')
    {{-- SVG icon, target URL display, continue button --}}

    @if($is_admin)
        <p style="margin-top:1rem;">
            <a href="{{ config('app.url') }}"
               style="font-size:0.8125rem;color:var(--text-muted);text-decoration:underline;">
                Back to Admin Console
            </a>
        </p>
    @endif

    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
    </style>
@endsection
```

No `<script>` block. No auto-redirect. Zero JavaScript.

---

## 4. Admin gold flag

### 4.1 Payload extension

**File:** `app/Services/PortalRouter.php` — `enterTenant()` method

Add `is_admin` to the encrypted payload array (after `exp`):

```php
$payload = [
    'access_token' => $tokens['access_token'],
    'refresh_token' => $tokens['refresh_token'],
    'token_type' => $tokens['token_type'],
    'expires_in' => $tokens['expires_in'],
    'user' => [
        'id' => $user->id,
        'email' => $user->email,
        'name' => $user->name,
    ],
    'tenant' => [
        'id' => $tenant->id,
        'slug' => $tenant->slug,
    ],
    'iat' => time(),
    'exp' => time() + $tokens['expires_in'],
    'is_admin' => $this->isAdmin($user),            // ← NEW
];
```

The `isAdmin()` method already exists on `PortalRouter` (line 148) and is already called at line 121 for audit logging. This reuses the same check.

### 4.2 Controller decryption

**File:** `app/Http/Controllers/WebAuthController.php` — `showRedirect()` method

After building `$fullUrl`, decrypt the payload to extract `is_admin`:

```php
$isAdmin = false;
if ($payload) {
    $decrypted = $this->encryption->decrypt($payload);
    $isAdmin = is_array($decrypted) && ($decrypted['is_admin'] ?? false);
}
```

Pass to view:

```php
return view('redirect', [
    'url' => $targetUrl,
    'full_url' => $fullUrl,
    'is_admin' => $isAdmin,
]);
```

**Dependency:** `WebAuthController` must have `EncryptionService` injected via constructor. If not already present, add it to the constructor parameters.

### 4.3 View rendering

The `$is_admin` variable is passed to the view. When `true`, the "Back to Admin Console" link appears below the continue button, linking to `config('app.url')`.

When `false` (non-admin member), the link is hidden. The page shows only the target URL and the continue button.

---

## 5. Edge cases

| Case | Behavior |
|---|---|
| Encrypted mode off (fragment fallback) | `is_admin` not in fragment URL; `$isAdmin` defaults to `false`; admin link hidden |
| Decryption fails | `$isAdmin` defaults to `false`; admin link hidden; continue button still works |
| Non-admin user | `$isAdmin` = `false`; admin link hidden |
| Platform admin entering tenant app | `$isAdmin` = `true`; admin link visible |
| Already portal-authenticated, visits `/login` | Smart router runs; if redirected through `/redirect`, admin flag applies |

---

## 6. Security invariants

1. `is_admin` is inside the encrypted payload — not visible to client-side JavaScript or network inspection.
2. The admin link uses `config('app.url')` — a server-side config value, not user input. No open redirect.
3. The link is informational only — it navigates to the auth platform's dashboard, which has its own `auth:web` middleware guard. Non-admins who somehow see the link get 403 at `admin.*` routes.
4. No new attack surface: the `/redirect` page already renders the full target URL and a clickable link. Adding an admin link does not expose tokens or sessions.

---

## 7. Logout impact

**No changes to logout.** The existing flow per `sso-logout.md` is sufficient:

1. Tenant app calls `clearAccessToken()` — removes JWT from localStorage
2. Tenant app navigates to `GET /sso/logout?redirect=...` — auth platform destroys portal session
3. User is redirected back to tenant app root — `AuthGuard` finds no valid token → redirects to SSO login

The refresh token (HttpOnly cookie on the cert backend) expires naturally (7 days). Subsequent refresh attempts fail once the auth platform session is destroyed. This is acceptable.

---

## 8. Testing checklist

- [ ] `/redirect` renders without `<script>` block — no auto-redirect
- [ ] "Continue to application" button is present and links to correct `full_url`
- [ ] Heading reads "Ready to redirect"
- [ ] Intro reads "Click the button below to continue to the application."
- [ ] Platform admin sees "Back to Admin Console" link pointing to `config('app.url')`
- [ ] Non-admin member does NOT see the admin link
- [ ] `is_admin` is present in encrypted payload (verify via tinker or test)
- [ ] Fragment fallback mode: admin link hidden (no `is_admin` in payload)
- [ ] Existing SSO login flow still works end-to-end
- [ ] Existing logout flow still works end-to-end

---

## 9. Doc control

| Version | Date | Change |
|---|---|---|
| 1.0 Final | 2026-08-29 | Initial Final: manual redirect, admin gold flag in encrypted payload |
