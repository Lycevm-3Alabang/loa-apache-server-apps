# LOA Consult Platform — Frontend Transition (Cutover)
## Product Assembly Component Specification

| Field | Value |
|-------|-------|
| ID | CONSULT-CUTOVER-001 |
| Title | e-consultation Next.js Cutover to Laravel API + Auth SSO |
| Status | Final v1.1 (user-approved 2026-09-30; DEC-2 mechanism corrected to code-verified BFF passthrough — same decision, correct mechanism; transition gates open per Rule 0) |
| Owner | Consult Platform assembly |
| Version | 1.1 Final |
| Scope | Ordered cutover of the `e-consultation` Next.js app from self-hosted auth + direct-Supabase Server Components + internal `/api/*` routes to Laravel API (`api-endpoints.md` v2.1, 104+5) + reports API (`endpoints-reports.md` v1.0) + Auth SSO; gate swap; decommission; E2E |
| Non-goals | Backend changes (backend is green — this spec consumes it); CSV/PDF export byte moves (stay in UI unless scoped later); visual redesign; changing report numbers (parity REQUIRED) |
| Layer | Product Assembly (`assemblies/loa-consult-platform/`) |

## RFC 2119 terminology

The key words MUST, MUST NOT, REQUIRED, SHALL, SHALL NOT, SHOULD, SHOULD NOT, RECOMMENDED, MAY, and OPTIONAL in this document are to be interpreted as described in RFC 2119.

## Context

The `e-consultation` Next.js app (repo root `D:\loa\e-consultation`) is fully self-contained today: `lib/auth.ts` (next-auth Credentials + `bcryptjs` against Supabase `userRepository`: `passwordHash`, `tokenVersion`, `hasLoggedInBefore`, JWT session `{id, role, tokenVersion}`), `proxy.ts` gate (PUBLIC_PATHS/PREFIXES, `getToken`, semester-lock, `getUserAccess` longest-match with API 403 JSON / UI pass-through + ADMIN fallback + closed-by-default), `lib/access.ts` (`DEFAULT_CONFIG` per ADMIN/DEAN/FACULTY/STUDENT/GUEST + `group_access` table merge + 60s caches) + `lib/default-access.ts`, Server Components calling `features/**` controllers directly against Supabase (verified: `HealthReportPage.tsx` → `auth()` → `getAdminReportData()`), 112 internal `route.ts` files (20 `app/api/*` areas, no `reports/`), `next.config.ts` with no rewrites (2 redirects only), no `vercel.json`, `netlify.toml` present (deploy target to confirm), `.env.example` carrying `AUTH_SECRET`/`NEXTAUTH_SECRET`, Supabase URL/KEY, `SSO_FEATURE_FLAG=false` (no usage found in `lib/` — verify at implementation). The Laravel backend is green (slices B/C/D, flattening 104+5, reports spec Final v1.0). This spec orders the frontend move with parity gates so cutover is reversible.

## Constraints

- **CON-1** — The frontend MUST NOT change data shapes the backend guarantees: bare shapes, camelCase aggregates, level gates per `api-endpoints.md` v2.1 §5 + `endpoints-reports.md` v1.0. Parity with legacy numbers on sampled inputs is REQUIRED (ACC-S1 pattern per area).
- **CON-2** — Tokens MUST live in memory only (never localStorage); refresh MUST ride the httpOnly `loa_connect_refresh` cookie; per DEC-2 the browser is same-origin only, so the §3 `SameSite=Lax` / `Secure` settings in `auth-integration.md` apply unchanged and no `SameSite=None` relaxation is required. If the BFF mechanism is ever rejected in favour of Option A or true cross-origin, cookie flags MUST be re-decided before T1 ships.
- **CON-3** — The 403-lock UX MUST survive the gate swap: backend JSON 403s surface as locked UI (`setLockedEndpoint`, `LockedTab`, `POST /api/audit/forbidden` telemetry), never redirects or breakage.
- **CON-4** — Cutover MUST be area-sequenced (T1→T4 below) with each area independently revertible; no flag-day rewrite. Semester-lock behavior (exactly 1 active) MUST be preserved or explicitly re-decided before its area ships.
- **CON-5** — Secrets MUST NOT ship to the client: Supabase service-role KEY, `AUTH_SECRET`/`NEXTAUTH_SECRET`, Gmail app password MUST leave the deployed frontend env at T4. `NEXT_PUBLIC_*` MUST carry only public URLs/slug.
- **CON-6** — No frontend code until this spec is Final (Rule 0); each transition area MUST have its own E2E gate (ACC-*) pasted by the USER from the running UI.

## Goal

### Decisions

