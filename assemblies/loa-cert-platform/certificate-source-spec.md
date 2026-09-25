# Certificate Source (Uploaded vs System-Generated) — Spec

| Field | Value |
|---|---|
| ID | CERT-SOURCE-001 |
| Title | Certificates — expose accurate source (`generation_mode`) + server-side `source` filter |
| Status | Final |
| Owner | LOA Cert Platform |
| Version | 1.1 (Final — v1.0 approved 2026-09-25; v1.1 amends CON-6/D-3 test runner to `php vendor/bin/phpunit` after user-proven `php artisan test` undefined in cert-app) |
| Scope | `GET /api/v1/certificates` (list) + `GET /api/v1/certificates/{id}` (show): additive `generation_mode` field + optional `source` query filter; `POST /api/v1/certificates` (`store`) file-mode contract clarification; `POST /api/v1/certificates/upload` interaction clarification |
| Non-goals | No schema migration; no route-path changes; no response-key removals/renames; no `{error}` shape change; no numbering change; no bulk-issue contract change; no email/PDF serving change; no frontend change (frontend consumes only) |
| Layer | Product Assembly (`assemblies/loa-cert-platform`) |

> **Final (approved 2026-09-25).** Phase 1 deliverable complete. No code, migration, config edit, or test was touched to produce it. Phase 2 may proceed exactly to this spec — DEC-3 = 422, DEC-5 = metadata-declare + two-step upload canonical, DEC-7 = `CertificateSource` service default.

---

## 1. RFC 2119 Terminology

The key words **MUST**, **MUST NOT**, **REQUIRED**, **SHALL**, **SHALL NOT**, **SHOULD**, **SHOULD NOT**, **RECOMMENDED**, **MAY**, and **OPTIONAL** in this document are to be interpreted as described in RFC 2119.

- **generation_mode** — canonical discriminator, `file | template`, resolved server-side (see DEC-1, DEC-4).
- **source** — public filter vocabulary on the list endpoint: `uploaded | system-generated` (see DEC-2). Mapping is fixed: `uploaded ↔ file`, `system-generated ↔ template`.
- **event-linked cert** — a `certificates` row with a matching `event_attendees` row (`event_attendees.certificate_id = certificates.id`).
- **standalone cert** — a `certificates` row with no matching `event_attendees` row (e.g. issued without `event_id`, or `event_id=none` listing).

---

## 2. Context

### 2.1 Frontend need

Frontend `e-cert` wants to filter and display `/certificates` by uploaded vs system-generated.

Current frontend rule (`file_path ? uploaded : system-generated`, see e-cert `certificate-detail.tsx:137`) is **INACCURATE** against backend reality.

### 2.2 Verified backend reality (read-only, 2026-09-25)

1. **List supports no source filter.** `app/Http/Controllers/CertificateController.php:120-191` (`index`): supports `event_id, recipient_email, status, search, from, to, limit, offset` only. No `source` / `generation_mode` param.
2. **Detail/list payload carries no source.** `CertificateController.php:1287-1310` (`formatCertificate`): returns `template_id, file_path` among bare keys, no `generation_mode`. `file_path` is nullable; `template_id` is nullable.
3. **`file_path` is not a discriminator.** `app/Services/PdfService.php:31-34` (`generateCertificatePdf`): template-rendered certs **ALSO** get `file_path` set (`certificates/<number>.pdf` written to `local` disk + `certificate->update(['file_path' => ...])`). Therefore `file_path != null` does **NOT** mean uploaded.
4. **True discriminator is `EventAttendee.metadata.generation_mode` (`file` vs `template`):**
   - `app/Http/Controllers/PublicCertificateController.php:235-242` (`resolveGenerationMode`): looks up `EventAttendee::where('certificate_id', $certificate->id)->first()`, reads `metadata['generation_mode'] ?? 'template'`, allow-lists to `['template','file']`, defaults unknown → `template`. This is the reference logic (already ships on public `view`/`verify` per `certificate-rules-spec.md` §7.4 v1.1).
   - `app/Http/Controllers/AttendeeController.php:590` (`fileData`): `$mode = $metadata['generation_mode'] ?? 'template'`.
   - `app/Services/DiskCertificateStorage.php:20-22` (`store`): `$mode = $metadata['generation_mode'] ?? 'template'`.
   - `app/Services/MetadataCertificateStorage.php:20-31` (`store`): same default.
