# Queue Infrastructure Spec

**Version:** 2.0
**Status:** Final
**Date:** 2026-09-11
**Supersedes:** v1.0 (2026-09-10) — replaced database queue + cron worker with sync driver.

---

## 1. Purpose

Deliver reliable certificate email sending without any external cron job or queue worker. Emails are sent synchronously during the HTTP request.

Goals:
- No cPanel cron required.
- Safe retry on refresh / network drop / browser close / cancel — re-sending the same attendee batch does not get blocked by "already exists".
- Only mark an attendee as "issued" after the email is confirmed sent, so unsent recipients remain retryable.
- Frontend stays responsive for large batches via chunking, progress, cancel, and a cap.

---

## 2. Background

v1.0 used `MAIL::to()->queue()` + `QUEUE_CONNECTION=database` + `php artisan queue:work` via cPanel cron. In practice:

- Cron is hard to set up and unreliable on cPanel.
- Without a running worker, queued jobs sit in `jobs` forever and emails never send.
- v1.0 marked `EventAttendee.certificate_id` immediately after `Certificate` creation, before email success — so a failed email left the attendee blocked on retry ("Active certificate already exists").

The Auth Platform (`loa-auth-platform`) avoids this by using `QUEUE_CONNECTION=sync` in `.env.cpanel`:

```
# cPanel has no queue worker -- use sync so Mail::queue() runs inline.
QUEUE_CONNECTION=sync
```

With `sync`, `Mail::to()->queue()` still works but executes inline in the request — no worker, no `jobs` polling.

This spec applies the same pattern to Cert Platform and fixes retry semantics.

---

## 3. Requirements

### 3.1 Environment — `QUEUE_CONNECTION=sync` on cPanel

**File:** `assemblies/loa-cert-platform/.env.cpanel` (create if absent)

```
QUEUE_CONNECTION=sync
```

Notes:
- `config/queue.php` and `config/mail.php` remain absent — Laravel 11 framework defaults are sufficient (same as v1.0 and same as Auth Platform).
- Local dev may keep `QUEUE_CONNECTION=database` + manual `php artisan queue:work` if desired; production cPanel uses `sync`.
- Existing `database/migrations/2026_09_10_000001_create_jobs_table.php` stays — harmless when driver is `sync`.

### 3.2 Backend — `set_time_limit(0)` for bulk operations

Bulk issuance may open one SMTP connection per recipient sequentially. PHP's default 30s limit will kill large batches. Add `set_time_limit(0)` at the top of each bulk entry point (matches Auth Platform `UserImportController`).

Affected methods:

| File | Method |
|------|--------|
| `app/Http/Controllers/EventController.php` | `bulkIssue()` and `issueCompleted()` — or inside `issueCertificates()` |
| `app/Http/Controllers/CertificateController.php` | `bulk()` |
| `app/Http/Controllers/CertificateController.php` | `store()` — single issue, for consistency |

### 3.3 Backend — retry-safe issuance (the core fix)

**Problem in current code** (`EventController::issueCertificates()` ~649 and `CertificateController::bulk()` ~385):

```
create Certificate
update EventAttendee.certificate_id   ← too early
audit + pdf
Mail::to()->queue(...)
CertificateEmailModel::create(status=sent/failed)
```

If email fails or the process dies after the attendee update, retry is blocked: the duplicate check `Certificate where event_id+email and revoked_at is null` finds the row and returns "already exists", even though the email never sent and the attendee is now stuck.

**Required change:**

1. **Defer the attendee link** — `EventAttendee.update(['certificate_id','certificate_number'])` (and `firstOrCreate` + `update` in `CertificateController::bulk`) must happen **only after** a successful email send, inside the `status='sent'` branch.
2. **Duplicate check considers email status** — only skip creation when a certificate already has a `certificate_emails` row with `status='sent'`:

```php
$existing = Certificate::where('event_id', $event->id)
    ->where('recipient_email', $attendee->email)
    ->whereNull('revoked_at')
    ->whereHas('emails', fn ($q) => $q->where('status', 'sent'))
    ->exists();
```

This makes `POST /api/v1/events/{id}/bulk-issue` idempotent for retry: re-sending the same `attendee_ids` skips already-emailed recipients and retries unsent/failed ones.

Notes:
- `Certificate` row is still created before the email (needed for `certificate_id` in `certificate_emails` and for PDF). That is fine — the retry check is on email status, not on certificate existence.
- On email failure (`catch`), `CertificateEmailModel::create(status='failed')` remains; attendee is NOT linked.
- On email success, create `status='sent'` row, then link attendee. `$emailed++` and `$emailSent=true` as today.
- `app/Mail/CertificateEmail.php` and all `Mail::to()->queue(new CertificateEmail(...))` call sites stay unchanged — with `sync` driver they run inline.
- `CertificateEmail` model relation `Certificate::emails()` must exist (already present).

### 3.3.1 Error isolation — single failure must not abort the batch