- **DEC-1** — Order: T0 topology → T1 auth swap → T2 data-path swap by area (reports last, against `endpoints-reports.md` v1.0) → T3 gate swap (`proxy.ts` + access) → T4 decommission → T5 E2E + rollback drill.
- **DEC-2** — T0 topology: **Option B — same-origin BFF passthrough (e-cert D2 pattern), code-verified 2026-09-30.** No `vercel.json` rewrites and no `next.config.ts` rewrites; the frontend serves a catch-all Route Handler `app/api/v1/[...path]/route.ts` that forwards **server-side** to `CONSULT_API_URL` / `AUTH_API_URL`. The browser only ever talks same-origin, so **no CORS configuration is required** and the httpOnly `loa_connect_refresh` cookie rides same-origin with the §3 `SameSite=Lax` setting unchanged. Forwarding contract (from e-cert `src/app/api/v1/[...path]/route.ts`): auth routes other than the callback/refresh/logout trio route to the Auth host, the trio stays on the Consult host; cookies forwarded only for refresh/logout; forward `authorization`, `content-type`, `accept`, `x-requested-with`, `x-forwarded-for`, `user-agent`; stream body for non-GET/HEAD; pass through upstream `content-type`/`content-disposition`/`content-length`/`set-cookie`; `redirect: "manual"`; empty path → 400, upstream unreachable → 502. **The BFF MUST NOT transform, validate, enrich, or inject auth** — unexpected data is an upstream defect, never a proxy fix. Deploy host Vercel (not Netlify — `netlify.toml` is secrets-scan-only). Option A (Vercel rewrite) remains the documented fallback if the BFF is rejected; cookie flags (`Secure`/`SameSite`, path `/api/v1/auth`) MUST be verified during T1 E2E (ACC-2). **Correction note:** v1.0 recorded "Option B (direct cross-origin + CORS)" based on the e-cert *docs*; reading the e-cert *code* on 2026-09-30 showed `vercel.json` `{}` and no rewrites are explained by the BFF handler, not by cross-origin calls. Same decision, correct mechanism — `e-consultation/specs/cutover-headline.md` Final v1.0 CON-8 was already correct.
- **DEC-3** — T1 auth swap: SSO fragment handler (`#payload=` → `POST /api/v1/auth/callback`), in-memory token store, silent refresh on load/expiry, logout 204 + store clear + SSO redirect, client guard (token → refresh attempt → SSO redirect). Replaces: `lib/auth.ts` authorize/JWT/session callbacks, `[...nextauth]` route, `/login` Credentials form, `useSession()` → JWT context, `getToken` in `proxy.ts`.
- **DEC-4** — T2 data-path swap per area (Server Component direct-controller imports → `fetch(NEXT_PUBLIC_CONSULT_API_URL + path, {Authorization: Bearer})`): appointments/availability → academic/semesters → evaluations/periods/rubrics/results → admin-import link-reads → reports (7 Laravel endpoints). Internal `route.ts` files stay live until their area's parity gate passes, then thin out (proxy to Laravel or delete per area note).
- **DEC-5** — T3 gate swap: `proxy.ts` token check → JWT-context check; `getUserAccess` (group_access + DEFAULT_CONFIG) → JWT `groups`-claim gating with identical page/API matrices; keep longest-match + ADMIN-fallback + closed-by-default semantics; keep semester-lock until explicitly re-decided.
- **DEC-6** — T4 decommission (mirrors `FRONTEND-INTEGRATION.md` + `consult-readiness.md` §11): NextAuth (`lib/auth.ts`, `[...nextauth]`, activate/forgot/change-password routes), custom RBAC (`lib/access.ts`, `lib/default-access.ts`, `group_access`, `user_permissions`), auth columns (`passwordHash`, `tokenVersion`, `hasLoggedInBefore`), `bcryptjs`, `AUTH_SECRET`/`NEXTAUTH_SECRET`, Supabase client + service-role key from the frontend bundle.
- **DEC-7** — T5 E2E + rollback: per-group matrices (ADMIN/DEAN/FACULTY/STUDENT per `auth-integration.md` §9) + report parity sampling + 403-lock drill; rollback = revert area to internal route + direct path (kept until T5 passes).

### Acceptance — Objective (machine-checkable)

- **ACC-1** — T0 recorded 2026-09-26, mechanism corrected 2026-09-30: Option B = same-origin BFF passthrough Route Handler (no rewrites, no CORS, refresh cookie same-origin). Verified at T1 E2E before any area ships: browser Network tab shows only same-origin `/api/v1/*`; cookie flags confirmed.
- **ACC-2** — T1: login → `#payload` → callback 200 → in-memory token → refresh on expiry → logout 204, all via Laravel hosts (no `[...nextauth]`, no Supabase auth call in Network tab).
- **ACC-3** — T2 per area: legacy page vs cutover page return identical aggregates on 2 sampled inputs (dept + date range); internal `route.ts` for the area thinned only after paste.
- **ACC-4** — T2 reports: all 7 Laravel report payloads match legacy Server Component numbers on the sampled department + range (ACC-S1 of `endpoints-reports.md` executed from the UI).
- **ACC-5** — T3: ADMIN/DEAN/FACULTY/STUDENT matrices pass (allowed pages load, revoked APIs return JSON 403, UI shows locked tab, telemetry POST fires).
- **ACC-6** — T4: `bcryptjs`, `next-auth`, Supabase service-role key absent from the production bundle/env; `AUTH_SECRET`/`NEXTAUTH_SECRET` unset in deploy env.
- **ACC-7** — T5: rollback drill pasted (area reverted + re-cut in staging without data loss).

