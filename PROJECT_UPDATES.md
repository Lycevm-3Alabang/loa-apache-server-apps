# SESSION PROMPT

## How to Use

1. **Starting a new session:** paste the `## Startup Prompt` block below verbatim into the first message.
2. **Ending a session:** replace `## Last Session Notes` with where we stopped (date, completed, next action). Keep it to one entry — older notes live in git history.
3. **Cross-boundary record:** `## PROJECT UPDATES` keeps only decisions/design that span assemblies. Per-platform detail lives in each assembly's `SESSION-PROMPT.md`. Delivery is tracked **per platform** — Auth / Cert / Consult ship independently.

---

## Startup Prompt

Paste this block into the first message of a new session:

```
Read these files IN ORDER and report your understanding of where we left off:

1. AGENTS.md        - sole agent entry: SDD+TDD loop, Rule 0, Rule 0.5, No Auto-Pilot
2. principles.md   - SDD+TDD detail + coding rules (authoritative)
3. platform.md     - LOA architecture + Laravel gotchas
4. PROJECT.md      - project tracker: status of every layer and phase
5. PROJECT_UPDATES.md - this file: shared decisions + per-platform Done/Next + Last Session Notes
6. assemblies/loa-auth-platform/SESSION-PROMPT.md - auth session details (if working on Auth)
7. assemblies/loa-cert-platform/api-endpoints.md - cert endpoint source of truth (if working on Cert)

Then:
- Summarize Done / In Progress / Backlog for the platform in scope
- Identify the NEXT action item from "Last Session Notes"
- Do NOT write code until the governing spec is Final (AGENTS.md Rule 0)
```

---

## PROJECT UPDATES

Durable cross-boundary record only. Detail lives in assembly specs; history lives in git.

### Shared decisions

| Decision | Detail |
|----------|--------|
| App topology | 3 Laravel 12 APIs + 1 Next.js 16 UI (table below) |
| Domains | All APIs on `*.lyceumalabang.edu.ph`; example emails `@lyceumalabang.edu.ph` |
| Spec-first + TDD | No code without a Final spec (`AGENTS.md` Rule 0); SDD sets the contract, TDD explores/refines it (`principles.md` §2b) |
| JWT model | Shared HMAC-SHA256, HS256, `type=access`, local validation — no HTTP per request |
| Cross-app access | JWT local validation + HTTP (Bearer) user lookup; each app owns its DB |
| Identity authority | Auth is sole source of truth; apps are consumers (state changes via Auth API) |
| Permission model | Level-based grants (`<level>:<path>`) per `tenant-group-endpoint-grants.md`, not static keys |
| Consumer allowlist | `AUTH_ALLOWED_REDIRECTS` + `CORS_ALLOWED_ORIGINS` + tenant `redirect_origins` must include every UI origin (currently `https://e-cert.vercel.app`) |
| Governance (2026-09-22) | `AGENTS.md` is the sole agent entry; `AI-RULES.md`/`AI-GUIDE.md` merged into it (+ `principles.md`/`platform.md`) and removed |

| App | Subdomain | Database | Purpose |
|-----|-----------|----------|---------|
| Auth | auth.lyceumalabang.edu.ph | lyceumalabang_auth_db (local: `loa_auth`) | JWT service, users, admin dashboard |
| Consult | aces-api.lyceumalabang.edu.ph | loa_consult | Booking + evaluation (spec program **ACTIVE** 2026-09-23) |
| Cert API | cert-api.lyceumalabang.edu.ph | lyceumalabang_e_cert (local: `loa_cert`) | Issuance, verification, PDF/QR/email |
| e-cert UI | e-cert.vercel.app | — (Vercel) | Next.js consumer of Auth + Cert APIs |

Prod DBs/users provisioned in cPanel 2026-08-24 (user `lyceumalabang_auth_admin`; passwords deploy-time only). See each platform's `DEPLOY.md` + `.env.cpanel` template.

