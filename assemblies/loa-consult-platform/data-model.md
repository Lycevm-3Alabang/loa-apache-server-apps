# LOA Consult Platform — Data Model
## Product Assembly Component Specification

**Version:** 0.1
**Status:** Draft
**Layer:** Product Assembly (`loa-consult-platform`)
**Audience:** Architects, Engineers, AI Development Agents

> Follows the cert↔auth relationship pattern exactly: Consult owns its MySQL database, holds **no identity tables**, references Auth users only by **opaque sub string** (never FK), and keeps a slim local `users` table for application-specific fields only.
> Source: `D:\loa\e-consultation\supabase-schema.sql` (2081 lines, final migrated shape). Column renames camelCase→snake_case cascade through queries per the e-consultation AGENTS lesson — module specs must map every quoted identifier.

---

# 1. Relationship Rule (cert pattern, applied)

1. Database `loa_consult` (MySQL 8) holds **domain data only**.
2. **No identity tables.** Dropped: `role`, `userrole`, `group_access`, `user_permissions`, `password_reset_tokens`, `accounts`, `sessions`, `verification_tokens` (see §5).
3. **Opaque Auth sub.** Any reference to an Auth user is `string(...)->nullable()` with `// opaque Auth sub` — TEXT, no FK, no join, exactly like cert's `created_by`/`updated_by`/`user_id`/`sent_by`. Nullable-first; harden to NOT NULL later with backfill guards (cert `2026_09_15` pattern). Group membership is never stored: no role column, no pipe-delimited parsing — the JWT `groups` claim is the only membership input, read-only.
4. **Slim local `users`.** Kept for application fields and as the FK target of domain rows (appointments, enrollments, evaluations). Keyed and upserted by `email` from JWT claims (§4). This is the one deliberate delta from cert (which has no users table).
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
| `DEFERRABLE` circular FK (`departments.dean_id` ↔ `users.department_id`) | Both NULLABLE, created without deferral (departments → users → add dean FK last, §8) |
| `exec_sql()` RPC (reset-db helper) | DROPPED — reset-db becomes Laravel truncates in FK-safe order (module spec) |
| `ON CONFLICT` seed upserts | Laravel seeders, idempotent |

---

# 3. Tables (final shapes, snake_case)

## 3.1 Academic infrastructure

**departments** — `id`, `name`, `code` UNIQUE, `dean_id` NULL → users (SET NULL), `is_disabled` default false.
**department_courses** — `id`, `department_id` CASCADE, `name`, `code`, `created_at`; UNIQUE(`department_id`, code). No data seeds ported (BSIT/BSCS inserts were env-specific).
**subjects** — `id`, `code` UNIQUE, `name`.
**sections** — `id`, `name`, `program`, `department_course_id` → department_courses, `is_disabled` default false; UNIQUE(`name`, program).
**faculty_subjects** — `id`, `faculty_id` → users CASCADE, `subject_id` → subjects CASCADE, `section_id` → sections CASCADE; UNIQUE(`subject_id`, `section_id`).
**student_enrollments** — `id`, `student_id` → users CASCADE, `section_id` → sections CASCADE; UNIQUE(`student_id`, `section_id`).
**semesters** — `id`, `title`, `eval_start_date` DATE NULL, `eval_end_date` DATE NULL, `is_active` default false, `created_at`. Invariant (enforced in service, ex-`proxy.ts` rule): exactly one active semester; non-active state locks all but academic-infrastructure UI (cutover concern).

## 3.2 Consultation

