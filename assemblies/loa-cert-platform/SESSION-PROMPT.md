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

### Date: 2026-08-07 (Session 6)
### Completed
- **Phase C Scaffolding & Testing - Events Group:** Implemented the full resource group for Events. This includes creating/updating migration, EventController with CRUD methods (index, store, show, update, destroy), and adding all required routes (`GET/POST/PATCH/DELETE` + stats). The implementation was verified with comprehensive unit tests.
- **Phase C Scaffolding & Testing - Attendees Group:** Implemented the full resource group for Attendees. This includes creating/updating migration, `AttendeeController`, and necessary nested event routes. This covered single record management and the complex bulk JSON import logic (`POST /import`), along with associated unit tests.
- **Phase C Scaffolding & Testing - Templates Group:** Implemented the full resource group for CertificateTemplates (CRUD) with template locking logic. Created `CertificateTemplateController` with methods: `index`, `store`, `show`, `update`, `destroy`. Implemented locking logic that checks if template is referenced by events (locks update/delete with 409) or issued certificates (prevents delete with 409). Added routes (`GET/POST/PATCH/DELETE /templates`). Created comprehensive unit tests covering all CRUD operations, validation, locking behavior, and edge cases.
- **Phase C Scaffolding & Testing - Certificates Group:** Implemented the full resource group for Certificates (CRUD + issue/revoke/reissue/expire). Created migrations for `certificates` (with generated column for active-number uniqueness), `certificate_sequences` (atomic number generation), and `certificate_emails` (email logs). Updated `Certificate` model with relationships and status derivation. Created `CertificateController` with all 14 endpoints. Added routes (`GET/POST/PATCH/DELETE /certificates` + sub-routes). Created comprehensive unit tests covering all operations, validation, status derivation, and edge cases.

### In Progress
- **Deferred to Auth Phase:** SSO + `jwt.auth`/`jwt.endpoint` middleware implementation
- **Deferred to Service Phase:** PDF streaming (DOMPDF), QR generation, email sending services

### Next Action
- **Phase C Complete:** Core domain CRUD slice (events → attendees → templates → certificates) is implemented. Ready for next phase: Auth integration or Service implementation (PDF/QR/email).