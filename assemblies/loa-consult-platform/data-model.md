# LOA Consult Platform — Data Model
## Product Assembly Component Specification

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-consult-platform`)
**Audience:** Architects, Engineers, AI Development Agents

> Follows the cert↔auth relationship pattern exactly: Consult owns its MySQL database, holds **no identity tables**, references Auth users only by **opaque sub string** (never FK). A **users cache table** (no FK relationships) stores profile data synced from JWT claims for fast local reads — Auth remains the single source of truth for identity.
> Source: `D:\loa\e-consultation\supabase-schema.sql` (2081 lines, final migrated shape). Column renames camelCase→snake_case cascade through queries per the e-consultation AGENTS lesson — module specs must map every quoted identifier.

---

# 1. Relationship Rule (cert pattern, applied)

1. Database `loa_consult` (MySQL 8) holds **domain data only**.
2. **No identity tables.** Dropped: `role`, `userrole`, `group_access`, `user_permissions`, `password_reset_tokens`, `accounts`, `sessions`, `verification_tokens` (see §5).
3. **Opaque Auth sub.** Any reference to an Auth user is `string(...)->nullable()` with `// opaque Auth sub` — TEXT, no FK, no join, exactly like cert's `created_by`/`updated_by`/`user_id`/`sent_by`. Nullable-first; harden to NOT NULL later with backfill guards (cert `2026_09_15` pattern). Group membership is never stored: no role column, no pipe-delimited parsing — the JWT `groups` claim is the only membership input, read-only.
4. **Users cache (no FK).** A `users` table stores profile data (`name`, `email`, `department_id`, `course`, `employee_no`, `semester_id`, `is_disabled`, `deleted_at`, `onboarding_version`) synced from JWT claims on login. It has **no PK referenced by other tables** — consult tables store opaque Auth sub strings, not FK integer/uuid references. The cache is populated by upserting on `email` from JWT; consult logic reads name/email/department locally instead of calling Auth API per query.
5. **No cross-database reads.** Identity beyond claims comes via the Auth API, never SQL.
6. **No RLS.** Authorization enforced in the API layer (`jwt.auth` + `jwt.endpoint` + controller scoping).

---

# 2. Postgres → MySQL 8 Adaptations

| Postgres | MySQL 8 |
|----------|---------|
| `TEXT PK DEFAULT gen_random_uuid()::TEXT` | `uuid` PK (`HasUuids` trait per AI-RULES; `$table->uuid('id')->primary()`) |
| camelCase quoted identifiers (`"facultyId"`) | snake_case (`faculty_id`) — avoids PostgREST-style introspection traps and matches cert columns |
| `TIMESTAMPTZ` | `timestamp`/`datetime` UTC |
| `DATE` | `DATE` |
| `BOOLEAN` | boolean (TinyInt) |
| `DECIMAL(5,2)` | `DECIMAL(5,2)` |
| `TEXT[]` (only on dropped `user_permissions`) | n/a — table dropped |
| `CHECK (...)` enums | MySQL 8 CHECK (enforced ≥8.0.16); status values documented per table |
| `DEFERRABLE` circular FK (`departments.dean_id` ↔ `users.department_id`) | Both columns become plain strings on consult tables; users table has no FK outward — circular FK eliminated entirely |
| `exec_sql()` RPC (reset-db helper) | DROPPED — reset-db becomes Laravel truncates in FK-safe order (module spec) |
| `ON CONFLICT` seed upserts | Laravel seeders, idempotent |

---

# 3. Tables (final shapes, snake_case)

> **FK note:** All user-reference columns are `string()->nullable()` TEXT — **no FK constraints** to the users cache table. The users cache exists for local reads; consult domain rows key on opaque Auth sub strings, not user PKs.

## 3.1 Academic infrastructure

**departments** — `id`, `name`, `code` UNIQUE, `dean_id` NULL `// opaque Auth sub`, `is_disabled` default false.
**department_courses** — `id`, `department_id` CASCADE, `name`, `code`, `created_at`; UNIQUE(`department_id`, code). No data seeds ported (BSIT/BSCS inserts were env-specific).
**subjects** — `id`, `code` UNIQUE, `name`.
**sections** — `id`, `name`, `program`, `department_course_id` → department_courses, `is_disabled` default false; UNIQUE(`name`, program).
**faculty_subjects** — `id`, `faculty_id` `// opaque Auth sub`, `subject_id` → subjects CASCADE, `section_id` → sections CASCADE; UNIQUE(`subject_id`, `section_id`).
**student_enrollments** — `id`, `student_id` `// opaque Auth sub`, `section_id` → sections CASCADE; UNIQUE(`student_id`, `section_id`).
**semesters** — `id`, `title`, `eval_start_date` DATE NULL, `eval_end_date` DATE NULL, `is_active` default false, `created_at`. Invariant (enforced in service, ex-`proxy.ts` rule): exactly one active semester; non-active state locks all but academic-infrastructure UI (cutover concern).