### Auth — `assemblies/loa-auth-platform/`

- **Status:** Phase 1 largely implemented, **not deployed**. SSO entry live (`/sso/login`, `/sso/register`, `/redirect`).
- **Final specs (implemented):** `web-ui.md`, `admin-dashboard.md` (v1+v2), `tenant-endpoint-catalog.md` v3.2, `tenant-group-endpoint-grants.md` v1.1, `access-config-import-export.md` v1.0, data-driven permission policy, RefreshToken, `admin-dashboard-home.md`, `group-permission-management.md` v3.0, `auth-tenant.md` v1.0.
- **Done (latest):** §12 group-permission restructure + auth-tenant 8 items (multi-select Add Member, CSV multi-group, Create User + set-password, Platform badge/shortcut); test fixes + `PasswordSetToken` HasUuids fix; SQL consolidation (`cpanel-auth-db-install.sql`).
- **Next:** run tests + lint to verify; commit + push; then `user-account-activation.md` v1.0 implementation. Deploy deferred (user decision — focus on Cert).
- **2026-09-22 — Spec-mirror pass:** Final specs rewritten to match working code (admin-top ordinals, wrapped catalog, auto-attach, deny-deletes, toggle/invalidate/register-cleanup marked unimplemented, web-ui supersession pointers); proper fixes filed DEFERRED in-spec.
- **2026-09-23 — Consult endpoints catalog:** `database/json/consult-endpoints-catalog.json` v1.0 (118 entries, transcribed from consult `api-endpoints.md` Final v1.0 §5) added as bulk-import path for deploy-time provisioning (cert-file precedent).

### Cert — `assemblies/loa-cert-platform/`

- **Status:** C-Auth complete (2026-08-11). All endpoints behind `jwt.auth` + `jwt.endpoint`; SSO trio live. Retrofit phases A–H COMPLETE (e-cert SPA: auth swap, data swap, cleanup, decommission, JWT/audit tests, OpenAPI).
- **Source of truth:** `api-endpoints.md` Final v1.13 (attendee-list `certificate`), `certificate-source-spec.md` Final v1.4 (CERT-SOURCE-001), `legacy-e-cert-integration.md` Final v2.2, `authenticated-endpoints-spec.md` v1.2.
- **Done (latest):** template visibility (commit `9904746`, 23 tests); post-reset redirect (`28f152e`, log-viewer routes); refresh-cookie crash fix (`Cookie::queue()`); 413 JSON handler (419-CSRF handling unevidenced — claim corrected); parametrized dist builds.
- **2026-09-25 — Certificate source filter (CERT-SOURCE-001 v1.1, user-green):** gated `GET /certificates` + `GET /certificates/{id}` return additive `generation_mode: file|template` via single `CertificateSource` helper (attendee first, standalone `certificates.metadata` fallback); `?source=uploaded|system-generated` AND-combines with existing filters (invalid → 422); `upload()` stamps `metadata.generation_mode=file`. Specs: `api-endpoints.md` v1.10, `certificate-rules-spec.md` v1.2. Runner fix: cert-app has no `php artisan test` — tests run via `.\scripts\run-tests.ps1 -Target cert` (`php vendor/bin/phpunit`). Full suite **263 passed (787 assertions)** incl. new `CertificateSourceTest` (11 behaviors).
- **2026-09-25 — Certificate source save fix (CERT-SOURCE-001 v1.2, pending user-green):** every create/reissue stamps `certificates.metadata.generation_mode` (attendee mode when event-linked, request mode when standalone); `store()` validates `metadata.generation_mode=in:file,template` and rejects top-level `file_path` with 422; dead `$certificate->file_data` reads removed; `CertificateSource::stamp()/stampFile()` own the write side. Specs: `certificate-source-spec.md` v1.2, `api-endpoints.md` v1.11, `certificate-rules-spec.md` v1.3. Tests: `CertificateSourceTest` +7 (ACC-11–15).
- **2026-09-25 — Participant source (CERT-SOURCE-001 v1.3, pending user-green):** `GET /me/certificates` + `GET /me/certificates/{id}` return additive `generation_mode` via `CertificateSource::resolve()` with `attendee` eager load (no N+1); guards/pagination unchanged. Specs: `certificate-source-spec.md` v1.3, `api-endpoints.md` v1.12. Tests: `CertificateSourceTest` +2 (ACC-17–18).
- **2026-09-25 — Attendee-list certificate (CERT-SOURCE-001 v1.4, user-green):** `GET /events/{id}/attendees` items gain REQUIRED `certificate: { id, revoked_at, expires_at } | null` (null when unissued) via `certificate` relation eager load (separate whereIn — status join/select, count, pagination untouched); `Attendee` OA schema +1 nullable object; no catalog change. Specs: `certificate-source-spec.md` v1.4 (DEC-12, ACC-20–21), `api-endpoints.md` v1.13. Tests: `AttendeeTest` +2 (revoked-object + unissued-null; `?status=revoked`/`not_issued` agreement).
- **2026-09-24 — Public view/download file mode:** `GET /view/{id}` returns `data.generation_mode`; `publicDownload` + storage `pdf`/`download` serve uploaded bytes first (same as email attachment) with disk fallback in file mode; e-cert `/view/[id]` loads public download blob when mode is `file` (no JWT attendee calls). Specs: `certificate-rules-spec.md` §7.4/§8.3, `api-endpoints.md` v1.9. Tests: `PublicCertificateTest` +2 (mode + upload bytes).
- **Known gaps:** local seed has no organization → FK 1452 on template/certificate writes (needs seeder); certificate owner-rule check missing on show/pdf/download (only `MeController` scopes); QR route shape code-vs-spec drift (`/{n}/qr` vs `?certificate_number=`); `test-suite.md` still Draft (says SQLite, actual MySQL `loa_cert_test`).
- **Next:** auth/cert deploy; cert org seeder; user-run cert tests after public view change.