**appointments** — `id`, `student_id` NULL → users CASCADE (null = faculty internal meeting), `faculty_id` → users CASCADE, `session_group_id` NULL, `created_by_email`, `meeting_type` (CONSULTATION|INTERNAL, default CONSULTATION), `date`, `start_time`, `end_time` (TEXT as in source), `title`, `description` NULL, `status` (PENDING|APPROVED|REJECTED|COMPLETED|CANCELLED, default PENDING), `action_taken` NULL, `additional_remarks` NULL, `teams_link` NULL, Teams sync fields (`teams_sync_status` UNWRITTEN|WRITTEN|FAILED default UNWRITTEN, `teams_sync_retries` default 0, `teams_sync_error` NULL, `teams_sync_last_attempt` NULL), `requested_at`, `updated_at`.
**appointment_time_slots** — `id`, `appointment_id` CASCADE, `date`, `start_time`, `end_time`, `teams_link` NULL, `created_at`; UNIQUE(`appointment_id`, date, start_time).
**appointment_attendees** — `id`, `appointment_id` CASCADE, `user_id` → users CASCADE, `status` (INVITED|ACCEPTED|DECLINED, default INVITED), `is_mandatory` default true; UNIQUE(`appointment_id`, user_id).
**appointment_files** — `id`, `appointment_id` CASCADE, `file_name`, `file_type`, `file_data` (base64 as implemented), `file_size` INT; 5 MB / image-only / dedup rules live in the module spec, not DDL.
**faculty_availability_rules** — `id`, `faculty_id` → users CASCADE, `day_of_week` INT, `is_blocked` default false, `start_time`/`end_time` NULL, `start_date`, `end_date` NULL; UNIQUE(`faculty_id`, day_of_week, start_date).

## 3.3 Evaluation

**evaluation_periods** — `id`, `semester_id` CASCADE, `name`, `source` NULL, `start_date`/`end_date` DATE NULL, `is_active` default false, `rubric_group_id` NULL → rubric_groups, `created_at`. One active period per… (active-period rule ported in module spec).
**rating_scales** — `id`, `semester_id` CASCADE, `name`, `value` INT ≥1, `display_order`; UNIQUE(`semester_id`, value). (Legacy semester link kept as in source; period-link column existed only transiently — not ported.)
**rubric_groups** — `id`, `name`, `description` NULL, `seed` default false (immutable original; only duplicated copies editable), `created_at`.
**rubric_categories** — `id`, `rubric_group_id` CASCADE (final shape post-migration-31; transient `semesterId`/`evaluation_period_id` columns NOT ported), `name`, `display_order`, `created_at`.
**rubric_items** — `id`, `category_id` CASCADE, `text`, `display_order`, `weight` DECIMAL(5,2) default 1.00.
**rubric_group_snapshots** — `id`, `evaluation_period_id` CASCADE, `rubric_group_id` (no FK, point-in-time), `rubric_group_name`, `category_name`, `category_display_order`, `item_text`, `item_display_order`, `item_weight`, `item_id` NULL, `category_id` NULL, `created_at`. Append-only; never updated.
**evaluations** — `id`, `evaluation_period_id` CASCADE (+ legacy `semester_id` CASCADE kept as in source), `evaluator_id` → users CASCADE, `evaluatee_id` → users CASCADE, `faculty_subject_id` (verify column shape in module spec), `source` NULL (`unenrolled` bypass), `status` (DRAFT|SUBMITTED, default DRAFT), `is_invalid` default false (period-reset marker), `submitted_at` NULL, `created_at`, `updated_at`; UNIQUE(`evaluation_period_id`, evaluator_id, faculty_subject_id).
**evaluation_ratings** — `id`, `evaluation_id` CASCADE, `item_id` → rubric_items CASCADE, `rating` INT 1–5; UNIQUE(`evaluation_id`, item_id).
**evaluation_comments** — `id`, `evaluation_id` CASCADE, `comment`, `sentiment_score` DECIMAL(5,4) NULL, `sentiment_label` NULL, `sentiment_analyzed_at` NULL, `created_at`. (Sentiment is placeholder upstream — columns kept, analyzer deferred.)
**evaluation_results** — `id`, `evaluation_period_id` CASCADE (+ legacy `semester_id` CASCADE), `faculty_id` → users CASCADE, `department_id` NULL → departments SET NULL, `subject_id` (verify shape in module spec), `total_respondents` default 0, nine category DECIMAL(5,2) NULL (professional_manner … assessment_and_feedback), `general_rating` NULL, `remarks` NULL, `is_results_visible` default false, `computed_at`; UNIQUE(`evaluation_period_id`, faculty_id, subject_id).