5. **`store()` drops standalone file intent.** `CertificateController.php:213-294` (`store`): validator (`213-221`) accepts `event_id, template_id, recipient_name, recipient_email, expires_at, send_email, metadata` — **not** `file_path`. `Certificate::create()` (`286-295`) persists `metadata` to `certificates.metadata` but ignores `file_path`. Storage call (`304-305`) passes **attendee** metadata (`$attendee?->metadata ?? []`), not request `metadata`. Consequence: a standalone IssueForm file-mode `file_path` is dropped; upload is a second step via `upload():663-691` (requires existing `certificate_number`, `multipart/form-data` `certificate_number + file`, sets `file_path` only, does **not** set any `generation_mode`).
6. **Schema has no source column.** `database/migrations/2026_08_06_000005_create_certificates_table.php`: `template_id` nullable, `file_path` nullable, `metadata` nullable JSON, no source/`generation_mode` column. `app/Models/Certificate.php:18-31` `$fillable` includes `file_path, metadata`; no `generation_mode` accessor. `app/Models/EventAttendee.php:31-40` casts `metadata => array`.
7. **Catalog has no query-param concept.** `config/cert-endpoints.php:42-46` lists `GET /api/v1/certificates` (`read`) and `GET /api/v1/certificates/{id}` (`read`) as path+level only; query params are documented in `api-endpoints.md` + `OA\Parameter` blocks, not in the catalog.

### 2.3 Why this spec exists

Define — **not yet implement** — a backward-compatible way for the gated list + show endpoints to return accurate source and for the list to filter by it, covering:

- (a) **event-linked certs** (attendee row exists → `attendee.metadata.generation_mode`), and
- (b) **standalone certs** (no attendee row → fallback to `certificate.metadata.generation_mode ?? 'template'`).

Mapping is fixed: `generation_mode=file` = "uploaded", `generation_mode=template` = "system-generated".

---

## 3. Constraints (normative)

