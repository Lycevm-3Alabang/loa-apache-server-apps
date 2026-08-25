# LOA Auth Platform - cPanel Deployment Notes (2026-08-25)

Field notes from deploying `loa-auth-platform-dist.zip` to shared cPanel hosting at
`auth.lyceumalabang.edu.ph` (account `lyceumalabang`, **no SSH/terminal available**).

Complements [DEPLOY.md](DEPLOY.md). Everything below was hit and resolved live.

---

## 1. Environment

| Item | Value |
|---|---|
| Host | cPanel shared hosting, Apache + PHP (MultiPHP) |
| Domain | `https://auth.lyceumalabang.edu.ph` |
| Home dir | `/home/lyceumalabang/auth.lyceumalabang.edu.ph` |
| Docroot | project `/public` folder |
| Database | MySQL `lyceumalabang_auth_db` (21 tables), user `lyceumalabang_auth_admin` |
| Tooling | cPanel File Manager + phpMyAdmin only (no terminal) |

---

## 2. Issues encountered, root causes, fixes

### 2.1 Unzip "checkdir error: Permission denied" (partial extraction)
**Symptom:** unzip created some folders (`app/Mail`, `app/Models`, ...) but failed on others
(`app/Http/Controllers`, `app/Http/Middleware`, `resources/views/admin/*`,
`storage/framework/{cache,sessions,views}`).
**Cause:** pre-existing directories with restrictive perms / wrong owner blocked creation.
**Impact:** fatal - controllers/middleware/storage dirs missing.
**Fix (no terminal):**
1. Delete all partially-extracted contents via File Manager.
2. If delete is denied (web-server-owned files), upload a one-shot PHP script that recursively
   `chmod 0755` dirs / `0644` files, run it from the browser, then delete it.
3. Re-upload zip -> right-click -> Extract.
4. Verify critical dirs exist: `app/Http/Controllers`, `app/Http/Middleware`,
   `resources/views/admin`, `vendor/composer`.

> Lesson: after any failed extraction, verify folder presence before debugging anything else.

### 2.2 Generic Apache 500 on first load
Layered causes: missing `.env`, docroot not on `/public`.
Fix: point subdomain Document Root to `/public`; create `.env`
(the dist zip intentionally ships without one). Keep MultiPHP >= 8.2.

### 2.3 "Unsupported cipher or incorrect key length"
Cause: empty `APP_KEY`, and no artisan for `key:generate`.
Fix pattern used all session - temporary PHP file in `public/`:

```php
<?php echo 'base64:'.base64_encode(random_bytes(32));
```

Visit URL, copy value into `.env`, DELETE THE FILE IMMEDIATELY.

### 2.4 "Cannot assign null to property App\Services\JWTService::$secret" (500 on API login)
Cause: `JWT_SECRET` missing from `.env`. `config('jwt.secret')` returns null and the typed
property assignment throws (app/Services/JWTService.php:14).
Also pre-fixed proactively: `ENCRYPTION_KEY` and `TENANT_SLUG=loa-e-cert`.

### 2.5 phpMyAdmin import error #3780 (FK incompatible)
Symptom: `Referencing column 'group_id' and referenced column 'id' ... are incompatible`
on `CREATE TABLE group_claims`.
Cause: `group_claims` is created (line ~72) BEFORE the fresh DROP+CREATE of `user_groups`
(line ~300). A stale `user_groups` from a previous import attempt with an older column
definition satisfied the FK reference and failed type-compatibility.
Fix: rebuilt installer `database/sql/cpanel-auth-db-install-fixed.sql` which purges ALL
21 tables up-front (right after FOREIGN_KEY_CHECKS=0) before any CREATE runs.
Re-runnable: yes, drops everything each run (wipes data).

### 2.6 API login 401 "Invalid credentials"
Sequence of elimination:
1. Seeded bcrypt verified OK via server-side `password_verify()` -> hash/data fine.
2. Laravel-context probe revealed the real cause: **DB credentials** -
   `.env` had wrong DB user/password (`SQLSTATE[HY000] [1045] Access denied`), so the
   users lookup failed silently into the generic 401.
Fix: cPanel MySQL Databases -> reset user password, ensure ALL PRIVILEGES on target DB,
mirror exact names in `.env`. Verified with helper script printing `DB_CONNECT: OK`,
`Tables: 21`.

