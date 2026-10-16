# Mail-Relay Endpoint (ACES)

**ID:** `mail-relay-aces`
**Title:** Synchronous mail-relay endpoint for ACES (Vercel) to submit mail locally on the cPanel auth host
**Status:** Final v1.0 (2026-10-16; public path `/api/v1/mail/send`, controller `LocalEmailController`, config `mail-relay`)
**Owner:** Auth platform (caller: ACES Next.js app on Vercel)
**Version:** 0.1
**Scope:** One HTTP endpoint `POST /api/v1/mail/send` served by `auth.lyceumalabang.edu.ph` (Laravel 12, PHP 8.3+), sending synchronously through the host's existing `MAIL_*` path.
**Non-goals:** No queues/database tables/UI per v1, no attachments, no templating, no CORS opening, no per-user auth, no new Composer dependencies.
**Layer:** Platform Service (Mail Sending) + LOA Auth Platform consumer/edge gateway.

---

## Context

ACES (Next.js on Vercel) currently sends activation / password-reset mail over SMTP to `mail.lyceumalabang.edu.ph:465` from `noreply@lyceumalabang.edu.ph`. Those connections originate from AWS/Vercel IPs with a junk HELO, trip `QUARANTINE[require_local_sender_domain_global]`, and are dropped before delivery — while `sendMail()` returns success, so app-side retry cannot detect it. Submitting mail on the cPanel host (`auth.lyceumalabang.edu.ph`) places it in the same trust class as the host's own delivered mail (no quarantine). ACES therefore calls this endpoint server-to-server over HTTPS.

Existing auth-assembly mail wiring is reused unchanged: `MAIL_MAILER=smtp`, `MAIL_HOST=localhost`, `MAIL_PORT=587`, `MAIL_USERNAME=no-reply@lyceumalabang.edu.ph`, `MAIL_FROM_ADDRESS=noreply@lyceumalabang.edu.ph`. The endpoint overrides the `From` name to "ACES".

---

## Constraints

- **CON-1 (Hosting):** No sudo, no supervisor, no daemon workers. Send is synchronous within the HTTP request; shared-hosting process/CPU limits apply.
- **CON-2 (Dependencies):** Laravel framework pieces only. No new Composer packages.
- **CON-3 (Surface):** Single endpoint `POST /api/v1/mail/send`. No database tables, migrations, seeders, queues, or UI.
- **CON-4 (Auth):** No Sanctum / no user identity. Access is guarded by a static shared secret compared in constant time (`hash_equals`) against env `RELAY_SECRET`.
- **CON-5 (Transport):** Send through the assembly's configured default mailer (localhost SMTP on cPanel, Mailpit locally), NOT over the WAN SMTP path ACES uses today. Synchronous `Mail::html(...)`.
- **CON-6 (Envelope identity):** Envelope sender / `From` address = `noreply@lyceumalabang.edu.ph`; display name = `ACES`.
- **CON-7 (Privacy):** Daily log records domain only by default; full recipient address recorded only on send failure (no secrets, no stack traces in logs or responses).
- **CON-8 (CORS):** No CORS for this route. The global CORS middleware (none currently applies to api.php in auth, but any future policy) MUST exclude `/api/v1/mail/*`.
- **CON-9 (CPanel gotcha):** Route sits behind cPanel Apache; `public/.htaccess` already forwards `Authorization` — keep that rule intact (needed for the Bearer check).
- **CON-10 (No host override):** Do not change existing auth email flows (forgot-password, change-request, activation). Their `MAIL_FROM` stays the existing auth identity.

---

## Goal

### Decisions (DEC-*)

