# Certificate Platform Rules — Template Locking, Visibility, File Storage & Issuance Invariants

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-cert-platform`)
**Audience:** Engineers, AI Development Agents

> **Scope.** This spec consolidates four business rules that span the Certificate, Template, and Event aggregates: (1) template immutability when referenced, (2) certificate visibility/owner scoping, (3) certificate file storage, and (4) one-active-certificate-per-recipient-per-event invariant. Each section states the rule, current implementation status, the exact API contract (request/response/error), and identified gaps.

---

# 1. Template Immutability When Referenced

## 1.1 Rule

> A certificate template **must not be editable** once it is referenced by an Event (as `template_id` or `email_template_id`) or by an issued Certificate (as `template_id`).

Rationale: editing a locked template would silently change the visual layout of already-issued certificates and future certificates differently, breaking consistency.

## 1.2 Current Implementation

| Condition | Update (PATCH) | Delete |
|-----------|---------------|--------|
| Referenced by **Events** | **BLOCKED** — 409 | Soft-blocked — 409, override with `force=true` |
| Referenced by **Certificates** | Allowed (gap) | **HARD BLOCKED** — 409, no override |
| Not referenced anywhere | Allowed | Allowed |

**Lock is computed at runtime**, not stored in the database. Two private methods in `CertificateTemplateController` perform the check:

```php
// CertificateTemplateController.php:412-422
private function isTemplateLocked(CertificateTemplate $template): bool
{
    return Event::where('template_id', $template->id)
        ->orWhere('email_template_id', $template->id)
        ->exists();
}