> Lesson: 401 "Invalid credentials" can mean "cannot reach database". Check the log.

### 2.7 Web /login 500 while API works: hex2bin() even-length error
Cause: `.env` contained literal placeholder `ENCRYPTION_KEY=ENTER_ENCRYPTION_KEY_HERE`;
`EncryptionService::decodeKey()` calls `hex2bin()` on it (line 122) and crashes during
container build of `WebAuthController`. API login never touches this service, hence split
behaviour.
Fix: real 64-hex-char key in `.env`.

### 2.8 /api/v1/auth/me 401 "Invalid or expired token"
Not an app bug: the Bearer header had been copy-pasted together with JSON remnants
(`...TE4E", "refresh_token": "eyJ...`). Only the access_token belongs in the header;
refresh_token is only for POST /auth/refresh body.

### 2.9 Suspected /login -> /sso/login redirect
Verified NOT in code (controller, routes, middleware, views, .htaccess all clean).
Attributed to browser-cached 302s accumulated during the broken states; incognito /
hard refresh resolves. If it recurs, capture address-bar before/after URLs.

---

## 3. Final production `.env` reference

> Canonical copy lives in [.env.example](.env.example) (passwords blanked, keys included).

```env
APP_NAME="LOA Auth Platform"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://auth.lyceumalabang.edu.ph
APP_KEY=base64:<generated>            # required, 32-byte base64
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<exact cPanel name>       # e.g. lyceuma_auth
DB_USERNAME=<exact cPanel user>       # cPanel prefixes/truncates names
DB_PASSWORD=<set in cPanel>

SESSION_DRIVER=file
CACHE_STORE=file

JWT_SECRET=<64 hex chars>             # missing = TypeError on any login
JWT_ACCESS_TTL=15
JWT_REFRESH_TTL=10080

ENCRYPTION_KEY=<64 hex chars>         # placeholder text = hex2bin crash on /login
TENANT_SLUG=loa-e-cert
AUTH_ADMIN_GROUP=loa-auth-admin

CORS_ALLOWED_ORIGINS=https://e-cert.vercel.app

MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=587
MAIL_USERNAME=no-reply@lyceumalabang.edu.ph
MAIL_PASSWORD=<mailbox password>
MAIL_FROM_ADDRESS=no-reply@lyceumalabang.edu.ph
MAIL_FROM_NAME="LOA Auth"
```

Gotchas: no spaces around `=`; DB names must match cPanel exactly (prefix!);
`.env` changes take effect per-request (no restart needed on shared hosting).

---

## 4. Reusable no-terminal patterns

**Log triage:** `storage/logs/laravel.log`, read the NEWEST block at the bottom.
Every issue above was identified from one log line.

**Bootstrap-context probe** (`public/pw.php` style - delete after use):

```php
<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app = require_once dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    echo 'DB: '.config('database.connections.mysql.database')."\n";
    echo 'Tables: '.count(\Illuminate\Support\Facades\DB::select('SHOW TABLES'))."\n";
} catch (\Throwable $e) { echo 'ERR: '.substr($e->getMessage(),0,200); }
```

**Key generation without artisan:** random_bytes(32) -> base64 for APP_KEY,
random_bytes(32) -> bin2hex for JWT_SECRET / ENCRYPTION_KEY.

**Password reset without artisan:** `password_hash('...', PASSWORD_BCRYPT)` via temp PHP,
then UPDATE users + TRUNCATE login_attempts via phpMyAdmin SQL tab.

---

## 5. Outstanding items after go-live

- [ ] DELETE helper scripts (`pw.php` etc.) from public/ - security critical
- [ ] Change seeded admin password (`admin@lyceumalabang.edu.ph` / `Admin123!`)
- [ ] UPDATE tenants row off localhost URLs:
      app_url / redirect_origins -> production cert-app URL
      (seeded values are localhost:9001 and localhost:3000)
- [ ] `migrations` table is EMPTY by design of the dump - never run
      `php artisan migrate` against this DB or it will try to recreate all tables;
      backfill INSERT can be generated if artisan ever becomes available
- [ ] Confirm CORS_ALLOWED_ORIGINS matches the real cert-app origin
- [ ] storage/ and bootstrap/cache/ at 775
