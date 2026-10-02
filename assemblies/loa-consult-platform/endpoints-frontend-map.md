# LOA Consult Platform — Frontend Endpoint Map
## Product Assembly Component Specification

| Field | Value |
|-------|-------|
| ID | CONSULT-FE-MAP-001 |
| Title | Frontend Endpoint Map (per-table request/response contract) |
| Status | **Final v1.1** (user-approved 2026-10-02; §5 appendix added, no shape change) |
| Owner | Consult Platform assembly |
| Version | 1.1 Final |
| Scope | The frontend-facing view of the Consult API: per table, which endpoints it serves, the exact request and response schema with wire→frontend field mapping. Behavior is cited to the owning module spec (DEC-3). T2-consumed areas only. |
| Non-goals | Redefining paths, levels, or table shapes (owned by `api-endpoints.md` v2.1 §5 + `data-model.md` v1.3 §3 + the module specs); Phase E reports (deferred by user 2026-10-01); Auth-owned families (DEC-2a open); any envelope migration; frontend code |
| Layer | Product Assembly (`assemblies/loa-consult-platform/`) |

## RFC 2119 terminology

The key words MUST, MUST NOT, REQUIRED, SHALL, SHALL NOT, SHOULD, SHOULD NOT, RECOMMENDED, MAY, and OPTIONAL in this document are to be interpreted as described in RFC 2119.

## Context

The `e-consultation` Next.js app has 112+ internal `app/api/**/route.ts` handlers and typed modules (`lib/api/appointments.ts`, `lib/api/availability.ts`) that still wrap the legacy `/api/*` paths. Three contracts already exist and differ in scope: `api-endpoints.md` Final v2.1 §5 (104+5 catalog + levels + bare shapes), the per-area module specs, and `e-consultation/specs/endpoint-catalog.md` (143 entries — flagged **not truth** in its own repo).

What none of them states: for a given table, **which fields actually cross the wire on a given request or response, in which envelope, required versus optional, and how each one maps to the camelCase name the frontend already uses.** `data-model.md` Final v1.3 §3 gives table columns; `api-endpoints.md` §3.4 gives the envelope; the module specs give prose. The wire projection — the third thing — is what this spec owns.

## Constraints

- **CON-1 (shape vs projection)** — Table **shape** (columns, types, constraints) is owned by `data-model.md` Final v1.3 §3 and MUST NOT be restated here as a column list. The **wire projection** (which fields appear on a given request or response, in which envelope, required versus optional) is owned by this spec. This is a distinct artifact, not a fourth copy of the shape.
- **CON-2** — Response schemas MUST be the bare shapes `api-endpoints.md` v2.1 §3.4 defines (`{appointments}`, `{appointment}`, `{data}`, `{success:true}`, `{error}`). No `{data,meta}` envelope.
- **CON-3** — Every response and request field MUST carry both the **wire field** (snake_case, authoritative per `data-model.md` v1.3 §2) and the **frontend field** (camelCase per `lib/types/*`). The mapping is produced by the frontend's typed module at the client boundary — `EC-API-001` Final v1.1 CON-6 forbids reshaping responses to match legacy Supabase output, so the backend does not rename; this spec states the mapping so no one guesses.
- **CON-4** — A table section MUST NOT specify an endpoint the Consult API does not serve. Phase E `/reports/*` (deferred by user 2026-10-01), `audit-logs` (blocked by the `data-model.md` v1.3 §3 gate), `bug-reports`, and `audit/forbidden` appear only as pointer rows naming their owner and the reason for absence.
- **CON-5** — The `data-model.md` v1.3 §3 Status column is binding. A `Delta pending` table MUST carry its delta marker in the section header (no new columns implied); a `Specified — not migrated` table MUST NOT have a codeable section.
- **CON-6** — Levels are owned by `api-endpoints.md` v2.1 §5. Each endpoint row cites its level and owner spec ID; this spec MUST NOT restate or re-derive a level.
- **CON-7** — Bare shapes stay until cutover (assembly `AGENTS.md` §1.4). Any envelope or field-rename change is cutover work requiring its own spec.
- **CON-8** — This spec MUST NOT be promoted to Final until the user confirms the D-2 table set is complete and every `ACC-*` is checkable against a served endpoint.
- **CON-9 (provenance)** — Every field row MUST cite its source: a `data-model.md` §3 column, or an owner module spec when the field is request-only (not a column). On any conflict between this spec and an owner spec, the **owner wins** and this spec is corrected — never the reverse.

## Goal

### Decisions

- **DEC-1** — Per-table layout, not per-page. A table section lists its endpoints, request, response, field mapping, and a service block if any. Rationale: matches the frontend's ask, matches `data-model.md` v1.3's own table-per-section shape, and stays stable when pages are reorganized.
- **DEC-2** — Hybrid boundary. Full field-level projection for tables the UI touches today with a served endpoint; pointer rows for deferred, unserved, and Auth-owned surfaces. Rationale: gives the frontend one file to code against without becoming a fourth full catalog.
- **DEC-3** — **No service blocks.** Behavior that lives outside CRUD (validation, conflicts, gating, computation, side-effect chains) is **cited to its owning module spec**, not restated: `endpoints-appointments.md`, `endpoints-academic.md`, `endpoints-evaluations.md`, `endpoints-admin-import.md`. Rationale (revised 2026-10-01): the wire projection is what this spec owns (CON-1); behavioral rules belong to the module specs that already hold them, and restating them created a second place to drift. This supersedes the earlier DEC-3, which proposed service blocks for `appointments`, `evaluation_periods`, `evaluations`, `evaluation_results`, `import`, and `evaluation_comments`.
- **DEC-4** — Draft v0.1 ships the skeleton plus the `appointments` section to lock the section template before it repeats. The table set (D-2) is confirmed by the user before promotion.

### Acceptance — Objective (machine-checkable)

- **ACC-1** — For every endpoint row, the (method, path, level) triple matches `api-endpoints.md` v2.1 §5 and `config/consult-endpoints.php` for that area; a mismatch fails the check.
- **ACC-2** — Every field row carries both a wire name and a frontend name, **and** a source citation (a `data-model.md` §3 column or an owner module spec). A row missing either fails.
- **ACC-3** — No section names an endpoint absent from the served set, except explicit pointer rows (CON-4).
- **ACC-4** — Every `Delta pending` table section carries the delta marker; no `Specified — not migrated` table has a codeable section (CON-5).
- **ACC-5** — Every behavioral rule referenced in a table section resolves to a module-spec citation (`endpoints-*.md` + section). No table section restates a rule; no service is specified in this document.

### Acceptance — Subjective (human-judged)

- **ACC-S1** — A frontend developer picks any table, reads one section, and can write the typed module without opening another spec.
- **ACC-S2** — Reviewer confirms no projection in this spec contradicts `data-model.md` v1.3 §3 or a module spec, and that no table shape is restated here (CON-1).

## Deliverables

- **D-1** — Metadata, RFC 2119, Context, CON-1…CON-9, DEC-1…DEC-4, ACC-1…ACC-5 + ACC-S1/S2, Glossary, References, Document Control.
- **D-2** — Table sections, one per table in the user-confirmed set: `appointments`, `appointment_time_slots`, `appointment_attendees`, `appointment_files`, `faculty_availability_rules`, `students`, `employees`, `departments`, `department_courses`, `subjects`, `sections`, `faculty_subjects`, `student_enrollments`, `semesters`, `evaluation_periods`, `rating_scales`, `rubric_groups`, `rubric_categories`, `rubric_items`, `rubric_group_snapshots`, `evaluations`, `evaluation_ratings`, `evaluation_comments`, `evaluation_results`. Set confirmed before Final (ACC-1/ACC-4).
- **D-3** — Superseded by DEC-3 (2026-10-01). Behavior is cited to the module specs; no service blocks are written in this document.
- **D-4** — Pointer rows: Auth-owned (`auth/{me,access,users,onboarding}`, `access-config`, `user-permissions`), Phase E reports (7 reads + 2 sentiment writes, deferred), `audit-logs` (gate-blocked), `bug-reports` + `audit/forbidden` (§2.2, stay in Next.js).
- **D-5** — Pointer line in assembly `AGENTS.md` §4 + a root `TODO.md` entry.

## Glossary

| Term | Meaning |
|------|---------|
| Wire field | The snake_case name as it crosses HTTP, authoritative per `data-model.md` v1.3 §2 |
| Frontend field | The camelCase name in `lib/types/*`, produced by the typed module's mapping |
| Projection | Which fields cross the wire on a given endpoint, in which envelope, required or optional (owned here, CON-1) |
| Pointer row | A named surface with owner and reason for absence, no schema (CON-4) |
| Delta pending | Migration + model exist; `data-model.md` v1.3 §7 follow-ups not landed |

## References