### Consult — `assemblies/loa-consult-platform/` (spec program **ACTIVE** 2026-09-24)

- **Status:** scaffold done + auth-layer port COMPLETE (§11 Steps 1–7 green 2026-09-22) + slices B/C COMPLETE (B1–B5 academic/appointments, C1–C4 evaluations green 2026-09-22) + Phase D link-reads + import-domain + data/audit green 2026-09-23 (`UserLinkTest` 8/8, `ImportTest` 7/7, `DataAuditTest` 8/8 user) + URL-flattening F1+F2 green (flat 104+5 normative, scoped results). Planning FLUSHED 2026-09-23 (P0–P5 done, P6 parked — see TODO AFTER FINAL).
- **Specs:** `api-endpoints.md` Final v2.1 (flat 104+5 green, tenant `loa-consultation`), `auth-integration.md` Final v1.5 (§11 Steps 1–7 landed, port COMPLETE; tenant `loa-consultation`), 3 endpoint modules Final v1.0–v1.1 (academic/evaluations v1.1 flat green), `endpoints-admin-import.md` Final v1.1 (Phase D baselines green 2026-09-23; DEC-5 proxy OPEN), `url-flattening.md` Final v1.1 (flat 104+5 per ACC-2), `docker-compose-spec.md` Final v1.1 (tenant `loa-consultation`), `data-model.md` Final v1.3 (shape contract + §3 Status gate 6/3/17); `test-suite.md` Final v1.3 (CON-11 bypass, re-green 2026-09-26); `consult-readiness.md` Final v1.6 (104+5 pointer, tenant `loa-consultation`); runbooks Final v1.0.
- **Next:** P6 cutover/Phase E BLOCKED (frontend decision, reports 7 types, §8 provisioning under new `loa-consultation` tenant); DEC-5 proxy OPEN; runbooks Final v1.0 (promotion complete); tenant code rollout done, suite re-green + Auth re-provisioning pending (user runs). Auth JSON regen 118→104+5 deferred to deploy-time. See TODO AFTER FINAL (planning FLUSHED 2026-09-23).