- **DEC-1 (Placement):** Implement inside the `loa-auth-platform` assembly (`routes/api.php`, `app/Http/Controllers`, `app/Http/Requests`, `app/Http/Middleware`, `config/*`, `AppServiceProvider`, `bootstrap/app.php`).
- **DEC-2 (Secret source):** A dedicated `RELAY_SECRET` env key, not a reuse of `APP_KEY`/`JWT_SECRET`/`ENCRYPTION_KEY`. Compared with `hash_equals`.
- **DEC-3 (Allowlist):** Client-supplied recipients are accepted only if each recipient domain is in `RELAY_ALLOWED_DOMAINS` (comma-separated env; default `lyceumalabang.edu.ph,itmlyceumalabang.onmicrosoft.com,gmail.com,outlook.com`). Domains matched case-insensitively on the part after the last `@`.
- **DEC-4 (Throttle):** `throttle:mail-relay` rate limiter at **60/min**, registered in `AppServiceProvider::boot`, key = hash of provided Bearer token if present else client IP. `Route::middleware('throttle:mail-relay')`.
- **DEC-5 (Validation normalization):** `to` accepts a string OR array of strings; normalized to an array in `LocalEmailSendRequest::prepareForValidation`. `cc`/`bcc` optional array of strings only.
- **DEC-6 (No text alternative required):** v1 sends HTML only (`Mail::html`). Caller provides complete HTML.
- **DEC-7 (Message ID capture):** Return `message_id` from `SentMessage::getMessageId()` (nullable; `null` when SMTP declines).
- **DEC-8 (Logging):** Laravel daily channel `mail-relay` with `path = storage_path('logs/mail-relay.log')` so files are `storage/logs/mail-relay-YYYY-MM-DD.log`. Structured single-line message via `Log::info` / `Log::error`.
- **DEC-9 (Throttle 429 body):** Register an exception renderer in `bootstrap/app.php` for `ThrottleRequestsException` scoped to `api/v1/mail/*` returning JSON `{ ok:false, error, retry_after }` with `Retry-After` header preserved.

### Acceptance (ACC-*)

- **ACC-1 (401):** Missing / mismatched Bearer → 401 `{ ok:false, error:"Unauthorized." }`; no mail sent; one `info`/`warning` log line recorded outcome=unauthorized.
- **ACC-2 (422 domain):** Any recipient (to/cc/bcc) with an off-allowlist domain → 422 `{ ok:false, error, invalid:[{recipient,domain}...] }`; no mail sent.
- **ACC-3 (422 validation):** Missing/invalid subject, html > ~200KB, invalid email → 422 `{ ok:false, error, errors }` (form-validation body), no mail sent.
- **ACC-4 (429):** Exceed 60 in 60s → 429 JSON + `Retry-After` header (value ≥ 1).
- **ACC-5 (200):** Valid payload → 200 `{ ok:true, message_id }` with non-null `message_id` when SMTP provides one. `Content-Type: application/json`.
- **ACC-6 (502):** SMTP throw converts to 502 `{ ok:false, error:"Mail delivery failed." }`. Full exception detail goes only to the daily log/app log on `APP_DEBUG`-safe terms (never env values/stack traces to body).
- **ACC-7 (Log row, success):** One daily-log entry: timestamp, to-domain (domain only), subject (truncated ≤ 120 chars), outcome=sent, message_id. No full recipient.
- **ACC-8 (Log row, failure):** On send failure/422/401, log entry includes full recipient address(es) for triage. Still no secrets/stack.
- **ACC-9 (Privacy invariant):** Success log never contains the local part of any recipient email.
- **ACC-10 (CORS):** `OPTIONS /api/v1/mail/send` returns 404/405 (no CORS preflight served); `Access-Control-*` headers absent from responses.
- **ACC-11 (HAM, not just 200):** A test message submitted via a real curl against the host arrives delivered as HAM (verifiable in the cPanel mail log); NOT quarantined with `require_local_sender_domain_global`.
- **ACC-12 (No queue):** No job/queue touched; `MAIL_MAILER` runtime stays auth's configured default.

### Non-goals (explicit, for test-suite scope)

- Attachments, text/plain alternative, templates, DKIM handling, bounce tracking, retries (no retry from this endpoint — caller decides).

---

## Deliverables