- `api-endpoints.md` Final v2.1 — §3.4 bare shapes, §4 levels, §5 catalog (paths + levels owner)
- `data-model.md` Final v1.3 — §2 casing adaptation, §3 table shapes + Status gate, §7 baseline delta
- `endpoints-appointments.md` Final v1.0 · `endpoints-academic.md` v1.1 · `endpoints-evaluations.md` v1.1 · `endpoints-admin-import.md` v1.1 · `endpoints-reports.md` v1.0 (deferred)
- `url-flattening.md` Final v1.1 — flat scheme, single scoped results surface
- `auth-integration.md` Final v1.6 §3 — SSO trio shapes
- `e-consultation` (reference only): `lib/api/{client,appointments,availability}.ts`, `lib/types/*`, `app/api/v1/[...path]/route.ts`, `specs/services/api-client.md` (`EC-API-001` CON-6/CON-7), `specs/cutover-headline.md` CON-8
- Assembly `AGENTS.md` §1.4 (no breaking changes), §1.9 (spec format), §2 (thin models)

---

## Document Control

- **Status:** Draft v0.1
- **Created:** 2026-10-01 from the `e-consultation` scan (112+ `route.ts`, ~70 pages, no `app/api/reports/**`)
- **Revised 2026-10-01:** DEC-3 / ACC-5 / D-3 aligned with the written content. Service blocks were removed by user decision (behavior prose stripped from §3.8–§3.24, then the two in §3.1 and §3.5); behavioral rules are now cited to the owning module spec. No schema, endpoint, level, or shape changed in this revision — the field tables, endpoint tables, and pointer rows are untouched.
- **Promoted 2026-10-02:** v0.1 → **Final v1.0** (no normative change — table set confirmed as written: 24 sections + §4.1–§4.7 pointer rows; DEC-2a and Phase E remain open as tracked)
- **Revised 2026-10-02:** v1.0 → **Final v1.1** — §5 appendix added (six cited status/shape surprises). No endpoint, level, field, or shape changed; no new behavior specified.

---

# 3. Table Sections

## 3.1 `appointments`

**Status:** Implemented · **Shape owner:** `data-model.md` Final v1.3 §3.2 · **Contract owner:** `endpoints-appointments.md` Final v1.0 §1–§2

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| GET | `/api/v1/appointments` | read | `endpoints-appointments.md` §2 |
| POST | `/api/v1/appointments` | write | §2 |
| POST | `/api/v1/appointments/batch` | write | §2 |
| GET | `/api/v1/appointments/{id}` | read | §2 |
| POST | `/api/v1/appointments/{id}/{action}` | write | §2 |
| POST | `/api/v1/appointments/{id}/student-cancel` | write | §2 |
| GET | `/api/v1/appointments/faculty-booked` | read | §2 |
| POST | `/api/v1/appointments/{id}/retry-sync` | write | §2 |
| POST | `/api/v1/appointments/slots/{slotId}/teams-link` | write | §2 (`faculty_availability_rules` rules apply to the caller) |
| POST | `/api/v1/appointments/{id}/files` | write | §2 (`appointment_files`) |

Booking model (`endpoints-appointments.md` §1, cited not restated): STUDENT books self; FACULTY/DEAN book on-behalf or internal (`student_id` null); creator ≠ student → 400; statuses PENDING → APPROVED | REJECTED → COMPLETED | CANCELLED.

### Request — `POST /api/v1/appointments`

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `faculty_id` | `facultyId` | string (uuid) | yes | `data-model.md` §3.2 `appointments.faculty_id` |
| `session_group_id` | `sessionGroupId` | string \| null | no | §3.2 `appointments.session_group_id` |
| `date` | `date` | string `YYYY-MM-DD` | yes | §3.2 `appointments.date` |
| `start_time` | `startTime` | string `HH:MM` | yes | §3.2 `appointments.start_time` |
| `end_time` | `endTime` | string `HH:MM` | yes | §3.2 `appointments.end_time` |
| `time_slots` | `timeSlots` | array of `{date, start_time, end_time}` | no | `endpoints-appointments.md` §2 (request-only, not a column) |
| `title` | `title` | string \| null | yes (nullable) | §3.2 `appointments.title` |
| `description` | `description` | string \| null | no | §3.2 `appointments.description` |
| `attendee_ids` | `attendeeIds` | string[] | no | §2 (request-only; `employees.id` values) |
| `meeting_type` | `meetingType` | `"CONSULTATION"` | no | §3.2 `appointments.meeting_type` |

`time_slots` is an alternative to the `date` + `start_time` + `end_time` triple; one of the two forms is required. Validation failure → 400, with a `conflicts` key when present (§2).

### Response — 201 `{appointment, conflicts}`

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `appointment.id` | `appointment.id` | string | §3.2 `appointments.id` |
| `appointment.student_id` | `appointment.studentId` | string \| null | §3.2 `appointments.student_id` |
| `appointment.faculty_id` | `appointment.facultyId` | string | §3.2 `appointments.faculty_id` |
| `appointment.session_group_id` | `appointment.sessionGroupId` | string \| null | §3.2 `appointments.session_group_id` |
| `appointment.created_by_email` | `appointment.createdByEmail` | string | §3.2 `appointments.created_by_email` |
| `appointment.meeting_type` | `appointment.meetingType` | `"CONSULTATION" \| "INTERNAL"` | §3.2 `appointments.meeting_type` |
| `appointment.date` | `appointment.date` | string `YYYY-MM-DD` | §3.2 `appointments.date` |
| `appointment.start_time` | `appointment.startTime` | string `HH:MM` | §3.2 `appointments.start_time` |
| `appointment.end_time` | `appointment.endTime` | string `HH:MM` | §3.2 `appointments.end_time` |
| `appointment.title` | `appointment.title` | string \| null | §3.2 `appointments.title` |
| `appointment.description` | `appointment.description` | string \| null | §3.2 `appointments.description` |
| `appointment.status` | `appointment.status` | `PENDING\|APPROVED\|REJECTED\|COMPLETED\|CANCELLED` | §3.2 `appointments.status` |
| `appointment.action_taken` | `appointment.actionTaken` | string \| null | §3.2 `appointments.action_taken` |
| `appointment.additional_remarks` | `appointment.additionalRemarks` | string \| null | §3.2 `appointments.additional_remarks` |
| `appointment.teams_link` | `appointment.teamsLink` | string \| null | §3.2 `appointments.teams_link` |
| `appointment.teams_sync_status` | `appointment.teamsSyncStatus` | `UNWRITTEN\|WRITTEN\|FAILED` | §3.2 `appointments.teams_sync_status` |
| `appointment.teams_sync_retries` | `appointment.teamsSyncRetries` | int | §3.2 `appointments.teams_sync_retries` |
| `appointment.teams_sync_error` | `appointment.teamsSyncError` | string \| null | §3.2 `appointments.teams_sync_error` |
| `appointment.teams_sync_last_attempt` | `appointment.teamsSyncLastAttempt` | RFC 3339 \| null | §3.2 `appointments.teams_sync_last_attempt` |
| `appointment.requested_at` | `appointment.requestedAt` | RFC 3339 | §3.2 `appointments.requested_at` |
| `appointment.is_active` | `appointment.isActive` | bool | §3.2 `appointments.is_active` |
| `conflicts` | `conflicts` | array | `endpoints-appointments.md` §1–§2 (never silently dropped) |

**Variants.**

- Batch: 201 `{appointment, session_group_id, conflicts}` — one booking across `faculty_ids[]`, not a per-faculty fan-out (§2).
- `GET /appointments/faculty-booked`: `{appointments: [{date, start_time, end_time}]}` — lightweight slots only, never full records (§2). Do not type it as an `Appointment`.
- `GET /appointments`: `{appointments}` — role-split list, in-memory `q` filter over title/student/faculty names (§2).
- `GET /appointments/{id}`: `{appointment}` with the detail enrichment; service throw → 404 (§2).
- Errors: `{error}` string (§3.4); 400 on service error, 401 tokenless, 403 closed-by-default or insufficient level.

Validation rules (15-minute boundary, duration bounds, intra-appointment overlap, creator ≠ student, conflict computation) are in `endpoints-appointments.md` §1–§2; the frontend mirrors none of them and renders `conflicts` alongside any 201.

## 3.2 `appointment_time_slots`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.2 · **Access:** nested projection — no endpoint of its own

Slots appear inside `GET /appointments/{id}` (`{appointment.time_slots}`) and are written only through the parent write endpoints. There is **no** slot CRUD path and no list endpoint (ACC-3).

### Response projection — `GET /api/v1/appointments/{id}` → `appointment.time_slots[]`

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `id` | `id` | string | §3.2 `appointment_time_slots.id` |
| `appointment_id` | `appointmentId` | string | §3.2 `appointment_time_slots.appointment_id` |
| `date` | `date` | string `YYYY-MM-DD` | §3.2 `appointment_time_slots.date` |
| `start_time` | `startTime` | string `HH:MM` | §3.2 `appointment_time_slots.start_time` |
| `end_time` | `endTime` | string `HH:MM` | §3.2 `appointment_time_slots.end_time` |
| `teams_link` | `teamsLink` | string \| null | §3.2 `appointment_time_slots.teams_link` |
| `is_active` | `isActive` | bool | §3.2 `appointment_time_slots.is_active` |

