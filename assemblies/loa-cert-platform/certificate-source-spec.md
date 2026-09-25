# Certificate Source (Uploaded vs System-Generated) — Spec

| Field | Value |
|---|---|
| ID | CERT-SOURCE-001 |
| Title | Certificates — expose accurate source (`generation_mode`) + server-side `source` filter |
| Status | Final v1.4 (§12 attendee-list `certificate` amendment approved Final; §§1–11 history preserved) |
| Owner | LOA Cert Platform |
| Version | 1.4 Final (v1.3 Final 2026-09-25; v1.4 Final adds `certificate` object on `GET /api/v1/events/{id}/attendees` list) |
| Scope | `GET /api/v1/certificates` (list) + `GET /api/v1/certificates/{id}` (show): additive `generation_mode` field + optional `source` query filter; `POST /api/v1/certificates` (`store`) file-mode contract clarification; `POST /api/v1/certificates/upload` interaction clarification; §12 (Final): `GET /api/v1/events/{id}/attendees` list gains additive `certificate` object |
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

- **DEC-1 — Response field: `generation_mode: file | template` (Final (approved 2026-09-25)).** Both `GET /api/v1/certificates` items and `GET /api/v1/certificates/{id}` **SHALL** include an additive bare key `generation_mode` with exactly two values: `file` (uploaded) or `template` (system-generated). The key name is `generation_mode` (not `source`, not `is_uploaded`) to match the existing public `view`/`verify` contract (`certificate-rules-spec.md` §7.4) and the `metadata.generation_mode` storage vocabulary. `file_path` and `template_id` stay as-is for download/debug use but are **NOT** source signals.
- **DEC-2 — Filter param: `?source=uploaded | system-generated` (Final (approved 2026-09-25)).** The list endpoint **SHALL** accept one **OPTIONAL** query param `source` with exactly two values: `uploaded` (≡ `generation_mode=file`) and `system-generated` (≡ `generation_mode=template`). Rationale: frontend already speaks "uploaded vs system-generated"; backend already speaks `file vs template`; the param translates once at the boundary so neither side renames its vocabulary. `source` omitted → both modes (current behaviour + additive field).
- **DEC-3 — Invalid `source` → `422` (Final (approved 2026-09-25) — open).** An unrecognised `source` value (anything other than `uploaded | system-generated`) **SHALL** return `422` with the standard error shape (`{status: "error", message, errors: {source: [...]}}` via `ValidationException`), consistent with `store()`/`upload()` validation behaviour. **Alternative REJECTED (recorded):** silently ignoring invalid values — rejected because it hides frontend typos (`uploadedd`) and makes `meta.total` misleading. Final approver confirms 422 vs ignore; default if no objection is **422**.
- **DEC-4 — Resolution order incl. standalone fallback (Final (approved 2026-09-25)).** `generation_mode` for a certificate **SHALL** resolve as:
  1. Find `EventAttendee::where('certificate_id', $certificate->id)->first()`. If found, `$mode = $attendee->metadata['generation_mode'] ?? 'template'`.
  2. Else (no attendee row — standalone), `$mode = $certificate->metadata['generation_mode'] ?? 'template'`.
  3. `return in_array($mode, ['template','file'], true) ? $mode : 'template'`.
  
  This is `resolveGenerationMode()` extracted verbatim + step 2 added. Unknown/missing/odd types (e.g. `File`, `UPLOADED`, `null`) → `template`.
