# MAIL-RELAY deploy checklist — `LOA Auth Platform` (`/api/v1/mail/send`)

Companion to assembly `DEPLOY.md`; applies to the mail-relay endpoint (Final spec
`mail-relay-endpoint.md`). Adds **no DB tables**, **no queues**, **no UI** — only
runtime + transport.

## 0. Files you add / edit (all under `assemblies/loa-auth-platform/`)

- `app/Http/Controllers/LocalEmailController.php`
- `app/Http/Middleware/RelaySecretMiddleware.php`
- `app/Http/Requests/LocalEmailSendRequest.php`
- `config/mail-relay.php`
- `tests/Feature/Api/LocalEmailTest.php`
- **Edited** `routes/api.php`, `bootstrap/app.php`, `config/logging.php`,
  `app/Providers/AppServiceProvider.php`, `.env.example`, `.env.cpanel`.
- `storage/logs/mail-relay-YYYY-MM-DD.log` (auto-created, rotated daily).

## 1. cPanel host pre-flight (one-time)

- Confirm **Apache Authorization forward** in `public/.htaccess` is both present and
  at the TOP of the rewrite block (auth assembly contract; the relay returns 401 if it
  is missing). Rule order: `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`
  BEFORE the front-controller `RewriteCond` + `RewriteRule ^ index.php [L]`.
- Confirm PHP MultiPHP version is **8.3+** (there are no `array_filter`/Arrow fn
  shims in these files, and it must be ≥ the running app uses in composer).
- `php artisan config:cache` currently runs at deploy? If yes, add `config:clear` after
  uploading and RE-CACHE — stale `config/mail-relay.php` with no `RELAY_SECRET` → every
  call returns 401 and no audit of why. Commands (run from cPanel Terminal or one-time cron):
  - `php artisan config:clear`
  - `php artisan config:cache`
  - `php artisan route:cache` (the new middleware + api route is added)
  - `php artisan view:cache` (optional; no blade for this endpoint)
- If the host has NO terminal, use phpMyAdmin/cPanel cron to run those one-off
  commands and paste the output back here. Do NOT modify vendor or logs via File
  Manager upload.
- `storage/logs/` and `storage/framework/*` writable by the cPanel user: 755. The
  first request auto-creates `mail-relay-YYYY-MM-DD.log`; if it gets a permission
  error, check file Manager ownership, not a fresh deploy.

## 2. `.env` on cPanel

- File<rfl:parameter name=" `copy .env.cpanel to `.env` on `~/loa-auth-platform/`, then fill:
  - `RELAY_SECRET` — 32+ bytes
    - Generate in terminal: `openssl rand -hex 32`
    - No terminal: create a temporary `__secret.php` in the docroot that echoes
      `bin2hex(random_bytes(32));` in plain text, load in browser once, then
      DELETE the file before merging the result into `.env`.
  - `RELAY_ALLOWED_DOMAINS` — default `lyceumalabang.edu.ph,itmlyceumalabang.onmicrosoft.com,gmail.com,outlook.com`.
    - Change (lowercase, comma-separated) only if a new recipient class appears.
- The SMTP path stays the one already working for `MAIL_*`:
  `MAIL_MAILER=smtp`, `MAIL_HOST=mail.lyceumalabang.edu.ph`, port 587,
  `MAIL_USERNAME=noreply@…`, password set by cPanel. DO NOT switch to host
  SMTP-auth or port 465 `smtps`, nor to sendmail, unless mail stops.
- Save `.env`, then `php artisan config:clear && php artisan config:cache`.

## 3. ACES (Vercel) server-side wiring — (NOT part of this commit; documented so it lands)

- Add server-only env `AUTH_RELAY_URL=https://auth.lyceumalabang.edu.ph/api/v1/mail/send`,
  `RELAY_SECRET=<matching cPanel value>`.
- In the Next.js API layer (server route) `fetch(AUTH_RELAY_URL, { method: 'POST', headers: {'Authorization':'Bearer '+RELAY_SECRET}, body: JSON.stringify({to, subject, html}) })`.
- Honor 429 → read `retry_after` (seconds); honour 422/502 → surface error.
- For blast radius, if `to` is a user-controlled list, still validate server-side there; the
  cPanel allowlist is the second gate, never the first.

## 4. curl — one valid test (test secret substitute)

Run locally or via online shell:

```bash
curl -sS -X POST https://auth.lyceumalabang.edu.ph/api/v1/mail/send \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer REPLACE_WITH_REAL_RELAY_SECRET' \
  -d '{
        "to": "you@gmail.com",
        "subject": "relayed-test \u2014 should be HAM",
        "html": "<p>If you see this, the host delivered, not quarantine.</p>"
      }'
```

Expected HTTP status: `200` body `{ "ok": true, "message_id": "..." }`.

Other shape tests expected:

- Bad secret → 401 `{ ok:false, error:"Unauthorized." }`.
- Recipient `you@disallowed-domain.example` → 422 `invalid` listing it.
- `html` > 200KB → 422.
- 61 quick repeats → 429 `{ ok:false, error:"Rate limit exceeded.", "retry_after": ... }`
  with `Retry-After: <int>` header.

## 5. The real acceptance (ACC-11) — NOT HTTP 200

After step 4's curl:

1. Find the message in the **cPanel mail log** (sub-Panel → Track Delivery / Mail Delivery Logs, or `/var/log/exim_mainlog` if SSH is available). The message you submitted must appear as `HAM` / delivered.
2. Specifically, it must NOT match any of:
   - `require_local_sender_domain_global`
   - `quarantine[...]`
   - dropped/greylisted status
3. Also confirm the relay log file `storage/logs/mail-relay-YYYY-MM-DD.log` contains one line: timestamp, to-domain (gmail.com), subject snippet, `outcome=sent`, message id.
4. If you find quarantine or "remote SMTP denied" in logs, check: (a) are we on the local host smtp at all (the mail log should show `relay` for localhost), (b) did `MAIL_HOST` accidentally point to `mail.lyceum…` remote — that's what triggered the initial quarantine.
   Probably resolve `MAIL_HOST=mail.lyceumalabang.edu.ph` on cPanel is acceptable (it resolves inside the same host) — your existing auth flow already works — but if mail shows only `Q`.
5. The relay log should show failure/full recipient only on errors.

## 6. Rollback

- Revert the Leave `.env` RELAY_SECRET blank or remove the route via git
  diff pull. No DB migration exists, so a rollback is just the file changes
  plus `php artisan config:cache && php artisan route:cache`.
- Do NOT delete `storage/logs/mail-relay-*.log` in production — it's the audit
  trail. To rotate/retain, adjust `LOG_DAILY_DAYS` early on day 0 and restart
  the daily job (cPanel cron handles it nightly).

## 7. One-open-risk

If someone brute-forces `RELAY_SECRET`, the 60/min throttle (per IP *or*
bearer) bounds abuse but does not stop it entirely. Protect at firewall or
cPanel IP deny when possible; rotate on suspicion. The `to` domain allowlist means
even a leaked secret can target only whitelisted families and never our own
mailboxing mailbox at scale, but that does NOT block high-volume BCC/gmail sends.
Monitor the daily log and alarming on `outcome=sent` spikes.
