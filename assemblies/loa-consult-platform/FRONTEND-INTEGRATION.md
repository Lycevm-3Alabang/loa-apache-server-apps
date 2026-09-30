# LOA Consult Platform — Frontend Integration Guide (cutover)

**Version:** 1.1
**Status:** Final (backend built + green 2026-09-23; activates at cutover; topology DECIDED 2026-09-26)
**Layer:** Product Assembly (`loa-consult-platform`)

> Follows `../loa-cert-platform/FRONTEND-INTEGRATION.md` as pattern. This is a handoff doc for the cutover phase. The Next.js frontend changes NOTHING until cutover.

---

## What's Done (backend — built + green 2026-09-23)

- [x] Auth endpoints (`POST /api/v1/auth/callback|refresh|logout`) — built per `auth-integration.md` Final v1.6 §11, `AuthTrioTest` 16/16 green
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

## Topology Decision — DECIDED 2026-09-26, mechanism corrected 2026-09-30 (see `auth-integration.md` §2)

- [x] **Option B — same-origin BFF passthrough** (e-cert D2 pattern, code-verified 2026-09-30) — catch-all Route Handler `app/api/v1/[...path]/route.ts` forwards server-side to `CONSULT_API_URL` / `AUTH_API_URL`; browser talks same-origin only
- [x] **No CORS required** — same-origin browser calls mean no cross-origin allowance is needed on the Laravel host (v1.0's "direct cross-origin + CORS" wording was wrong)
- [x] **No host rewrites** — `vercel.json` stays `{}`, `next.config.ts` gains no `rewrites()` (verified: e-cert has neither, because the BFF handler does the forwarding)
- [ ] Cookie flags verified at T1 E2E (`Secure`, `SameSite=Lax`, path `/api/v1/auth`) — same-origin posture means no `SameSite=None` relaxation is required
- [ ] BFF forwarding contract implemented + verified (auth-route host split, cookie scoping to refresh/logout, header/body/`set-cookie` passthrough, `redirect: "manual"`, 400 empty path, 502 upstream down, no transform/validate/enrich)

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
| `test-suite.md` | Final v1.3 |
| `LOCAL-DEV-RUNBOOK.md`, `DEPLOY.md` | Final v1.0 |

---

## Document Control

- **Status:** Final v1.1 (backend green; topology DECIDED 2026-09-26)
- **Reconciled 2026-09-30 (twice, no normative change):** (a) `test-suite.md` pointer v1.2 → v1.3, and the topology section updated to record the decision; (b) topology mechanism re-stated as **same-origin BFF passthrough with no CORS**, correcting the earlier "direct cross-origin + CORS" wording that this file had adopted and that v1.0's DEC-2 carried — verified against `D:\loa\e-cert\src\app\api\v1\[...path]\route.ts` (a server-side passthrough handler, not cross-origin calls). The decision, host, and cookie posture are unchanged; only the mechanism and its CORS implication were wrong. See `frontend-transition.md` Final v1.1 DEC-2 for the corrected record.
- Cutover execution is sequenced by `frontend-transition.md` (T0–T5) — this file is the handoff checklist it sequences.