- **DEC-5 — `store()` + `upload()` file-mode contract (Final (approved 2026-09-25) — open).** Canonical contract:
  - **Event-linked file-mode (single-step, declarative):** caller ensures the attendee row carries `metadata.generation_mode=file` (+ `file_data` as today via CSV-import/attendee paths) **before** issuance; `store()`/`bulk()`/`issueCompleted` then resolve `file` via DEC-4 step 1. No `file` bytes are posted to `store()` itself.
  - **Standalone file-mode (two-step, canonical for bytes):** (1) `POST /api/v1/certificates` **without** `event_id`, with `metadata: {generation_mode: "file"}` to declare intent (persisted to `certificates.metadata`; validator already allows `metadata: array` — no new top-level field); (2) `POST /api/v1/certificates/upload` (`multipart/form-data`, existing `certificate_number + file` contract) delivers the bytes and sets `file_path`. `upload()` **SHALL** additionally stamp `certificate.metadata.generation_mode=file` (merge, preserving other keys) so DEC-4 step 2 resolves `file` even if step 1 omitted the declaration. Direct `file_path` in `store()` JSON remains **REJECTED** (multipart bytes cannot ride JSON; validator stays closed).
  
  **Alternative RECORDED:** single-step `store()` with base64 `metadata.file_data` for standalone — rejected for Phase 1 because it duplicates the attendee `file_data` path, risks 10M body-limit interaction (`body-size-limits.md`), and bypasses the existing `upload()` virus/size (`pdf|max:10240`) gate. May be revisited in a later spec.
- **DEC-6 — Filtering semantics (Final (approved 2026-09-25)).** `?source=` **SHALL** combine with **AND** against all existing list filters (`event_id` incl. `none`, `recipient_email`, `status`, `search`, `from`, `to`) with `meta.total` reflecting the **filtered** count (not the unfiltered table count) and `has_more` computed from it. Pagination (`limit` cap 100, `offset`) applies **after** filtering. Because `generation_mode` derives from a related JSON field, the implementation **SHALL** filter server-side (subquery/`whereHas` on `event_attendees.metadata` + `certificates.metadata` fallback — exact query shape is implementation detail) — it **MUST NOT** fetch-then-slice in PHP in a way that breaks `meta.total`.
- **DEC-7 — Helper home (Final (approved 2026-09-25) — open).** The single helper **SHOULD** live as a small injectable service or model-adjacent resolver (e.g. `App\Services\CertificateSource::resolve(Certificate $c): string` or `Certificate::getGenerationModeAttribute()` delegating to one private resolver), with `PublicCertificateController::resolveGenerationMode()` refactored to call it (no behaviour change on public endpoints). Exact class name is implementation detail; the spec constrains only: one owner, constructor-injected (no static business logic per `principles.md` coding detail), covered by the TDD plan. Final approver confirms the home; default if no objection is a `CertificateSource` service.

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

---

## 10. Amendment v1.2 Final — Certificate source save fix (Phase 1 complete, approved 2026-09-25)

> **Status:** Final approved 2026-09-25. Phase 1 delivered this spec text only — no code, migration, config edit, or test touched.

### 10.1 Context delta (verified write-path breakage — do not re-derive)

Read path works: `app/Services/CertificateSource.php` (`resolve()` attendee.metadata → certificate.metadata fallback; `applySourceFilter()` same two branches) + `CertificateController::formatCertificate:1328` returns `generation_mode` + `index:176-184` `?source=uploaded|system-generated` (422 invalid). Frontend `e-cert` already filters/displays on it.

Write path is broken in two places:

(a) Event issuance never stamps the cert row: `EventController::issueCertificates:792-800` `Certificate::create()` has no `metadata`; `CertificateController::store:300-309` writes `metadata=request.metadata`, not the attendee's; `bulk:459+` same pattern. Attendee roster writes ARE correct (`AttendeeController::store:268-273`, `update:328-330`, `import:498-507` persist metadata verbatim; frontend sends `generation_mode` correctly). Consequence: `resolve()`/filter depend 100% on the attendee row — `AttendeeController::destroy:398-410` keeps the cert, which then falls back to null → `template` (uploaded misclassified). The `applySourceFilter` no-attendee branch on `certificates.metadata` can never match.

