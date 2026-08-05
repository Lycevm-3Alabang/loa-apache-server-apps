# SESSION PROMPT — LOA Cert Platform

> Assembly-scoped session prompt for `assemblies/loa-cert-platform/`. This complements (does not replace) the repo-wide `PROJECT_UPDATES.md` at the workspace root.

## How to Use

1. **Starting a new session:** Paste the `## Startup Prompt` block below verbatim into the first message.
2. **Ending a session:** Update the `## Last Session Notes` section so the next session knows exactly where to pick up.
3. **Scope rule:** This prompt governs **Cert Platform work only** (endpoints, schema, SSO, deployment). Do not pull in Auth Platform implementation tasks unless the note explicitly says so.

---

## Startup Prompt

Paste this block into the first message of a new session:

```
Read these files IN ORDER and report your understanding of where we left off:

1. AI-RULES.md                       - mandatory spec-first rules (Rule 0: no code without a Final spec)
2. AI-GUIDE.md                       - architecture + Step 0 (spec check)
3. PROJECT.md                        - repo tracker: Phase 3 "Cert App" = this platform's status
4. PROJECT_UPDATES.md (root)    - repo-wide cross-boundary tracker: decisions/design/changes per platform
5. assemblies/loa-cert-platform/README.md   - assembly scope + SSO callback contract (§11)
6. assemblies/loa-cert-platform/web-ui.md   - frontend spec + permission→role mapping (§5)
7. assemblies/loa-cert-platform/api-endpoints.md - THE endpoint source of truth (priority spec)
8. assemblies/loa-cert-platform/SESSION-PROMPT.md - this file: "Last Session Notes" = where we stopped
9. business-contexts/certificate/README.md (+ entities/) - domain ownership for cert domain
10. assemblies/loa-auth-platform/tenant-group-endpoint-grants.md - level-based grants model (authority for §4/§9 of api-endpoints.md)

Then:
- Summarize the endpoint spec status (Draft vs Final) and what's implemented vs pending
- List what's Done, In Progress, Backlog for the Cert Platform only
- Identify the NEXT action item from "Last Session Notes"
- Do NOT write any code until api-endpoints.md (and any in-progress spec) is Final
```

---

## Last Session Notes

### Date: 2026-08-05 (session 2)

### Completed
- **Auth integration fully spec'd into `api-endpoints.md` (Draft v1.1)** — the deferred §9 note is replaced by a concrete contract:
  - **Authorization model** (§4): level-based (`read`/`write`/`admin` + `deny`/`none`) per `tenant-group-endpoint-grants.md` (Final v1.1); `<level>:<path>` entries in the JWT `permissions` claim; `cert.*` keys explicitly **not enforced** (§4.5); role→grant guidance for admin/staff/participant (§4.4)
  - **Every endpoint** now carries a `required_level` (not `cert.*` keys); §6 route summary and the new **Appendix A catalog** (Auth `POST /admin/tenants/{tenant}/endpoints/bulk` import payload) updated to match
  - **Full SSO** (§9): `POST /api/v1/auth/callback` (AES-256-GCM decrypt w/ `_PREVIOUS` fallback, `exp` + tenant-slug validation, httpOnly `SameSite=Lax` refresh cookie), `jwt.auth` middleware (local HS256, no users table, tenant claim check), `jwt.endpoint` middleware (local catalog mirror, closed-by-default, owner-rule hook via `jwt_endpoint_level` attribute), Cert-proxied refresh/logout (refines README §11.5–11.6), frontend `/access` usage, `.env`/config reference (§9.10)
  - Verified against the **actual Auth implementation**: `JWTService`, `EncryptionService`, `JwtMiddleware`, `ClaimPolicyMiddleware::handleLevelBased`, `TenantAppEndpoint::matchPath`, `EndpointGrantController::catalogBulk`, `AuthController::access()`, `routes/api.php`, `bootstrap/app.php` middleware aliases
- **Confirmed decisions with user (this session):**
  - Enforcement model: **level-based** `<level>:<path>` (Recommended) — not `cert.*` keys
  - Spec target: **update `api-endpoints.md`** as single source of truth (incl. Appendix A catalog)
  - SSO depth: **full SSO + authorization** (not authorization-only)
  - Output: **spec first, implement after review** (no code this session)
  - Implementation slice: **core first** (events/attendees/templates/certificates + middleware/tests; PDF/QR/email/bulk/audit/dashboard later)
  - **Author scope** (follow-up): `/me`-style own paths (`GET /api/v1/me/events`, `GET /api/v1/me/templates`) + `created_by` (opaque `sub`) on **events + templates**; non-admin item-path grants are author-scoped in controllers (§9.6)