private function isTemplateInUseByCertificate(CertificateTemplate $template): bool
{
    return Certificate::where('template_id', $template->id)->exists();
}
```

The API response includes computed `is_locked` and `locked_reason` fields (not database columns).

## 1.3 API Contract

### `PATCH /api/v1/templates/{id}` — Update Template

**Success 200:** updated template resource (includes `is_locked`, `locked_reason`).

**Error 409 — Locked:**
```json
{
  "status": "error",
  "message": "Template is locked and cannot be updated."
}
```

**Error 409 — Name conflict:**
```json
{
  "status": "error",
  "message": "Template name already exists for this organization."
}
```

### `DELETE /api/v1/templates/{id}` — Delete Template

**Body (optional):** `{ "force": true }`

**Error 409 — Certificate-referenced (hard block):**
```json
{
  "status": "error",
  "message": "Template is referenced by issued certificates and cannot be deleted."
}
```

**Error 409 — Event-referenced (soft block):**
```json
{
  "status": "error",
  "message": "Template is referenced by events. Use force=true to delete."
}
```

## 1.4 Identified Gaps

| Gap | Impact | Recommendation |
|-----|--------|----------------|
| **No lock check on certificate issuance** — `CertificateController::store()` validates `template_id` exists but does NOT check if the template is locked. A locked template can still be used to issue new certificates. | Low — locking is about preventing edits, not use. But if the intent is "freeze the template," issuance should also be gated. | Add `isTemplateLocked()` check in `CertificateController::store()` and return 409 if the caller explicitly passes a `template_id` that is locked. Event-inherited templates (`$event->template_id`) are exempt since the event already locked the reference. |
| **No lock check on visibility change** — A locked template's visibility can be changed by the owner or cert-admin. | Low — visibility is an access-control flag, not content. | Acceptable as-is. Visibility changes do not alter certificate content. |
| **`force=true` on delete leaves orphaned event references** — Database FK uses `ON DELETE SET NULL`, so events lose their `template_id` silently. | Medium — events lose their template assignment without user awareness. | Consider returning the affected event IDs in the force-delete response, or require explicit detachment first. |

---

# 2. Certificate Visibility & Owner Scoping

## 2.1 Rule

> Certificates are **private by default**. Only the certificate **recipient** (matched by email) and **cert-admin** users may view a certificate's details. Other authenticated staff see only the list entry (number, name, status) but not the full record.

Rationale: certificate data contains PII (recipient name, email) and should not be globally visible within the organization.

## 2.2 Current Implementation — IMPLEMENTED (2026-09-22 verification)

**Templates** have a full visibility system (`public`/`private` column, `scopeVisibleTo()`, `isVisibleTo()`, owner checks). **Certificates enforce owner scoping inline** (no `visibility` column — scoping derives from `recipient_email`, per §2.3 item 4).

| Component | Visibility field | Scope method | Owner check |
|-----------|-----------------|--------------|-------------|
| `CertificateTemplate` | `visibility` (`public`/`private`) | `scopeVisibleTo($sub, $groups)` | `isOwnedBy($sub)` via `created_by`/`updated_by` |
| `Certificate` | **NONE (by design — §2.3 item 4)** | **inline in `show`/`pdf`/`download`** | **inline: `recipient_email === jwt.email` or `cert-admin`** |

**Current behavior (verified 2026-09-22):**
- `GET /api/v1/certificates` — returns **all** certificates to any user with `read` permission. No filtering by caller identity (list view, per §2.3 item 3).
- `GET /api/v1/certificates/{id}` — owner check enforced (`CertificateController::show()`, 403 otherwise). Tested: recipient 200, non-recipient 403, admin 200 (`CertificateTest`).
- `GET /api/v1/certificates/{id}/pdf`, `/download` — same owner check enforced (403 before 410, so non-recipients cannot probe revoked/expired state). **Behavioral tests pending** — no feature test hits these paths yet.
- `GET /api/v1/me/certificates` — filters by `recipient_email === JWT.email`. Participant-scoped listing.

The `api-endpoints.md` spec (§5.4, §9.6) declares an **owner rule** for certificate detail/pdf/download endpoints (`jwt.email === certificate.recipient_email`), but this is **not enforced** in `CertificateController::show()`, `pdf()`, or `download()`.

## 2.3 Required Behavior (Spec)

### Access Matrix

| Caller | List (`GET /certificates`) | Detail (`GET /certificates/{id}`) | PDF/Download | Revoke/Delete |
|--------|---------------------------|-----------------------------------|-------------|---------------|
| **Recipient** (email matches) | Own only via `/me/certificates` | Allowed | Allowed | Not allowed |
| **cert-admin** | All certificates | Allowed | Allowed | Allowed |
| **cert-staff** (non-admin) | All certificates (list only) | **403** (not recipient, not admin) | **403** | Not allowed |
| **cert-user** (participant) | Own only via `/me/certificates` | Allowed (own only) | Allowed (own only) | Not allowed |

### Implementation Required

1. **`CertificateController::show()`** — add owner check:
   ```php
   if ($certificate->recipient_email !== $email && !in_array('cert-admin', $groups)) {
       return 403;
   }
   ```

2. **`CertificateController::pdf()` and `download()`** — same owner check.

3. **`CertificateController::index()`** — remains unscoped for admin/staff (list view shows all). The `/me/certificates` endpoint handles participant-scoped listing.

4. **No `visibility` column needed on certificates** — scoping is derived from `recipient_email` (not a stored flag), matching the template pattern where `created_by`/`updated_by` drive ownership.

## 2.4 API Contract

### `GET /api/v1/certificates/{id}` — Get Certificate

**Auth:** `read` + owner rule.

**Success 200:** certificate resource.

**Error 403 — Not recipient, not admin:**
```json
{
  "status": "error",
  "message": "You do not have access to this certificate."
}
```

**Error 404:** certificate not found (or not visible — same shape, no leaking).

### `GET /api/v1/certificates/{id}/pdf` — Stream PDF

**Auth:** `read` + owner rule.

**Error 403:** same as above.
**Error 410:** revoked or expired certificate (when not `view_all`).

---

# 3. Certificate File Storage & Generation Modes

## 3.1 Rule

> Certificates support two generation modes: **template** (system-generated from HTML/CSS template via DomPDF) and **file** (user-uploaded PDF). The `generation_mode` is stored in `event_attendees.metadata.generation_mode` and determines which PDF is used as the certificate. **Primary path: metadata-based serving.** Template-generated PDFs are rendered on-the-fly by DomPDF. Uploaded PDFs are decoded from `event_attendees.metadata.file_data` (base64). **Existing disk storage code is retained as fallback** — if metadata-based serving fails, the system falls back to the original file_path/disk approach. Certificates are always tied to an event-attendee; when the attendee is deleted, the certificate is deleted too.

## 3.2 Generation Modes

| Mode | Source | Primary serving (metadata-based) | Fallback (disk-based) |
|------|--------|----------------------------------|----------------------|
| `template` | DomPDF renders HTML from `certificate_templates` | `PdfService::streamCertificatePdf()` — renders on-the-fly | `file_path` on disk (if exists) |
| `file` | User-uploaded PDF (base64 in attendee metadata) | Decode `metadata.file_data` base64 → return as PDF response | `file_path` on disk (if exists) |

**Key principle:** Metadata-based serving is the primary path. Existing disk storage code is **deferred, not removed**. If metadata-based serving fails or returns nothing, the system falls back to the original `file_path`/disk approach. This allows safe revert if issues arise.

## 3.3 Storage Architecture

```
Primary (metadata-based):
  event_attendees.metadata.file_data:
      "JVBERi0x..."    ← file mode (base64, decoded on-the-fly)
  certificate_templates.html_content:
      <div>...</div>   ← template mode (rendered on-the-fly by DomPDF)

