# LOA Consult Platform — Frontend Integration Guide (cutover)

**Version:** 1.3
**Status:** Final (backend built + green 2026-09-23; activates at cutover; topology DECIDED 2026-09-26; frontend transport built 2026-09-30)
**Layer:** Product Assembly (`loa-consult-platform`)

> Follows `../loa-cert-platform/FRONTEND-INTEGRATION.md` as pattern. This is a handoff doc for the cutover phase. The Next.js frontend changes NOTHING until cutover.

---

## What's Done (backend — built + green 2026-09-23)

- [x] Auth endpoints (`POST /api/v1/auth/callback|refresh|logout`) — built per `auth-integration.md` Final v1.6 §11, `AuthTrioTest` 16/16 green
- [x] Domain endpoints (`api-endpoints.md` Final v2.1, 104+5 flat) — slices B/C/D green, full suite 120 passed
- [x] `jwt.auth` + `jwt.endpoint` middleware — Steps 3/5 done, routes gated since Step 7
- [x] Config (`jwt.php`, `auth-platform.php`, `consult-platform.php` with `tenant_slug=loa-consultation`, `consult-endpoints.php` 104+5)

## What the Frontend Builds at Cutover (TODO each)

- [ ] SSO fragment handler: extract `#payload=` from Auth redirect hash → `POST /api/v1/auth/callback` — **built 2026-09-30** (`app/auth/callback/page.tsx`, `history.replaceState` per `EC-AUTH-001` DEC-1), awaiting the user's lint/typecheck gates
- [x] Same-origin BFF passthrough: `app/api/v1/[...path]/route.ts` forwards to `CONSULT_API_URL` (prod fallback in code), cookie forwarding scoped to `auth/refresh`+`auth/logout`, `400` empty path, `502` upstream down, `204` bodyless with `set-cookie` preserved — **built 2026-09-30**, awaiting gates
- [ ] In-memory token store (never localStorage); `Authorization: Bearer` on every call — store done at T1-b; the `window.fetch` patch in `lib/api/client.ts` still duplicates the token and is removed at `EC-API-001` D-2
- [ ] Silent refresh on load/expiry (`POST /api/v1/auth/refresh`, cookie auto-sent) — same-origin + single-flight guard done 2026-09-30
- [ ] Logout (`POST /api/v1/auth/logout`, 204) + clear store + redirect to SSO — same-origin done 2026-09-30
- [ ] Client auth guard (token → refresh attempt → SSO redirect) — `proxy.ts` still runs a legacy server gate; retired at T3 per `frontend-transition.md` DEC-6
- [ ] Permission gating from JWT `groups` claim (Auth vocabulary — no local roles; see `api-endpoints.md` §4.0)
- [ ] Keep the 403-lock pattern (`setLockedEndpoint`, `LockedTab`, `POST /api/audit/forbidden` telemetry) — backend guarantees JSON 403, never redirects (§7 #19)

## Topology Decision — DECIDED 2026-09-26, mechanism corrected 2026-09-30 (see `auth-integration.md` §2)

- [x] **Option B — same-origin BFF passthrough** (e-cert D2 pattern, code-verified 2026-09-30) — catch-all Route Handler `app/api/v1/[...path]/route.ts` forwards server-side to `CONSULT_API_URL` / `AUTH_API_URL`; browser talks same-origin only
- [x] **No CORS required** — same-origin browser calls mean no cross-origin allowance is needed on the Laravel host (v1.0's "direct cross-origin + CORS" wording was wrong)
- [x] **No host rewrites** — `vercel.json` stays `{}`, `next.config.ts` gains no `rewrites()` (verified: e-cert has neither, because the BFF handler does the forwarding)
- [ ] Cookie flags verified at T1 E2E (`Secure`, `SameSite=Lax`, path `/api/v1/auth`) — same-origin posture means no `SameSite=None` relaxation is required
- [ ] BFF forwarding contract implemented + verified (auth-route host split, cookie scoping to refresh/logout, header/body/`set-cookie` passthrough, `redirect: "manual"`, 400 empty path, 502 upstream down, no transform/validate/enrich)

## Environment (cutover)

Frontend env after the BFF lands (per `e-consultation/specs/services/platform.md` `EC-PLAT-001` Final v1.0 DEC-3). The Consult API host is **server-only** — it must not be a `NEXT_PUBLIC_*` variable, because the BFF keeps the browser same-origin and the upstream host out of the bundle.

```env
# Public — URLs and tenant slug only
NEXT_PUBLIC_AUTH_URL=https://auth.lyceumalabang.edu.ph
NEXT_PUBLIC_CONSULT_TENANT_SLUG=loa-consultation

# Server-only — Route Handler only, never shipped to the browser.
# Unset on Vercel; the BFF falls back to the production hosts in code.
CONSULT_API_URL=http://localhost:9002
AUTH_API_URL=http://localhost:8080
```

> **`NEXT_PUBLIC_CONSULT_API_URL` removed 2026-09-30 (user-approved).** This file previously listed it as a public variable set to the Laravel host, which contradicted the BFF topology in `frontend-transition.md` Final v1.1 DEC-2 — a public API host is exactly what the BFF exists to prevent. Removed alongside `e-consultation/specs/cutover-headline.md` → v1.1 DEC-5 and `EC-PLAT-001` → Final v1.0 DEC-6. The browser base is a same-origin relative-path constant, not a variable.
>
> **What this means for the Laravel host:** no CORS allowlist is needed, and the `loa_connect_refresh` cookie stays same-origin with `SameSite=Lax` per `auth-integration.md` v1.6 §3. Local dev still uses `:9002` (Consult) and `:8080` (Auth) per `docker-compose-spec.md` v1.1 — those are the BFF's local targets, not browser-visible values.

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

- **Status:** Final v1.3 — backend green; topology DECIDED 2026-09-26; frontend transport built 2026-09-30 (pending the frontend's own gates).
- **Revision history:**
  - **v1.3 (2026-09-30):** cutover checklist annotated with what the frontend has actually built. The BFF passthrough row was missing entirely and is added; the fragment handler, refresh, logout, and token-store rows now record 2026-09-30 progress. Frontend spec pointers advanced to `EC-API-001` v1.1 / `EC-AUTH-001` v1.1 / `EC-CUTOVER-001` v1.3. **No Laravel contract change** — this file is the backend's view of frontend progress, and nothing here alters a route, shape, or flag.
- **v1.2 (2026-09-30):** env block corrected — `NEXT_PUBLIC_CONSULT_API_URL` removed; `CONSULT_API_URL`/`AUTH_API_URL` marked server-only; noted that no CORS allowlist is needed and the refresh cookie stays same-origin `SameSite=Lax`. Normative, and coordinated with `e-consultation/specs/cutover-headline.md` → v1.1 DEC-5 and `e-consultation/specs/services/platform.md` → Final v1.0 DEC-6. **No change to any Laravel contract** — callback/refresh/logout, cookie path, and flags are untouched.
  - **v1.1 (2026-09-30):** topology mechanism re-stated as **same-origin BFF passthrough with no CORS**, retracting this file's earlier "direct cross-origin + CORS" wording (inherited from v1.0's DEC-2, which was an inference from the e-cert *docs*). Verified against `D:\loa\e-cert\src\app\api\v1\[...path]\route.ts` — a server-side passthrough handler, not cross-origin calls. `test-suite.md` pointer v1.2 → v1.3. Decision, host, and cookie posture unchanged; only the mechanism and its CORS implication were wrong. See `frontend-transition.md` Final v1.1 DEC-2.
- Cutover execution is sequenced by `frontend-transition.md` (T0–T5) — this file is the handoff checklist it sequences.
- Frontend-side counterpart: `e-consultation/specs/cutover-headline.md` Final v1.1 (`EC-CUTOVER-001`), `specs/services/{api-client,auth,platform}.md` (`EC-API-001`/`EC-AUTH-001`/`EC-PLAT-001`, all Final v1.0).