- Each attendee is processed inside its own `try/catch`. A failure for one attendee (duplicate, DB error, PDF error, SMTP error) **must not terminate** the loop or the chunk. The loop records the per-attendee `error` and `continue`s to the next attendee.
- A chunk `POST /api/v1/events/{id}/bulk-issue` always returns `200` with a `results[]` array, even when some attendees fail. HTTP `5xx` is only for whole-request failures (auth, validation, event not found).
- Per-attendee result shape (extends current `IssueResult`):

```json
{
  "name": "Juan Dela Cruz",
  "email": "juan@example.com",
  "success": true,
  "emailed": true,
  "skipped": false,
  "certNumber": "CERT-2026-0042",
  "error": null
}
```

| `success` | `emailed` | `skipped` | `error` | Meaning |
|-----------|-----------|-----------|---------|---------|
| true | true | false | null | Issued and emailed (attendee linked) |
| false | false | true | "Already issued — skipped" | Already has a certificate with `certificate_emails.status='sent'` — not retried |
| false | false | false | "SMTP timeout" / "Active certificate already exists" / other | Not issued or email failed — retryable. If `Certificate` was created but email failed, `certificate_emails.status='failed'` exists; attendee NOT linked. |
| true | false | false | null | (Should not occur with `send_email=true` after this spec — kept for `send_email=false` compatibility) |

- Backend must set `skipped=true` explicitly when the `whereHas('emails', status='sent')` duplicate check matches, with `error="Already issued — skipped"`. Current code's generic "Active certificate already exists" is replaced for this case so the frontend can categorize correctly.
- Counters in the response: `issued` = count of `success && emailed`, `emailed` = same, `skipped` = count of `skipped`, `failed` = remainder. Frontend derives `failed` from `results` if not returned.

### 3.4 Frontend — chunked bulk issue with progress, cancel, cap

**File:** `e-cert/src/app/(dashboard)/events/[id]/components/attendees-tab.tsx`

Current behavior: one `POST /api/events/{id}/bulk-issue` with all `attendee_ids`, static fullscreen "Issuing certificates..." overlay, no progress, no cancel.

Required behavior:

- `BATCH_CHUNK = 25` — attendees per request (tunable constant at top of file).
- `MAX_BATCH = 200` — cap with first-200 behavior (see §3.5). If `selectedAttendeeIds.length > MAX_BATCH`, confirmation dialog shows: "You've selected N attendees. Only the first 200 will be issued in this batch (equivalent to Select All when not choosing meticulously). Remaining N−200 can be issued in the next batch." On confirm, frontend issues `selectedAttendeeIds.slice(0, 200)` chunked into 25s. Backend still rejects any single `POST` with `attendee_ids.length > 200` with `422` as safety net.
- `handleIssueSelected()` splits `selectedAttendeeIds` into chunks of `BATCH_CHUNK`, sends each chunk sequentially with `authFetch('/api/events/{id}/bulk-issue', { attendee_ids: chunk, send_email: true })`.
- Each chunk uses its own `AbortController` with 60s timeout.
- Progress state: `{ current, total, processed, totalAttendees }` drives a progress bar in the loading overlay: "Issuing batch 3/5 — 75 of 125 processed".
- Cancel button aborts current chunk's `AbortController` and stops remaining chunks; partial `IssueSummary` from completed chunks is still shown.
- Results from all completed chunks are merged into one `IssueSummary` (`issued`, `emailed`, `skipped`, `failed`, `results[]`) and rendered in the existing summary banner + `downloadCsv()`. The banner must list four groups so the user can audit the outcome:
  1. **Emailed** — `success && emailed` (green)
  2. **Skipped — already issued** — `skipped` (amber/grey) with "Already issued — skipped"
  3. **Failed — not emailed** — `!success && !skipped` with per-row `error` (red)
  4. **Issued without email** — only when `send_email=false`
  Each group shows `email — error` where applicable; counts are shown in the header "X emailed, Y skipped, Z failed".
- `downloadCsv()` header becomes `Name,Email,Issued,Emailed,Skipped,Error` and includes the `skipped` column.
- Chunk-level failure (network drop, 5xx, `AbortController` timeout, 401/422) **does not discard prior chunks**. Completed chunks' `results[]` are kept and shown immediately. The failed chunk's attendees are appended as `failed` entries with `error` set to the chunk error (e.g., "Network error — not sent, retryable" or "Request timed out — not sent"). Remaining unstarted chunks are not sent after a non-cancel failure unless the user clicks "Retry failed" / re-sends the same batch. Cancel via button aborts the in-flight chunk and stops remaining chunks; partial results remain visible and CSV-downloadable.
- A single attendee failure inside a chunk never aborts that chunk — backend returns the per-attendee `results[]` for the whole chunk (see §3.3.1). Frontend shows the chunk as completed with mixed success/failed rows.
- Toast after all chunks: `emailed`/`skipped`/`failed` breakdown; `toast.warning` if any `failed`, otherwise `toast.success`.

No new API endpoint required — existing `POST /api/v1/events/{id}/bulk-issue` is reused per chunk.

### 3.5 Guard rails — quick-fail at 3000 scale (no Polly retry)