- **Bumped `api-endpoints.md` to Draft v1.2** with the author-scope additions (§4.4/§4.6, §5.9, §6, §7.2, §8 #19, §9.6 resource-scoping rules, §10, §13, Appendix A) — now 50 domain endpoints (48 JWT-gated + 2 public)

### In Progress
- `api-endpoints.md` is **Draft v1.2** — awaiting user review (updated from v1.1).

### Next Action
- [ ] Review `api-endpoints.md` v1.1 → fix any gaps → promote to **Final v1.1**
- [ ] After Final: scaffold the Laravel 12 Cert app in the **loa-auth-platform stack** (PHP 8.3, MySQL 8, Docker, PHPUnit, l5-swagger): config files, `JWTService`, `EncryptionService`, `jwt.auth` + `jwt.endpoint` middleware, `AuthController` (callback/refresh/logout), core-slice endpoints + migrations + tests
- [ ] Import Appendix A catalog into the Auth Platform (`POST /api/v1/admin/tenants/{tenant}/endpoints/bulk`) once Auth-side grants are ready

### Backlog / Known Gaps
- **Local catalog mirror** (`config/cert-endpoints.php`) must stay in sync with the Auth catalog — add `permissions:sync-cert-catalog` artisan command (§9.5)
- **MySQL adaptations to honor at implementation:** JSON columns, no partial indexes (generated-column trick §7.3), base64 `rendered_pdf` moved to storage (only `file_path` kept), audit `user_id` / email `sent_by` as opaque TEXT without FK
- **No workflow runtime:** bulk-issue/reissue/expire are synchronous with per-item results (decision #3 in api-endpoints.md §8)
- **Participant role:** read grants on participant paths only + owner rule (§9.6); no `cert.*` usage
- **Template locking:** update/delete of a referenced template returns 409
- **Auth platform dependency:** JWT issued for the `loa` tenant must include `tenant.slug` claim; `AUTH_ALLOWED_REDIRECTS` must include `https://e-cert.vercel.app` (the e-cert UI origin)

### Open Questions
- Certificate number default pattern `LOA-YYYY-####` acceptable? (legacy default was `EPOCH`)
- Confirm Cert-proxied refresh/logout (httpOnly cookie design) is preferred over frontend-direct calls to Auth (refines README §11.5–11.6) — flagged as decision #17 in api-endpoints.md §8
- Confirm dashboard stats at `read` is acceptable (was `cert.certificates.view_all`)

---

## Session Log

| Date | Work Done | Next Action |
|------|-----------|-------------|
| 2026-08-05 | Reviewed e-cert legacy docs; locked scope decisions; produced `api-endpoints.md` (Draft v1.0, 48 endpoints, MySQL data-model ref, permission table); created cert-scoped SESSION-PROMPT | Review + promote api-endpoints.md to Final; then Auth integration spec; then scaffold + implement |
| 2026-08-05 (2) | Read auth-platform actual code (`JWTService`, `EncryptionService`, `ClaimPolicyMiddleware`, `EndpointGrantController`, `routes/api.php`); rewrote `api-endpoints.md` to **Draft v1.1**: level-based auth model (§4), `required_level` on all endpoints (§5/§6), full SSO + JWT/permission middleware contract (§9), Appendix A catalog; updated this SESSION-PROMPT | Review v1.1 → promote to Final v1.1 → scaffold Laravel 12 Cert app in auth stack (core slice: config + middleware + events/attendees/templates/certificates + tests) |
| 2026-08-05 (2b) | Added **author scope** to `api-endpoints.md` (**Draft v1.2**): `GET /api/v1/me/events` + `/api/v1/me/templates`, `created_by` on events/templates (§7.2), resource-scoping rules (recipient/author/unscoped, §9.6), grant patterns (§4.4), catalog + route summary updated → 50 domain endpoints | Review v1.2 → promote to Final v1.2 → scaffold Laravel 12 Cert app (core slice) |

---

## Anti-Scope Rules (Cert Platform work only)

| Rule | Detail |
|------|--------|
| No auth implementation here | SSO callback, JWT issuance, permission resolution all belong to Auth Platform sessions |
| No direct Auth DB access | User data comes via Auth API / JWT claims only |
| No business logic in the assembly | Cert Platform wires routes; logic lives in `business-contexts/certificate/` |
| Specs before code, always | No implementation until the governing spec is Final (AI-RULES.md Rule 0) |
| No auto-pilot | Confirm every significant action with the user (AI-RULES.md §13, AI-GUIDE.md) |
