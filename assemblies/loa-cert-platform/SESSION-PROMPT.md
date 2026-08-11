# SESSION PROMPT — LOA Cert Platform

> Assembly-scoped session prompt for `assemblies/loa-cert-platform/`. This complements (does not replace) the repo-wide `PROJECT_UPDATES.md` at the workspace root.

## How to Use

1. **Starting a new session:** Paste the `## Startup Prompt` block below verbatim into the first message.
2. **Ending a session:** Update the `## Last Session Notes` section so the next session knows exactly where to pick up.
3. **Scope rule:** This prompt governs **Cert Platform work only** (endpoints, schema, SSO, deployment). Do not pull in Auth Platform implementation tasks unless the note explicitly says so.
4. **Plan of record:** When a `*PLAN*.txt` file exists in this assembly (see `## Current Plan` below), that plan is the authoritative task list for the current work. Read it fully before any implementation and follow it step-by-step.

---

### ⛔ Mandatory Compliance

**`AI-RULES.md` and `AI-GUIDE.md` are the standing, non-negotiable rules for every session. They are always in force and take precedence over convenience.**

- **Rule 0 / Specs Before Code (AI-RULES.md §0, AI-GUIDE.md):** NO implementation code until the relevant spec is **Final**. `api-endpoints.md` v1.4 is Final — code exactly to it. Any in-progress spec must be Final before its code is written.
- **No Auto-Pilot — Always Ask (AI-RULES.md §13, AI-GUIDE.md):** every significant action (writing/modifying/deleting code or specs, running migrations, installing packages, updating tracker files, running tests, committing) requires explicit user confirmation.
- **Run Tests Before Commit (AI-GUIDE.md):** the test suite must pass after every code change.
- **Spec authorship is NOT code (AI-RULES.md §0 clarification):** if the user explicitly asks to create/edit/promote a spec, comply — that is spec authoring, not implementation.
- **Layer/ownership discipline (AI-GUIDE.md):** assemblies contain no business logic; reuse before creating; dependencies point downward only.
- **Rule check before acting:** when in doubt between "architecturally correct" and "quick/easy", the architecture wins.

---

### Startup Prompt

Paste this block into the first message of a new session:

```
Read these files IN ORDER and report your understanding of where we left off:

1. AI-RULES.md                       - mandatory spec-first rules (Rule 0: no code without a Final spec) + No Auto-Pilot rules
2. AI-GUIDE.md                       - architecture + Step 0 (spec check) + Run Tests Before Commit
3. PROJECT.md                        - repo tracker: Phase 3 "Cert App" = this platform's status
4. PROJECT_UPDATES.md (root)    - repo-wide cross-boundary tracker: decisions/design/changes per platform
5. assemblies/loa-cert-platform/README.md   - assembly scope + SSO callback contract (§11)
6. assemblies/loa-cert-platform/web-ui.md   - frontend spec + permission→role mapping (§5)
7. assemblies/loa-cert-platform/api-endpoints.md - THE endpoint source of truth (priority spec)
8. assemblies/loa-cert-platform/SESSION-PROMPT.md - this file: "Last Session Notes" = where we stopped
9. assemblies/loa-cert-platform/TEMPLATES-PLAN.txt  - CURRENT plan of record for the Templates resource group (read fully; follow step-by-step)
10. business-contexts/certificate/README.md (+ entities/) - domain ownership for cert domain
11. assemblies/loa-auth-platform/tenant-group-endpoint-grants.md - level-based grants model (authority for §4/§9 of api-endpoints.md)

Then:
- Confirm you will strictly adhere to AI-RULES.md and AI-GUIDE.md (Rule 0: no code before a Final spec; No Auto-Pilot: ask before every significant action; tests must pass)
- Read the current plan of record (TEMPLATES-PLAN.txt) and state its next unexecuted step
- Summarize the endpoint spec status (Draft vs Final) and what's implemented vs pending
- List what's Done, In Progress, Backlog for the Cert Platform only
- Identify the NEXT action item from "Last Session Notes" / the current plan
- Do NOT write any code until api-endpoints.md (and any in-progress spec) is Final
```

---

## Last Session Notes

### Date: 2026-08-11 (Session 11)
### Completed
- **C-Auth (authentication layer) COMPLETE — all 6 steps implemented and tested.**
  - **Step 1 — Services:** `JWTService` (HS256 validate-only), `EncryptionService` (AES-256-GCM decrypt + previous-key fallback), `config/jwt.php`, `config/auth-platform.php`. 15 unit tests.
  - **Step 2 — Middleware:** `JwtMiddleware` (`jwt.auth` — validates JWT, tenant claim, sets `jwt_claims`/`jwt_token`/`cert_user`), `EndpointPolicyMiddleware` (`jwt.endpoint` — level-based catalog enforcement, path param matching, public path bypass). `config/cert-endpoints.php` (48 catalog entries + 5 public paths). Middleware registered in `bootstrap/app.php`. 20 middleware tests.
  - **Step 3 — Auth endpoints:** `AuthCallbackController` (decrypt SSO payload, validate JWT, set httpOnly refresh cookie, return access token, throttled 10/min), `AuthRefreshController` (read cookie, proxy refresh to Auth platform, rotate cookie), `AuthLogoutController` (read cookie, proxy logout to Auth platform, clear cookie, return 204). Routes: `/api/v1/auth/callback`, `/refresh`, `/logout` (public, unauthenticated).
  - **Step 4 — Config:** `config/cert-platform.php` updated with `refresh_cookie`, `refresh_cookie_ttl`.
  - **Step 5 — Route changes:** All existing endpoints wrapped in `jwt.auth` + `jwt.endpoint` middleware in `routes/api.php`.
  - **Step 6 — Bootstrap:** Middleware aliases registered in `bootstrap/app.php`.