**Write path (the only one):** `POST /api/v1/appointments/slots/{slotId}/teams-link` — body `{teams_link}`; must be a valid `https://teams.microsoft.com/...` URL, else 400; trimmed on save; 404 "Time slot not found"; returns `{success:true}` (`endpoints-appointments.md` §2).

## 3.3 `appointment_attendees`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.2 · **Access:** nested projection — written via `POST /appointments` `attendee_ids`

### Response projection — `GET /api/v1/appointments/{id}` → `appointment.attendees[]`

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `id` | `id` | string | §3.2 `appointment_attendees.id` |
| `appointment_id` | `appointmentId` | string | §3.2 `appointment_attendees.appointment_id` |
| `user_id` | `userId` | string | §3.2 `appointment_attendees.user_id` → `employees.id` (FK) |
| `status` | `status` | `INVITED\|ACCEPTED\|DECLINED` | §3.2 `appointment_attendees.status` |
| `is_mandatory` | `isMandatory` | bool | §3.2 `appointment_attendees.is_mandatory` |
| `is_active` | `isActive` | bool | §3.2 `appointment_attendees.is_active` |

**Write path:** `POST /api/v1/appointments` body `attendee_ids` (see §3.1). Responses come back through the `attendee-accept` / `attendee-decline` actions on `POST /appointments/{id}/{action}`, which return the **service payload, not a normalized `{appointment}`** (`endpoints-appointments.md` §2) — type these two actions separately from the others.

Note: `user_id` is an `employees` FK, not an arbitrary user (shape §3.2). A person who is both student and employee resolves here by their `employees.id`.

## 3.4 `appointment_files`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.2 · **Access:** nested projection — written via `POST /appointments/{id}/files`

### Response projection — `GET /api/v1/appointments/{id}` → `appointment.files[]`

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `id` | `id` | string | §3.2 `appointment_files.id` |
| `appointment_id` | `appointmentId` | string | §3.2 `appointment_files.appointment_id` |
| `file_name` | `fileName` | string | §3.2 `appointment_files.file_name` |
| `file_type` | `fileType` | string | §3.2 `appointment_files.file_type` |
| `file_data` | `fileData` | string (base64) | §3.2 `appointment_files.file_data` |
| `file_size` | `fileSize` | int | §3.2 `appointment_files.file_size` |
| `is_active` | `isActive` | bool | §3.2 `appointment_files.is_active` |

### Request — `POST /api/v1/appointments/{id}/files`

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `files` | `files` | array | yes | `endpoints-appointments.md` §2 (request-only) |
| `files[].file_name` | `fileName` | string | yes | `data-model.md` §3.2 `appointment_files.file_name` |
| `files[].file_type` | `fileType` | string | yes | §3.2 `appointment_files.file_type` |
| `files[].file_data` | `fileData` | string (base64) | yes | §3.2 `appointment_files.file_data` |
| `files[].file_size` | `fileSize` | int | yes | §3.2 `appointment_files.file_size` |

Upload rules (`endpoints-appointments.md` §2, `api-endpoints.md` §3.7): FACULTY/DEAN holders **and** `appointment.faculty_id === caller id`, else 403; 5 MB max per file; images only (PNG/JPEG/GIF/WebP); dedup by `file_name` + `file_size`, skipped silently. Returns `{files: [created…]}` — only the created rows, not the skipped ones, so a partial dedup is invisible to the client.

Wire format is **base64 inside JSON**, not multipart (`api-endpoints.md` §3.7). Reading `file_data` back is a JSON string, not a Blob; a streaming download path needs its own decision.

## 3.5 `faculty_availability_rules`
**Status:** Implemented · **Shape owner:** `data-model.md` §3.2 · **Contract owner:** `endpoints-appointments.md` §3

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| GET | `/api/v1/availability-rules` | read | `endpoints-appointments.md` §3 |
| POST | `/api/v1/availability-rules` | write | §3 |

### Request — `POST /api/v1/availability-rules`

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `day_of_week` | `dayOfWeek` | int 0–6 | yes | `data-model.md` §3.2 `faculty_availability_rules.day_of_week` |
| `is_blocked` | `isBlocked` | bool | no | §3.2 `faculty_availability_rules.is_blocked` |
| `start_time` | `startTime` | string `HH:MM` \| null | no | §3.2 `faculty_availability_rules.start_time` |
| `end_time` | `endTime` | string `HH:MM` \| null | no | §3.2 `faculty_availability_rules.end_time` |
| `start_date` | `startDate` | string `YYYY-MM-DD` | yes | §3.2 `faculty_availability_rules.start_date` |
| `end_date` | `endDate` | string `YYYY-MM-DD` \| null | no | §3.2 `faculty_availability_rules.end_date` |
| `faculty_id` | `facultyId` | string | no | §3.2 `faculty_availability_rules.faculty_id` → `employees.id` |

### Response — `{rule}`

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `id` | `id` | string | §3.2 `faculty_availability_rules.id` |
| `faculty_id` | `facultyId` | string | §3.2 `faculty_availability_rules.faculty_id` |
| `day_of_week` | `dayOfWeek` | int 0–6 | §3.2 `faculty_availability_rules.day_of_week` |
| `is_blocked` | `isBlocked` | bool | §3.2 `faculty_availability_rules.is_blocked` |
| `start_time` | `startTime` | string \| null | §3.2 `faculty_availability_rules.start_time` |
| `end_time` | `endTime` | string \| null | §3.2 `faculty_availability_rules.end_time` |
| `start_date` | `startDate` | string `YYYY-MM-DD` | §3.2 `faculty_availability_rules.start_date` |
| `end_date` | `endDate` | string \| null | §3.2 `faculty_availability_rules.end_date` |
| `is_active` | `isActive` | bool | §3.2 `faculty_availability_rules.is_active` |

`GET /availability-rules` returns `{rules}`. Query `faculty_id` optional: no query → FACULTY/DEAN holders read their own (others 401); another faculty's rules require ADMIN-group membership (403 otherwise) — `endpoints-appointments.md` §3. Upsert key and non-ADMIN self-forcing are in the same §3.

## 3.6 `students`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.1.1 · **Access:** link read only — no user CRUD (`api-endpoints.md` §5.3)

**No CRUD endpoints exist and none may be added here.** Identity is Auth-owned (`endpoints-admin-import.md` Final v1.1 DEC-1/DEC-2); consult resolves a person through the Auth↔domain email link and exposes link reads only.

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| GET | `/api/v1/users/primary` | read | `api-endpoints.md` §5.3 · `endpoints-admin-import.md` DEC-1 |
| GET | `/api/v1/users/attendees` | read | §5.3 · DEC-1 |
| GET | `/api/v1/users/{id}/related-data` | read | §5.3 · DEC-1 |

### Response fields — link reads

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `user.id` | `id` | string | Auth user id (not a `students` column) |
| `user.email` | `email` | string | §3.1.1 `students.email` (link key) |
| `user.name` | `name` | string | §3.1.1 `students.name` |
| `student.id` | `studentId` | string | §3.1.1 `students.id` — null when unlinked |
| `student.student_number` | `studentNumber` | string | §3.1.1 `students.student_number` |
| `student.course_id` | `courseId` | string \| null | §3.1.1 `students.course_id` → `department_courses.id` |
| `student.is_active` | `isActive` | bool | §3.1.1 `students.is_active` |

`related-data` returns the Auth user plus the linked `students` row **or an explicit unlinked marker** — absence is a state, not an empty object (`endpoints-admin-import.md` ACC-2).

Columns the frontend may see but that no link read returns: `student_number` is written by import/SSO only (`auth-integration.md` §7 keeps domain attributes local). No endpoint mutates a `students` row from the consult API; user writes attempted here → 404 by design (`endpoints-admin-import.md` ACC-2).

## 3.7 `employees`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.1.2 · **Access:** link read only — same as §3.6

### Response fields — link reads

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `user.id` | `id` | string | Auth user id |
| `user.email` | `email` | string | §3.1.2 `employees.email` (link key) |
| `user.name` | `name` | string | §3.1.2 `employees.name` |
| `employee.id` | `employeeId` | string | §3.1.2 `employees.id` — null when unlinked |
| `employee.employee_number` | `employeeNumber` | string \| null | §3.1.2 `employees.employee_number` (nullable by shape) |
| `employee.department_id` | `departmentId` | string \| null | §3.1.2 `employees.department_id` → `departments.id` |
| `employee.is_active` | `isActive` | bool | §3.1.2 `employees.is_active` |

**Why `employees` and not `users`:** `faculty_id` on appointments, `user_id` on attendees, and `dean_id` on departments all reference `employees.id` (`data-model.md` §3.2/§3.1). A person who is both student and employee has **two** ids, one per table — `students.id` for the evaluator/booker side, `employees.id` for the faculty side. A single `userId` type in the frontend produces FK 400s.

Groups (`aces-admin`, `aces-dean`, `aces-faculty`, `aces-user`) are **not** a column here. They come from the JWT `groups` claim (`api-endpoints.md` §4.0); no endpoint returns them (`auth-integration.md` §3 — the callback response carries no groups or permissions).