- **CON-1 — No breaking changes.** The implementation **MUST NOT** change route paths, remove/rename any existing bare response key, change the `{status: error, message, errors?}` error shape (`api-endpoints.md` §3.4), alter the `loa_cert` schema (no migration), or alter certificate-numbering atomicity (`SELECT FOR UPDATE` on `certificate_sequences`). The change is **additive only**: one new response field `generation_mode` + one new **OPTIONAL** list query param `source`. Existing clients omitting `source` **MUST** observe byte-equivalent filtering behaviour (both modes returned) plus one additive key per item.
- **CON-2 — Single helper, no duplicated branching.** The implementation **MUST** reuse the `PublicCertificateController::resolveGenerationMode()` logic (allow-list `['template','file']`, unknown/missing → `template`). The logic **MUST** live in exactly one shared helper (see DEC-7) consumed by `CertificateController::index/show/formatCertificate` (and later `upload`/`store` paths); copy-pasted `metadata['generation_mode'] ?? 'template'` branches in controllers/storage **MUST NOT** be added.
- **CON-3 — Standalone-cert fallback is explicit.** Resolution **MUST** be specified as: (1) if a linked `EventAttendee` row exists (`certificate_id = certificates.id`), use `attendee.metadata.generation_mode` (allow-listed, default `template`); (2) otherwise (standalone, no attendee row) use `certificate.metadata.generation_mode` (allow-listed, default `template`). The implementation **MUST NOT** return `null`/unknown for `generation_mode` and **MUST NOT** infer source from `file_path` or `template_id` presence.
- **CON-4 — `store()` file-mode contract is explicit.** This spec **MUST** state the canonical file-mode contract for `POST /api/v1/certificates` + `POST /api/v1/certificates/upload` (see DEC-5). The implementation **MUST NOT** silently accept a `file_path` request field on `store()` (validator stays closed), and **MUST NOT** change numbering/duplicate-409/owner/audit behaviour while clarifying the contract.
- **CON-5 — OpenAPI + catalog mirror noted.** The implementation **MUST** add `OA\Parameter(name: "source", ...)` on `index()` and `OA\Property(property: "generation_mode", ...)` on the `Certificate` schema in `CertificateController.php`; `config/cert-endpoints.php` **MUST NOT** change (catalog keys on path+level only — query params are not catalogued), but the spec-version bump in `api-endpoints.md` (v-next) **MUST** document the new query param + response field so the mirror stays accurate.
- **CON-6 — TDD plan, user-run tests only.** The agent **MUST NOT** run any CLI (per assembly contract §1.3 — even read-only). Verification is by **user-run** request-level PHPUnit cases: one behavior per test, `RefreshDatabase`, self-seeded org row per `setUp` (never hardcoded tokens, never touching the `loa_cert` app DB), plus user-run manual verify steps. The cert-app vendor has **no `php artisan test` command** (proven 2026-09-25: `Command "test" is not defined`; `TestCommand` absent from `vendor/laravel/framework`, only `make:test` exists) — tests run via **`php vendor/bin/phpunit`** inside the container, preferably through the repo runner which ensures the `loa_cert_test` DB exists. The spec **MUST** list the exact commands the user runs from the **repo root** (`loa-platform` project, never the assembly-level compose file), suites **sequentially** (never overlapping):
  - `.\scripts\run-tests.ps1 -Target cert -CertFilter CertificateSourceTest` (new suite; ensures `loa_cert_test`, runs `docker compose exec cert-app php vendor/bin/phpunit --filter CertificateSourceTest`)
  - then existing regression suites sequentially, e.g. `.\scripts\run-tests.ps1 -Target cert -CertFilter CertificateTest`, `.\scripts\run-tests.ps1 -Target cert -CertFilter PublicCertificateTest` (or full `.\scripts\run-tests.ps1 -Target cert`)
  - No change is complete until the user pastes green results.

---

## 4. Goal (normative)

### 4.1 Decisions

- **DEC-1 — Response field: `generation_mode: file | template` (PROPOSED, needs Final).** Both `GET /api/v1/certificates` items and `GET /api/v1/certificates/{id}` **SHALL** include an additive bare key `generation_mode` with exactly two values: `file` (uploaded) or `template` (system-generated). The key name is `generation_mode` (not `source`, not `is_uploaded`) to match the existing public `view`/`verify` contract (`certificate-rules-spec.md` §7.4) and the `metadata.generation_mode` storage vocabulary. `file_path` and `template_id` stay as-is for download/debug use but are **NOT** source signals.
- **DEC-2 — Filter param: `?source=uploaded | system-generated` (PROPOSED, needs Final).** The list endpoint **SHALL** accept one **OPTIONAL** query param `source` with exactly two values: `uploaded` (≡ `generation_mode=file`) and `system-generated` (≡ `generation_mode=template`). Rationale: frontend already speaks "uploaded vs system-generated"; backend already speaks `file vs template`; the param translates once at the boundary so neither side renames its vocabulary. `source` omitted → both modes (current behaviour + additive field).
- **DEC-3 — Invalid `source` → `422` (PROPOSED, needs Final — open).** An unrecognised `source` value (anything other than `uploaded | system-generated`) **SHALL** return `422` with the standard error shape (`{status: "error", message, errors: {source: [...]}}` via `ValidationException`), consistent with `store()`/`upload()` validation behaviour. **Alternative REJECTED (recorded):** silently ignoring invalid values — rejected because it hides frontend typos (`uploadedd`) and makes `meta.total` misleading. Final approver confirms 422 vs ignore; default if no objection is **422**.
- **DEC-4 — Resolution order incl. standalone fallback (PROPOSED, needs Final).** `generation_mode` for a certificate **SHALL** resolve as:
  1. Find `EventAttendee::where('certificate_id', $certificate->id)->first()`. If found, `$mode = $attendee->metadata['generation_mode'] ?? 'template'`.
  2. Else (no attendee row — standalone), `$mode = $certificate->metadata['generation_mode'] ?? 'template'`.
  3. `return in_array($mode, ['template','file'], true) ? $mode : 'template'`.
  
  This is `resolveGenerationMode()` extracted verbatim + step 2 added. Unknown/missing/odd types (e.g. `File`, `UPLOADED`, `null`) → `template`.