Fallback (disk-based, retained):
  storage/app/private/certificates/
      CERT-0001.pdf    ← template mode (if file_path set)
      CERT-0002.pdf    ← file mode (if file_path set)
```

| Mode | Primary serving | Fallback | Column used |
|------|----------------|----------|-------------|
| `template` | DomPDF renders at request time | Read from `file_path` on disk | `file_path` (retained) |
| `file` | Decode `metadata.file_data` at request time | Read from `file_path` on disk | `metadata.file_data` + `file_path` (retained) |
| Email (template) | `fromData(fn() => $pdf->output())` | `fromStorageDisk('local')` | — |
| Email (file) | `fromData(fn() => base64_decode($fileData))` | `fromStorageDisk('local')` | — |

## 3.4 Issuance Flow

### Step 1: CSV Import (Frontend → Backend)

Frontend sends base64 in attendee metadata:

```json
{
  "attendees": [
    {
      "name": "Maria Santos",
      "email": "maria@example.com",
      "metadata": {
        "generation_mode": "file",
        "file_data": "JVBERi0x...",
        "file_name": "cert-maria.pdf",
        "file_type": "application/pdf"
      }
    }
  ]
}
```

Backend stores metadata as JSONB in `event_attendees.metadata`. **No disk write at this stage.**

### Step 2: Certificate Issuance (`issueCompleted` → `issueCertificates`)

`EventController::issueCertificates()` iterates attendees and for each:

1. Checks `attendee->metadata['generation_mode']`
2. **If `file`:** creates certificate record. **Primary:** no disk write — `metadata.file_data` is the source of truth. **Fallback:** also writes to disk via existing code path (retained for safety).
3. **If `template`:** creates certificate record. **Primary:** no disk write — PDF rendered on-the-fly when needed. **Fallback:** also writes to disk via `PdfService::generateCertificatePdf()` (retained for safety).

### Step 3: Serving the PDF

**Primary path (metadata-based):**
- **Template mode:** `PdfService::streamCertificatePdf($certificate)` renders HTML from template → DomPDF → returns PDF binary
- **File mode:** loads attendee relation, reads `attendee->metadata['file_data']`, decodes base64 → returns PDF binary

**Fallback path (disk-based, if primary fails):**
- If `file_path` is set and file exists on disk → serve from disk
- If neither primary nor fallback works → return error

### Step 4: Email Delivery

**Primary path:**
- **Template mode:** render PDF in-memory → `Attachment::fromData(fn() => $pdf->output())`
- **File mode:** decode base64 → `Attachment::fromData(fn() => base64_decode($fileData))`

**Fallback path (if primary fails):**
- **Template mode:** `fromStorageDisk('local')` with `file_path`
- **File mode:** `fromStorageDisk('local')` with `file_path`

## 3.5 Serving Priority Logic

```
pdf() / download():
  1. Check generation_mode via attendee metadata
     → If 'file' and metadata.file_data exists: decode and serve
     → If 'template': call PdfService::streamCertificatePdf()
  2. Fallback: if file_path exists on disk → serve from disk
  3. Error: nothing to serve

email attachment():
  1. Check generation_mode via attendee metadata
     → If 'file' and metadata.file_data exists: fromData(decoded binary)
     → If 'template': render PDF in-memory, fromData(output)
  2. Fallback: if file_path exists on disk → fromStorageDisk('local')
  3. No attachment