- **D-1:** `routes/api.php` — register `POST /mail/send` (within the existing `v1` group, lands at `/api/v1/mail/send`), middleware `[throttle:mail-relay, relay.secret]`.
- **D-2:** `app/Http/Requests/LocalEmailSendRequest.php` — FormRequest: `to` required (string|array), `to.*` email; `cc`/`bcc` optional arrays with `*.email`; `subject` required string max 255; `html` required string max 204800; `prepareForValidation` normalizes scalar `to` to a single-element array.
- **D-3:** `app/Http/Middleware/RelaySecretMiddleware.php` — reads `Authorization: Bearer ...`, `hash_equals` against `config('mail-relay.secret')`; on mismatch/absence sets `request attribute relay.auth_ok=false` and short-circuits 401 (preferred: throw `HttpException(401)` so the global handler returns the JSON 401 shape). Logging outcome=unauthorized includes request IP.
- **D-4:** `app/Http/Controllers/LocalEmailController.php` — domain allowlist check (single pass over to/cc/bcc, collects invalid), synchronous `Mail::html`, log success/failure, return `{ok,message_id}` / `{ok:false,error}` with statuses 401 (middleware), 422 (domain/validation), 429 (middleware/throttle), 502 (catch).
- **D-5:** `config/mail-relay.php` — `secret` (env `RELAY_SECRET`, null default) and `allowed_domains` (parsed from comma-separated env `RELAY_ALLOWED_DOMAINS` with the default string included).
- **D-6:** `app/Providers/AppServiceProvider.php::boot()` — register `'mail-relay'` rate limiter.
- **D-7:** `config/logging.php` — add `'mail-relay'` daily channel pointing to `storage/logs/mail-relay.log`.
- **D-8:** `bootstrap/app.php` — add ThrottleRequestsException renderer for `api/v1/mail/*` returning the JSON 429 body; register middleware alias `relay.secret`.
- **D-9:** `.env.example` / `.env.cpanel` / cPanel deployment template — keys `RELAY_SECRET=` (blank, fill at deploy) and `RELAY_ALLOWED_DOMAINS=lyceumalabang.edu.ph,itmlyceumalabang.onmicrosoft.com,gmail.com,outlook.com`. Document secret generation (`openssl rand -hex 32` or one-off PHP file).
- **D-10:** cPanel deploy checklist (docroot already exists): uploads, `.env` keys filled, `storage/logs` writable (755), no `config:cache` change required (`config/mail-relay.php` added to the cached config set — run `php artisan config:clear` then `config:cache` if the host uses it, since cPanel has no terminal for artisan; provide File-Manager-safe instr to avoid cache-stale secrets), `.htaccess` Authorization rule already in place.
- **D-11:** Feature test `tests/Feature/Api/LocalEmailTest.php` — 401 (secret mismatch / missing), 422 (domain not allowed, html oversize, bad email), 429 under a stubbed throttle (or skip with test-only fast-forward comment), 200 success with `Mail::fake()` asserting `SentMessage` and check `message_id` nullability. Mock the mailer (do NOT send real mail from tests; use `Mail::fake` or `array` mailer).
- **D-12:** Test procedure document (curl happy path, bad-secret, off-domain, oversize, throttled) + confirmation steps for the cPanel mail log showing HAM not quarantine ( ACC-11 ).

---

## Glossary

- **RELAY_SECRET** — 32+ random bytes (64 hex) shared only between the auth host and ACES server-side code; presented as `Authorization: Bearer <secret>`.
- **HAM** — mail delivered to inbox, not quarantined.
- **Quarantine rule `require_local_sender_domain_global`** — the blocking that drops external-HELO submissions of a local From domain; not present for in-host submission.
- **MAILER message id** — `Message-ID` header emitted by the SMTP server, surfaced via Laravel's `SentMessage::getMessageId()`.

## References

- Root `AGENTS.md` (spec-first rule), `principles.md` (SDD+TDD), `platform.md` (cPanel gotchas: `.htaccess` Authorization forward).
- Auth assembly `AGENTS.md` §1.8 (no breaking changes), `CPANEL-DEPLOYMENT-NOTES.md` (deploy), `environment.md` (MAIL_*).
- Platform service `services/mail/README.md` (delivery semantics — stateless, synchronous by default).
- Vercel-side ACES integration: out of scope for this spec; consumes HTTP 200/429/502.

## Status history

- 2026-10-16 — v0.1 Draft; awaiting user "Final" before any code.<|end|><|start|>startup<|channel|>xml<|message|><rfl:parameter name="filePath">
D:\loa\loa-apache-server-apps\assemblies\loa-auth-platform\mail-relay-endpoint.md