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
- RefreshToken entity spec created (`kernels/identity/entities/refresh-token.md`)
- `RefreshTokenRepository` + `TokenService.revokeAllRefreshTokens()` added to `kernels/identity/contracts/interfaces.md`
- Identity Kernel README updated (RefreshToken core concept + TokenService contract)
- Auth Web UI spec created (`assemblies/loa-auth-platform/web-ui.md`): login page + post-login redirect (URL-fragment token handoff, `AUTH_ALLOWED_REDIRECTS` allowlist), unified forgot/change-password flow, CSRF + one-time token validation, SMTP email templates
- Auth assembly README updated (web UI surface + `POST /auth/password/change-request` endpoint + hybrid JSON/web note)
- `password-reset-flow.md` rule updated (forgot = change, one flow two entry points)
- PROJECT.md tracker updated

### In Progress
- Nothing — last session ended clean

### Next Action
- **Implement RefreshToken** (Phase 1 gap): model + migration + wire into IdentityService.refresh()/logout()/updatePassword()/resetPassword(), per the new spec, OR
- Implement Auth Web UI (login/forgot/change pages + SMTP mail), OR
- Deploy to auth.loa.edu.ph (Phase 1 final task)

### Backlog / Known Gaps
- RefreshToken implementation (model + migration + service wiring) — spec is Final-ready
- Auth Web UI implementation (Blade pages, mail config, redirect logic) — spec is Draft
- Education Domain + Business Contexts still use flat structure (no `entities/`, `events/`, `rules/` subdirs)
- Auth code not linted (PHP not on PATH) — run `php -l` before deploy
- JWT_SECRET not configured (env) — required before deploy

### Open Questions
- None pending (redirect mechanism decision documented in web-ui.md: URL fragment + allowlist)

---

## Session Log

| Date | Work Done | Next Action |
|------|-----------|-------------|
| 2026-07-31 | Identity events/rules specs; auth controllers; middleware; CORS spec+impl; spec-first mandate | Deploy auth or start Phase 2 |
| 2026-07-31 | RefreshToken entity spec + contract + README/PROJECT updates | Implement RefreshToken or deploy auth or Phase 2 |
| 2026-07-31 | Auth Web UI spec (login redirect, forgot/change password, email, CSRF) + rule unification | Implement RefreshToken or Auth Web UI or deploy |
