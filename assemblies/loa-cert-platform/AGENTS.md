# AGENTS.md — LOA Cert Platform Agent Contract

> This file is the standing contract for any AI agent working in `assemblies/loa-cert-platform/`.
> It outranks ad-hoc instructions when they conflict. If a request violates
> Section 1, stop and ask instead of proceeding.
> Companion rules: root `AGENTS.md` (sole entry) + `principles.md` (SDD+TDD detail) + `platform.md` (architecture + gotchas). Change journal: root `PROJECT_UPDATES.md` (Cert section + Last Session Notes).

---

## 1. Working agreements (must-follow, moving forward)

1. **Spec-first, no exceptions.** No code, migration, config, or dependency changes without a
   written spec the user has explicitly marked **Final**. The loop is:
   discuss → write spec → user approves spec as Final → implement exactly the spec.
   SDD writes the contract; TDD tests it against reality.
2. **No auto-pilot.** Never chain beyond what was approved. Finish the approved
   step, report, and stop. Ask before starting the next phase, even if it seems
   "obvious."
3. **Agent does not run CLIs — the user does.** The agent must NEVER execute
   terminal commands (`docker compose`/`php artisan`/`composer`/`git`/builds/deploys, etc. — even read-only ones). The agent
   provides the exact commands; the user runs them and pastes back output.
   Rationale: the user owns the device, build environment, and credentials.
   Docker runs from repo root only (`loa-platform` project); never an assembly-level compose file.
   Tests: `docker compose exec cert-app php artisan test`; suites sequentially only.
4. **No breaking changes unless the finalized spec requires them.** Route paths under `/api/v1/*`, response shapes
   (bare keys per endpoint; `{error}` errors), the `loa_cert` schema, certificate-numbering atomicity, and the legacy
   e-cert UI contract (`legacy-e-cert-integration.md`) must stay compatible. Any migration must be in the spec with rollback noted.
5. **Auth-consumer invariant.** Cert never issues identity and never reads the Auth DB. JWT validates locally
   (shared HMAC-SHA256 secret, `type=access`) via `jwt.auth`; levels enforce via `jwt.endpoint` closed-by-default
   (`no_catalog_entry` → 403); tenant slug is `loa-e-cert`; identity beyond claims comes via the Auth API only.
6. **Keep it cPanel-deployable.** `public/` docroot; `public/.htaccess` MUST forward `Authorization`
   before the front-controller rule; `JWT_SECRET`/`ENCRYPTION_KEY` byte-identical with Auth; `CERT_TENANT_SLUG=loa-e-cert`
   must match Auth DB. `.env.cpanel` contains real passwords — keep gitignored, rotate post-deploy. Dist builds go through
   `generate-dist.ps1` (parametrized `-Path`, strips `.env*`/tests, 7-Zip re-zip).
7. **Keep tests green.** Nothing may break the existing suites (visibility, audit, SSO, public cert, dashboard).
   No change is complete until the user pastes green results. Tests self-seed the org row per `setUp` + `RefreshDatabase`;
   never touch the `loa_cert` app DB from tests.
8. **Document after approved changes.** Update root `PROJECT_UPDATES.md` (Cert section + Last Session Notes)
   and append a `Current status` entry in Section 3 below.
9. **Spec format standard (all future specs).** Every normative spec MUST live
   as its own file under `assemblies/loa-cert-platform/` (never inline in `AGENTS.md` — §4 is a
   pointer only) and MUST follow the consult template: metadata table
   (ID/Title/Status/Owner/Version/Scope/Non-goals/Layer) + RFC 2119 terminology +
   `Context` + `Constraints` (numbered `CON-*`, MUST/MUST NOT) + `Goal`
   (decisions `DEC-*`, acceptance `ACC-*`) + `Deliverables` (numbered `D-*`) + `Glossary` + `References`.
   No normative change via reformat/polish alone.
10. **Spec-first + TDD with behavioral coverage.** Every future spec MUST separate:
    - **Objective** — deterministic, machine-checkable (`ACC-*`): statuses, shapes, guards
      (401/403/404/409/413/422), visibility/owner rules, numbering atomicity, idempotent seeds.
    - **Subjective** — human-judged checks stated as observable reviewer steps
      (e.g. "reviewer confirms issued PDF renders the QR and verifies via the public link").
    TDD MUST cover both: `php artisan test` (user-run, request-level over mocks, one behavior per test,
    `RefreshDatabase` — never hardcoded tokens) for logic, plus user-run manual checks (PDF/QR/email, SSO trio)
    for paths tests cannot prove. No behavior without a `CON-*` + `ACC-*` + `D-*`.

---

## 2. App overview and scaffold

