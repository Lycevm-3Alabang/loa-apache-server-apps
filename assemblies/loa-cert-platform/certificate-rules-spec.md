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

## 2.2 Current Implementation — GAP

**Templates** have a full visibility system (`public`/`private` column, `scopeVisibleTo()`, `isVisibleTo()`, owner checks). **Certificates have NO equivalent.**

| Component | Visibility field | Scope method | Owner check |
|-----------|-----------------|--------------|-------------|
| `CertificateTemplate` | `visibility` (`public`/`private`) | `scopeVisibleTo($sub, $groups)` | `isOwnedBy($sub)` via `created_by`/`updated_by` |
| `Certificate` | **NONE** | **NONE** | **NONE** |

**Current behavior:**
- `GET /api/v1/certificates` — returns **all** certificates to any user with `read` permission. No filtering by caller identity.
- `GET /api/v1/certificates/{id}` — returns **any** certificate by ID. No ownership check.
- `GET /api/v1/me/certificates` — filters by `recipient_email === JWT.email`. This is the **only** owner-scoped certificate endpoint.

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

# 3. Certificate File Storage

## 3.1 Rule

> Certificate PDFs are stored on the server filesystem. The `file_path` column on `certificates` holds a **relative path** from the storage root. Two storage paths exist: auto-generated PDFs (DomPDF) and manually uploaded PDFs.

## 3.2 Storage Architecture

```
storage/app/
├── private/
│   └── certificates/          ← Auto-generated PDFs (DomPDF)
│       ├── CERT-0001.pdf
│       └── CERT-0002.pdf
└── public/                    ← Uploaded PDFs (multipart upload)
    └── certificates/
        └── CERT-0003.pdf
```

| Source | Disk | Path pattern | Column |
|--------|------|-------------|--------|
| DomPDF auto-generation (`PdfService`) | `local` → `storage/app/` | `certificates/{CERT_NUMBER}.pdf` | `file_path` |
| Manual upload (`CertificateController::upload`) | `public` → `storage/app/public/` | `certificates/{CERT_NUMBER}.pdf` | `file_path` |
| Email attachment (`CertificateEmail`) | reads from `local` | `storage/app/{file_path}` | — |

**Both paths use the same relative prefix** (`certificates/`) but land on **different disks**. This is an inconsistency — uploaded PDFs are publicly web-accessible (via the `public` symlink) while auto-generated ones are not.

## 3.3 API Contract

### `POST /api/v1/certificates/upload` — Upload Certificate File

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

**Error 404:**
```json
{
  "status": "error",
  "message": "Certificate not found."
}
```

**Error 422:**
```json
{
  "status": "error",
  "message": "Validation error.",
  "errors": {
    "file": ["The file must be a PDF."]
  }
}
```

### `GET /api/v1/certificates/{id}/pdf` — Stream PDF

**Success 200:** raw binary `Content-Type: application/pdf`.
**Error 404:** certificate or file not found.
**Error 410:** revoked/expired.

### `GET /api/v1/certificates/{id}/download` — Download PDF

**Success 200:** raw binary with `Content-Disposition: attachment; filename="CERT-0001.pdf"`.

## 3.4 Identified Gaps

| Gap | Impact | Recommendation |
|-----|--------|----------------|
| **Disk inconsistency** — auto-generated PDFs go to `local`, uploaded PDFs go to `public`. Both use the same relative path. | Uploaded PDFs are publicly web-accessible without auth if the `storage/app/public` symlink exists. Auto-generated ones are not. | Unify to `local` disk. The `public` disk should only be used for intentionally public assets. Alternatively, add middleware to guard `/storage/` paths. |
| **No file-type check on upload** — only PDF is validated (`mimes:pdf`). PNG/image uploads are not supported. | If the business needs PNG support, the upload validator must be extended. | Add `mimes:pdf,png,jpg` if image certificates are desired. Update the `file_path` extension accordingly. |
| **No file cleanup on certificate deletion** — deleting a certificate does not delete the PDF from disk. | Orphaned PDF files accumulate over time. | Add a deletion hook or scheduled job to clean up `file_path` entries for deleted certificates. |

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

| # | Area | Gap | Severity | Spec says | Code does |
|---|------|-----|----------|-----------|-----------|
| 1 | Template Locking | No lock check when issuing certificates with a locked `template_id` | Low | §1.1: "must not be editable once referenced" | Only checks update/delete, not issuance |
| 2 | Template Locking | `force=true` delete orphans event `template_id` silently | Medium | — | `ON DELETE SET NULL` clears reference |
| 3 | Certificate Visibility | `CertificateController::show()` has no owner check | **High** | `api-endpoints.md` §9.6: owner rule for detail/pdf/download | Returns any cert to any authenticated user |
| 4 | Certificate Visibility | `pdf()` and `download()` have no owner check | **High** | §9.6: owner rule | Streams PDF to any authenticated user |
| 5 | File Storage | Auto-generated PDFs on `local` disk, uploaded PDFs on `public` disk | Medium | — | Inconsistent storage; `public` disk is web-accessible |
| 6 | File Storage | No PNG/image upload support | Low | — | Only `mimes:pdf` validated |
| 7 | File Storage | No file cleanup on certificate deletion | Low | — | Orphaned PDFs accumulate |
| 8 | Issuance | No DB constraint on `(event_id, recipient_email)` for active certs | Medium | §7.2 declares `UNIQUE(event_id, recipient_email)` | Only app-level `whereNull('revoked_at')` check |

---

# 6. Migration Path

For gaps requiring implementation:

1. **Gap 3+4 (certificate owner check)** — Add `recipient_email` check in `CertificateController::show()`, `pdf()`, `download()`. No migration needed. Add tests.
2. **Gap 8 (no duplicate active cert per event+email)** — **Resolved without migration.** MySQL 8.0 InnoDB cannot create a unique index on a generated column referencing a FK column. Constraint already enforced at the application layer in all 4 issuance paths: `store()` (line 258), `bulk()` (line 412), `issueCertificates()` (line 662), and `reissue()` (revokes old before creating new, transactional). No code change needed.
3. **Gap 5 (disk unification)** — Config change + update `CertificateController::upload()` to use `local` disk. No migration.
4. **Gap 1 (lock check on issuance)** — Add `isTemplateLocked()` call in `CertificateController::store()` when `template_id` is explicitly provided. No migration.