- **DEC-5 — `store()` + `upload()` file-mode contract (PROPOSED, needs Final — open).** Canonical contract:
  - **Event-linked file-mode (single-step, declarative):** caller ensures the attendee row carries `metadata.generation_mode=file` (+ `file_data` as today via CSV-import/attendee paths) **before** issuance; `store()`/`bulk()`/`issueCompleted` then resolve `file` via DEC-4 step 1. No `file` bytes are posted to `store()` itself.
  - **Standalone file-mode (two-step, canonical for bytes):** (1) `POST /api/v1/certificates` **without** `event_id`, with `metadata: {generation_mode: "file"}` to declare intent (persisted to `certificates.metadata`; validator already allows `metadata: array` — no new top-level field); (2) `POST /api/v1/certificates/upload` (`multipart/form-data`, existing `certificate_number + file` contract) delivers the bytes and sets `file_path`. `upload()` **SHALL** additionally stamp `certificate.metadata.generation_mode=file` (merge, preserving other keys) so DEC-4 step 2 resolves `file` even if step 1 omitted the declaration. Direct `file_path` in `store()` JSON remains **REJECTED** (multipart bytes cannot ride JSON; validator stays closed).
  
  **Alternative RECORDED:** single-step `store()` with base64 `metadata.file_data` for standalone — rejected for Phase 1 because it duplicates the attendee `file_data` path, risks 10M body-limit interaction (`body-size-limits.md`), and bypasses the existing `upload()` virus/size (`pdf|max:10240`) gate. May be revisited in a later spec.
- **DEC-6 — Filtering semantics (PROPOSED, needs Final).** `?source=` **SHALL** combine with **AND** against all existing list filters (`event_id` incl. `none`, `recipient_email`, `status`, `search`, `from`, `to`) with `meta.total` reflecting the **filtered** count (not the unfiltered table count) and `has_more` computed from it. Pagination (`limit` cap 100, `offset`) applies **after** filtering. Because `generation_mode` derives from a related JSON field, the implementation **SHALL** filter server-side (subquery/`whereHas` on `event_attendees.metadata` + `certificates.metadata` fallback — exact query shape is implementation detail) — it **MUST NOT** fetch-then-slice in PHP in a way that breaks `meta.total`.
- **DEC-7 — Helper home (PROPOSED, needs Final — open).** The single helper **SHOULD** live as a small injectable service or model-adjacent resolver (e.g. `App\Services\CertificateSource::resolve(Certificate $c): string` or `Certificate::getGenerationModeAttribute()` delegating to one private resolver), with `PublicCertificateController::resolveGenerationMode()` refactored to call it (no behaviour change on public endpoints). Exact class name is implementation detail; the spec constrains only: one owner, constructor-injected (no static business logic per `principles.md` coding detail), covered by the TDD plan. Final approver confirms the home; default if no objection is a `CertificateSource` service.

### 4.2 Acceptance criteria (objective, machine-checkable)