**What it is:** LOA Cert Platform — certificate issuance + verification API, Laravel 12 + PHP 8.3 + MySQL
(local `loa_cert`; prod `lyceumalabang_e_cert`), subdomain `cert-api.lyceumalabang.edu.ph`. Serves the Next.js
e-cert UI (`e-cert.vercel.app`). Composes the Certificate business context (events, attendees, templates, issuance).

**Features:** SSO trio (callback/refresh/logout, throttled) + `jwt.auth`/`jwt.endpoint` on all non-public routes ·
event CRUD + attendee management + CSV import · template CRUD + visibility (`public|owner|cert-admin`, 404-masking,
seed/lock 409s) · issuance (atomic numbers via `SELECT FOR UPDATE` on `certificate_sequences`) + bulk sync ·
PDF (DOMPDF) + QR + email with attachment · public verify/view/download · revoke/delete · audit trail · auth proxy
(11 routes, JWT pass-through vs `X-Api-Key`, staff revoke-only, audit-logged) · `/me` recipient/author scoping ·
413 JSON handler + 10M body limits · log viewer.

**Scaffold:**

```
assemblies/loa-cert-platform/
├── app/Models/ (8)         # Organization, Event, EventAttendee, CertificateTemplate, Certificate,
│                           # CertificateSequence, CertificateEmail, AuditLog
├── app/Http/Controllers/   # Event, Attendee, Certificate, CertificateTemplate, Me, PublicCertificate,
│                           # AuthCallback/Refresh/Logout, AuthProxy, AuditLog, Dashboard, LogViewer
├── routes/api.php          # public SSO/verify + gated domain routes + 11 auth-proxy routes
├── config/cert-endpoints.php # endpoint catalog mirror (levels read1/write2/admin3/deny-1)
├── database/migrations/    # incl. certificate_sequences + number_active generated col
├── database/sql/           # cpanel install + org seed SQL (FK-1452 guard)
├── database/seeders/       # DatabaseSeeder (org 00000000-…-000000000001)
├── tests/                  # visibility, audit, SSO, public cert, dashboard (self-seeding org)
├── docker/                 # php/Dockerfile + nginx/default.conf
├── generate-dist.ps1       # parametrized cPanel dist builds
├── *.md specs              # api-endpoints + legacy integration + authenticated spec + proxy + rules (see §4)
└── AGENTS.md               # this file
```

**Architecture notes:** no local roles — `cert-admin` is an Auth tenant group; owner rule is recipient-email scoping
(except admin); numbers are database-atomic, never app-computed; snapshots/visibility follow the evaluation-assembly
pattern (404-masking, seed-immutable). Identity referenced by JWT claims + `created_by/updated_by` subs only.

---

## 3. Current status (historical tracking — append newest at bottom)

- **C-Auth complete + retrofit A–H COMPLETE.** All endpoints behind `jwt.auth` + `jwt.endpoint`; SSO trio live; e-cert SPA
  auth swap, data swap, cleanup, decommission, JWT/audit tests, OpenAPI done.
- **Latest — template visibility** (commit `9904746`, 23 tests); post-reset redirect (`28f152e`); refresh-cookie crash fix
  (`Cookie::queue` removed); 419-CSRF redirect handler; parametrized dist builds.
- **Deploy READY (with notes).** FK-1452 closed (org seeded via seeder + SQL; tests self-seed). Remaining manual step:
  run `db:seed --force` once locally; import both SQLs on cPanel. Env parity verified (`.env`/`.env.cpanel`/`.env.example`).
- **2026-09-22 — Alignment audit (read-only, Type A/B/C filed — implementation DEFERRED, mirror rule applies).**
  7 Type-A (QR path+key vs spec; public `verify` email leak; callback/refresh body token leaks; logout `Secure` hardcoded;
  owner bypass group-vs-level; unevidenced 419 handler). 4 Type-B (`test-suite.md` SQLite claim vs MySQL reality; public test
  enshrines leak; `JwtMiddlewareTest` slug `loa` vs `loa-e-cert`; QR/owner coverage gaps). 7 Type-C (slug split; counts;
  proxy checklist; public-list mirror; 10M vs 50M limits; manual catalog sync; `28f152e` scope).