## 3.8 `departments`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.1 · **Contract owner:** `endpoints-academic.md` §3

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| GET | `/api/v1/departments` | read | `endpoints-academic.md` §3 |
| POST | `/api/v1/departments` | admin | §3 |
| PATCH | `/api/v1/departments/{id}` | admin | §3 |

`GET` returns a **bare array**; `POST` and `PATCH` return the resource directly.

### Request — `POST /api/v1/departments`

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `name` | `name` | string | yes | `data-model.md` §3.1 `departments.name` |
| `code` | `code` | string | yes | §3.1 `departments.code` |
| `dean_id` | `deanId` | string \| null | no | §3.1 `departments.dean_id` → Auth sub |

### Request — `PATCH /api/v1/departments/{id}`

All four fields optional; an empty body is a 400.

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `name` | `name` | string | no | §3.1 `departments.name` |
| `code` | `code` | string | no | §3.1 `departments.code` |
| `dean_id` | `deanId` | string \| null | no | §3.1 `departments.dean_id` |
| `is_active` | `isActive` | bool | no | §3.1 `departments.is_active` |

### Response — `departments[]` / created / updated (bare resource)

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `id` | `id` | string | §3.1 `departments.id` |
| `name` | `name` | string | §3.1 `departments.name` |
| `code` | `code` | string | §3.1 `departments.code` |
| `dean_id` | `deanId` | string \| null | §3.1 `departments.dean_id` |
| `is_active` | `isActive` | bool | §3.1 `departments.is_active` |
| `created_at` / `updated_at` | `createdAt` / `updatedAt` | RFC 3339 | §3.1 `departments` timestamps |

No `DELETE` endpoint exists — a delete control has nothing to call (ACC-3).

## 3.9 `department_courses`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.1 · **Contract owner:** `endpoints-academic.md` §4

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| GET | `/api/v1/department-courses` | read | `endpoints-academic.md` §4 |
| POST | `/api/v1/department-courses` | write | §4 |
| DELETE | `/api/v1/department-courses/{id}` | admin | §4 |

`GET` returns a bare array, each course with an embedded `department`.

### Request — `POST /api/v1/department-courses`

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `department_id` | `departmentId` | string | yes | `data-model.md` §3.1 `department_courses.department_id` |
| `name` | `name` | string | yes | §3.1 `department_courses.name` |
| `code` | `code` | string | yes | §3.1 `department_courses.code` |

### Response — created

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `id` | `id` | string | §3.1 `department_courses.id` |
| `department_id` | `departmentId` | string | §3.1 `department_courses.department_id` |
| `name` | `name` | string | §3.1 `department_courses.name` |
| `code` | `code` | string | §3.1 `department_courses.code` |
| `is_active` | `isActive` | bool | §3.1 `department_courses.is_active` |
| `department` | `department` | `{name, code}` \| **null** | embedded via join on `departments` — nullable, so error responses do not throw |

`DELETE` is a hard delete, CASCADE to `sections`, returns `{success:true}` (§4).

## 3.10 `subjects`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.1 · **Contract owner:** `endpoints-academic.md` §5

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| POST | `/api/v1/subjects` | admin | `endpoints-academic.md` §5 |
| PATCH | `/api/v1/subjects/{id}` | admin | §5 |

No `GET /subjects` endpoint exists — subject data reaches the client through `faculty-subjects`, `student-enrollments`, `evaluations`, and results endpoints (ACC-3).

### Request — `POST /api/v1/subjects`

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `code` | `code` | string | yes | `data-model.md` §3.1 `subjects.code` |
| `name` | `name` | string | yes | §3.1 `subjects.name` |

### Request — `PATCH /api/v1/subjects/{id}`

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `code` | `code` | string | no | §3.1 `subjects.code` |
| `name` | `name` | string | no | §3.1 `subjects.name` |
| `is_active` | `isActive` | bool | no | §3.1 `subjects.is_active` |

Response is the bare resource (created 201 / updated 200).

## 3.11 `sections`

**Status:** **Delta pending** — DDL baseline landed, `data-model.md` §7 follow-ups not landed; no column beyond the §3.1 shape may be implied · **Shape owner:** `data-model.md` §3.1 · **Contract owner:** `endpoints-academic.md` §6

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| POST | `/api/v1/sections` | admin | `endpoints-academic.md` §6 |
| PATCH | `/api/v1/sections/{id}` | admin | §6 |
| POST | `/api/v1/sections/fix-names` | admin | §6 |

### Request — `POST /api/v1/sections`

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `name` | `name` | string | yes | `data-model.md` §3.1 `sections.name` |
| `department_course_id` | `departmentCourseId` | string | yes | §3.1 `sections.department_course_id` → `department_courses.id` |

### Request — `PATCH /api/v1/sections/{id}`

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `name` | `name` | string | no | §3.1 `sections.name` |
| `department_course_id` | `departmentCourseId` | string | no | §3.1 `sections.department_course_id` |
| `is_active` | `isActive` | bool | no | §3.1 `sections.is_active` |

### Response — bare resource

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `id` | `id` | string | §3.1 `sections.id` |
| `name` | `name` | string | §3.1 `sections.name` |
| `program` | `program` | string | §3.1 `sections.program` — **server-derived** from `department_courses.code`; never sent by the client |
| `department_course_id` | `departmentCourseId` | string | §3.1 `sections.department_course_id` |
| `is_active` | `isActive` | bool | §3.1 `sections.is_active` |

### `POST /sections/fix-names` — response

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `fixed` | `fixed` | int | `endpoints-academic.md` §6 |
| `fixes[].id` | `id` | string | `data-model.md` §3.1 `sections.id` |
| `fixes[].old_name` | `oldName` | string | §3.1 `sections.name` (pre-fix) |
| `fixes[].new_name` | `newName` | string | §3.1 `sections.name` (post-fix) |
| `fixes[].program` | `program` | string | §3.1 `sections.program` |

## 3.12 `faculty_subjects`

**Status:** **Delta pending** — DDL baseline landed, §7 follow-ups not landed · **Shape owner:** `data-model.md` §3.1 · **Contract owner:** `endpoints-academic.md` §7

Faculty Loading: a faculty member teaches a subject, optionally scoped to a section and semester.

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| POST | `/api/v1/faculty-subjects` | admin | `endpoints-academic.md` §7 |
| POST | `/api/v1/faculty-subjects/reassign` | admin | §7 |

No `GET /faculty-subjects` endpoint exists — mappings are read through evaluations, enrollment, and results endpoints (ACC-3).

### Request — `POST /api/v1/faculty-subjects`

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `faculty_id` | `facultyId` | string | yes | `data-model.md` §3.1 `faculty_subjects.faculty_id` → `employees.id` |
| `subject_id` | `subjectId` | string | yes | §3.1 `faculty_subjects.subject_id` → `subjects.id` |
| `section_id` | `sectionId` | string \| null | yes (nullable) | §3.1 `faculty_subjects.section_id` → `sections.id` |
| `semester_id` | `semesterId` | string \| null | no | §3.1 `faculty_subjects.semester_id` — repo-layer field, **verify at build** (`endpoints-academic.md` §7) |

`faculty_id`, `subject_id`, `section_id` are accepted **snake_case as implemented** (§7); the frontend field column shows the camelCase name the typed module maps to. 201 `{data}` — the `data` wrapper, not a bare resource.

A NULL `semester_id` is a cross-semester mapping (`data-model.md` §3.1); reassign flows treat it as "all periods".

### Request — `POST /faculty-subjects/reassign`

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `old_faculty_subject_id` | `oldFacultySubjectId` | string | yes | `endpoints-academic.md` §7 (request-only) |
| `new_faculty_id` | `newFacultyId` | string | yes | §7 → `employees.id` |

Returns `{success:true}`.

### Service — reassign side-effect chain

The client cannot skip, reorder, or partially trigger these; a 200 means all three ran (`endpoints-academic.md` §7).

| # | Effect |
|---|-------|
| 1 | Update the mapping to `new_faculty_id` |
| 2 | Invalidate evaluations for that mapping (`invalidateByFacultySubject`); remarks cite the acting admin + reason |
| 3 | Recompute results for all periods of the mapping's semester (when a semester is present) |

## 3.13 `student_enrollments`

**Status:** **Delta pending** — DDL baseline landed, §7 follow-ups not landed · **Shape owner:** `data-model.md` §3.1 · **Contract owner:** `endpoints-academic.md` §8

A student in a section for a semester under a faculty mapping.

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| POST | `/api/v1/student-enrollments` | admin | `endpoints-academic.md` §8 |
| DELETE | `/api/v1/student-enrollments/{id}` | admin | §8 |

### Request — `POST /api/v1/student-enrollments`

