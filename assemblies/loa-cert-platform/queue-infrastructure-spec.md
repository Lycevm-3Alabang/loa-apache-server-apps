# Queue Infrastructure Spec

**Version:** 1.0
**Status:** Final
**Date:** 2026-09-10

---

## 1. Purpose

Add database-backed queue infrastructure to the Cert Platform to enable asynchronous email delivery for bulk certificate operations (3000+ recipients).

## 2. Background

The Cert Platform sends certificate emails via `Mail::to()->queue(new CertificateEmail(...))`. Currently, no queue worker processes these jobs. Emails either sit in the `jobs` table forever or fail silently.

The Auth Platform (`loa-auth-platform`) already uses this pattern successfully:
- `QUEUE_CONNECTION=database` in `.env`
- `jobs` table migration present
- Queue worker running via `php artisan queue:work`

## 3. Requirements

### 3.1 Environment Configuration

Add to `cert-platform/.env`:

```
QUEUE_CONNECTION=database
```

This tells Laravel to use the `jobs` table for queue storage. No `config/queue.php` file is needed — Laravel 11 framework defaults handle this.

### 3.2 Jobs Table Migration

Create `database/migrations/2026_09_10_000001_create_jobs_table.php`:

```php
Schema::create('jobs', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->string('queue')->index();
    $table->longText('payload');
    $table->unsignedTinyInteger('attempts');
    $table->unsignedInteger('reserved_at')->nullable();
    $table->unsignedInteger('available_at');
    $table->unsignedInteger('created_at');
});
```

This is the standard Laravel queue table schema, identical to the Auth Platform's migration.

### 3.3 Queue Worker (cPanel Deployment)

On cPanel, the queue worker runs via a cron job:

```
* * * * * cd /home/loa/cert-platform && php artisan queue:work --sleep=3 --tries=3 --max-time=3600 >> /dev/null 2>&1
```

**Behavior:**
- `--sleep=3`: Poll every 3 seconds when idle
- `--tries=3`: Retry failed jobs up to 3 times
- `--max-time=3600`: Restart worker every hour (prevents memory leaks)

### 3.4 Mail Dispatch

All `Mail::to()->queue()` calls remain unchanged. The queue worker picks them up within seconds.

**Affected methods:**
- `CertificateController::store()` (line 316)
- `CertificateController::bulk()` (line 473)
- `CertificateController::email()` (line 1008)
- `EventController::issueCertificates()` (line 722)
- `EventController::reissue()` (line 901)

### 3.5 Error Handling

- Failed jobs are stored in the `failed_jobs` table (Laravel default)
- The existing `try/catch` blocks in controllers record `status: 'failed'` to `certificate_emails` table
- Queue worker retries up to 3 times before marking as failed

## 4. Scope

**In scope:**
- Jobs table migration
- `.env` configuration
- Queue worker cron job documentation

**Out of scope:**
- `config/queue.php` (framework defaults are sufficient)
- `config/mail.php` (framework defaults are sufficient)
- Custom job classes (existing `CertificateEmail` mailable is sufficient)
- Failed job retry UI (admin can query `failed_jobs` table directly)

## 5. Deployment Steps

1. Run migration: `php artisan migrate`
2. Add `QUEUE_CONNECTION=database` to `.env`
3. Set up cron job on cPanel
4. Monitor `certificate_emails` table for `status: 'failed'` entries

## 6. Rollback

1. Remove cron job
2. Set `QUEUE_CONNECTION=sync` in `.env` (emails send synchronously)
3. `jobs` table can remain (harmless)