- **ACC-1 — List exposes source.** `GET /api/v1/certificates` (authenticated, `read`) returns per item an additive `generation_mode: file | template` resolved per DEC-4. Existing keys (`id, certificate_number, recipient_name, recipient_email, issued_at, expires_at, revoked_at, revoke_reason, status, event_id, event, template_id, file_path, created_at`) are unchanged.
- **ACC-2 — Show exposes source.** `GET /api/v1/certificates/{id}` returns `generation_mode: file | template` (same resolution), with existing owner rule (recipient-or-`cert-admin`, 403 otherwise) and 404 shape unchanged.
- **ACC-3 — Filter `uploaded`.** Seeded fixture: ≥1 `file`-mode cert (attendee `metadata.generation_mode=file`) + ≥1 `template`-mode cert. `GET /api/v1/certificates?source=uploaded` returns **only** `generation_mode=file` items, and `meta.total` equals the count of file-mode certs matching the other filters.
- **ACC-4 — Filter `system-generated`.** `GET /api/v1/certificates?source=system-generated` returns **only** `generation_mode=template` items, with correct `meta.total`.
- **ACC-5 — Omitted = both.** `GET /api/v1/certificates` without `source` returns both modes (existing behaviour) with the additive field present on every item.
- **ACC-6 — Invalid value.** `GET /api/v1/certificates?source=bogus` returns **422** with `{status: "error", ...}` (per DEC-3; if Final flips to ignore, this ACC flips to "returns both modes with 200" — approver strikes one).
- **ACC-7 — AND-combination.** `?source=uploaded&event_id=<id>&status=active&search=<s>&from=<d>&to=<d>&limit=<n>&offset=<m>` applies all predicates conjunctively; `meta.{total,limit,offset,has_more}` are consistent with the conjunction (`has_more = (offset+limit) < total`). At minimum: `source + event_id`, `source + search`, and `source + limit/offset` combinations are exercised.
- **ACC-8 — Standalone fallback.** A certificate with **no** attendee row and `certificates.metadata = {generation_mode: "file"}` resolves `generation_mode=file` (list + show) and is returned by `?source=uploaded`; with missing/null/invalid `certificates.metadata` it resolves `template` and is returned by `?source=system-generated`.
- **ACC-9 — Frontend reliance.** Frontend can rely on `generation_mode` alone: for every cert in a mixed fixture, `generation_mode` equals the attendee/certificate metadata truth regardless of `file_path` presence (explicitly: a `template`-mode cert **with** `file_path` set still reports `template`; a `file`-mode cert reports `file`). No `file_path` heuristic is needed.
- **ACC-10 — No-regression envelope.** All pre-existing list/show behaviours (statuses 401/403/404, `{error}` shape, `event_id=none` handling, `limit` cap 100, ordering `created_at desc`) are unchanged; existing suites (`CertificateTest`, `PublicCertificateTest`, visibility/audit/SSO/dashboard) stay green (user-pasted results).

### 4.3 Subjective checks (human-judged, reviewer steps)

- **SUBJ-1 —** Reviewer opens e-cert list filtered "Uploaded" and confirms every row's detail view shows source Uploaded and downloads the uploaded bytes (not a template render).
- **SUBJ-2 —** Reviewer opens e-cert list filtered "System-generated" and confirms detail shows system-generated preview as today.

---

## 5. Deliverables