- **2026-09-22 — Assembly contract created.** This `AGENTS.md` (wise_wallet format); referenced from root `AGENTS.md`.
- **2026-09-22 — Spec-mirror pass (cert).** Specs rewritten to match working code, improvements filed DEFERRED: QR path-param + `data_url` (UI-verified), callback/refresh body token + Secure-note, group-check owner rule, verify email note, `loa-e-cert` slug, number_active + app-check uniqueness, proxy checklist closed, test-suite MySQL, `.user.ini` 50M note.
- **2026-09-24 — Public view file-mode.** `/view/{id}` + `/verify/{n}` return `generation_mode`; `publicDownload` → `CertificateStorage`; storage file-mode serves upload bytes (email parity) with disk fallback; e-cert `/view/[id]` uses public download blob when `file`; e-cert verify hides Preview Certificate when `file`. Specs `api-endpoints.md` v1.9 + `certificate-rules-spec.md` v1.1; `PublicCertificateTest` +2 (user-run).
- **2026-09-25 — Certificate source filter (CERT-SOURCE-001 v1.1).** Gated list/show return additive `generation_mode: file|template` via single `App\Services\CertificateSource` (attendee first, standalone `certificates.metadata` fallback); list filters `?source=uploaded|system-generated` (AND-combined, `meta.total` reflects conjunction, invalid → 422); `upload()` stamps `metadata.generation_mode=file`. Specs `api-endpoints.md` v1.10 + `certificate-rules-spec.md` v1.2 + `certificate-source-spec.md` v1.1 (CON-6/D-3 corrected to `php vendor/bin/phpunit` — `php artisan test` absent in cert-app). Tests: new `CertificateSourceTest` (11 behaviors); full suite user-green **263 passed (787 assertions)** via `.\scripts\run-tests.ps1 -Target cert`.
- **2026-09-25 — Certificate source save fix (CERT-SOURCE-001 v1.2, pending user-green).** Every create/reissue stamps `certificates.metadata.generation_mode` (attendee mode event-linked, request mode standalone) via `CertificateSource::stamp()/stampFile()`; `store()` validates `metadata.generation_mode=in:file,template`, rejects top-level `file_path` 422; dead `$certificate->file_data` reads replaced with `certificateStorage->emailAttachment()`. Specs `certificate-source-spec.md` v1.2 + `api-endpoints.md` v1.11 + `certificate-rules-spec.md` v1.3. Tests: `CertificateSourceTest` +7 (ACC-11–15; one test-fixture fix: standalone case uses unlocked template).
- **2026-09-25 — Participant source (CERT-SOURCE-001 v1.3, pending user-green).** `GET /me/certificates` + `GET /me/certificates/{id}` return additive `generation_mode` via `CertificateSource::resolve()` with `with(['event','attendee'])` (no N+1); guards/pagination unchanged; `MyCertificate` OA property. Specs `certificate-source-spec.md` v1.3 + `api-endpoints.md` v1.12. Tests: `CertificateSourceTest` +2 (ACC-17 list both modes + 1 attendee query; ACC-18 detail 200/403/404).
- **2026-09-25 — Attendee-list certificate (CERT-SOURCE-001 v1.4, user-green).** `GET /events/{id}/attendees` items gain REQUIRED `certificate: { id, revoked_at, expires_at } | null` (null when unissued) via `with('certificate:id,revoked_at,expires_at')` on the base query (separate whereIn — no status join/select interference, no N+1, count/pagination unchanged); `Attendee` OA schema +1 nullable object; no catalog change. Specs `certificate-source-spec.md` v1.4 (DEC-12) + `api-endpoints.md` v1.13. Tests: `AttendeeTest` +2 (ACC-20 revoked-object + unissued-null + `?status=` agreement; ACC-21 user-run green).

---

## 4. Specs (pointer only)

**Normative text lives in the assembly `*.md` files; implement exactly those. This section is a pointer only.**

### Normative specs

| Spec | Status |
|---|---|
| `api-endpoints.md` | FINAL v1.13 (attendee-list `certificate` 2026-09-25; Me list/detail `generation_mode` 2026-09-25; gated list/show `generation_mode` + `?source=` filter 2026-09-25; tenant slug `loa-e-cert`) |
| `legacy-e-cert-integration.md` | FINAL v2.2 |
| `authenticated-endpoints-spec.md` | FINAL v1.2 |
| `certificate-rules-spec.md` | FINAL v1.2 (§3.9 gated source 2026-09-25) |
| `certificate-source-spec.md` | FINAL v1.4 (CERT-SOURCE-001; v1.2 save-fix stamp rule; v1.3 Me `generation_mode`; v1.4 attendee-list `certificate`) |
| `body-size-limits.md` | FINAL (10M effective; `.user.ini` 50M note) |
| `queue-infrastructure-spec.md` | FINAL |
| `auth-proxy.md` | DRAFT v1.0 (11 routes live; §9 checklist closed this pass) |
| `bff-layer.md` | DRAFT (future — not developed now) |

### Operate (not normative specs)

| File | Status |
|---|---|
| `test-suite.md` | DRAFT (MySQL `loa_cert_test` — mirrored this pass) |
| `DEPLOY.md` / `LOCAL-DEV-RUNBOOK.md` / `FRONTEND-INTEGRATION.md` | runbooks (FRONTEND: ready for Phase D) |
| `README.md` | DRAFT — assembly overview |