The body is passed through to `createEnrollment(body, actorId)` with validation in the service, so it is **not a closed schema** (`endpoints-academic.md` §8). Known fields:

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `student_id` | `studentId` | string | yes | `data-model.md` §3.1 `student_enrollments.student_id` → `students.id` |
| `section_id` | `sectionId` | string \| null | no | §3.1 `student_enrollments.section_id` → `sections.id` |
| `semester_id` | `semesterId` | string \| null | no | §3.1 `student_enrollments.semester_id` |
| `faculty_subject_id` | `facultySubjectId` | string \| null | no | §3.1 `student_enrollments.faculty_subject_id` → `faculty_subjects.id` |

201 `{data}`. A NULL `faculty_subject_id` is the unenrolled-from-mapping case — the evaluation bypass (`data-model.md` §3.1).

### Service — delete side-effect chain

Runs only when `faculty_subject_id` is present; then the row is deleted. Returns `{success:true}` (`endpoints-academic.md` §8).

| # | Effect |
|---|-------|
| 1 | Invalidate that student's evaluations for the mapping; remarks cite the admin + removal |
| 2 | Recompute results for all periods of the enrollment's semester (when present) |
| 3 | Delete the enrollment row |

## 3.14 `semesters`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.1 · **Contract owner:** `endpoints-academic.md` §9

**Invariant: exactly one active semester** (`data-model.md` §3.1) — the same rule the legacy `proxy.ts` gate enforces (`frontend-transition.md` DEC-5, semester-lock). One source, two enforcement points.

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| GET | `/api/v1/semesters` | read | `endpoints-academic.md` §9 |
| POST | `/api/v1/semesters` | write | §9 (create) |
| GET | `/api/v1/semesters/{id}` | read | §9 |
| POST | `/api/v1/semesters/{id}` | admin | §9 (activate — a distinct action, not an update alias) |
| PATCH | `/api/v1/semesters/{id}` | admin | §9 |
| DELETE | `/api/v1/semesters/{id}` | admin | §9 |
| GET | `/api/v1/semesters/{id}/impacts` | admin | §9 |
| GET | `/api/v1/semesters/count-active` | **public** | §9 |

### Request — `POST /api/v1/semesters`

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `title` | `title` | string | yes | `data-model.md` §3.1 `semesters.title` |

### Request — `PATCH /api/v1/semesters/{id}`

An empty body is a 400.

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `title` | `title` | string | no | §3.1 `semesters.title` |
| `is_active` | `isActive` | bool | no | §3.1 `semesters.is_active` |
| `eval_start_date` | `evalStartDate` | string `YYYY-MM-DD` \| null | no | §3.1 `semesters.eval_start_date` |
| `eval_end_date` | `evalEndDate` | string `YYYY-MM-DD` \| null | no | §3.1 `semesters.eval_end_date` |

### Response — `{data}`

This family uses the **`{data}` wrapper**, unlike departments/subjects/sections which return bare resources (`api-endpoints.md` §3.4).

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `id` | `id` | string | §3.1 `semesters.id` |
| `title` | `title` | string | §3.1 `semesters.title` |
| `is_active` | `isActive` | bool | §3.1 `semesters.is_active` |
| `eval_start_date` | `evalStartDate` | string \| null | §3.1 `semesters.eval_start_date` |
| `eval_end_date` | `evalEndDate` | string \| null | §3.1 `semesters.eval_end_date` |

`DELETE` returns `{success:true}`.

### `GET /semesters/{id}/impacts` — response

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `faculty_subjects` | `facultySubjects` | int | `endpoints-academic.md` §9 |
| `enrollments` | `enrollments` | int | §9 |
| `evaluations` | `evaluations` | int | §9 |
| `results` | `results` | int | §9 |
| `sections` | `sections` | int | §9 |

Five parallel counts, authoritative once shipped — the client does not compute them. Build-time caveat: enrollments and sections carry no `semester_id` in the DDL, so the counts resolve through section→course or mapping joins and the join path is a build-time decision (`endpoints-academic.md` §9).

### `GET /semesters/count-active` — public

`{count}` — one of the 5 public endpoints (`api-endpoints.md` §3; `config/consult-endpoints.php`).

## 3.15 `evaluation_periods`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.3 · **Contract owner:** `endpoints-evaluations.md` §3

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| GET | `/api/v1/evaluation-periods` | read | `endpoints-evaluations.md` §3 |
| POST | `/api/v1/evaluation-periods` | admin | §3 |
| GET | `/api/v1/evaluation-periods/{id}` | read | §3 |
| PUT | `/api/v1/evaluation-periods/{id}` | admin | §3 |
| DELETE | `/api/v1/evaluation-periods/{id}` | admin | §3 |
| POST | `/api/v1/evaluation-periods/{id}/activate` | admin | §3 |
| POST | `/api/v1/evaluation-periods/{id}/reset` | admin | §3 |
| GET | `/api/v1/evaluation-periods/{id}/rubric` | read | §3 |
| POST | `/api/v1/evaluation-periods/{id}/rubric/copy` | read | §3 |
| POST | `/api/v1/evaluation-periods/{id}/rubrics/items` | admin | §3 |
| PATCH | `/api/v1/evaluation-periods/{id}/rubrics/items/{itemId}` | admin | §3 |
| DELETE | `/api/v1/evaluation-periods/{id}/rubrics/items/{itemId}` | admin | §3 |

Bodies for `POST` and `PUT` pass to `createEvaluationPeriod` / `updateEvaluationPeriod` with validation in the service, so neither is a closed schema (`endpoints-evaluations.md` §3). Known fields:

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `semester_id` | `semesterId` | string | yes | `data-model.md` §3.3 `evaluation_periods.semester_id` → `semesters.id` |
| `name` | `name` | string | yes | §3.3 `evaluation_periods.name` |
| `source` | `source` | string \| null | no | §3.3 `evaluation_periods.source` |
| `start_date` | `startDate` | string `YYYY-MM-DD` \| null | no | §3.3 `evaluation_periods.start_date` |
| `end_date` | `endDate` | string `YYYY-MM-DD` \| null | no | §3.3 `evaluation_periods.end_date` |
| `is_active` | `isActive` | bool | no | §3.3 `evaluation_periods.is_active` |
| `rubric_group_id` | `rubricGroupId` | string \| null | no | §3.3 `evaluation_periods.rubric_group_id` → `rubric_groups.id` |

### Response — `{period}` (list wraps as `{periods}`)

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `id` | `id` | string | §3.3 `evaluation_periods.id` |
| `semester_id` | `semesterId` | string | §3.3 `evaluation_periods.semester_id` |
| `name` | `name` | string | §3.3 `evaluation_periods.name` |
| `source` | `source` | string \| null | §3.3 `evaluation_periods.source` |
| `start_date` | `startDate` | string \| null | §3.3 `evaluation_periods.start_date` |
| `end_date` | `endDate` | string \| null | §3.3 `evaluation_periods.end_date` |
| `is_active` | `isActive` | bool | §3.3 `evaluation_periods.is_active` |
| `rubric_group_id` | `rubricGroupId` | string \| null | §3.3 `evaluation_periods.rubric_group_id` |
| `evaluation_count` | `evaluationCount` | int | `endpoints-evaluations.md` §3 (list enrichment) |

`GET` accepts optional `semesterId`. `DELETE` → `{success:true}`. `GET /{id}/rubric` and `POST /{id}/rubric/copy` return the **same** `{rubric}` snapshot payload (§3.20) — copy performs no duplication server-side.

## 3.16 `rating_scales`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.3

**No endpoint in the served surface writes or lists rating scales** (`api-endpoints.md` §5.6–§5.9). The `rating` domain is fixed at 1–5 by `evaluation_ratings.rating` (§3.3). The legacy semester and period links exist in the shape but no consult endpoint exposes them (ACC-3).

## 3.17 `rubric_groups`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.3 · **Contract owner:** `endpoints-evaluations.md` §4

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| GET | `/api/v1/rubric-groups` | read | `endpoints-evaluations.md` §4 |
| POST | `/api/v1/rubric-groups` | admin | §4 |
| GET | `/api/v1/rubric-groups/{id}` | read | §4 |
| PATCH | `/api/v1/rubric-groups/{id}` | admin | §4 |
| DELETE | `/api/v1/rubric-groups/{id}` | admin | §4 |
| POST | `/api/v1/rubric-groups/{id}/items` | admin | §4 |
| PATCH | `/api/v1/rubric-groups/{id}/items/{itemId}` | admin | §4 |
| DELETE | `/api/v1/rubric-groups/{id}/items/{itemId}` | admin | §4 |
| POST | `/api/v1/rubric-groups/{id}/duplicate` | admin | §4 |
| GET | `/api/v1/rubric-groups/{id}/snapshot` | read | §4 |
| POST | `/api/v1/rubric-groups/{id}/categories` | admin | §4 |
| DELETE | `/api/v1/rubric-groups/{id}/categories` | admin | §4 (body `{category_id}`) |

### Request — create / update / duplicate

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `name` | `name` | string | yes (POST, duplicate) | `data-model.md` §3.3 `rubric_groups.name` |
| `description` | `description` | string \| null | no | §3.3 `rubric_groups.description` |

`DELETE categories` takes `{category_id}` in the **body**, not a path segment.

