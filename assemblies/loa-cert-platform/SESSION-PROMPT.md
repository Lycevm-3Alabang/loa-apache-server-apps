# SESSION PROMPT — LOA Cert Platform

> Assembly-scoped session prompt for `assemblies/loa-cert-platform/`. This complements (does not replace) the repo-wide `PROJECT_UPDATES.md` at the workspace root.

## How to Use

1. **Starting a new session:** Paste the `## Startup Prompt` block below verbatim into the first message.
2. **Ending a session:** Update the `## Last Session Notes` section so the next session knows exactly where to pick up.
3. **Scope rule:** This prompt governs **Cert Platform work only** (endpoints, schema, SSO, deployment). Do not pull in Auth Platform implementation tasks unless the note explicitly says so.

---

### Startup Prompt

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

### Date: 2026-08-07 (Session 4)
### Completed
- **Phase C Scaffolding & Testing - Events Group:** Implemented the full resource group for Events. This includes creating/updating migration, EventController with CRUD methods (index, store, show, update, destroy), and adding all required routes (`GET/POST/PATCH/DELETE` + stats). The implementation was verified with comprehensive unit tests.
- **Phase C Scaffolding & Testing - Attendees Group:** Implemented the full resource group for Attendees. This includes creating/updating migration, `AttendeeController`, and necessary nested event routes. This covered single record management and the complex bulk JSON import logic (`POST /import`), along with associated unit tests.

### In Progress
- **Phase C: Templates Resource Group**: Next up is implementing the full resource group for CertificateTemplates (CRUD) to manage templates, including mandatory template locking logic on update/delete.
- **Pending Implementation of Sessions:** After templates, we will proceed to implement the Certificates resource group (migration + controller + routes).

### Next Action
- Start implementation of the Templates resource group: Migration check, Controller creation, Route definition, and Unit testing for all CRUD endpoints (`POST /templates`, `GET /templates/{id}`, etc.).