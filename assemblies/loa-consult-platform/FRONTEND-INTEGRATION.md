# LOA Consult Platform — Frontend Integration Guide (cutover)

**Version:** 1.0
**Status:** Final (user-approved 2026-09-24; backend built + green — activates at cutover; frontend untouched until then)
**Layer:** Product Assembly (`loa-consult-platform`)

> Follows `../loa-cert-platform/FRONTEND-INTEGRATION.md` as pattern. This is a handoff doc for the cutover phase. The Next.js frontend changes NOTHING until cutover.

---

## What's Done (backend — built + green 2026-09-23)

- [x] Auth endpoints (`POST /api/v1/auth/callback|refresh|logout`) — built per `auth-integration.md` Final v1.5 §11, `AuthTrioTest` 16/16 green
- [x] Domain endpoints (`api-endpoints.md` Final v2.1, 104+5 flat) — slices B/C/D green, full suite 120 passed
- [x] `jwt.auth` + `jwt.endpoint` middleware — Steps 3/5 done, routes gated since Step 7
- [x] Config (`jwt.php`, `auth-platform.php`, `consult-platform.php` with `tenant_slug=loa-consultation`, `consult-endpoints.php` 104+5)

## What the Frontend Builds at Cutover (TODO each)

- [ ] SSO fragment handler: extract `#payload=` from Auth redirect hash → `POST /api/v1/auth/callback`
- [ ] In-memory token store (never localStorage); `Authorization: Bearer` on every call
- [ ] Silent refresh on load/expiry (`POST /api/v1/auth/refresh`, cookie auto-sent)
- [ ] Logout (`POST /api/v1/auth/logout`, 204) + clear store + redirect to SSO
- [ ] Client auth guard (token → refresh attempt → SSO redirect)
- [ ] Permission gating from JWT `groups` claim (Auth vocabulary — no local roles; see `api-endpoints.md` §4.0)
- [ ] Keep the 403-lock pattern (`setLockedEndpoint`, `LockedTab`, `POST /api/audit/forbidden` telemetry) — backend guarantees JSON 403, never redirects (§7 #19)

## Topology Decision (cutover — see `auth-integration.md` §2)

- [ ] Option A (recommended): Vercel rewrite `/api/v1/:path*` → `https://aces-api.lyceumalabang.edu.ph/api/v1/:path*` (same-origin cookie)
- [ ] Option B (e-cert actual): direct cross-origin + CORS fallback
- [ ] Cookie flags must match the choice (`Secure`, `SameSite=Lax`, path `/api/v1/auth`)

## Environment (cutover)

```env
NEXT_PUBLIC_CONSULT_API_URL=https://aces-api.lyceumalabang.edu.ph
NEXT_PUBLIC_AUTH_URL=https://auth.lyceumalabang.edu.ph
NEXT_PUBLIC_CONSULT_TENANT_SLUG=loa-consultation
```

## Decommission at Cutover (TODO — mirrors consult-readiness §11)

- [ ] NextAuth (`lib/auth.ts`, `[...nextauth]`, activate/forgot/change-password routes)
- [ ] Custom RBAC (`lib/access.ts`, `lib/default-access.ts`, `group_access`, `user_permissions`, `role`, `userrole`, `password_reset_tokens`)
- [ ] Auth columns (`passwordHash`, `tokenVersion`, `hasLoggedInBefore`)
- [ ] `bcryptjs`, `AUTH_SECRET`/`NEXTAUTH_SECRET`, `useSession()` → JWT context

## Testing (cutover)

- [ ] E2E SSO: login → payload → callback → refresh → logout (mirror cert §Testing with consult hosts/cookie)
- [ ] Per-group matrices (ADMIN/DEAN/FACULTY/STUDENT scenarios in `auth-integration.md` §9)
- [ ] Closed-by-default 403s surface as locked UI, not breakage

---

## Reference Docs

| Doc | Status |
|-----|--------|
| `api-endpoints.md` | Final v2.1 |
| `auth-integration.md` | Final v1.5 |
| `data-model.md` | Final v1.3 |
| `endpoints-academic.md`, `endpoints-appointments.md`, `endpoints-evaluations.md` | Final v1.0–v1.1 |
| `test-suite.md` | Final v1.2 |
| `LOCAL-DEV-RUNBOOK.md`, `DEPLOY.md` | Final v1.0 |

---

## Document Control

- **Status:** Final v1.0 (user-approved 2026-09-24; backend green — topology decision stays OPEN until cutover; frontend untouched until then)