```

## 3.6 API Contract

### `POST /api/v1/certificates/upload` — Upload Certificate File (standalone)

**Auth:** `write`

**Request:** `multipart/form-data`

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `certificate_number` | string | yes | Must exist in `certificates` table |
| `file` | file | yes | PDF only, max 10 MB |

**Success 200:**
```json
{
  "data": {
    "certificate_id": "uuid",
    "file_path": "certificates/CERT-0001.pdf"
  }
}
```

### `GET /api/v1/certificates/{id}/pdf` — Stream PDF

**Success 200:** raw binary `Content-Type: application/pdf`.
**Error 404:** certificate not found.
**Error 410:** revoked/expired.

### `GET /api/v1/certificates/{id}/download` — Download PDF

**Success 200:** raw binary with `Content-Disposition: attachment; filename="CERT-0001.pdf"`.

## 3.7 Identified Gaps (resolved)

| Gap | Status | Resolution |
|-----|--------|-----------|
| **`issueCertificates()` ignores `generation_mode`** | **Fixed** | Checks `metadata.generation_mode`. Both primary (metadata) and fallback (disk) paths implemented. |
| **`store()` and `bulk()` always generate template PDF** | **Fixed** | Checks `metadata.generation_mode`. Primary: metadata-based. Fallback: disk-based (retained). |
| **`file_data` LONGBLOB on certificates table** | **Removed** | No column needed. Uploaded PDFs live in `event_attendees.metadata.file_data`. |
| **`file_path` on certificates table** | **Retained as fallback** | Used only when metadata-based serving fails. |
| **`CertificateEmail` dual attachment strategy** | **Fixed** | Primary: `fromData()`. Fallback: `fromStorageDisk('local')`. |
| **`pdf()` and `download()` dual serving strategy** | **Fixed** | Primary: metadata-based. Fallback: disk-based. |
| **Preview page for uploaded certs** | **Fixed** | `AttendeeController::fileData()` serves from `metadata.file_data`. Frontend checks `metadata.generation_mode` instead of `file_path`. |
| **No file cleanup on certificate deletion** | **Retained** | `destroy()` still cleans up `file_path` from disk (fallback safety). |

## 3.8 Implementation Plan — Interface + Feature Flag

**Status:** Done — 246/246 tests passing (2026-09-18)

### Architecture: Strategy Pattern with Feature Flag

Two storage strategies behind a common interface, toggled by an environment variable:

```
CertificateStorage (interface)
├── DiskCertificateStorage      <-- old (CERT_USE_METADATA_SERVING=false)
└── MetadataCertificateStorage  <-- new (CERT_USE_METADATA_SERVING=true)
```

**Feature flag:** `CERT_USE_METADATA_SERVING=true` in `.env`
- `true` -> `MetadataCertificateStorage` (serves from `metadata.file_data`, no disk dependency)
- `false` -> `DiskCertificateStorage` (original behavior, reads/writes from disk)

**Binding:** `AppServiceProvider` reads the env flag and binds `CertificateStorage` interface to the correct implementation.

**Dependency inversion:** Controllers depend only on the `CertificateStorage` interface, never on concrete implementations.

**Liskov substitution:** Both implementations satisfy the same contract. Swapping requires zero controller changes -- only the `.env` flag changes.

### Interface

```php
namespace App\Interfaces;

use App\Models\Certificate;
use Illuminate\Http\Response;

