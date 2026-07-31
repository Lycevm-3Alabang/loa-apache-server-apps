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

### Date: 2026-08-01

### Completed
- RefreshToken spec promoted to **Final** (`kernels/identity/entities/refresh-token.md` + PROJECT.md status)
- RefreshToken implemented per spec:
  - `app/Models/RefreshToken.php` (UUID PK, jti hidden/hashed, isValid() helper, belongsTo user + replacedBy)
  - Migration `2026_07_30_000009_create_refresh_tokens_table.php` (unique jti, `replaced_by` FK nullOnDelete, user_id/revoked_at + expires_at indexes)
  - `User::refreshTokens()` hasMany relation added
  - `IdentityService` wiring: record issued on login, rotated on refresh (old revoked + `replaced_by` set), revoked on logout / password change / password reset / account lock (`revokeAllRefreshTokens`)
- Account disable endpoint (rule 7):
  - `IdentityService::setUserStatus()` — disables user, revokes all refresh tokens; re-enables from lock
  - `UserController::updateStatus()` — PATCH /users/{id}/status, users.manage permission
- Refresh token pruning (rule 8):
  - `PruneRefreshTokens` command (`refresh-tokens:prune`) — purges expired/revoked >30 days
  - `routes/console.php` — `Schedule::command('refresh-tokens:prune')->daily()`
- Docker local dev environment (`environment.md` spec):
  - `docker-compose.yml` — php:8.3-fpm, nginx:1.27, mysql:8.0, mailpit, scheduler service
  - `docker/php/Dockerfile` — PHP 8.3 + pdo_mysql, bcmath, zip + composer
  - `docker/nginx/default.conf` — fastcgi to app:9000, root public/
  - `.env` — local DB/JWT/MAIL config
- PHP 8.3 + Laravel 12 upgrade (Laravel 11 EOL Jul 2026, security advisories)
- All verified: `php -l` passed, 9 migrations ran, `refresh_tokens` schema confirmed (indexes, FK)
- PROJECT.md tracker + Decisions Log + assembly READMEs updated
- Auth Web UI spec promoted to **Final** and expanded with implementation, security, rate-limit, session, and deployment details
- Auth Web UI implemented:
  - Blade login, forgot-password, and reset-password forms with CSRF protection
  - Safe allowlisted redirect with JWT pair delivered in the URL fragment
  - Shared password-reset notification service and reset/change email templates
  - `POST /api/v1/auth/password/change-request` with JWT authentication
  - Email+IP password-reset throttling with generic anti-enumeration responses
  - Laravel web, mail, cache, and session configuration plus sessions migration
- PROJECT.md implementation tracker updated for the Auth Web UI and SMTP/mail items
- Investigated deployed logs and hardened deployment fixes:
  - CORS spec promoted to **Final**; origin parsing now always produces a flat trimmed string list
  - Production deployment docs now clear/rebuild config cache and regenerate Swagger docs
  - OpenAPI default corrected to 3.1.0; CORS, cache, and Swagger environment variables documented
  - Seeder/autoload files verified; route-list command documented with Laravel 12-compatible `--path`

### In Progress
- Nothing — last session ended clean

### Next Action
- Deploy the corrected release to auth.loa.edu.ph, clear/rebuild config cache, regenerate Swagger docs, run migrations, and seed the admin account

### Backlog / Known Gaps
- Education Domain + Business Contexts still use flat structure (no `entities/`, `events/`, `rules/` subdirs)
- Auth Web UI code not linted in this session (PHP is not on PATH) — run `php -l` before deploy
- JWT_SECRET not configured (env) — required before deploy
- Production environment: cPanel cron for `schedule:run`, MySQL host, MAIL SMTP credentials

### Open Questions
- None pending (redirect mechanism decision documented in web-ui.md: URL fragment + allowlist)

---

## Session Log

| Date | Work Done | Next Action |
|------|-----------|-------------|
| 2026-07-31 | Identity events/rules specs; auth controllers; middleware; CORS spec+impl; spec-first mandate | Deploy auth or start Phase 2 |
| 2026-07-31 | RefreshToken entity spec + contract + README/PROJECT updates | Implement RefreshToken or deploy auth or Phase 2 |
| 2026-07-31 | Auth Web UI spec (login redirect, forgot/change password, email, CSRF) + rule unification | Implement RefreshToken or Auth Web UI or deploy |
| 2026-07-31 | RefreshToken spec → Final; model + migration + IdentityService wiring | Auth Web UI or deploy auth |
| 2026-07-31 | Docker local dev (php:8.3, nginx, mysql, mailpit); Rule 7 disable endpoint + Rule 8 pruning; Laravel 12 upgrade; all verified | Auth Web UI or deploy auth |
| 2026-08-01 | Auth Web UI implemented; deployed CORS/Swagger/seeder issues investigated and deployment safeguards added | Deploy corrected auth release |