## 3.2 Consultation

**appointments** — `id`, `student_id` NULL `// opaque Auth sub` (null = faculty internal meeting), `faculty_id` `// opaque Auth sub`, `session_group_id` NULL, `created_by_email`, `meeting_type` (CONSULTATION|INTERNAL, default CONSULTATION), `date`, `start_time`, `end_time` (TEXT as in source), `title`, `description` NULL, `status` (PENDING|APPROVED|REJECTED|COMPLETED|CANCELLED, default PENDING), `action_taken` NULL, `additional_remarks` NULL, `teams_link` NULL, Teams sync fields (`teams_sync_status` UNWRITTEN|WRITTEN|FAILED default UNWRITTEN, `teams_sync_retries` default 0, `teams_sync_error` NULL, `teams_sync_last_attempt` NULL), `requested_at`, `updated_at`.
**appointment_time_slots** — `id`, `appointment_id` CASCADE, `date`, `start_time`, `end_time`, `teams_link` NULL, `created_at`; UNIQUE(`appointment_id`, date, start_time).
**appointment_attendees** — `id`, `appointment_id` CASCADE, `user_id` `// opaque Auth sub`, `status` (INVITED|ACCEPTED|DECLINED, default INVITED), `is_mandatory` default true; UNIQUE(`appointment_id`, user_id).
**appointment_files** — `id`, `appointment_id` CASCADE, `file_name`, `file_type`, `file_data` (base64 as implemented), `file_size` INT; 5 MB / image-only / dedup rules live in the module spec, not DDL.
**faculty_availability_rules** — `id`, `faculty_id` `// opaque Auth sub`, `day_of_week` INT, `is_blocked` default false, `start_time`/`end_time` NULL, `start_date`, `end_date` NULL; UNIQUE(`faculty_id`, day_of_week, start_date).

## 3.3 Evaluation

**evaluation_periods** — `id`, `semester_id` CASCADE, `name`, `source` NULL, `start_date`/`end_date` DATE NULL, `is_active` default false, `rubric_group_id` NULL → rubric_groups, `created_at`. One active period per… (active-period rule ported in module spec).
**rating_scales** — `id`, `semester_id` CASCADE, `name`, `value` INT ≥1, `display_order`; UNIQUE(`semester_id`, value). (Legacy semester link kept as in source; period-link column existed only transiently — not ported.)
**rubric_groups** — `id`, `name`, `description` NULL, `seed` default false (immutable original; only duplicated copies editable), `created_at`.
**rubric_categories** — `id`, `rubric_group_id` CASCADE (final shape post-migration-31; transient `semesterId`/`evaluation_period_id` columns NOT ported), `name`, `display_order`, `created_at`.
**rubric_items** — `id`, `category_id` CASCADE, `text`, `display_order`, `weight` DECIMAL(5,2) default 1.00.
**rubric_group_snapshots** — `id`, `evaluation_period_id` CASCADE, `rubric_group_id` (no FK, point-in-time), `rubric_group_name`, `category_name`, `category_display_order`, `item_text`, `item_display_order`, `item_weight`, `item_id` NULL, `category_id` NULL, `created_at`. Append-only; never updated.
**evaluations** — `id`, `evaluation_period_id` CASCADE (+ legacy `semester_id` CASCADE kept as in source), `evaluator_id` `// opaque Auth sub`, `evaluatee_id` `// opaque Auth sub`, `faculty_subject_id` (verify column shape in module spec), `source` NULL (`unenrolled` bypass), `status` (DRAFT|SUBMITTED, default DRAFT), `is_invalid` default false (period-reset marker), `submitted_at` NULL, `created_at`, `updated_at`; UNIQUE(`evaluation_period_id`, evaluator_id, faculty_subject_id).
**evaluation_ratings** — `id`, `evaluation_id` CASCADE, `item_id` → rubric_items CASCADE, `rating` INT 1–5; UNIQUE(`evaluation_id`, item_id).
**evaluation_comments** — `id`, `evaluation_id` CASCADE, `comment`, `sentiment_score` DECIMAL(5,4) NULL, `sentiment_label` NULL, `sentiment_analyzed_at` NULL, `created_at`. (Sentiment is placeholder upstream — columns kept, analyzer deferred.)
**evaluation_results** — `id`, `evaluation_period_id` CASCADE (+ legacy `semester_id` CASCADE), `faculty_id` `// opaque Auth sub`, `department_id` NULL → departments SET NULL, `subject_id` (verify shape in module spec), `total_respondents` default 0, nine category DECIMAL(5,2) NULL (professional_manner … assessment_and_feedback), `general_rating` NULL, `remarks` NULL, `is_results_visible` default false, `computed_at`; UNIQUE(`evaluation_period_id`, faculty_id, subject_id).

## 3.4 Support