- **D-1 — This spec file.** `assemblies/loa-cert-platform/certificate-source-spec.md` (this file), Status `Final` v1.1. Phase 1 delivered the DRAFT; Phase 2 implemented exactly to it (see §9 resolutions).
- **D-2 — Future implementation (DEFERRED, listed WITHOUT touching — needs Final first).**
  - `app/Http/Controllers/CertificateController.php` — `index()` (`source` validation + AND-filter + eager-load for resolution), `show()`/`formatCertificate()` (additive `generation_mode`), `store()` (document/keep `metadata.generation_mode=file` declaration path), `upload()` (stamp `certificate.metadata.generation_mode=file` on success), plus `OA\Parameter(source)` + `OA\Property(generation_mode)` blocks.
  - Shared helper (per DEC-7) + refactor of `PublicCertificateController::resolveGenerationMode()` to call it (public `view`/`verify` behaviour unchanged).
  - `api-endpoints.md` v-next (minor bump; document `?source=` + `generation_mode` field; no route/catalog-level change).
  - `certificate-rules-spec.md` v-next (minor bump; extend §3 generation-modes + §7.4 with gated list/show `generation_mode` + standalone fallback).
  - Tests (new `CertificateSourceTest`, user-run): list-field presence, show-field presence, `uploaded`-only + `total`, `system-generated`-only + `total`, omitted-both, invalid-422 (or ignore per Final), AND-combinations (`event_id`, `search`, `limit/offset`), standalone fallback (`certificate.metadata`), `file_path`-independence (template cert with `file_path` still `template`).
- **D-3 — Future verification (DEFERRED, user-run).** Exact commands (repo root, sequentially, never overlapping):
  1. `.\scripts\run-tests.ps1 -Target cert -CertFilter CertificateSourceTest`
  2. `.\scripts\run-tests.ps1 -Target cert -CertFilter CertificateTest`
  3. `.\scripts\run-tests.ps1 -Target cert -CertFilter PublicCertificateTest`
  
  (Each ensures `loa_cert_test` exists, then runs `docker compose exec cert-app php vendor/bin/phpunit --filter=<Suite>`; full-suite alternative: `.\scripts\run-tests.ps1 -Target cert`. `php artisan test` does NOT exist in cert-app — do not use.)
  
  Plus manual: seed one uploaded + one system-generated cert, `GET /certificates?source=uploaded|system-generated|<omitted>|bogus` via authenticated client, confirm payloads + `meta.total`; confirm e-cert detail no longer needs the `file_path` heuristic.

---

## 6. TDD Plan (behavioural coverage outline — no tests written in Phase 1)

One behavior per test, `RefreshDatabase`, self-seed org row in `setUp` (pattern of existing suites), JWT via test helper (never hardcoded tokens). Proposed `CertificateSourceTest` cases (each maps to an `ACC-*`):

| # | Behaviour | Maps to |
|---|---|---|
| 1 | list item includes `generation_mode=file` for attendee file-mode cert | ACC-1, ACC-9 |
| 2 | list item includes `generation_mode=template` for template cert **with** `file_path` set | ACC-1, ACC-9 |
| 3 | show returns `generation_mode` with owner rule intact | ACC-2 |
| 4 | `?source=uploaded` returns only file-mode + correct `meta.total` | ACC-3 |
| 5 | `?source=system-generated` returns only template-mode + correct `meta.total` | ACC-4 |
| 6 | omitted `source` returns both modes | ACC-5 |
| 7 | invalid `source` → 422 `{error}` shape (or 200-both if Final flips) | ACC-6 |
| 8 | `source` ANDs with `event_id` | ACC-7 |
| 9 | `source` ANDs with `search` + `limit/offset` (`has_more` consistent) | ACC-7 |
| 10 | standalone cert with `certificate.metadata.generation_mode=file`, no attendee → `file` + matched by `uploaded` | ACC-8 |
| 11 | standalone cert with missing metadata → `template` + matched by `system-generated` | ACC-8 |

---

## 7. Glossary