interface CertificateStorage
{
    public function store(Certificate $certificate, string $decodedPdf): void;
    public function delete(Certificate $certificate): void;
    public function pdf(Certificate $certificate): Response;
    public function download(Certificate $certificate): Response;
    public function emailAttachment(Certificate $certificate): ?string;
}
```

### DiskCertificateStorage (old behavior)

| Method | Behavior |
|--------|----------|
| `store()` | Write to `Storage::disk('local')`, set `file_path` |
| `delete()` | Remove from `Storage::disk('local')` via `file_path` |
| `pdf()` | `PdfService::streamCertificatePdf()` |
| `download()` | `PdfService::downloadCertificatePdf()` |
| `emailAttachment()` | Read from `Storage::disk('local')` via `file_path` |

### MetadataCertificateStorage (new behavior)

| Method | Behavior |
|--------|----------|
| `store()` | No-op (`metadata.file_data` is the source of truth) |
| `delete()` | No-op (no files on disk) |
| `pdf()` | File mode → decoded `metadata.file_data` binary inline; template mode → `PdfService::streamCertificatePdf()` on-the-fly |
| `download()` | Same as `pdf()` with `Content-Disposition: attachment`; template mode → `PdfService::downloadCertificatePdf()` |
| `emailAttachment()` | File mode → decoded binary; template mode → `null` (caller falls back to `PdfService` render) |

### Controller Changes

Controllers inject `CertificateStorage` interface instead of direct `PdfService` for PDF operations:

```php
public function __construct(
    private readonly CertificateStorage $certificateStorage,
    private readonly PdfService $pdfService,        // kept for template HTML rendering
    private readonly AuditLogger $auditLogger,
    // ...
) {}
```

- `pdf()` -> `$this->certificateStorage->pdf($certificate)`
- `download()` -> `$this->certificateStorage->download($certificate)`
- `destroy()` -> `$this->certificateStorage->delete($certificate)`
- Issuance paths -> `$this->certificateStorage->store($certificate, $decoded)`

### CertificateEmail Changes

`CertificateEmail` receives the raw PDF binary (already decoded) via `$fileData` param. The controller resolves which binary to pass using the interface:

```php
$pdfBinary = $this->certificateStorage->emailAttachment($certificate);
Mail::to(...)->send(new CertificateEmail(
    // ...
    fileData: $pdfBinary,
));
```

`CertificateEmail::attachments()` always uses `fromData()` when `$fileData` is set, falls back to `fromStorageDisk()` otherwise.

### AppServiceProvider Binding

```php
public function register()
{
    $this->app->bind(CertificateStorage::class, function ($app) {
        $useMetadata = $app['config']->get('cert-platform.use_metadata_serving', true);

        if ($useMetadata) {
            return $app->make(MetadataCertificateStorage::class);
        }

        return $app->make(DiskCertificateStorage::class);
    });
}
```

> **Gotcha (2026-09-18):** `AppServiceProvider` is NOT auto-discovered — it must be registered explicitly in `bootstrap/app.php`, otherwise the container throws `Target [App\Interfaces\CertificateStorage] is not instantiable`:
>
> ```php
> ->withProviders([
>     \App\Providers\AppServiceProvider::class,
> ])
> ```

### Config

```php
// config/cert-platform.php
'use_metadata_serving' => env('CERT_USE_METADATA_SERVING', true),
```

### Revert strategy

Set `CERT_USE_METADATA_SERVING=false` in `.env` -> `DiskCertificateStorage` becomes active -> zero code changes needed.

### Files to create/modify

| File | Action |
|------|--------|
| `app/Interfaces/CertificateStorage.php` | **Create** -- interface definition |
| `app/Services/DiskCertificateStorage.php` | **Create** -- old behavior implementation |
| `app/Services/MetadataCertificateStorage.php` | **Create** -- new behavior implementation |
| `app/Providers/AppServiceProvider.php` | **Modify** -- bind interface based on env flag |
| `config/cert-platform.php` | **Modify** -- add `use_metadata_serving` key |
| `.env` | **Modify** -- add `CERT_USE_METADATA_SERVING=true` |
| `app/Http/Controllers/CertificateController.php` | **Modify** -- inject interface, use for pdf/download/destroy/store |
| `app/Http/Controllers/EventController.php` | **Modify** -- inject interface, use for issuance paths |
| `app/Mail/CertificateEmail.php` | **Modify** -- keep `$fileData` param, use `fromData()` primary |
| `app/Http/Controllers/AttendeeController.php` | **Modify** -- serve from `metadata.file_data` |
| `app/Models/Certificate.php` | **Modify** -- remove `file_data` from `$fillable` |
| `database/migrations/` | **Create** -- drop `file_data` column |
| `e-cert/src/app/view/[id]/page.tsx` | **Modify** -- check `metadata.generation_mode` |

---

# 4. One Active Certificate Per Recipient Per Event

## 4.1 Rule

> An organization may issue **at most one active (non-revoked) certificate** per `(event_id, recipient_email)` pair. Issuing a duplicate returns `409 Conflict`.

Rationale: prevents accidental double-issuance. Reissuance is handled by revoking the old certificate first (which nullifies the unique constraint).

## 4.2 Current Implementation

### Application Layer (soft check)

All three issuance paths perform the same check:

```php
$existing = Certificate::where('event_id', $eventId)
    ->where('recipient_email', $email)
    ->whereNull('revoked_at')
    ->exists();