### Response — `{group}` / `{groups}`

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `id` | `id` | string | §3.3 `rubric_groups.id` |
| `name` | `name` | string | §3.3 `rubric_groups.name` |
| `description` | `description` | string \| null | §3.3 `rubric_groups.description` |
| `seed` | `seed` | bool | §3.3 `rubric_groups.seed` — true = immutable original |
| `is_active` | `isActive` | bool | §3.3 `rubric_groups.is_active` |

All mutating paths return 409 when the group is `seed` or locked to an active period, and 404 when missing (`endpoints-evaluations.md` §4, `assertEditable`). `duplicate` is the escape hatch for a locked group.

## 3.18 `rubric_categories`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.3 · **Contract owner:** `endpoints-evaluations.md` §4

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| POST | `/api/v1/rubric-groups/{id}/categories` | admin | `endpoints-evaluations.md` §4 |
| DELETE | `/api/v1/rubric-groups/{id}/categories` | admin | §4 |

### Request — `POST /{id}/categories`

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `name` | `name` | string | yes | `data-model.md` §3.3 `rubric_categories.name` |
| `display_order` | `displayOrder` | int | no | §3.3 `rubric_categories.display_order` |

### Response — 201 `{category}`

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `id` | `id` | string | §3.3 `rubric_categories.id` |
| `rubric_group_id` | `rubricGroupId` | string | §3.3 `rubric_categories.rubric_group_id` |
| `name` | `name` | string | §3.3 `rubric_categories.name` |
| `display_order` | `displayOrder` | int | §3.3 `rubric_categories.display_order` |
| `is_active` | `isActive` | bool | §3.3 `rubric_categories.is_active` |

Both paths carry the same `assertEditable` 404/409 outcomes as §3.17.

## 3.19 `rubric_items`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.3 · **Contract owner:** `endpoints-evaluations.md` §4

Two surfaces create the same row — through a group, or through a period.

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| POST | `/api/v1/rubric-groups/{id}/items` | admin | `endpoints-evaluations.md` §4 |
| PATCH | `/api/v1/rubric-groups/{id}/items/{itemId}` | admin | §4 |
| DELETE | `/api/v1/rubric-groups/{id}/items/{itemId}` | admin | §4 |
| POST | `/api/v1/evaluation-periods/{id}/rubrics/items` | admin | §3 |
| PATCH | `/api/v1/evaluation-periods/{id}/rubrics/items/{itemId}` | admin | §3 |
| DELETE | `/api/v1/evaluation-periods/{id}/rubrics/items/{itemId}` | admin | §3 |

### Request — create

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `category_id` | `categoryId` | string | yes | `data-model.md` §3.3 `rubric_items.category_id` → `rubric_categories.id` |
| `text` | `text` | string | yes | §3.3 `rubric_items.text` |
| `display_order` | `displayOrder` | int | yes | §3.3 `rubric_items.display_order` |
| `weight` | `weight` | decimal(5,2) | no (default 1.00) | §3.3 `rubric_items.weight` |

`PATCH` is a full-body update (`updateItem`), not partial. Response 201 `{item}` on create.

### Response — `{item}`

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `id` | `id` | string | §3.3 `rubric_items.id` |
| `category_id` | `categoryId` | string | §3.3 `rubric_items.category_id` |
| `text` | `text` | string | §3.3 `rubric_items.text` |
| `display_order` | `displayOrder` | int | §3.3 `rubric_items.display_order` |
| `weight` | `weight` | decimal(5,2) | §3.3 `rubric_items.weight` |
| `is_active` | `isActive` | bool | §3.3 `rubric_items.is_active` |

On the **period** path the `{id}` segment is ignored — the item is located by `category_id` (`endpoints-evaluations.md` §3).

## 3.20 `rubric_group_snapshots`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.3 · **Contract owner:** `endpoints-evaluations.md` §4

**Append-only; never updated.** No create/update/delete endpoint exists — snapshots are produced by server-side snapshot logic when a period binds a rubric group (ACC-3).

### Response — `GET /rubric-groups/{id}/snapshot` → `{snapshot}`

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `evaluation_period_id` | `evaluationPeriodId` | string | `data-model.md` §3.3 `rubric_group_snapshots.evaluation_period_id` |
| `rubric_group_id` | `rubricGroupId` | string | §3.3 `rubric_group_snapshots.rubric_group_id` — **no FK**, point-in-time |
| `rubric_group_name` | `rubricGroupName` | string | §3.3 `rubric_group_snapshots.rubric_group_name` |
| `category_name` | `categoryName` | string | §3.3 `rubric_group_snapshots.category_name` |
| `category_display_order` | `categoryDisplayOrder` | int | §3.3 `rubric_group_snapshots.category_display_order` |
| `item_text` | `itemText` | string | §3.3 `rubric_group_snapshots.item_text` |
| `item_display_order` | `itemDisplayOrder` | int | §3.3 `rubric_group_snapshots.item_display_order` |
| `item_weight` | `itemWeight` | decimal(5,2) | §3.3 `rubric_group_snapshots.item_weight` |
| `item_id` | `itemId` | string \| null | §3.3 `rubric_group_snapshots.item_id` |
| `category_id` | `categoryId` | string \| null | §3.3 `rubric_group_snapshots.category_id` |

`GET /evaluation-periods/{id}/rubric` returns the same rows as `{rubric}` (§3.15) — one payload type serves both, grouped client-side by `category_name` / `category_display_order`.

Rows are **flat, not nested**: there is no `{categories: [{items: []}]}` envelope. The nested form exists only in the evaluation read path (`groupSnapshotRows`, `endpoints-evaluations.md` header).

## 3.21 `evaluations`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.3 · **Contract owner:** `endpoints-evaluations.md` §2

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| GET | `/api/v1/evaluations` | read | `endpoints-evaluations.md` §2 |
| POST | `/api/v1/evaluations` | write | §2 |
| GET | `/api/v1/evaluations/{id}` | read | §2 |
| GET | `/api/v1/evaluations/{id}/ratings` | read | §2 |
| PUT | `/api/v1/evaluations/{id}/ratings` | write | §2 |
| GET | `/api/v1/evaluations/{id}/comments` | read | §2 |
| POST | `/api/v1/evaluations/{id}/comments` | write | §2 |
| POST | `/api/v1/evaluations/{id}/submit` | write | §2 |
| GET | `/api/v1/evaluations/pending` | read | §2 |
| GET | `/api/v1/evaluations/bootstrap` | write | §2 |
| POST | `/api/v1/evaluations/dispute` | write | §2 |
| GET | `/api/v1/evaluations/disabled` | read | §6 |
| DELETE | `/api/v1/evaluations/disabled` | admin | §6 |
| POST | `/api/v1/evaluations/disabled/restore` | admin | §6 |
| GET | `/api/v1/evaluations/{evaluationId}/details` | read | §6 |
| POST | `/api/v1/evaluations/{evaluationId}/invalidate` | admin | §6 |

### Request — `POST /api/v1/evaluations`

Two modes in one endpoint (`endpoints-evaluations.md` §2): with `id` it is an owner-checked fetch; without, get-or-create on the active period.

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `id` | `id` | string | no | present → owner-check + return existing |
| `faculty_subject_id` | `facultySubjectId` | string | no | `data-model.md` §3.3 `evaluations.faculty_subject_id` → `faculty_subjects.id` |
| `evaluation_period_id` | `evaluationPeriodId` | string | no | §3.3 `evaluations.evaluation_period_id` — defaults to active |
| `semester_id` | `semesterId` | string | no | §3.3 `evaluations.semester_id` (legacy grain, kept) |
| `source` | `source` | `"unenrolled" \| "dispute"` | no | §3.3 `evaluations.source` |

Create returns **200, not 201** (§2).

### Response — `{evaluation}` / `{evaluations}`

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `id` | `id` | string | `data-model.md` §3.3 `evaluations.id` |
| `evaluation_period_id` | `evaluationPeriodId` | string | §3.3 `evaluations.evaluation_period_id` |
| `semester_id` | `semesterId` | string | §3.3 `evaluations.semester_id` (legacy) |
| `evaluator_id` | `evaluatorId` | string | §3.3 `evaluations.evaluator_id` → `students.id` |
| `evaluatee_id` | `evaluateeId` | string | §3.3 `evaluations.evaluatee_id` → `employees.id` |
| `faculty_subject_id` | `facultySubjectId` | string \| null | §3.3 `evaluations.faculty_subject_id` — SET NULL on reassign, history kept |
| `source` | `source` | string \| null | §3.3 `evaluations.source` |
| `status` | `status` | `DRAFT\|SUBMITTED` | §3.3 `evaluations.status` |
| `is_invalid` | `isInvalid` | bool | §3.3 `evaluations.is_invalid` |
| `is_disabled` | `isDisabled` | bool | §3.3 `evaluations.is_disabled` |
| `remarks` | `remarks` | string \| null | §3.3 `evaluations.remarks` — invalidation reason |
| `submitted_at` | `submittedAt` | RFC 3339 \| null | §3.3 `evaluations.submitted_at` |
| `is_active` | `isActive` | bool | §3.3 `evaluations.is_active` |
| `evaluatee_name` | `evaluateeName` | string | `endpoints-evaluations.md` §2 (enrichment) |
| `subject_code` | `subjectCode` | string | §2 (enrichment) |
| `subject_name` | `subjectName` | string | §2 (enrichment) |