---

## Last Session Notes

### Date: 2026-09-23

### Completed
- **Tracker reconciliation (user-approved):** `TODO.md` header → ACTIVE 2026-09-23; Phase D deferred markers → SUPERSEDED (Final v1.1 + baselines green); counts → 104+5 normative (`api-endpoints.md` v2.0 §5 Total + `url-flattening.md` ACC-2 + `consult-readiness.md` CON-2 + `config/consult-endpoints.php` 104 gated); 118+5 = v1.0 history, 113+5 = F2 intermediate. `PROJECT.md` Phase 2 synced (auth catalog, admin-import, url-flattening, F1+F2, results 20→15, academic flat). This file synced (ACTIVE date, Status, Specs).
- **Open drift — RESOLVED 2026-09-23 (see drift-fix entry below):** assembly `AGENTS.md` §4 url-flattening 113+5; `url-flattening.md` Document Control 113+5; `endpoints-admin-import.md` Refs config 118; `config/consult-endpoints.php` L131-132 `/admin/audit-logs` unflattened.
- **Phase planning FLUSHED 2026-09-23 (user-approved):** TODO AFTER FINAL + `PROJECT.md` Phase 2 rows synced (P0-P5 done, P6 blocked).
- **Drift fix green pasted 2026-09-23:** 4 spec-owned drifts fixed (AGENTS §4, url-flattening Doc Control, admin-import Refs, config audit-logs flat) — full suite **120 passed (594 assertions)** incl Policy 104+5; migrations 000001–000014 DONE.
- **Docker pipelines wired 2026-09-23 (user-requested):** consult in `reset-all`/`build-all`/`run-tests`/`dump.ps1`/`mega.ps1` (+ new `generate-dist.ps1` twin); auth/cert blocks untouched. **Pipeline COMPLETE green (user-reported): `.\mega.ps1` end-to-end.**
- **Cert PDF 500 fixed 2026-09-23:** `PdfService::streamCertificatePdf()` used non-existent `$pdf->inline()` → `$pdf->stream()` (one line); full cert suite **249 passed (738 assertions)**.
- **`mega.ps1` re-run green 2026-09-23 (user-reported):** tests green → dumps (auth zipped, cert in progress, consult queued) → **all 3 zipped successfully**.

### Date: 2026-09-24

### Completed
- **Tenant rename `loa` → `loa-consultation` (user-approved, own-tenant cert pattern):** 5 specs to Final (`api-endpoints.md` v2.1, `auth-integration.md` v1.5, `consult-readiness.md` v1.6, `test-suite.md` v1.2, `docker-compose-spec.md` v1.1); code/config/tests/runbooks/AGENTS updated (3 defaults, phpunit+bootstrap, 11 test files, `.env.example`, DEPLOY/FRONTEND-INTEGRATION); trackers reconciled (TODO/PROJECT/this file). Pending (user runs): real `.env` slug, Auth re-provisioning, suite re-green paste.
- **Runbook promotions 2026-09-24 (user-approved):** `LOCAL-DEV-RUNBOOK.md`/`DEPLOY.md`/`FRONTEND-INTEGRATION.md` v0.1 → **Final v1.0** (stale sections refreshed, DEPLOY cert-patterned, topology OPEN until cutover); consult spec program 100% Final.
- **Cert public view/upload preview (user-requested):** `/view/{id}` + `/verify/{n}` return `generation_mode`; public download uses `CertificateStorage` (upload bytes = email attachment); e-cert `/view/[id]` loads public PDF blob when `file` (no JWT); e-cert `/verify/{n}` hides “Preview Certificate” when `generation_mode=file`. Specs: `certificate-rules-spec.md` v1.1, `api-endpoints.md` v1.9. Tests added in `PublicCertificateTest` (user-run).