if ($existing) {
    return 409; // or "Active certificate already exists" in bulk
}
```

| Issuance path | File | Lines |
|---------------|------|-------|
| Single issue (`POST /certificates`) | `CertificateController.php` | 247-259 |
| Bulk issue (`POST /certificates/bulk`) | `CertificateController.php` | 401-407 |
| Event bulk-issue (`POST /events/{id}/bulk-issue`) | `EventController.php` | 662-668 |
| Event issue-completed (`POST /events/{id}/issue-completed`) | `EventController.php` | delegates to `issueCertificates()` |

### Database Layer (hard constraint)

```php
// Migration: create_certificates_table
$table->string('number_active')->storedAs('IF(revoked_at IS NULL, certificate_number, NULL)');
$table->unique('number_active');
```

This prevents duplicate **certificate numbers** among active certificates globally. It does NOT enforce `(event_id, recipient_email)` uniqueness — that is application-only.

**Note:** The `api-endpoints.md` spec (§7.2) declares `UNIQUE(event_id, recipient_email)` on the certificates table, but the actual migration uses the `number_active` generated-column trick instead. These are different constraints.

## 4.3 API Contract

### `POST /api/v1/certificates` — Issue Certificate

**Error 409 — Duplicate active certificate:**
```json
{
  "status": "error",
  "message": "An active certificate already exists for this event and email."
}
```

### `POST /api/v1/certificates/bulk` — Bulk Issue

**Per-recipient result in response:**
```json
{
  "data": {
    "issued": 0,
    "emailed": 0,
    "results": [
      {
        "name": "Maria Santos",
        "email": "maria@example.com",
        "success": false,
        "emailed": false,
        "certNumber": null,
        "error": "Active certificate already exists"
      }
    ]
  }
}
```

### `POST /api/v1/events/{id}/bulk-issue` — Event Bulk Issue

Same result shape. Per-attendee `error: "Active certificate already exists"` when duplicate.

## 4.4 Reissuance Flow

Reissuance bypasses the duplicate check by revoking the existing certificate first:

1. `POST /api/v1/certificates/{id}/revoke` — sets `revoked_at` on the old certificate.
2. `POST /api/v1/certificates` — issues a new certificate (old one is now revoked, so `whereNull('revoked_at)` no longer matches).

Or via the combined endpoint:

- `POST /api/v1/certificates/{id}/reissue` — revokes old + creates new in a transaction.
- `POST /api/v1/events/{id}/reissue` — bulk revocation + reissuance for selected attendees.

## 4.5 Identified Gaps

| Gap | Impact | Recommendation |
|-----|--------|----------------|
| **No DB-level unique constraint on `(event_id, recipient_email)`** — the spec declares it but the migration uses `number_active` instead. The app-level check is the only guard. | Medium — a race condition in concurrent issuance could bypass the app check (though atomic number generation mitigates this). | Add a composite unique index on `(event_id, recipient_email, is_active)` using a generated column: `IF(revoked_at IS NULL, CONCAT(event_id, recipient_email), NULL)` with a unique index. |
| **Bulk issue does not stop on first duplicate** — it continues processing all recipients and reports per-item errors. | Low — this is by design (§3.8 idempotency semantics). | Acceptable. The per-item error reporting is correct. |

---

# 5. Summary of All Gaps

| # | Area | Gap | Severity | Status | Resolution |
|---|------|-----|----------|--------|-----------|
| 1 | Template Locking | No lock check when issuing certificates with a locked `template_id` | Low | Open | — |
| 2 | Template Locking | `force=true` delete orphans event `template_id` silently | Medium | Open | — |
| 3 | Certificate Visibility | `CertificateController::show()` had no owner check | **High** | **Fixed** | Owner check enforced + tested (recipient 200 / non-recipient 403 / admin 200) |
| 4 | Certificate Visibility | `pdf()` and `download()` had no owner check | **High** | **Fixed in code, tests pending** | Owner check enforced (403 before 410); feature tests for pdf/download owner matrix still to add |
| 5 | File Storage | `issueCertificates()` ignores `generation_mode: "file"` | **High** | **Fixed** | Checks `metadata.generation_mode`. Primary: metadata-based. Fallback: disk-based (retained). |
| 6 | File Storage | `store()` and `bulk()` always generate template PDF | Medium | **Fixed** | Checks `metadata.generation_mode`. Primary: metadata-based. Fallback: disk-based (retained). |
| 7 | File Storage | No file cleanup on certificate deletion | Low | **Retained** | `destroy()` still cleans up `file_path` from disk (fallback safety). |
| 8 | Issuance | No DB constraint on `(event_id, recipient_email)` for active certs | Medium | Open | — |
| 9 | File Storage | `file_data` LONGBLOB on certificates table | Medium | **Removed** | No column needed. Uploaded PDFs live in `event_attendees.metadata.file_data`. |
| 10 | File Storage | Flat folder structure — no event grouping | Low | **Deferred** | Disk fallback retains original path. Metadata-based serving doesn't use folders. |
| 11 | Preview | Preview page for uploaded certs relies on disk-based file-data endpoint | Medium | **Fixed** | `AttendeeController::fileData()` serves from `metadata.file_data`. Frontend checks `metadata.generation_mode`. |

---

# 6. Migration Path

For gaps requiring implementation:

1. **Gap 3+4 (certificate owner check)** — **Done in code (verified 2026-09-22).** `show()`, `pdf()`, `download()` enforce the owner check. `show()` covered by tests; pdf/download owner-matrix tests still to add. No migration needed.
2. **Gap 8 (no duplicate active cert per event+email)** — **Resolved without migration.** MySQL 8.0 InnoDB cannot create a unique index on a generated column referencing a FK column. Constraint already enforced at the application layer in all 4 issuance paths: `store()` (line 258), `bulk()` (line 412), `issueCertificates()` (line 662), and `reissue()` (revokes old before creating new, transactional). No code change needed.
3. **Gap 5 (uploaded files ignored)** — **Fixed.** `issueCertificates()`, `store()`, and `bulk()` check `metadata['generation_mode']`. Primary path: metadata-based serving (no disk write). Fallback path: disk-based (existing code retained).
4. **Gap 6 (store/bulk template-only)** — **Fixed.** Both endpoints now support `file` mode via `metadata.generation_mode` check. Primary + fallback paths.
5. **Gap 9 (file_data LONGBLOB)** — **Removed.** New migration drops `file_data` column from `certificates`. Model updated. Uploaded PDFs served directly from `event_attendees.metadata.file_data`.
6. **Gap 10 (flat folder structure)** — **Deferred.** Disk fallback retains original path format. Metadata-based serving doesn't depend on folder structure.
7. **Gap 11 (preview page for uploaded certs)** — **Fixed.** `AttendeeController::fileData()` updated to serve from `metadata.file_data`. Frontend preview page updated to check `metadata.generation_mode` instead of `cert.file_path`.

### Revert strategy

If metadata-based serving causes issues:
1. Remove the metadata-based primary path code (the `if ($attendee->metadata['generation_mode'] === 'file')` blocks in controllers)
2. The fallback disk path becomes the active path again
3. No data loss — `file_path` and disk files still exist from the fallback writes during issuance

---

# 6. Migration Path

For gaps requiring implementation:

1. **Gap 3+4 (certificate owner check)** — **Done in code (verified 2026-09-22).** `show()`, `pdf()`, `download()` enforce the owner check. `show()` covered by tests; pdf/download owner-matrix tests still to add. No migration needed.
2. **Gap 8 (no duplicate active cert per event+email)** — **Resolved without migration.** MySQL 8.0 InnoDB cannot create a unique index on a generated column referencing a FK column. Constraint already enforced at the application layer in all 4 issuance paths: `store()` (line 258), `bulk()` (line 412), `issueCertificates()` (line 662), and `reissue()` (revokes old before creating new, transactional). No code change needed.
3. **Gap 5 (uploaded files ignored)** — **Fixed.** `issueCertificates()`, `store()`, and `bulk()` check `metadata['generation_mode']`. Primary path: metadata-based serving (no disk write). Fallback path: disk-based (existing code retained).
4. **Gap 6 (store/bulk template-only)** — **Fixed.** Both endpoints now support `file` mode via `metadata.generation_mode` check. Primary + fallback paths.
5. **Gap 9 (file_data LONGBLOB)** — **Removed.** New migration drops `file_data` column from `certificates`. Model updated. Uploaded PDFs served directly from `event_attendees.metadata.file_data`.
6. **Gap 10 (flat folder structure)** — **Deferred.** Disk fallback retains original path format. Metadata-based serving doesn't depend on folder structure.
7. **Gap 11 (preview page for uploaded certs)** — **Fixed.** `AttendeeController::fileData()` updated to serve from `metadata.file_data`. Frontend preview page updated to check `metadata.generation_mode` instead of `cert.file_path`.

### Revert strategy

If metadata-based serving causes issues:
1. Remove the metadata-based primary path code (the `if ($attendee->metadata['generation_mode'] === 'file')` blocks in controllers)
2. The fallback disk path becomes the active path again
3. No data loss — `file_path` and disk files still exist from the fallback writes during issuance

---

# 7. Organization Website & Email URL Generation

## 7.1 Rule

> Certificate issuance emails contain two links: a **download link** (PDF) and a **verify link** (frontend verification page). Both URLs must resolve dynamically from the `organizations.website` column, not from environment variables.

Rationale: the frontend URL is a property of the organization, not the server deployment. Multiple organizations (tenants) may share one backend but have different frontend URLs.

## 7.2 Current Implementation

| URL type | Current source | Fallback |
|----------|---------------|----------|
| Download | `config('app.url') . '/api/v1/certificates/{id}/download'` | — |
| Verify | `config('app.url') . '/api/v1/verify/{number}'` | — |

Both point to the **backend API**. The verify URL returns JSON, not an HTML page. The download URL requires JWT auth — recipients clicking from email get `401 Missing bearer token`.

## 7.3 Required Behavior (Spec)

### Email URLs

| URL type | Pattern | Source | Auth required |
|----------|---------|--------|---------------|
| Download | `{organizations.website}/api/v1/public/certificates/{id}/download` | `certificate->organization->website` | **No** (public endpoint) |
| Verify | `{organizations.website}/verify/{certificate_number}` | `certificate->organization->website` | **No** (frontend page) |

### Fallback chain

```
certificate->organization->website
  ↓ (if null)
config('app.url')
  ↓ (if null)
null → link omitted from email
```

> **Seed guarantee:** `organizations.website` must never be NULL in practice. It is set by `DatabaseSeeder` and backfilled by `database/sql/cpanel-cert-db-seed.sql` (idempotent, dump-proof). A NULL website silently produces API-domain verify links — if that ever recurs, check the seed row first.

### Database change

`organizations` table gains a nullable `website` column:

```sql
ALTER TABLE organizations ADD COLUMN website VARCHAR(255) NULL AFTER slug;
```

## 7.4 API Contract

### Email payload (internal — `CertificateEmail` mailable)

| Variable | Resolved from | Example |
|----------|--------------|---------|
| `$downloadUrl` | `$certificate->organization->website . '/api/v1/public/certificates/' . $id . '/download'` | `https://staging-loa-vericert.vercel.app/api/v1/public/certificates/uuid/download` |
| `$verifyUrl` | `$certificate->organization->website . '/verify/' . $certificateNumber` | `https://staging-loa-vericert.vercel.app/verify/CERT-0001` |

### `GET /api/v1/public/certificates/{id}/download` — Public Download

**Auth:** None (public endpoint, org-scoped).

**Success 200:** `Content-Type: application/pdf`, `Content-Disposition: attachment`.

**Error 404:** certificate not found.

**Error 410:** certificate revoked or expired.

## 7.5 Identified Gaps

| Gap | Impact | Status |
|-----|--------|--------|
| **No `website` column on organizations** | Cannot store frontend URL per tenant | ✅ Resolved — migration `2026_09_13_000001`; seeded via `DatabaseSeeder` + `database/sql/cpanel-cert-db-seed.sql` |
| **Download endpoint requires auth** | Email recipients cannot download | ✅ Resolved — public download endpoint in `PublicCertificateController` |
| **Verify URL points to API (JSON)** | Email recipients see raw JSON, not verification page | ✅ Resolved — frontend `/verify/{number}` via `organizations.website` (seed guarantee, 2026-09-18) |

---

# 8. Public Certificate Download Endpoint

## 8.1 Rule

> A certificate PDF may be downloaded **without authentication** by anyone who possesses the certificate UUID. The endpoint is scoped to the organization via the URL prefix (`/api/v1/public/`) and validates the certificate belongs to the configured organization.

Rationale: email recipients do not have JWT tokens. The download link must work from any email client.

## 8.2 Current Implementation — IMPLEMENTED (2026-09-22 verification)

`CertificateController::download()` requires `jwt.auth` middleware (owner-scoped, §2). The public equivalent exists: `GET /api/v1/public/certificates/{id}/download` in `PublicCertificateController::publicDownload()` (no auth, org-scoped, 404/410 contract per §7.4/§8.3; route outside the `jwt.auth` group).

## 8.3 Required Behavior (Spec)

### `GET /api/v1/public/certificates/{id}/download`

**Auth:** None.

**Flow:**
1. Resolve organization from `config('cert-platform.organization_id')`.
2. Load certificate with `event`, `template`, `organization` relations.
3. Filter by `organization_id` — 404 if not found.
4. Check `status` — 410 if `revoked` or `expired`.
5. Log audit event: `certificate.downloaded`, channel: `email`.
6. Return PDF via `PdfService::downloadCertificatePdf()`.

**Error responses:**

| Status | Condition |
|--------|-----------|
| 404 | Certificate not found or wrong organization |
| 410 | Certificate revoked or expired |
| 500 | PDF generation failed |

## 8.4 Migration Path

1. **Add `website` column** — migration `2026_09_13_000001_add_website_to_organizations_table.php`.
2. **Update `Organization` model** — add `website` to `$fillable`.
3. **Update `DatabaseSeeder`** — set `website` in seed data.
4. **Add public download route** — `GET /api/v1/public/certificates/{id}/download` in `PublicCertificateController`.
5. **Update email URL generation** — 4 sites in `CertificateController` and `EventController` to use `$certificate->organization->website`.
6. **Remove `NEXT_PUBLIC_BASE_URL` config** — no longer needed; URL sourced from database.