3000 recipients in one operation is a bottleneck: SMTP (0.5–2s per mail) + certificate `INSERT` + PDF generation, all inside the HTTP request with `QUEUE_CONNECTION=sync`. Guard rails are quick-fail, not retry:

- **Cap `MAX_BATCH = 200` with first-200 frontend behavior.** Frontend: if selection is N > 200 (including Select All), only `slice(0, 200)` is issued in this operation; dialog states "First 200 of N will be issued — remaining N−200 next batch." Backend: any single `POST /api/v1/events/{id}/bulk-issue` with `attendee_ids.length > 200` still returns `422 { message: "Batch too large — max 200 per request. Split into smaller batches." }` as safety net (frontend never hits it because it slices + chunks into 25s). 3000 therefore requires 15 operations of 200 — intentional. This keeps any single flow under `200 × (DB+PDF+SMTP)` and avoids PHP/proxy/memory blow-ups.
- **Chunking `BATCH_CHUNK = 25` is the unit of progress and timeout.** Each `POST` processes exactly 25 attendees: 25 SMTP round-trips ≈ 12–50s, fits inside the `AbortController` 60s per-chunk timeout and typical Apache/PHP-FPM 60–120s limits. Larger chunks risk timeout; smaller chunks increase request overhead. 25 is the tuned default.
- **Per-attendee isolation — quick fail, continue.** No global DB transaction across the batch. Each attendee is its own `try/catch` (see §3.3.1). Any single failure (SMTP timeout, DB error, PDF error) records `results[]` entry with `error` and `skipped=false`, then `continue`s. No Polly / exponential backoff / automatic retry — failed rows stay `status='failed'` (or no row) and are surfaced in the Failed group for manual retry.
- **Per-email quick fail.** Rely on SMTP/transport timeout (Laravel `mail.mailers.smtp.timeout`, default ~10s). If `Mail::to()->queue()` throws (sync driver), catch immediately, log `certificate_emails.status='failed'` with `error_message`, do not retry in-process. Do not add `ShouldQueue` retries or `failed_jobs` retries — they require a worker.
- **Per-chunk quick fail.** Frontend `AbortController` 60s: if a chunk exceeds 60s (slow SMTP/DB), abort that chunk, append its 25 attendees as `failed` with error "Chunk timed out — not sent, retryable", keep prior chunks' results, stop remaining chunks. User retries the failed attendee subset.
- **No background retry, no `jobs` polling.** With `sync`, there is no queue to drain — retries are explicit user actions (re-send same `attendee_ids` or "Retry failed" subset). This avoids hidden bottleneck of 3000 jobs sitting in `jobs`.
- **Estimated time hint.** Before starting, frontend shows estimate: `ceil(total / 25) × ~15s` (e.g., 200 ≈ 8 chunks ≈ ~2 min; 3000 ≈ 15×200 ≈ ~30 min total operator time across operations). This sets expectation and encourages splitting large events.
- **DB persist pressure.** `Certificate` + `CertificateEmailModel` + `EventAttendee` update per attendee is sequential. No bulk `INSERT` optimization in this spec — keep per-row isolation for retry correctness. If profiling shows DB as bottleneck at 3000, follow-up is indexed `whereHas` + queued chunk outside request, not Polly inside request.

---

## 4. Scope

In scope:
- `.env.cpanel` `QUEUE_CONNECTION=sync`
- `set_time_limit(0)` in bulk entry points
- Deferred attendee link + email-status-aware duplicate check
- Frontend chunking / progress / cancel / cap in `attendees-tab.tsx`
- Quick-fail guard rails for 3000 scale (§3.5): hard cap 200 (422), chunk 25, per-attendee isolation, no Polly retry

Out of scope:
- `config/queue.php`, `config/mail.php` (framework defaults)
- Custom `app/Jobs/` classes; `ShouldQueue` on `CertificateEmail` (not needed with `sync`)
- New backend endpoint for chunking (reuse existing `bulk-issue`)
- `jobs` table removal (keep harmless)

---

## 5. Deployment

1. Set `QUEUE_CONNECTION=sync` in cPanel `.env` (or `.env.cpanel` dist).
2. Remove/disable any `queue:work` cron if present (optional — harmless with `sync`).
3. Deploy backend + frontend.
4. Smoke test: bulk issue 3 attendees → each receives email, `certificate_emails.status='sent'`, attendee shows certificate.
5. Retry test: kill/cancel mid-batch → re-send same `attendee_ids` → only unsent recipients get emailed, no "already exists" block.

## 6. Rollback

- Set `QUEUE_CONNECTION=database` and restore cron `* * * * * cd /home/loa/cert-platform && php artisan queue:work --sleep=3 --tries=3 --max-time=3600 >> /dev/null 2>&1`.
- Attendee link deferral and duplicate check change are safe to keep with either driver.

---

## 7. History

| Version | Date | Change |
|---------|------|--------|
| 1.0 | 2026-09-10 | Database queue + cron worker |
| 2.0 | 2026-09-11 | Sync driver, no cron, deferred attendee link, chunked frontend, quick-fail guard rails, first-200 cap — Final |