### Next Action
- [ ] Cert: frontend switch — e-cert `certificate-detail.tsx:137` `file_path` heuristic → backend `generation_mode`; verify Uploaded/System-generated list filter against `?source=`; e-cert `npm run lint` / `npm run test`
- [ ] Tenant rollout finish (user runs): real `.env` `TENANT_SLUG=loa-consultation` → Auth re-provisioning (tenant + aces-* + 104+5 catalog/grants) → consult suite re-green paste → Auth JSON regen 118→104+5 at deploy time
- [ ] P6 PARKED items only (DEC-5 + §8 provisioning — see TODO PLANNED). **Phase E reports implementation D-1–D-4 is now a live OPEN gate** (spec Final v1.0, code absent — `ReportService`/`ReportController`/routes/catalog rows/`ReportsTest` all missing), and it blocks frontend T2-reports.
- [ ] Consult frontend cutover T2–T5 (T0 + T1-a + T1-b done 2026-09-26): T2 areas (reports last, blocked on Phase E) → T3 gate swap (semester-lock decision required) → T4 decommission → T5 E2E + rollback. **Prerequisite:** the e-consultation BFF handler (`app/api/v1/[...path]/route.ts`) does not exist yet while T1-b landed a direct Bearer client — must be built per the corrected DEC-2 before T2 areas wire up.

### Date: 2026-09-30