**audit_logs** — cert shape minus organization (consult has no org table): `id` uuid PK, `user_id` string NULL `// opaque Auth sub`, `user_email` NULL, `action`, `details` (TEXT/JSON) NULL, `created_at`. No org FK (single tenant). Keep composite index (`email`, `action`, `created_at`) from source.
**bug_reports** — `id`, `user_id`, `user_email`, `url`, `description`, `status` (open|resolved, default open), `created_at`. (Next.js-owned; ported for data parity, no Laravel endpoints in Phase B–D.)

---

# 4. Users Cache Table (no FK)

> Profile cache only. **No PK referenced by other tables.** Consult domain rows store opaque Auth sub strings as TEXT; the users cache exists so queries can resolve name/email/department locally without calling Auth API per row.

**users** — `id` uuid PK `HasUuids`, `name`, `email` UNIQUE (sync key), `department_id` NULL `// opaque Auth sub`, `course` NULL, `employee_no` NULL, `semester_id` NULL `// opaque Auth sub`, `is_disabled` default false, `last_login_at` NULL, `deleted_at` NULL (soft-delete backing `/deleted`, `/restore`, `/soft-delete`, bulk), `onboarding_version` default 0, `created_at`. No `evaluation_eligible` — named in README only, never migrated, not ported.

**Sync rule:** On every authenticated request, upsert by `email` from JWT claims (`name`, `email`, `groups`). Profile fields (`department_id`, `course`, `employee_no`) are written by admin endpoints (import, user management), not by JWT sync.

Drop: `password_hash`, `token_version`, `has_logged_in_before` (auth-owned). No password/resets/sessions tables (§5).

---

# 5. Dropped Tables (auth-owned or dead)

`role`, `userrole`, `group_access`, `user_permissions`, `password_reset_tokens`, `accounts`, `sessions`, `verification_tokens`. Group/role concepts live exclusively in auth-app tenant groups; access config lives in the Auth catalog/grants.

---

# 6. Conventions (cert-verified)

- PKs: `$table->uuid('id')->primary()` + `HasUuids` (no manual `Str::uuid` boot).
- Opaque-sub columns: `string()->nullable()` + `// opaque Auth sub`; harden later with backfill-guard migrations (cert `2026_09_15` pattern), never at initial port.
- **No FK constraints to users.** All `faculty_id`, `student_id`, `evaluator_id`, `evaluatee_id`, `dean_id`, `user_id` columns are plain TEXT — the users cache is read-only for lookups, never joined.
- Audit writes on mutations (CREATE/UPDATE/DELETE/activate), mirroring source `logAuditEvent` call sites — action vocabulary ported in module specs.
- Carry source indexes (appointments faculty/status/date composite, enrollment/rubric/evaluation FK indexes, audit composite). No index is dropped without a module-spec note.
- One migration per table, date-prefixed (`2026_xx_xxxxxx_create_*`), reversible `down()` (cert `database/migrations` pattern, 16 files).

---

# 7. Seeds

None required. Unlike cert (FK-1452 org-row trap), consult has no mandatory seed row: departments/semesters/periods are admin-managed data created through the API. Dev may ship an optional seeder (one semester; ADMIN access via Auth-side group membership, never a local flag); production seeds nothing.

---

# 8. Migration Order (FK-safe)

`users` → `departments` → `department_courses` → `subjects` → `sections` → `semesters` → `evaluation_periods` → `rating_scales` → `rubric_groups` → `rubric_categories` → `rubric_items` → `rubric_group_snapshots` → `faculty_subjects` → `student_enrollments` → `appointments` → `time_slots` → `attendees` → `files` → `availability_rules` → `evaluations` → `ratings` → `comments` → `results` → `audit_logs` → `bug_reports`.

> No circular FKs. The old `departments.dean_id` ↔ `users.department_id` circular FK is eliminated: `departments.dean_id` is now a plain TEXT `// opaque Auth sub` with no constraint.

---

## Document Control

- **Status:** Final v1.0
- **Created:** 2026-09-18
- **Updated:** 2026-09-19 — Promoted v0.2 → Final v1.0: §3 column shapes reviewed against endpoints-academic/appointments/evaluations v1.0 + `api-endpoints.md` Final v1.0 §5.4/§5.1–§5.2/§5.6–§5.9. Hybrid users approach unchanged (cache table, no FK, all user-ref TEXT `// opaque Auth sub`).
- **Source:** `supabase-schema.sql` final migrated shape; relationship pattern verified against cert migrations (`// opaque Auth sub`, uuid PKs, audit shape, seeder policy)
- **Build-time carry-forward (not blocking Final):** `evaluations.faculty_subject_id`, `evaluation_results.subject_id` exact shapes; `faculty_subjects.semester_id` + `student_enrollments.faculty_subject_id`/`semester_id` (repo-layer fields absent from DDL); `countBySemesterId` join paths for enrollments/sections — all flagged in module specs, resolve at first-migration build without inventing columns.
- **Next:** first migration(s) — `users` + `departments` (FK-safe per §8)
