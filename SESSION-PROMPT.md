# SESSION PROMPT

## How to Use

1. **Starting a new session:** Paste the `## Startup Prompt` block below verbatim into the first message.
2. **Ending a session:** Update the `## Last Session Notes` section so the next session knows exactly where to pick up.

---

## Startup Prompt

Paste this block into the first message of a new session:

```
Read these files IN ORDER and report your understanding of where we left off:

1. AGENT.md        - mandatory spec-first rules
2. AI-GUIDE.md     - architecture + Step 0 (spec check)
3. AI-RULES.md     - coding rules + Rule 0 (specs before code)
4. PROJECT.md      - project tracker: current status of every layer and phase
5. SESSION-PROMPT.md - "Last Session Notes" section = exactly where we stopped
6. kernels/identity/rules/token-lifecycle.md  - the last spec we touched (if still relevant)

Then:
- Summarize the current state of each layer (kernels, domains, contexts, services, assemblies)
- Summarize Phase 1-4 implementation status
- List what's Done, what's In Progress, what's Backlog
- Identify the NEXT action item from "Last Session Notes"
- Do NOT write any code until a Final spec exists
```

---

## Last Session Notes

### Date: 2026-07-31

### Completed
- Identity Kernel fully spec'd: README, 5 entities, contracts, 15 events, 8 business rules
- `loa-auth-platform` assembly README cleaned up (removed Organization Kernel references)
- `services/cors/README.md` spec created; `config/cors.php` implemented
- Mandatory "Specs Before Code" rule added to AGENT.md, AI-GUIDE.md, AI-RULES.md, PROJECT.md
- Auth app controllers implemented (AuthController 9 endpoints + UserController 2)
- JWT + Permission middleware created (`jwt.auth`, `jwt.permission:{key}`)
- IdentityService: added `getUser()`, `updatePassword()`, tokens now carry `groups` + `permissions` claims
- `config/cors.php` created (LOA subdomains + env override)

### In Progress
- Nothing — last session ended clean

### Next Action
- **Deploy to auth.loa.edu.ph** (Phase 1 final task), OR
- Start Phase 2 (Consult App) spec work

### Backlog / Known Gaps
- Refresh token revocation — needs `RefreshToken` entity spec + contract + migration (tracked in PROJECT.md)
- Education Domain + Business Contexts still use flat structure (no `entities/`, `events/`, `rules/` subdirs)
- Auth code not linted (PHP not on PATH) — run `php -l` before deploy
- JWT_SECRET not configured (env) — required before deploy

### Open Questions
- None pending

---

## Session Log

| Date | Work Done | Next Action |
|------|-----------|-------------|
| 2026-07-31 | Identity events/rules specs; auth controllers; middleware; CORS spec+impl; spec-first mandate | Deploy auth or start Phase 2 |