### Completed
- **Frontend reference-discipline audit (user: pattern the e-consultation integration to e-cert's — reference only, not the same):** established e-cert's actual pattern from `loa-cert-platform`/`loa-auth-platform` — adopt the *mechanism* (`bff-layer.md`/`auth-proxy.md` passthrough, `cert-readiness.md` provisioning-by-reference) but **cite** the owning platform specs by ID, never restate them; the backend never adapts to the frontend. Audited the D-2 set and found it breaking that rule both ways. **Duplicated:** cookie name/flags, `aces-*` group names, claim structure, level vocabulary, a pagination scheme, and the endpoint inventory were all restated in `EC-API-001`/`EC-AUTH-001`/`EC-PLAT-001`/`EC-D1`–`EC-D3`/`EC-CUTOVER-001`. **Invented:** `EC-API-001` DEC-2/ACC-1 asserted a non-trio `auth/*` → Auth-host BFF target and used `/api/v1/auth/access` as the worked example, both lifted from e-cert's handler — but the Consult API has **no such route** (`api-endpoints.md` v2.1 = SSO trio + domain surface; §2.2 assigns user writes to Auth). Corrected: `AUTH_API_URL` removed from all six files; every restatement replaced with an ID citation; per-spec **Reference discipline** sections added; e-cert reference lines relabelled "mechanism, not the contract". `EC-CUTOVER-001` → **v1.2** (also fixed a stale Context line still saying "Option B direct+CORS", contradicting its own CON-8). **New open item filed against this repo:** DEC-2a asks `api-endpoints.md` §2.2 where the consult frontend's admin user/group management is met at cutover — in the Auth Platform's own admin UI, or via an Auth API target on the frontend BFF. e-cert has `service/*` auth-proxy routes for this; consult has no equivalent, and the frontend's legacy `app/api/auth/{users,access,onboarding,me}` handlers show a need that the Laravel contract does not currently serve. Not answered in the frontend spec, because it is not the frontend's to answer. No Laravel contract changed.

- **`e-consultation` D-2 COMPLETE + DEC-6 settled (user: `proceed`; cross-repo normative):** frontend service layer written 2026-09-30 as `EC-CUTOVER-001` D-2 and promoted to **Final v1.0** — `specs/services/{api-client,auth,platform}.md` (`EC-API-001`/`EC-AUTH-001`/`EC-PLAT-001`: 11/10/10 CON, 8 DEC each, 10 objective + 2–3 subjective ACC, 5 D each) + `specs/decisions/{csr-spa,bff-passthrough,jwt-display-only}.md` (`EC-D1`/`EC-D2`/`EC-D3`) + `specs/decisions/{README,_template}.md`; `specs/README.md` → v2.0 (legacy 3 marked Superseded-by-ID, `endpoint-catalog.md` 143 flagged as **not truth** vs 104+5); `cutover-headline.md` → **v1.1** (DEC-5 env, plus the `Document Control` + `Layer` fields it never had) and `AGENTS.md` topology line corrected to BFF passthrough. **DEC-6 settled as option (a):** `NEXT_PUBLIC_CONSULT_API_URL` removed from the frontend env contract and from this repo's `FRONTEND-INTEGRATION.md` (v1.1 → **v1.2**) — a public variable holding the API host is exactly what the BFF exists to prevent; `CONSULT_API_URL`/`AUTH_API_URL` are now server-only, so **no CORS allowlist is needed on the Laravel host** and the refresh cookie stays same-origin `SameSite=Lax`. **No Laravel contract changed** (callback/refresh/logout, cookie path, flags untouched). **Credential hygiene landed** (`EC-PLAT-001` D-1): `.env.example` rewritten as a committed placeholder-only template, `.gitignore` narrowed so it is tracked while `.env` stays ignored — the Gmail app password and Supabase service-role key it held are removed from the file, and `.env` (identical content, gitignored) is untouched and still working, so **the two credentials still need rotating by the user**. **Remaining blocker:** the BFF handler `app/api/v1/[...path]/route.ts` does not exist, so the landed T1-b client still calls the Laravel host directly — the path `EC-CUTOVER-001` CON-8 forbids. It is a prerequisite for every T2 area, not a T2 item; it is now unblocked by the promotions.

- **Consult topology CORRECTED (user-approved `yes`; normative — version bumps recorded):** root cause was reading the e-cert *docs* instead of its *code*. Verified `D:\loa\e-cert`: `vercel.json` is `{}` and `next.config.ts` has no rewrites, but `src/app/api/v1/[...path]/route.ts` (175 lines) is a **server-side BFF passthrough** (auth-route host split, cookies forwarded only for refresh/logout, `authorization`/`content-type`/`accept`/`x-requested-with`/`x-forwarded-for`/`user-agent` forwarded, body streamed for non-GET/HEAD, upstream `content-type`/`content-disposition`/`content-length`/`set-cookie` passed through, `redirect: "manual"`, 400 empty path, 502 upstream down). "Option B = direct cross-origin + CORS" was an inference and wrong; it would have forced CORS + a cross-site cookie the verified pattern does not need. Updated `auth-integration.md` → **Final v1.6** (§2), `frontend-transition.md` → **Final v1.1** (DEC-2 forwarding contract, ACC-1, CON-2, D-1, refs), `FRONTEND-INTEGRATION.md` → **v1.1** (retracting my own earlier "direct cross-origin + CORS" edit), `endpoints-reports.md` DEC-9, `AGENTS.md` §2/§4; pointers re-synced in `consult-readiness.md`, `LOCAL-DEV-RUNBOOK.md`, `test-suite.md`. **Laravel contract unchanged** (auth trio, cookie path, `SameSite=Lax`); BFF MUST NOT transform/validate/enrich/inject auth. Frontend-side `cutover-headline.md` CON-8 was already correct — both specs now cite it.
- **Consult tracker reconciliation (user-approved; no code):** verified the build against `TODO.md` — migrations `000001`–`000014` ✓, 16 controllers / 24 models / 17 test files, `routes/api.php` 107 = 5 public + 102 gated served, catalog 104 = 102 served + 2 `/audit-logs` unserved by the `data-model.md` §3 gate, `audit_logs`/`bug_reports` still unmigrated, e-consultation T1-b state confirmed. Two **untracked implementation gates** surfaced and were filed: Phase E reports D-1–D-4 (spec Final, code absent) and frontend cutover T2–T5 (T2-reports blocked on Phase E). `e-consultation` D-2/D-3 gaps filed in that repo's `TODO.md` (no `specs/services/`, no `specs/decisions/`, **no BFF handler while T1-b landed a direct client** — i.e. current code sits on the path CON-8 forbids; plus spec-hygene: both frontend specs lack `Document Control`/`Layer`, Owner `TBD`, stale `specs/README.md` index). `AGENTS.md` §2 scaffold block reconciled to file-tree facts with NOT-YET markers. No tests run — spec/doc changes only, suite unaffected.

### Date: 2026-09-26

### Completed
- **Consult throttle re-green (user-green):** `test-suite.md` Final v1.3 CON-11 + `tests/TestCase.php` bypass verified; 120 WARN triaged to missing host `.env` (bind-mount → `/var/www/html/.env`); `.env` created from `.env.example`; suite re-green pasted; trackers reconciled (TODO SPEC STATUS + PROJECT Phase 2 v1.2→v1.3). P6 left: DEC-5 + Phase E + §8.
- **Consult Phase E opened 2026-09-26 (user-approved, scan-first):** `e-consultation` re-verified (112 route.ts, no `app/api/reports/**`); `endpoints-reports.md` v0.1 Draft written (7 families + 2 sentiment writes, §1.9 template, NOT Final — promotion gates code per Rule 0).
- **Consult Phase E spec Final 2026-09-26 (user-approved `final`):** `endpoints-reports.md` v0.1 Draft → **Final v1.0** (no normative change); D-1–D-4 implementation gates open (per-step yes, TDD per §1.10).
- **Consult frontend transition Draft 2026-09-26 (user-requested):** `frontend-transition.md` v0.1 Draft (T0 topology → T1 auth → T2 areas → T3 gate → T4 decommission → T5 E2E; surface-mapped from `e-consultation` reads; NOT Final — promotion gates frontend code; D-1 ReportService study parked intact).
- **Consult frontend transition Final 2026-09-26 (user-approved `final`):** `frontend-transition.md` v0.1 Draft → **Final v1.0** (no normative change); D-1–D-6 transition gates open (per-area yes + pasted gates).
- **Consult T0 topology DECIDED 2026-09-26 (user: Vercel + cert-actual strategy):** Option B recorded (DEC-2/ACC-1); no frontend code touched; cookie flags verified at T1 E2E. **Mechanism label corrected 2026-09-30** — Option B is same-origin BFF passthrough, not "direct + CORS" (see the 2026-09-30 entry); decision + host unchanged.
- **Consult T1-a JWT seam 2026-09-26 (user-approved `go`):** new `e-consultation/lib/jwt-context.tsx` + 6 client files on `useJwt` (bridge mirrors next-auth, runtime unchanged); `SessionProvider` stays until T1-b; gate: user `npm run lint`.
- **Consult T1-b SSO flow 2026-09-26 (user-approved):** `jwt-context` re-sourced (callback login, silent + proactive refresh, logout, `apiFetch`, claim-groups decode); `api/client` Bearer mirror; `/auth/callback` fragment page; login → SSO button; `proxy.ts` pass-through; `app/` free of next-auth reads; gate: user lint + SSO E2E (login → token → refresh → logout).

### Date: 2026-09-25

### Completed
- **Attendee-list certificate (CERT-SOURCE-001 v1.4 — spec Final + implemented + user-green):** §12 approved Final; `AttendeeController::index` eager-loads `certificate:id,revoked_at,expires_at`; OA `Attendee` +1 nullable object; `AttendeeTest` +2 (ACC-20 both states + status agreement, ACC-21); `api-endpoints.md` v1.13 mirror. Roster can now badge Revoked + disable resend via `certificate.revoked_at`.

### Next Action
- [ ] Cert: roster UI verify — e-cert attendees list reads `certificate?.revoked_at` (Revoked badge + resend disabled); confirm `?status=revoked` rows match badged rows