- **Test suite updated:** Created `WithJwt` trait for tests (generates valid JWT tokens with full permissions). Updated all 6 existing test files (Feature + Unit) to use `actingAsJwt()`. Full suite: **126 tests, 386 assertions, all green.**
- **Docs updated:** `api-endpoints.md` → v1.5 (§13 items marked done, security checklist updated), `authenticated-endpoints-spec.md` → v1.1 (fixed PUT→PATCH, added auth endpoints, added missing endpoints), `legacy-e-cert-integration.md` §12 updated.

### Notes / Caveats
- **Branch:** `cert/c-auth-step1-services` (pushed to origin, commit `e0866a7`).
- **Test DB:** MySQL `loa_cert_test`, user `loa`, pass `loa-secret`. Tests run via `docker compose exec cert-app php vendor/bin/phpunit`.
- **Auth platform not yet deployed** — auth endpoints proxy to `auth.lyceumalabang.edu.ph` which may not be live. Local dev works with the default config.
- **Pending:** `permissions:sync-cert-catalog` artisan command (generate local catalog mirror from config).

### In Progress
- **Phase D (unblocked):** `e-cert` auth swap — in-memory token, silent refresh, SSO fragment handler, parse-only JWT, client auth guard (depends on C-Auth, now done).

### Next Action
- [ ] Create `FRONTEND-INTEGRATION.md` handoff file for e-cert AI.
- [ ] Commit docs update (await explicit user instruction).
- [ ] Remove leftover `cert-app/` dir (confirm first).

### Date: 2026-08-10 (Session 10)
### Completed
- **Events & Attendees resource groups: COMPLETE per `api-endpoints.md` v1.4 (Final).**
  - **Events (13 endpoints):** CRUD + real `stats()`, `clone-template`, `clone-email-template`, `bulk-issue`, `reissue`, `issue-completed`, `revoke-expired` (GET count + POST action). All OpenAPI-annotated.
  - **Attendees (8 endpoints):** list, create (upsert by event+email → 201), import (JSON, `merge`/`replace`, replace requires `confirm=true`), update (PATCH, event-scoped email conflict), destroy, destroy-with-cert, delete-preview, file-data (template → 200, missing file → 410, else Storage download). All OpenAPI-annotated.
  - Routes registered in `routes/api.php` (PATCH for updates per spec; nested `events/{eventId}/attendees` group).
- **Test suite: GREEN — 91 tests, 334 assertions pass** (`docker compose exec -T cert-app vendor/bin/phpunit`; `php artisan test` not defined).
  - New feature tests: `tests/Feature/Api/EventTest.php` (13 endpoints), `tests/Feature/Api/AttendeeTest.php` (8 endpoints + variations).
- **Bugs fixed during suite run:** composite-PK sequence increment (`certificate_sequences`, `where id is null`) in `CertificateNumberService` + `CertificateController`; `destroy*()` returned `Response` against `: JsonResponse` type → `response()->json(null, 204)`; `CertificateController::store` attendee `firstOrCreate` missing `organization_id` + `$event` null scope; `expire()` counted revoked AFTER update (always 0); `PdfService` DomPDF v3 (facade + `loadHtml` signature); `Organization` model missing `HasFactory`; factories (`Organization` unique slug, `EventAttendee` + `organization_id`).
- **Infra:** dev deps added to `composer.json` (`phpunit ^12.5` 12.5.33 — 13.x needs PHP ≥8.4.1, container is 8.3.33; `mockery ^1.6`; `fakerphp/faker ^1.24`), `autoload-dev` `Tests\`; created host `bootstrap/cache` + `storage/framework/{cache,sessions,views}` + `storage/logs` (volume shadowing); renamed `database/Migrations` → `database/migrations` (git mv; was only working on Windows host via case-insensitive mount — would break cPanel/Linux).
- **Cleanup:** removed dead duplicate `app/Http/Controllers/Api/CertificateTemplateController.php`; deleted `.tmp_debug.php` debug script.

### Notes / Caveats
- **NOT committed.** All changes above are working-tree only (incl. new files `CertificateNumberService.php`, the two feature test files, factories dir).
- **Test DB (2026-08-10):** SQLite is a non-goal. `phpunit.xml.dist` now forces MySQL (`force="true"` on `DB_*`, so it beats the compose shell env) against a dedicated **`loa_cert_test`** database (created + granted to `loa`). Tests no longer touch `loa_cert` app data. The `certificates` migration's MySQL-only `storedAs('IF(...)')` column is intentional — MySQL-only stack.
- **`cert-app/` leftover dir** (early scaffold) still present at assembly root; compose mounts `.` → `/var/www/html`, so the real app lives at assembly root. Verify before deleting.
- Test env facts: container service `cert-app`; MySQL DBs `loa_cert` (app) + `loa_cert_test` (tests), user `loa`, pass `loa-secret`.

### In Progress
- **Deferred to Auth Phase (C-Auth):** SSO + `jwt.auth`/`jwt.endpoint` middleware (decision #20/D9).
- **Deferred to Service Phase:** QR code generation, email sending services.

### Next Action
- [ ] Commit the completed Events/Attendees work (await explicit user instruction).
- [ ] Remove leftover `cert-app/` dir (confirm first).