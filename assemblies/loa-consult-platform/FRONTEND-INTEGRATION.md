# LOA Consult Platform — Frontend Integration Guide (cutover)

**Version:** 0.1
**Status:** Draft (skeleton — cutover-gated; frontend untouched until then)
**Layer:** Product Assembly (`loa-consult-platform`)

> Follows `../loa-cert-platform/FRONTEND-INTEGRATION.md` as pattern. This is a handoff doc for the cutover phase. The Next.js frontend changes NOTHING until cutover.

---

## What's Done (backend — nothing yet)

- [ ] Auth endpoints (`POST /api/v1/auth/callback|refresh|logout`) — spec Final (`auth-integration.md`), not built
- [ ] Domain endpoints (`api-endpoints.md` Final v1.0) — not built
- [ ] `jwt.auth` + `jwt.endpoint` middleware — not built
- [ ] Config (`jwt.php`, `auth-platform.php`, `consult-platform.php`, `consult-endpoints.php`) — not built

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
NEXT_PUBLIC_CONSULT_TENANT_SLUG=loa
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
| `api-endpoints.md` | Final v1.0 |
| `auth-integration.md` | Final v1.4 |
| `data-model.md` | Final v1.3 |
| `endpoints-academic.md`, `endpoints-appointments.md`, `endpoints-evaluations.md` | Final v1.0 |
| `test-suite.md`, `LOCAL-DEV-RUNBOOK.md`, `DEPLOY.md` | Draft v0.1 |

---

## Document Control

- **Status:** Draft v0.1 skeleton (2026-09-18) — activates at cutover; frontend untouched until then