### Acceptance — Subjective (human-judged)

- **ACC-S1** — Reviewer confirms the login-to-logout flow completes end-to-end in the UI with no error toast on all four roles.
- **ACC-S2** — Reviewer confirms a locked endpoint surfaces the locked tab (not a blank page or redirect) and the forbidden-telemetry entry appears.

## Deliverables

- **D-1** — T0 topology record (this revision: Option B same-origin BFF passthrough + forwarding contract + host rewrite syntax = *none* + CORS = *none*).
- **D-2** — T1 auth swap (fragment handler, token store, refresh, logout, guard; `useSession()` → JWT context; `proxy.ts` token check).
- **D-3** — T2 area swaps (per-area fetch rewiring + parity pastes; reports last vs `endpoints-reports.md` v1.0).
- **D-4** — T3 gate swap (`proxy.ts` + groups-claim matrices preserving T3 semantics + semester-lock decision).
- **D-5** — T4 decommission (file/env/column removal list executed + bundle verified).
- **D-6** — T5 E2E + rollback drill (matrices, parity sampling, 403-lock drill, pasted results).

## Glossary

| Term | Meaning |
|------|---------|
| Parity gate | Same-input legacy-vs-cutover number match, pasted by USER before an area's old path is thinned |
| Locked UI | `setLockedEndpoint` + `LockedTab` + forbidden telemetry on backend JSON 403 (never a redirect) |
| Thin out | Area's internal `route.ts` proxied to Laravel or deleted after its parity gate passes |
| Semester-lock | Exactly-1-active-semester enforcement currently in `proxy.ts` (preserved until re-decided) |

## References

- `api-endpoints.md` Final v2.1 (104+5 catalog, levels, scoping)
- `endpoints-reports.md` Final v1.0 (7 report endpoints consumed at T2-reports)
- `auth-integration.md` Final v1.6 §2 (topology A/B; B = same-origin BFF passthrough), §9 (group matrices), §10–§11 (SSO/trio/middleware)
- `FRONTEND-INTEGRATION.md` Final v1.1 (handoff checklist this spec sequences)
- `e-consultation/specs/cutover-headline.md` Final v1.0 `EC-CUTOVER-001` (frontend-side contract; CON-8 is the same BFF mechanism, already correct)
- `consult-readiness.md` Final v1.6 §11 (decommission mirror)
- Legacy ground truth, read-only (paths verified 2026-09-26): `lib/auth.ts` (Credentials authorize + JWT/session callbacks), `lib/access.ts` (DEFAULT_CONFIG + `group_access` merge), `lib/default-access.ts` (exists), `proxy.ts` (full 114-line gate), `next.config.ts` (no rewrites), `netlify.toml` (exists — target to confirm), `.env.example` (secret inventory), `app/*/reports/**/page.tsx` (20 pages), `HealthReportPage.tsx` (Server Component direct-controller pattern), 112 `app/api/**/route.ts`
- Root `AGENTS.md` (Rule 0/0.5, No Auto-Pilot) + assembly `AGENTS.md` §1.9–§1.10

---

## Document Control

- **Status:** Final v1.1 (user-approved 2026-09-30)
- **Created:** 2026-09-26 as v0.1 Draft from user request "spec out the actual transitioning" (D-1 ReportService study parked intact); promoted Final v1.0 2026-09-26; **v1.1 2026-09-30 — DEC-2 / ACC-1 / CON-2 / D-1 corrected: "Option B" re-stated as same-origin BFF passthrough (Route Handler forwarding server-side, no rewrites, no CORS) instead of "direct cross-origin + CORS"**
- **Why:** v1.0's "e-cert actual = direct cross-origin + CORS" was an inference from the cert *docs*. Reading the cert *code* (`D:\loa\e-cert\src\app\api\v1\[...path]\route.ts`, 175 lines) on 2026-09-30 shows the opposite: a server-side passthrough handler, which is why `vercel.json` is `{}` and `next.config.ts` has no rewrites. The frontend spec `e-consultation/specs/cutover-headline.md` Final v1.0 CON-8 was already correct (same-origin only, cross-origin forbidden) and is now aligned. **No change to the T0→T5 order, any CON, the Laravel contract, or any deliverable scope** — the decision, host, and cookie posture are unchanged; only the mechanism label and its CORS/cross-site-cookie implications are corrected.
- **Next:** execute D-1–D-6 area by area (needs per-area yes + pasted gates). T0 + T1-a + T1-b complete; T2 open (reports last, blocked on `endpoints-reports.md` D-1–D-4)