| Term | Meaning |
|---|---|
| `generation_mode` | Canonical source discriminator: `file` (uploaded) or `template` (system-generated). Stored in `event_attendees.metadata.generation_mode` (event-linked) or `certificates.metadata.generation_mode` (standalone fallback). |
| `source` | Public list-filter vocabulary: `uploaded` (≡ `file`) or `system-generated` (≡ `template`). Query param only, never stored. |
| Uploaded | A certificate whose PDF bytes come from a user upload (`metadata.file_data` primary, `file_path` disk fallback). |
| System-generated | A certificate whose PDF is rendered from a `certificate_templates` row via DomPDF. |
| Standalone cert | Certificate with no `event_attendees` row (issued without `event_id`). Source falls back to `certificates.metadata`. |
| `file_path` | Disk location (`certificates/<number>.pdf`) on `local` disk. Present for **both** modes — MUST NOT be used as a source signal. |

---

## 8. References

- `assemblies/loa-cert-platform/AGENTS.md` §1.3 (agent runs no CLI; repo-root docker; sequential suites), §1.4 (no breaking changes), §1.9 (this spec's format contract), §4 (`api-endpoints.md` FINAL v1.9, `certificate-rules-spec.md` FINAL v1.1).
- `assemblies/loa-cert-platform/api-endpoints.md` v1.9 — §3.4 envelope (`data`/`meta`/`{error}`), §5 certificates group (to be bumped v-next).
- `assemblies/loa-cert-platform/certificate-rules-spec.md` v1.1 — §3 (generation modes, storage strategies), §7.4 (`view`/`verify` `generation_mode` precedent), §8 (public download).
- Code (verified 2026-09-25): `app/Http/Controllers/CertificateController.php:120-191` (index filters), `:213-294` (store validator+create), `:663-691` (upload), `:1287-1310` (formatCertificate); `app/Http/Controllers/PublicCertificateController.php:235-242` (reference resolver); `app/Http/Controllers/AttendeeController.php:590` (fileData mode); `app/Services/PdfService.php:31-34` (template also sets `file_path`); `app/Services/DiskCertificateStorage.php:20-22`, `app/Services/MetadataCertificateStorage.php:20-31` (mode defaults); `app/Models/Certificate.php:18-41`, `app/Models/EventAttendee.php:17-40`; `database/migrations/2026_08_06_000005_create_certificates_table.php` (nullable `template_id`/`file_path`, no source column); `config/cert-endpoints.php:42-46` (catalog path+level only).
- Root `AGENTS.md` (Rule 0 spec-first; Rule 0.5 no CLI without permission; no auto-pilot), `principles.md` Phase 1–6 + coding detail (constructor injection, one behavior per test), `platform.md` §15b–17 (assembly composes; no business logic in assembly beyond composition).
- Frontend pointer (not normative): e-cert `certificate-detail.tsx:137` (`file_path` heuristic to be replaced by `generation_mode`).

---

## 9. Open decisions — RESOLVED (Final 2026-09-25, implemented Phase 2)

1. **DEC-3:** locked to **422** on invalid `?source=` (ignore alternative rejected).
2. **DEC-5:** locked to **declare-via-`metadata` + two-step `upload()` bytes** canonical; `upload()` stamps `certificate.metadata.generation_mode=file`.
3. **DEC-7:** locked to **`App\Services\CertificateSource`** helper home; `PublicCertificateController::resolveGenerationMode()` delegates to it.

**Status:** Phase 2 implemented exactly to these resolutions; full suite user-green 263 passed (787 assertions) via `.\scripts\run-tests.ps1 -Target cert`. The pre-approval proposal text below is superseded history.

1. **DEC-3:** invalid `?source=` → **422** (proposed) vs silently ignore. Approver strikes one; ACC-6 follows.
2. **DEC-5:** canonical standalone file-mode = **declare-via-`metadata` + two-step `upload()` bytes** (proposed) vs single-step base64. Approver confirms; `upload()` metadata-stamping follows.
3. **DEC-7:** helper home = **`App\Services\CertificateSource`** (proposed) vs model accessor. Approver confirms name; CON-2 (single owner) holds either way.

**Requested action:** mark this spec **Final** (or comment edits). On Final, Phase 2 (implementation exactly to this spec + TDD per §6 + user-run tests per D-3) may be proposed — never auto-started.