`GET /evaluations/{id}` accepts `?include=ratings,comments,rubric`, where `rubric` returns snapshot rows through `groupSnapshotRows` (§3.20). `GET /{id}/comments` returns `{comment}` — a **single object, nullable**, not a list.

### `POST /evaluations/dispute` — request

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `faculty_subject_id` | `facultySubjectId` | string | yes | `data-model.md` §3.3 `evaluations.faculty_subject_id` |
| `evaluatee_id` | `evaluateeId` | string | yes | §3.3 `evaluations.evaluatee_id` |
| `evaluatee_name` | `evaluateeName` | string | no | `endpoints-evaluations.md` §2 (request-only) |
| `subject_name` | `subjectName` | string | no | §2 (request-only) |

Returns `{success:true}`.

### `GET /evaluations/bootstrap` — response

Read-only aggregate; the level is `write` by catalog (conservative, preserved) (`endpoints-evaluations.md` §2).

| Wire field | Frontend field | Type |
|------------|----------------|------|
| `periods` | `periods` | array |
| `active_period_id` | `activePeriodId` | string \| null |
| `active_period_name` | `activePeriodName` | string \| null |
| `pending` | `pending` | array (enriched) |
| `evaluations` | `evaluations` | array (enriched) |
| `rubric` | `rubric` | snapshot rows array \| null (§3.20) |

`GET /evaluations/pending` returns `{pending: []}` when empty — never null, never an error.

### Disabled set — request bodies

| Endpoint | Wire field | Frontend field | Type | Required | Source |
|----------|-----------|----------------|------|----------|--------|
| `DELETE /evaluations/disabled` | `all` | `all` | bool | one of the two | `endpoints-evaluations.md` §6 |
| `DELETE /evaluations/disabled` | `ids` | `ids` | string[] | one of the two | §6 |
| `POST /evaluations/disabled/restore` | `ids` | `ids` | string[] | yes (non-empty) | §6 |
| `POST /evaluations/{evaluationId}/invalidate` | `evaluation_period_id` | `evaluationPeriodId` | string | yes | §6 |
| `POST /evaluations/{evaluationId}/invalidate` | `reason` | `reason` | string \| null | no | §6 |

`GET /evaluations/disabled` → `{evaluations}`; all three mutations return `{success:true}`.

## 3.22 `evaluation_ratings`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.3 · **Contract owner:** `endpoints-evaluations.md` §2

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| GET | `/api/v1/evaluations/{id}/ratings` | read | `endpoints-evaluations.md` §2 |
| PUT | `/api/v1/evaluations/{id}/ratings` | write | §2 |

### Request — `PUT /evaluations/{id}/ratings`

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `ratings` | `ratings` | array | yes | `endpoints-evaluations.md` §2 (request-only) |
| `ratings[].item_id` | `itemId` | string | yes | `data-model.md` §3.3 `evaluation_ratings.item_id` → `rubric_items.id` |
| `ratings[].rating` | `rating` | int 1–5 | yes | §3.3 `evaluation_ratings.rating` |

### Response — `{ratings}` (GET) / `{success:true}` (PUT)

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `id` | `id` | string | §3.3 `evaluation_ratings.id` |
| `evaluation_id` | `evaluationId` | string | §3.3 `evaluation_ratings.evaluation_id` |
| `item_id` | `itemId` | string | §3.3 `evaluation_ratings.item_id` |
| `rating` | `rating` | int 1–5 | §3.3 `evaluation_ratings.rating` |
| `is_active` | `isActive` | bool | §3.3 `evaluation_ratings.is_active` |

`PUT` upserts per item (UNIQUE evaluation + item) and returns `{success:true}` — resubmitting is safe, no client-side delete-then-insert.

## 3.23 `evaluation_comments`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.3 · **Contract owner:** `endpoints-evaluations.md` §2

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| GET | `/api/v1/evaluations/{id}/comments` | read | `endpoints-evaluations.md` §2 |
| POST | `/api/v1/evaluations/{id}/comments` | write | §2 |
| GET | `/api/v1/evaluation-comments` | read | §2 (ADMIN-or-DEAN holders) |

### Request — `POST /evaluations/{id}/comments`

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `comment` | `comment` | string | yes | `data-model.md` §3.3 `evaluation_comments.comment` |

201 with the created row.

### Response

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `id` | `id` | string | §3.3 `evaluation_comments.id` |
| `evaluation_id` | `evaluationId` | string | §3.3 `evaluation_comments.evaluation_id` |
| `comment` | `comment` | string | §3.3 `evaluation_comments.comment` |
| `sentiment_score` | `sentimentScore` | decimal(5,4) \| null | §3.3 `evaluation_comments.sentiment_score` |
| `sentiment_label` | `sentimentLabel` | string \| null | §3.3 `evaluation_comments.sentiment_label` |
| `sentiment_analyzed_at` | `sentimentAnalyzedAt` | RFC 3339 \| null | §3.3 `evaluation_comments.sentiment_analyzed_at` |
| `is_active` | `isActive` | bool | §3.3 `evaluation_comments.is_active` |

Sentiment is computed fire-and-forget after the write, errors swallowed (`endpoints-evaluations.md` §2), so `sentiment_score` / `sentiment_label` are null on the 201 and populate later. The `/analysis` and `/batch` endpoints that populate them on demand are Phase E and **not served** (`endpoints-reports.md` D-1–D-4 deferred).

The admin list `GET /evaluation-comments` returns `{comments}` with evaluation linkage, filtered by `evaluationPeriodId` and `sentimentLabel`.

## 3.24 `evaluation_results`

**Status:** Implemented · **Shape owner:** `data-model.md` §3.3 · **Contract owner:** `endpoints-evaluations.md` §5–§6

Subject-level results. **One flat scoped surface serves every audience** (`url-flattening.md` Final v1.1); scoping is server-side, not path-based.

### Endpoints

| Method | Path | Level | Owner |
|--------|------|-------|-------|
| GET | `/api/v1/evaluation-results` | read | `endpoints-evaluations.md` §5 |
| GET | `/api/v1/evaluation-results/department` | read | §5 |
| GET | `/api/v1/evaluation-results/details` | read | §5 |
| GET | `/api/v1/evaluation-results/subjects` | read | §5 |
| GET | `/api/v1/evaluation-results/subjects/{facultySubjectId}` | read | §5 |
| GET | `/api/v1/evaluation-results/departments/{departmentId}` | read | §5 |
| GET | `/api/v1/evaluation-results/faculty/{facultyId}` | read | §5 |
| GET | `/api/v1/evaluation-results/groups/{facultySubjectId}` | read | §5 |
| POST | `/api/v1/evaluation-results/invalidate` | admin | §6 |
| POST | `/api/v1/evaluation-results/visibility` | admin | §6 |

### Response — base aggregates (admin)

| Wire field | Frontend field | Type | Source |
|------------|----------------|------|--------|
| `departments` | `departments` | array | `endpoints-evaluations.md` §5 |
| `departments[].facultyStats` | `facultyStats` | array | §5 |
| `departments[].rawAppointments` | `rawAppointments` | array | §5 |
| `departments[].summaries` | `summaries` | array | §5 |
| `departments[].departmentFrequency` | `departmentFrequency` | array | §5 |
| `departments[].facultyFrequency` | `facultyFrequency` | array | §5 |
| `departments[].departmentYearlyFrequency` | `departmentYearlyFrequency` | array | §5 |
| `departments[].facultyYearlyFrequency` | `facultyYearlyFrequency` | array | §5 |

Departments with no resolvable faculty land in a `__unknown__` bucket rendered as **"Unassigned"** (§5) — a client filtering on department name must keep that bucket or faculty vanish from the totals.

### Per-audience variants on the same surface (§5)

| Audience | Base | Scoping |
|----------|------|---------|
| Admin | `{departments[]}` | all; per-entity 404s |
| Dean | same shapes | own department via `findByDeanId`; empty → empty shape; cross-department → 403; no department → `{students:[]}` |
| Faculty | `{results: [...], facultyNames}` | own results, gated on `is_results_visible`; 403 unless visible |
| Faculty (subjects) | `{subjects}` | own evaluations grouped per mapping |
| `department` | `{departmentId \| null}` | — |
| `details` | shared breakdown | DEAN/ADMIN any faculty; others own-only; students anonymized `S1..Sn` |

**`evaluationPeriodId` is required on every results read** — 400 otherwise; legacy aliases `semesterId` and `periodId` are also read, period preferred (§5). Exact response keys for the nested drill-downs are a build-time verification per endpoint (§5), so the shape above is the base family rather than a promise for every drill-down.