## 3.4 Support

**audit_logs** — cert shape minus organization (consult has no org table): `id` uuid PK, `user_id` string NULL `// opaque Auth sub`, `user_email` NULL, `action`, `details` (TEXT/JSON) NULL, `created_at`. No org FK (single tenant). Keep composite index (`email`, `action`, `created_at`) from source.
**bug_reports** — `id`, `user_id`, `user_email`, `url`, `description`, `status` (open|resolved, default open), `created_at`. (Next.js-owned; ported for data parity, no Laravel endpoints in Phase B–D.)

---

# 4. Slim `users` Table

Keep: `id`, `name`, `email` UNIQUE (sync key), `department_id` NULL → departments SET NULL, `course` NULL, `employee_no` NULL, `semester_id` NULL → semesters SET NULL (renamed from `evaluationPeriodId`, migration Step 9), `is_disabled` default false, `last_login_at` NULL, `deleted_at` NULL (soft-delete backing `/deleted`, `/restore`, `/soft-delete`, bulk), `onboarding_version` default 0, `created_at`. No `evaluation_eligible` — named in README only, never migrated, not ported.
Drop: `password_hash`, `token_version`, `has_logged_in_before` (auth-owned). No password/resets/sessions tables (§5).

---

# 5. Dropped Tables (auth-owned or dead)

`role`, `userrole`, `group_access`, `user_permissions`, `password_reset_tokens`, `accounts`, `sessions`, `verification_tokens`. Group/role concepts live exclusively in auth-app tenant groups; access config lives in the Auth catalog/grants.

---

# 6. Conventions (cert-verified)

- PKs: `$table->uuid('id')->primary()` + `HasUuids` (no manual `Str::uuid` boot).
- Opaque-sub columns: `string()->nullable()` + `// opaque Auth sub`; harden later with backfill-guard migrations (cert `2026_09_15` pattern), never at initial port.
- Audit writes on mutations (CREATE/UPDATE/DELETE/activate), mirroring source `logAuditEvent` call sites — action vocabulary ported in module specs.
- Carry source indexes (appointments faculty/status/date composite, enrollment/rubric/evaluation FK indexes, audit composite). No index is dropped without a module-spec note.
- One migration per table, date-prefixed (`2026_xx_xx_xxxxxx_create_*`), reversible `down()` (cert `database/migrations` pattern, 16 files).

---

# 7. Seeds

None required. Unlike cert (FK-1452 org-row trap), consult has no mandatory seed row: departments/semesters/periods are admin-managed data created through the API. Dev may ship an optional seeder (one semester; ADMIN access via Auth-side group membership, never a local flag); production seeds nothing.

---

# 8. Migration Order (FK-safe)

`users` (without department FK) → `departments` → add `users.department_id` + `departments.dean_id` → `department_courses` → `subjects` → `sections` → `semesters` → `evaluation_periods` → `rating_scales` → `rubric_groups` → `rubric_categories` → `rubric_items` → `rubric_group_snapshots` → `faculty_subjects` → `student_enrollments` → `appointments` → `time_slots` → `attendees` → `files` → `availability_rules` → `evaluations` → `ratings` → `comments` → `results` → `audit_logs` → `bug_reports`.

---

## Document Control

- **Status:** Draft v0.1
- **Created:** 2026-09-18
- **Source:** `supabase-schema.sql` final migrated shape; relationship pattern verified against cert migrations (`// opaque Auth sub`, uuid PKs, audit shape, seeder policy)
- **Verify in module specs:** `evaluations.faculty_subject_id`, `evaluation_results.subject_id` exact shapes (referenced by constraints/UNIQUEs, DDL read pending)
- **Next:** endpoint modules #2–#6
