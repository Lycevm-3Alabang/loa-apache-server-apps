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

### Date: 2026-08-06 (session 3)

### Completed
- **Resolved all open questions Q-2..Q-7** (Phase A gate) with the user:
  - **Q-2 (CSR, supersedes D8):** the refactored `e-cert` is a **client-side SPA** — token in memory only, no httpOnly access-token cookie, no server actions, no server-side JWT verification, no shared secret, `src/proxy.ts` deleted. The Cert API enforces its JWT model with app-level checks and does not adapt to front-end expectations. Refresh stays in the Cert-proxied httpOnly `loa_cert_refresh` cookie; route protection is a client-side guard (UI only).
  - **Q-3 (audit/email-log gaps):** deferred — drop the affected UI features from the retrofit; a dedicated SMTP API endpoint will come later (check reuse of Auth's temporary email tool); not blocking v1.2.
  - **Q-4 (seed groups):** `cert-admin` / `cert-staff` / `cert-user`; no reuse of existing LOA groups.
  - **Q-5 (/my/profile):** out of `e-cert` scope; noted as a refinement task (likely front-end).
  - **Q-6 (cert number):** `certificate_number_pattern` is user-configurable per event and **required**, must contain `####` to produce an incremental id (e.g. `CERT-####`, `TEMP-001-####`, `CERT-####-2026`); no fixed default.
  - **Q-7 (attendees/import):** `/attendees/import` accepts a **JSON payload**; CSV parsing / upload wizard is a front-end concern.
- **Bumped `api-endpoints.md` → Draft v1.3** (Q-6/Q-7 synced): `certificate_number_pattern` required + no default (§5.1, §7.4, events schema), `/attendees/import` is JSON (§5.2, Appendix A, security checklist, decision #5).
- **Bumped `legacy-e-cert-integration.md` → Draft v1.1** (all Qs resolved in §13): D8 reframed (API-enforced JWT-cookie model), seed groups renamed to `cert-admin/staff/user`, `/my/profile` + GAP rows marked per decisions.
- **CSR decision (2026-08-06):** aligned `e-cert` refactor with `D:\loa\e-cert\specs\` v2.0 — **CSR wins** over SSR. Rewrote `legacy-e-cert-integration.md` → **Draft v2.0**: D8 superseded in §5; §3 architecture is SPA; §6 in-memory session + parse-only client JWT + route guard; §8 server actions deleted (client API modules); §10 drops `JWT_SECRET`/cookie env, adds `NEXT_PUBLIC_CERT_TENANT_SLUG`; §12 phases D/E reworded; §13 Q-2 = CSR, R-4 = XSS/in-memory risk. Synced to `D:\loa\e-cert\legacy-e-cert-integration.md` (D7). Also fixed stale bits in `e-cert/specs` (seed groups `cert-admin/staff/user`, attendee import = JSON payload, CSV parse stays client-side).
- **Phase A COMPLETE (2026-08-06):** user confirmed remaining open questions + approved fixes. `api-endpoints.md` → **Final v1.3** (§9.2 SSO URL corrected to `/sso/login`; §9.9 `/access` made optional; §5.7 dashboard ownership note; §8 decision #17 confirmed; example cert numbers → `CERT-0001`). `legacy-e-cert-integration.md` → **Final v2.0** (§7.2 dashboard ownership note; §12 Phase A marked complete; §14 reference updated). D7 copy re-synced.

### In Progress
- **Phase B (Auth readiness) — DEFERRED 2026-08-06:** no baked-in Auth seeder; provisioned **manually at deploy-time** per Auth runbook `assemblies/loa-auth-platform/cert-readiness.md` (**Final v0.2**, branch `docs/cert-readiness-runbook`; §8 = Local Development via Docker Compose) — loa tenant (`redirect_origins` = `https://e-cert.vercel.app`), 48-endpoint Appendix A catalog, `cert-admin`/`cert-staff`/`cert-user` groups (created manually) + grants (admin 48 / staff 39 / user 7)

### Next Action
- [x] **Phase A — COMPLETE 2026-08-06:** `api-endpoints.md` v1.3 and `legacy-e-cert-integration.md` v2.0 promoted to **Final** (remaining open questions resolved: decision #17 proxy confirmed; dashboard stats `read` confirmed with ownership note).
- [x] **Phase B — SPEC LOCKED 2026-08-06:** Auth readiness provisioned **manually at deploy-time** per Auth runbook `cert-readiness.md` (**Final v0.2**, incl. §8 Local Development; `loa` tenant redirect_origins, Appendix A catalog, manual group creation + grants).
- [ ] **Phase C:** scaffold Laravel 12 Cert app (core slice: `jwt.auth`/`jwt.endpoint`, callback/refresh/logout, events/attendees/templates/certificates + tests)

### Backlog / Known Gaps
- **Local catalog mirror** (`config/cert-endpoints.php`) must stay in sync with the Auth catalog — add `permissions:sync-cert-catalog` artisan command (§9.5)
- **MySQL adaptations to honor at implementation:** JSON columns, no partial indexes (generated-column trick §7.3), base64 `rendered_pdf` moved to storage (only `file_path` kept), audit `user_id` / email `sent_by` as opaque TEXT without FK
- **No workflow runtime:** bulk-issue/reissue/expire are synchronous with per-item results (decision #3 in api-endpoints.md §8)
- **Participant role:** read grants on participant paths only + owner rule (§9.6); no `cert.*` usage
- **Template locking:** update/delete of a referenced template returns 409
- **Auth platform dependency:** JWT issued for the `loa` tenant must include `tenant.slug` claim; `AUTH_ALLOWED_REDIRECTS` must include `https://e-cert.vercel.app` (the e-cert UI origin)
- **Deferred (Q-3):** global email logs + audit-log delete/entity/user/by-ids queries dropped from the retrofit; future dedicated SMTP API (check reuse of Auth's temporary email tool)

### Open Questions
- ~~Confirm Cert-proxied refresh/logout (httpOnly cookie design) is preferred over frontend-direct calls to Auth~~ **Resolved 2026-08-06**: confirmed — decision #17 stands; refresh/logout proxied by Cert via the `loa_cert_refresh` httpOnly cookie (`api-endpoints.md` §9.3/§9.7, §8 decision #17).
- ~~Confirm dashboard stats at `read` is acceptable (was `cert.certificates.view_all`)~~ **Resolved 2026-08-06**: confirmed at `read`, **with an ownership note** — dashboard data is org-wide unscoped; grants are `cert-admin`/`cert-staff` only and excluded from `cert-user` (`api-endpoints.md` §5.7, `legacy-e-cert-integration.md` §7.2).

---

## Session Log

| Date | Work Done | Next Action |
|------|-----------|-------------|
| 2026-08-05 | Reviewed e-cert legacy docs; locked scope decisions; produced `api-endpoints.md` (Draft v1.0, 48 endpoints, MySQL data-model ref, permission table); created cert-scoped SESSION-PROMPT | Review + promote api-endpoints.md to Final; then Auth integration spec; then scaffold + implement |
| 2026-08-05 (2) | Read auth-platform actual code (`JWTService`, `EncryptionService`, `ClaimPolicyMiddleware`, `EndpointGrantController`, `routes/api.php`); rewrote `api-endpoints.md` to **Draft v1.1**: level-based auth model (§4), `required_level` on all endpoints (§5/§6), full SSO + JWT/permission middleware contract (§9), Appendix A catalog; updated this SESSION-PROMPT | Review v1.1 → promote to Final v1.1 → scaffold Laravel 12 Cert app in auth stack (core slice: config + middleware + events/attendees/templates/certificates + tests) |
| 2026-08-05 (2b) | Added **author scope** to `api-endpoints.md` (**Draft v1.2**): `GET /api/v1/me/events` + `/api/v1/me/templates`, `created_by` on events/templates (§7.2), resource-scoping rules (recipient/author/unscoped, §9.6), grant patterns (§4.4), catalog + route summary updated → 50 domain endpoints | Review v1.2 → promote to Final v1.2 → scaffold Laravel 12 Cert app (core slice) |
| 2026-08-06 | Resolved Q-2..Q-7 with user; bumped `api-endpoints.md` → Draft v1.3 (cert-number pattern required/user-configurable, attendees/import is JSON) and `legacy-e-cert-integration.md` → Draft v1.1 (all Qs resolved in §13, seed groups `cert-admin/staff/user`) | Phase A: review + promote v1.3/v1.1 → Final → Phase B (Auth readiness) → Phase C (scaffold) |
| 2026-08-06 (2) | Checked `D:\loa\e-cert\specs\` v2.0 — supersedes D8 with a **CSR SPA**; user chose **CSR wins**. Rewrote `legacy-e-cert-integration.md` → **Draft v2.0** (D8 superseded, §6 in-memory/parse-only/route guard, §8 server actions deleted, §10 no secrets env, §13 Q-2/R-4). Synced D7 copy; fixed `e-cert/specs` stale bits (seed groups, JSON import, CSV client-side) | Phase A: review + promote v1.3 / v2.0 → Final → Phase B → Phase C |
| 2026-08-06 (3) | **Phase A COMPLETE.** User confirmed decision #17 (Cert-proxied refresh/logout) and dashboard stats at `read` with ownership note. Promoted `api-endpoints.md` → **Final v1.3** (SSO URL `/sso/login`, §9.9 `/access` optional, §5.7 dashboard ownership, example numbers `CERT-0001`) and `legacy-e-cert-integration.md` → **Final v2.0** (§7.2 ownership note, §12 A complete). D7 re-synced | Phase B (Auth readiness) → Phase C (scaffold) |
| 2026-08-06 (4) | **Phase B DEFERRED (user decision):** no Cert data baked into Auth seeders/database.sql (seeder attempt reverted); Cert groups created **manually** at deploy-time. Auth runbook **`cert-readiness.md` (Draft v0.1)** written on branch `docs/cert-readiness-runbook` (loa tenant + redirect_origins, 48-endpoint Appendix A payload, groups, 48-row grant matrix: admin 48 / staff 39 / user 7, verification) | Review + promote `cert-readiness.md` → Final → deploy + provision → Phase C (scaffold) |
| 2026-08-06 (5) | **`cert-readiness.md` promoted to Final v0.1** — Phase B readiness spec locked (manual deploy-time provisioning) | Deploy + provision per runbook → Phase C (scaffold) |

---

## Anti-Scope Rules (Cert Platform work only)

| Rule | Detail |
|------|--------|
| No auth implementation here | SSO callback, JWT issuance, permission resolution all belong to Auth Platform sessions |
| No direct Auth DB access | User data comes via Auth API / JWT claims only |
| No business logic in the assembly | Cert Platform wires routes; logic lives in `business-contexts/certificate/` |
| Specs before code, always | No implementation until the governing spec is Final (AI-RULES.md Rule 0) |
| No auto-pilot | Confirm every significant action with the user (AI-RULES.md §13, AI-GUIDE.md) |