### `POST /evaluation-results/visibility` — request

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `evaluation_period_id` \| `semester_id` | `evaluationPeriodId` \| `semesterId` | string | yes (either) | `endpoints-evaluations.md` §6 |
| `faculty_ids` | `facultyIds` | string[] | yes (non-empty) | §6 |
| `visible` | `visible` | bool | yes | §6 |

### `POST /evaluation-results/invalidate` — request

| Wire field | Frontend field | Type | Required | Source |
|------------|----------------|------|----------|--------|
| `evaluation_period_id` \| `period_id` | `evaluationPeriodId` \| `periodId` | string | yes | `endpoints-evaluations.md` §6 |
| `faculty_id` | `facultyId` | string | one target required | §6 |
| `faculty_subject_id` | `facultySubjectId` | string | one target required | §6 |

Both return `{success:true}`. DEAN access to `visibility` is an Auth grant, not a consult path (`api-endpoints.md` §7 #10).

---

# 4. Pointer Rows — Surfaces Not Specified Here

Named surfaces the frontend touches or expects, with the owner that holds them. No schema here (CON-4).

## 4.1 Auth-owned — identity administration

| Surface | Legacy frontend path | Owner | Why not here |
|---------|---------------------|-------|--------------|
| Current user + permissions | `GET /api/auth/me` | Auth Platform (admin UI) | Consult exposes no equivalent; `api-endpoints.md` §2.2 assigns identity to Auth |
| Access config | `GET /api/admin/access-config`, `.../import`, `.../export` | Auth Platform | Replaced by Auth catalog + grants; no Laravel equivalent (`api-endpoints.md` §2.2) |
| Per-user permissions | `GET /api/admin/user-permissions/{userId}`, `.../paths` | Auth Platform | Same |
| User CRUD | `POST/PATCH/DELETE /api/admin/users*`, `soft-delete`, `restore`, `bulk-soft-delete`, `deleted` | Auth Platform | `endpoints-admin-import.md` DEC-1 — consult MUST NOT implement; user writes attempted here → 404 by design |
| User import | `GET /api/import/users/reference` | Auth Platform | Auth-owned (`endpoints-admin-import.md` DEC-2); no consult equivalent (`api-endpoints.md` §5.10) |
| Onboarding flag | `POST /api/auth/onboarding` | Auth Platform | Legacy write to a user column; no consult equivalent |
| Self-hosted auth | `/api/auth/[...nextauth]`, `activate`, `forgot-password`, `change-password`, `change-password/validate` | Auth Platform | Deleted at cutover (`api-endpoints.md` §2.2) |

**Open gap — DEC-2a.** The legacy admin UI manages users today through `app/api/auth/{users,access,onboarding,me}`, but `api-endpoints.md` v2.1 §2.2 does not say where that need is met at cutover: in the Auth Platform's own admin UI, or through an Auth API target on the frontend BFF. e-cert solves it by routing non-trio `auth/*` to the Auth host (`auth-proxy.md`); consult has no such route and no `service/users|groups|members` proxy. **Until this is answered, the BFF carries one target** (`CONSULT_API_URL`) and a consult admin screen that must edit users or grants has nowhere to call (`e-consultation/specs/services/api-client.md` DEC-2a).

Frontend impact: any admin screen touching the rows above is blocked on a backend scope decision, not on code. Track it as a **scope question for `api-endpoints.md` §2.2**, not a frontend defect.

## 4.2 Phase E reports — deferred

| Surface | Endpoint family | Owner | Why not here |
|---------|-----------------|-------|--------------|
| Reports (7 reads) | `GET /reports/{health,backlog,coverage,demand,distribution,responsiveness,sentiment}` | `endpoints-reports.md` Final v1.0 | Contract Final, **code absent** — no `ReportService`, `ReportController`, `/reports/*` routes, or `reports` catalog rows exist (verified 2026-09-30) |
| Sentiment analysis (2 writes) | `POST /reports/sentiment/analyze`, `.../batch` | `endpoints-reports.md` | Same |

Deferred by user decision 2026-10-01 until the frontend is fully integrated. Today report pages are Server Components calling `features/reports/*.service.ts` directly against Supabase — there is no HTTP surface to cut over to.

Consequence for the frontend: `evaluation_comments.sentiment_score` / `sentiment_label` (§3.23) are written fire-and-forget and there is no endpoint to populate them on demand, so those fields stay null until Phase E lands.

Blocks T2-reports in the cutover order (`frontend-transition.md` DEC-4, reports last).

## 4.3 Gate-blocked — cataloged but unserved

| Surface | Endpoints | Owner | Why not here |
|---------|-----------|-------|--------------|
| Audit logs | `GET /audit-logs` (read), `DELETE /audit-logs` (admin) | `data-model.md` §3.3 | `audit_logs` is **Specified — not migrated**; blocked by the §3 gate. The 2 rows exist in `config/consult-endpoints.php` as a grant placeholder and will 403 closed-by-default until the table lands |

The `AuditLogger` service is fail-soft and currently uncallable for this reason (no model, no table) — mutations across every area log best-effort without persisting.

## 4.4 Stays in Next.js — §2.2 exceptions

| Surface | Path | Why |
|---------|------|-----|
| Bug reports | `GET/POST /api/bug-reports`, `GET /api/bug-reports/{id}` | Support tool, not domain (`api-endpoints.md` §2.2) |
| Forbidden telemetry | `POST /api/audit/forbidden` | Client-lock telemetry; local route, never a consult API call (`EC-API-001` DEC-8) |

`bug_reports` is carried in `data-model.md` §3.4 for parity only — no Laravel endpoints (`data-model.md` §3.4 note).

## 4.5 Aspirational — no route files, not specced

Listed in `api-endpoints.md` §6. No schema until the frontend team confirms the need:

- `PUT /appointments/{id}/{accept|decline|complete|cancel}` — code uses `POST /appointments/{id}/{action}`
- `POST /api/admin/sync-teams`
- period `subjects` / `faculty-subjects` / `enrollments` / `enrollment-stats`
- `GET /evaluations/submitted`
- sentiment endpoints (see §4.2 — these become real at Phase E, not here)

## 4.6 Unread / dormant — verify at cutover

Cataloged and served, but the 2026-09-18 usage scan found no fetch callers (`api-endpoints.md` §7 #20). Treat as server-driven or dormant; confirm before the client builds a module for them:

- `GET /evaluations/pending`
- `POST /import/preview`
- `GET /evaluation-periods/{id}/rubric` and `POST /evaluation-periods/{id}/rubric/copy`
- `POST /rubric-groups/{id}/duplicate`, `GET /rubric-groups/{id}/snapshot`
- `GET /evaluation-results/subjects/{facultySubjectId}`
- `DELETE /audit-logs` (unread handler, §4.3)

## 4.7 Known redundancy — do not build against both

`api-endpoints.md` §7 #21 records exact duplicates. Build against the **left** column; the right is a cutover drop:

| Use | Drop at cutover |
|-----|-----------------|
| `GET /evaluation-periods/{id}/rubric` | `POST /evaluation-periods/{id}/rubric/copy` (identical fetch) |
| `POST /rubric-groups/{id}/items*` (seed/lock guarded) | `POST /evaluation-periods/{id}/rubrics/items*` (skips the guard) |
| `GET /evaluations/{id}` | `POST /evaluations` with `{id}` — equivalent to a get-by-id |
| `GET /evaluations/bootstrap` | `GET /evaluations/pending` if bootstrap covers all callers |

---

# 5. Status Codes and Shapes That Surprise

Behaviors that change what the client must send or how it must read a response.
Each row is owned by the cited module spec — this section states the client
obligation, never the rule itself (CON-1, CON-9, DEC-3).

| # | Signal | Client obligation | Owner |
|---|--------|-------------------|-------|
| 1 | Student evaluation reads return **404, never 403**, for non-owners, wrong statuses, and disabled rows | Do not branch on 403 for these paths — a 403 branch shows "no permission" for what is actually "not found" | `endpoints-evaluations.md` §1 (404-masking) |
| 2 | `POST /evaluations` create returns **200, not 201** | Assert 200 on the happy path | `endpoints-evaluations.md` §2 |
| 3 | Mutating a `seed` or period-locked rubric group returns **409** | Handle 409 as a distinct UI state (offer duplicate), not a generic error | `endpoints-evaluations.md` §4 (`assertEditable`) |
| 4 | Results carry a `__unknown__` bucket | Render it as **"Unassigned"** and keep it in totals — filtering on department name drops faculty otherwise | `endpoints-evaluations.md` §5 |
| 5 | Empty pending list is `{pending: []}` | Never null, never an error — no empty-state exception path needed | `endpoints-evaluations.md` §2 |
| 6 | `GET /evaluations/bootstrap` is level **`write`** | The typed module's caller needs a write grant for a GET request | `endpoints-evaluations.md` §2 (conservative, preserved) |

Deliberately absent here: everything already stated in §3 (period-items `{id}` ignored, `attendee-accept`/`attendee-decline` payload shape, `{comment}` single-nullable, snapshots flat, sentiment null-on-write, `{data}` vs bare-resource families). This section covers only what §3 does not already say.