(b) Standalone upload never saves as file: e-cert `issue-form.tsx` sends `file_path`, no `metadata`; `store:227-235` validator has no `file_path`, `create` ignores it, storage runs with `[]` → template PDF. `/upload:663-691` sets only `file_path`, not `certificate.metadata.generation_mode`. Net: standalone upload resolves `template`. Dead code: `store:356` reads `$certificate->file_data` (column dropped in `2026_09_18_071347`).

Frontend fix (separate repo, already spec'd): `issue-form.tsx` will send `metadata: { generation_mode: "file"|"template" }` by mode; pre-upload `file_path` flow stays.

### 10.2 Additional constraints (normative)

- **CON-7 — Additive only; precedence and mirrors preserved.** The implementation **MUST NOT** change route paths, remove/rename any existing response key, change the `{status: error, message, errors?}` error shape, or alter certificate-numbering atomicity. `CertificateSource::resolve()` precedence is **UNCHANGED**: (1) attendee.metadata when event-linked → (2) `certificates.metadata` fallback → (3) `template` default. `CON-2` (single helper, no duplicated branching) still holds — stamping call-sites **MUST** delegate mode computation to the helper where a helper is needed, not re-branch. OpenAPI (`OA\Parameter`/`OA\Property` for any new/clarified input) + `config/cert-endpoints.php` mirror **MUST** be updated if the contract surface changes, otherwise left untouched per CON-5. Schema migration is **PREFERABLY NONE** — `certificates.metadata` JSON column already exists; if a migration proves needed it **MUST** be specified separately with rollback noted before any code.

### 10.3 Additional decisions (normative — Final 2026-09-25)

- **DEC-8 — Single writer rule: stamp `certificates.metadata.generation_mode` on every create/reissue (Final).** At every certificate create/reissue the implementation **SHALL** stamp `certificates.metadata.generation_mode` from the authoritative mode: `attendee.metadata.generation_mode` when event-linked, `request.metadata.generation_mode` when standalone (allow-listed `file|template`, default `template` for missing/invalid). Attendee row remains the primary read; cert metadata is the durable fallback that survives attendee delete. `POST /api/v1/certificates/upload` **SHALL** stamp `file` by merging `generation_mode=file` into the existing `certificates.metadata` JSON, preserving all other keys. Reissue **SHALL** re-stamp from the current attendee/request mode. A post-issuance roster mode edit **without** reissue **SHALL NOT** change served bytes — this is stated explicitly; silent backfill is **FORBIDDEN**. Affected create/reissue call-sites: `EventController::issueCertificates` create (+ reissue path), `CertificateController::store`, `CertificateController::bulk`.
- **DEC-9 — `store()` accepts and validates `metadata.generation_mode`; `file_path` top-level is rejected (Final).** `store()` **SHALL** accept `metadata.generation_mode=file|template`, validate it (`in:file,template` or equivalent → invalid mode returns `422` with the standard error shape), and persist it to `certificates.metadata`. Top-level `file_path` input on `store()` JSON **SHALL** be **REJECTED with 422** (explicit `errors.file_path`), consistent with DEC-5/CON-4 (multipart bytes cannot ride JSON; validator stays closed for bytes). Silent drop of `file_path` is **FORBIDDEN** — either documented 422 (chosen) or accept+honor; this spec chooses 422. **Alternative RECORDED and REJECTED:** accept+honor `file_path` on `store()` — rejected because an unvalidated path write risks path-injection, bypasses the `upload()` virus/size (`pdf|max:10240`) gate, and conflicts with the canonical two-step upload contract (DEC-5). Canonical standalone file-mode remains: declare via `metadata` on `store()` + deliver bytes via `upload()`.
- **DEC-10 — Remove dead `file_data` read at `store:356` (Final).** The `$certificate->file_data` read at `CertificateController::store:356` **SHALL** be removed or replaced — the column was dropped in migration `2026_09_18_071347` so the read is dead. If file bytes are needed there, they **MUST** come from `metadata.file_data` via the established attendee/storage path, not a column read. No behaviour change beyond eliminating the dead read.

### 10.4 Additional acceptance criteria (objective, machine-checkable)

- **ACC-11 — Event file-mode survives attendee delete.** Issue event-linked cert with attendee `metadata.generation_mode=file` → cert row `certificates.metadata.generation_mode=file`; delete the attendee row (cert kept per `AttendeeController::destroy:398-410`) → list/show still resolve `generation_mode=file` and `?source=uploaded` still matches.
- **ACC-12 — Event template-mode.** Issue event-linked cert with `template` mode → cert row `metadata.generation_mode=template` (or absent→default), resolves `template`, matched by `?source=system-generated`, not by `?source=uploaded`.
- **ACC-13 — Standalone file-mode (both paths).** (i) With new frontend `metadata: {generation_mode: "file"}` on `store()` → cert row stamped `file`, resolves `file`; (ii) via `/upload` alone (no metadata declaration) → `upload()` merges `generation_mode=file` into `certificates.metadata` preserving other keys, resolves `file`, matched by `?source=uploaded`.
- **ACC-14 — Invalid mode → 422.** `store()` (and bulk/reissue where applicable) with `metadata.generation_mode=bogus` returns `422` with `{status: "error", ...}`; top-level `file_path` on `store()` JSON returns `422` with `errors.file_path` (no silent drop).
- **ACC-15 — Roster edit without reissue is inert.** Post-issuance edit of attendee `metadata.generation_mode` without reissue does **NOT** change served bytes for the already-issued cert (reissue required per DEC-8); test asserts bytes/mode stable until reissue, then re-stamped after reissue.
- **ACC-16 — User-run green.** User runs from repo root, suites sequentially: `docker compose exec cert-app php artisan test` (full cert suite) + affected suite `CertificateSourceTest` additions — all green, pasted results. (Runner note: assembly contract specifies `php artisan test`; v1.1 CON-6/D-3 records cert-app historically required `php vendor/bin/phpunit` via `.\scripts\run-tests.ps1 -Target cert` — user confirms the working runner at verification time and pastes green output.)

### 10.5 Deliverables

- **D-4 — This spec amendment (Phase 1, this phase only).** `assemblies/loa-cert-platform/certificate-source-spec.md` §10 + metadata bump to v1.2 DRAFT. No code, migration, config, or test touched.
- **D-5 — Future implementation (DEFERRED — listed WITHOUT touching, needs Final first).**
  - `app/Http/Controllers/CertificateController.php` — `store()` validator (`metadata.generation_mode` `in:file,template`; reject top-level `file_path` 422) + `create` stamping + dead `file_data` removal at `:356` + `upload()` metadata-merge stamp + `formatCertificate` OA updates.
  - `app/Http/Controllers/EventController.php` — `issueCertificates` create + reissue stamping from attendee mode.
  - `app/Services/CertificateSource.php` — helper if needed for stamping/resolution (CON-2 single-owner holds).
  - `api-endpoints.md` v-next + `certificate-rules-spec.md` v-next mirrors.
  - `tests/Feature/Api/CertificateSourceTest.php` additions covering ACC-11–ACC-15.

### 10.6 Open DECs — RESOLVED (Final 2026-09-25)

1. **DEC-8:** single writer rule + merge-preserve on `/upload` + reissue re-stamp + no silent backfill — **APPROVED Final**.
2. **DEC-9:** `store()` validates `metadata.generation_mode` (`in:file,template` → 422 invalid); top-level `file_path` → 422 (not silent drop, not accept+honor) — **APPROVED Final**.
3. **DEC-10:** remove/replace dead `$certificate->file_data` read at `store:356` — **APPROVED Final**.

**Status:** §10 is Final. Phase 2 implementation exactly to §10 + TDD (ACC-11–ACC-16) may be proposed — never auto-started.

---

## 11. Amendment v1.3 Final — `generation_mode` on participant endpoints (approved)

> **Status:** Final approved. Phase 1 delivered this spec text only — no code, migration, config edit, or test touched.

### 11.1 Context

Participant "My Certificates" pages must correctly show whether each certificate is Uploaded or System-generated — on BOTH the list and the detail view. Frontend (e-cert repo) is already done and waiting: it reads `generation_mode` per item (`"file"` → Uploaded badge, `"template"` → System-generated badge, `file_path` heuristic only as fallback when the field is absent). Today both pages always render "System-generated" because the endpoints below never return the field. This amendment unblocks that UI with a single additive response field. No new filters, no new routes, no email changes, no migrations.

Concerned endpoints (only these two — nothing else in this task):

- `GET /api/v1/me/certificates` → `MeController::certificates:55-99`
- `GET /api/v1/me/certificates/{id}` → `MeController::certificate:115-140`

Both serialize via `MeController::formatCertificate:254-269`, which today returns: `id`, `certificate_number`, `recipient_name`, `issued_at`, `expires_at`, `revoked_at`, `revoke_reason`, `status`, `event_id`, `event_name`, `created_at`. No `generation_mode`, no `file_path`, no `template_id`.

### 11.2 Investigation findings (verified, read-only)

- **I1 — Canonical resolver confirmed, reuse as-is.** `CertificateSource::resolve()` (`app/Services/CertificateSource.php:17-28`) resolves attendee.metadata → certificate.metadata fallback → `template` default via `modeFromMetadata()` allow-list, and is already consumed by `CertificateController::formatCertificate:1346` (and by `PublicCertificateController::resolveGenerationMode:238` delegation). The amendment **REQUIRES** reuse of `resolve()` with no new resolution logic. (`CertificateSource` gains no new methods in this task.)
- **I2 — N+1 risk confirmed, eager-load fix available.** Both Me endpoints load `Certificate::with(['event'])` only (`MeController.php:60`, `:120`). `resolve()` uses the loaded `attendee` relation when present (`CertificateSource.php:19-20`), else issues one query per certificate — on the LIST endpoint that is one extra query per row. `Certificate::attendee(): HasOne` exists (`app/Models/Certificate.php:75-78`) and **CAN** be added to both `with()` clauses (`with(['event', 'attendee'])`). The amendment **REQUIRES** this eager load.
- **I3 — No filter wanted here.** The admin list already owns `?source=`; the participant list stays unfiltered and the frontend does not send it. This amendment adds **NO** query param to either Me endpoint.

### 11.3 Expected schema — frontend contract (normative)

Request: **UNCHANGED.** `GET /api/v1/me/certificates?status=&limit=&offset=` and `GET /api/v1/me/certificates/{id}` behave exactly as today; the frontend sends nothing new.

Response: each item in list `data[]` AND the single `data` object gain one **REQUIRED** field:

```json
{ "generation_mode": "file" }
{ "generation_mode": "template" }
```

Full item shape becomes: `id`, `certificate_number`, `recipient_name`, `issued_at`, `expires_at`, `revoked_at`, `revoke_reason`, `status`, `event_id`, `event_name`, `generation_mode`, `created_at`. Frontend mapping (informational — backend only returns the mode): `file` → "Uploaded", anything else → "System-generated". `file_path` inclusion is **OPTIONAL** (frontend fallback only); the implementation **SHALL NOT** add it unless free.

### 11.4 Additional constraints (normative)

- **CON-8 — Additive only.** The implementation **MUST NOT** change route paths, existing response keys, the owner guards (`403` not-owner, `404` unknown id), the `status` filter, or the `data`/`meta` pagination shape on either Me endpoint. The change is one additive **REQUIRED** key `generation_mode` per item.
- **CON-9 — Reuse resolver; eager-load attendee.** The implementation **MUST** call `CertificateSource::resolve()` (constructor-injected into `MeController`, per `principles.md` coding detail) and **MUST NOT** add duplicated `metadata['generation_mode']` branching. Both queries **MUST** eager-load the relation (`with(['event', 'attendee'])`) so the list issues no per-row attendee query.
- **CON-10 — OpenAPI + catalog mirror.** The implementation **MUST** add `OA\Property(property: "generation_mode", enum: ["template", "file"])` to the `MyCertificate` schema (which serves both `MyCertificateListResponse` and `MyCertificateSingleResponse` items). `config/cert-endpoints.php` **MUST** change only if the catalog enumerates response fields — it keys on method+path+level only (verified: `GET /api/v1/me/certificates` + `GET /api/v1/me/certificates/{id}` at `:56-57`), so the expected change is **none**; `api-endpoints.md` v-next **MUST** document the new field.

### 11.5 Additional decisions (normative — Final)

- **DEC-11 — `generation_mode` required on both Me endpoints (Final).** Both `GET /api/v1/me/certificates` items and `GET /api/v1/me/certificates/{id}` **SHALL** include `generation_mode: file | template` resolved by `CertificateSource::resolve()` exactly as the admin list/show resolve it. Absent-attendee + null/invalid `certificates.metadata` resolves `template` (existing default, unchanged). No `?source=` param is added to either endpoint (I3).

### 11.6 Additional acceptance criteria (objective, machine-checkable)

- **ACC-17 — List exposes source without N+1.** Seeded fixture (JWT owner): ≥1 `file`-mode cert + ≥1 `template`-mode cert for the caller. `GET /api/v1/me/certificates` returns the file item with `generation_mode=file` and the template item with `generation_mode=template`; the implementation asserts no per-row attendee query (query-count assertion or eager-load presence on the list query).
- **ACC-18 — Detail exposes source; guards unchanged.** Detail for the caller's own uploaded cert returns `200` with `generation_mode=file`; another owner's id returns `403` (`not_owner`) as today; unknown id returns `404` as today.
- **ACC-19 — User-run green.** User runs from repo root, suites sequentially: `docker compose exec cert-app php artisan test` (full cert suite) + new request-level test in the existing source-suite style (`CertificateSourceTest`, one behavior per test, `RefreshDatabase`, self-seeded org) — all green, pasted results. (Runner note: v1.1 CON-6/D-3 records cert-app historically required `php vendor/bin/phpunit` via `.\scripts\run-tests.ps1 -Target cert` — user confirms the working runner at verification time and pastes green output.)

### 11.7 Deliverables

- **D-6 — This spec amendment (Phase 1, this phase only).** `assemblies/loa-cert-platform/certificate-source-spec.md` §11 + metadata bump to v1.3 DRAFT. No code, migration, config, or test touched.
- **D-7 — Future implementation (DEFERRED — listed WITHOUT touching, needs Final first).**
  - `app/Http/Controllers/MeController.php` — constructor-inject `CertificateSource`; `certificates()` + `certificate()` `with(['event', 'attendee'])`; `formatCertificate()` additive `generation_mode`; `MyCertificate` OA property.
  - `api-endpoints.md` v-next (§5 Me group: document `generation_mode` on both endpoints; no route/catalog change).
  - Tests: new request-level cases in source-suite style covering ACC-17–ACC-18 (list both modes + no-N+1, detail 200/403/404).

### 11.8 Open DECs — RESOLVED (Final)

1. **DEC-11:** `generation_mode` required on both Me endpoints via `resolve()` reuse + attendee eager load; no `?source=` filter; absent/invalid metadata → `template` — **APPROVED Final**.

**Status:** §11 is Final. Phase 2 implementation exactly to §11 + TDD (ACC-17–ACC-19) may proceed.

**Sign-off 2026-09-25:** frontend source contract verified against current code (9/9 PASS — admin list/show, store, upload, resend-email, Me list/detail with `generation_mode`, attendee writes, public verify/view/download); no GAPs, no frontend work open.

---

## 12. Amendment v1.4 Final — Linked-certificate status in attendee list

> **Status:** Final (approved 2026-09-25). Phase 1 delivered this spec text only — no code, migration, config edit, or test touched.

### 12.1 Context

The event roster UI must badge each attendee whose linked certificate is revoked and disable resend for those rows. Frontend (e-cert repo) is already shipped: it reads `certificate?.revoked_at ?? certificates?.revoked_at` (either key) and degrades to binary Yes/No (issued vs not) when the object is absent. Today `GET /api/v1/events/{id}/attendees` never returns the linked certificate, so revoked rows are indistinguishable from active ones.

Concerned endpoint (only this one — nothing else in this task):

- `GET /api/v1/events/{id}/attendees` → `AttendeeController::index:116-209`

It serializes Eloquent models directly (`'data' => $attendees`, `:199-200`) under the `Attendee` OA schema (`AttendeeController.php:15-30`, served via `AttendeeListResponse:31-39`). Note: `AttendeeDeletePreviewResponse:77-82` already carries `linked_certificate` (uuid string) + `deletes_certificate` (bool) — that shape belongs to the destroy-preview endpoint only and is **NOT** the canonical key here; the canonical key for this amendment is the `certificate` relation object (see DEC-12).

### 12.2 Investigation findings (verified, read-only)

- **I1 — Eager load does not interfere with the status-filter join/select in any filter mode.** Base query is `EventAttendee::where('event_attendees.event_id', $eventId)` (`:127`). The `?status=` branch (`:151-182`) adds `select('event_attendees.*')` (collision guard, `:153-157`) + `leftJoin('certificates', 'event_attendees.certificate_id', '=', 'certificates.id')` (`:158`) on the **base** query. A `with('certificate:id,revoked_at,expires_at')` eager load runs as a **separate** `whereIn` query on `certificates.id` — it touches neither the base `select` nor the join, so it is safe in all five modes (no filter + `not_issued` / `issued` / `revoked` / `expired`). The FK it needs (`event_attendees.certificate_id`) is present in both modes (full model by default; `event_attendees.*` under `?status=`). The count (`(clone $query)->count()`, `:189`) is an aggregate — eager loads do not alter it — and pagination (`limit` cap 100, `offset`, `:185-193`) plus `links`/`meta` are untouched.
- **I2 — Relation serializes as `certificate` (singular).** `EventAttendee::certificate(): BelongsTo` exists (`EventAttendee.php:58-61`, FK `certificate_id`). Eloquent serializes an eager-loaded relation under the method name, so the key is `certificate`; frontend also tolerates `certificates`, so either is consumable — this spec picks **ONE** canonical key, `certificate`, and documents it (see DEC-12).
- **I3 — No N+1; pagination/counts untouched.** One eager load serves the whole page (single extra query regardless of page size). No per-row query is added; `total`/`has_more`/`limit`/`offset`/`links` behaviour is unchanged (per I1).

### 12.3 Expected schema — frontend contract (normative)

Request: **UNCHANGED.** `GET /api/v1/events/{id}/attendees?search=&attended=&completed=&status=&limit=&offset=` behaves exactly as today; the frontend sends nothing new.

Response: each item in list `data[]` gains one **REQUIRED** key:

```json
{ "certificate": { "id": "uuid", "revoked_at": "2026-09-01T00:00:00Z", "expires_at": null } }
{ "certificate": null }
```

`certificate` is `null` when the attendee's `certificate_id` is null (unissued). When linked, it carries exactly `id` (reference/link), `revoked_at` (drives the Revoked badge + resend disable), `expires_at` (informational). Frontend mapping (informational — backend only returns the object): `certificate.revoked_at` non-null → Revoked badge + disable resend; `certificate: null` → binary not-issued state.

### 12.4 Additional constraints (normative)

- **CON-11 — Additive only.** The implementation **MUST NOT** change route paths, remove/rename any existing attendee key, change any filter (`search` / `attended` / `completed` / `status`), alter pagination (`limit` cap 100, `offset`), `links`/`meta` shape, guards (`401` unauthenticated, `404` unknown event), the `{status: error, message}` error shape, or the `loa_cert` schema (no migration). The change is one additive **REQUIRED** key `certificate` per list item (object or `null`). OpenAPI: the `Attendee` schema (`AttendeeController.php:15-30`) gains **one** nullable object property; `config/cert-endpoints.php` **MUST NOT** change (catalog keys on method+path+level only — response fields are not catalogued); `api-endpoints.md` v-next **MUST** document the new field so the mirror stays accurate.

### 12.5 Additional decisions (normative — Final (approved 2026-09-25))

- **DEC-12 — List items gain REQUIRED `certificate: { id, revoked_at, expires_at } | null` (Final (approved 2026-09-25)).** `AttendeeController::index` **SHALL** eager-load `with('certificate:id,revoked_at,expires_at')` on the base query (applies uniformly — no per-filter branching), and the existing model-to-JSON serialization **SHALL** carry it through with no manual mapping. Canonical key is `certificate` (singular, the Eloquent default — matches the relation name and one of the two frontend-tolerated keys). Column subset is exactly `id, revoked_at, expires_at` — the implementation **MUST NOT** select broader certificate columns (minimal additive surface; no audit/noise leakage). `certificate_id` null → `certificate: null`.

### 12.6 Additional acceptance criteria (objective, machine-checkable)

- **ACC-20 — Field present in both states; agrees with `?status=` filter.** Seeded fixture: ≥1 attendee linked to a revoked certificate + ≥1 attendee with `certificate_id` null. `GET /api/v1/events/{id}/attendees` returns the revoked row with `certificate` object and `certificate.revoked_at` non-null (with matching `id` passthrough), and the unissued row with `certificate: null`. Cross-agreement: `?status=revoked` rows and rows with `certificate.revoked_at != null` are the same set; `?status=not_issued` rows all carry `certificate: null`.
- **ACC-21 — User-run green.** User runs from the **repo root** (`loa-platform` project, never the assembly-level compose file), suites **sequentially**: `docker compose exec cert-app php artisan test` (full cert suite) + one new request-level test in the existing suite style asserting the field in both states (revoked-linked object + unissued `null`) — one behavior per test, `RefreshDatabase`, self-seeded org row, JWT via test helper (never hardcoded tokens). No change is complete until the user pastes green results. (Runner note: v1.1 CON-6/D-3 records cert-app historically required `php vendor/bin/phpunit` via `.\scripts\run-tests.ps1 -Target cert` — user confirms the working runner at verification time and pastes green output.)

### 12.7 Deliverables

- **D-8 — This spec amendment (Phase 1, this phase only).** `assemblies/loa-cert-platform/certificate-source-spec.md` §12 + metadata bump to v1.4 Final. No code, migration, config, or test touched.
- **D-9 — Future implementation (now approved — implement exactly to §12).**
  - `app/Http/Controllers/AttendeeController.php` — `index()` gains `with('certificate:id,revoked_at,expires_at')` + `OA\Property(property: "certificate", nullable object with `id`/`revoked_at`/`expires_at`)` on the `Attendee` schema (`:15-30`).
  - Attendee OA schema mirror only (no `config/cert-endpoints.php` change per CON-11) + `api-endpoints.md` v-next field documentation.
  - Tests: new request-level case(s) in the existing suite style covering ACC-20 (revoked object + unissued null + `?status=` agreement).

### 12.8 Open DECs — RESOLVED (Final 2026-09-25)

1. **DEC-12:** list items gain REQUIRED `certificate: { id, revoked_at, expires_at } | null` (null when `certificate_id` null); canonical key `certificate` — **APPROVED Final**.

**Status:** §12 is Final. Phase 2 implementation exactly to §12 + TDD (ACC-20–ACC-21) may proceed.
