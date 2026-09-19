# LOA Consult Platform — Data Model
## Product Assembly Component Specification

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-consult-platform`)
**Audience:** Architects, Engineers, AI Development Agents

> Follows the cert↔auth relationship pattern exactly: Consult owns its MySQL database, holds **no identity tables**, references Auth users only by **opaque sub string** (never FK). An **app_users cache table** (no FK relationships) stores profile data synced from JWT claims for fast local reads — Auth remains the single source of truth for identity. Students and faculty are **optional profiles** (FK → app_users) — a user can have zero, one, or both.
> Source: `D:\loa\e-consultation\supabase-schema.sql` (2081 lines, final migrated shape). Column renames camelCase→snake_case cascade through queries per the e-consultation AGENTS lesson — module specs must map every quoted identifier.

---

# 1. Relationship Rule (cert pattern, applied)

1. Database `loa_consult` (MySQL 8) holds **domain data only**.
2. **No identity tables.** Dropped: `role`, `userrole`, `group_access`, `user_permissions`, `password_reset_tokens`, `accounts`, `sessions`, `verification_tokens` (see §5).
3. **Opaque Auth sub.** Any reference to an Auth user is `string(...)->nullable()` with `// opaque Auth sub` — TEXT, no FK, no join, exactly like cert's `created_by`/`updated_by`/`user_id`/`sent_by`. Nullable-first; harden to NOT NULL later with backfill guards (cert `2026_09_15` pattern). Group membership is never stored: no role column, no pipe-delimited parsing — the JWT `groups` claim is the only membership input, read-only.
4. **App users cache (no FK).** An `app_users` table stores profile data (`name`, `email`, `department_id`, `course_id`, `semester_id`, `is_disabled`, `deleted_at`, `onboarding_version`) synced from JWT claims on login. It has **no PK referenced by other tables** — consult tables store opaque Auth sub strings, not FK integer/uuid references. The cache is populated by upserting on `email` from JWT; consult logic reads name/email locally instead of calling Auth API per query. Student/faculty profiles are optional FK → app_users (§3.1.1, §3.1.2).
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
| `DEFERRABLE` circular FK (`departments.dean_id` ↔ `app_users.department_id`) | Both columns become plain strings on consult tables; app_users table has no FK outward — circular FK eliminated entirely |
| `exec_sql()` RPC (reset-db helper) | DROPPED — reset-db becomes Laravel truncates in FK-safe order (module spec) |
| `ON CONFLICT` seed upserts | Laravel seeders, idempotent |

---

# 3. Tables (final shapes, snake_case)

> **FK note:** All user-reference columns are `string()->nullable()` TEXT — **no FK constraints** to the app_users cache table. The app_users cache exists for local reads; consult domain rows key on opaque Auth sub strings, not user PKs.

## 3.1 Academic infrastructure

| Table | Columns | Constraints | Notes |
|-------|---------|-------------|-------|
| **departments** | `id` BIGINT PK, `name`, `code`, `dean_id` NULL (Auth sub), `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(code) | |
| **department_courses** | `id` BIGINT PK, `department_id` FK CASCADE, `name`, `code`, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(department_id, code) | No data seeds ported |
| **subjects** | `id` BIGINT PK, `code`, `name`, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(code) | |
| **sections** | `id` BIGINT PK, `name`, `subject_id` FK CASCADE, `schedule` NULL, `room` NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(name, subject_id) | Logistical: classroom/schedule assignment. Loosely coupled — students may not be assigned to any section. |
| **faculty_subjects** | `id` BIGINT PK, `faculty_id` (Auth sub), `subject_id` FK CASCADE, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(faculty_id, subject_id) | Faculty Loading: faculty teaches a subject. Section assignment is separate (logistical). |
| **student_enrollments** | `id` BIGINT PK, `student_id` (Auth sub), `subject_id` FK CASCADE, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(student_id, subject_id) | Core enrollment: student is enrolled in a subject. Section is separate (logistical). |
| **student_sections** | `id` BIGINT PK, `student_id` (Auth sub), `section_id` FK CASCADE, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(student_id, section_id) | Optional: student is assigned to a section for scheduling. Irregular students may have no section. |
| **semesters** | `id` BIGINT PK, `title`, `eval_start_date` DATE NULL, `eval_end_date` DATE NULL, `is_active` default false, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | Invariant: exactly one active |

### 3.1.1 Student profile (optional, FK → app_users)

| Table | Columns | Constraints | Notes |
|-------|---------|-------------|-------|
| **students** | `id` UUID PK, `user_id` FK → app_users, `student_number`, `course_id` FK → department_courses NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(user_id), UNIQUE(student_number) | Email: `*@itmlyceumalabang.onmicrosoft.com` |

> SSO callback auto-creates this profile. `student_number` is the academic identifier; `course_id` is the enrolled program. Junction tables (`student_enrollments`) reference `user_id` (opaque Auth sub), not `students.id`.

### 3.1.2 Faculty profile (optional, FK → app_users)

| Table | Columns | Constraints | Notes |
|-------|---------|-------------|-------|
| **faculty** | `id` UUID PK, `user_id` FK → app_users, `employee_number` NULL, `department_id` FK → departments NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(user_id), UNIQUE(employee_number) | Email: `*@lyceumalabang.edu.ph` |

> SSO callback auto-creates this profile. `employee_number` is optional; `department_id` is the home department. Junction tables reference `user_id` (Auth sub), not `faculty.id`. A user can be **both** student and faculty.

## 3.2 Consultation

| Table | Columns | Constraints | Notes |
|-------|---------|-------------|-------|
| **appointments** | `id` BIGINT PK, `student_id` NULL (Auth sub), `faculty_id` (Auth sub), `session_group_id` NULL, `created_by_email`, `meeting_type` (CONSULTATION\|INTERNAL, default CONSULTATION), `date`, `start_time`, `end_time` TEXT, `title`, `description` NULL, `status` (PENDING\|APPROVED\|REJECTED\|COMPLETED\|CANCELLED, default PENDING), `action_taken` NULL, `additional_remarks` NULL, `teams_link` NULL, `teams_sync_status` (UNWRITTEN\|WRITTEN\|FAILED, default UNWRITTEN), `teams_sync_retries` default 0, `teams_sync_error` NULL, `teams_sync_last_attempt` NULL, `requested_at`, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | student_id null = faculty internal meeting |
| **appointment_time_slots** | `id` BIGINT PK, `appointment_id` FK CASCADE, `date`, `start_time`, `end_time`, `teams_link` NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(appointment_id, date, start_time) | |
| **appointment_attendees** | `id` BIGINT PK, `appointment_id` FK CASCADE, `user_id` (Auth sub), `status` (INVITED\|ACCEPTED\|DECLINED, default INVITED), `is_mandatory` default true, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(appointment_id, user_id) | |
| **appointment_files** | `id` BIGINT PK, `appointment_id` FK CASCADE, `file_name`, `file_type`, `file_data` (base64), `file_size` INT, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | 5 MB / image-only / dedup in module spec |
| **faculty_availability_rules** | `id` BIGINT PK, `faculty_id` (Auth sub), `day_of_week` INT, `is_blocked` default false, `start_time`/`end_time` NULL, `start_date`, `end_date` NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(faculty_id, day_of_week, start_date) | |

## 3.3 Evaluation

| Table | Columns | Constraints | Notes |
|-------|---------|-------------|-------|
| **evaluation_periods** | `id` BIGINT PK, `semester_id` FK CASCADE, `name`, `source` NULL, `start_date`/`end_date` DATE NULL, `is_active` default false, `rubric_group_id` NULL → rubric_groups, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | One active period rule in module spec |
| **rating_scales** | `id` BIGINT PK, `semester_id` FK CASCADE, `name`, `value` INT ≥1, `display_order`, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(semester_id, value) | Legacy semester link kept |
| **rubric_groups** | `id` BIGINT PK, `name`, `description` NULL, `seed` default false, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | seed = immutable original |
| **rubric_categories** | `id` BIGINT PK, `rubric_group_id` FK CASCADE, `name`, `display_order`, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | |
| **rubric_items** | `id` BIGINT PK, `category_id` FK CASCADE, `text`, `display_order`, `weight` DECIMAL(5,2) default 1.00, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | |
| **rubric_group_snapshots** | `id` BIGINT PK, `evaluation_period_id` FK CASCADE, `rubric_group_id` (no FK, point-in-time), `rubric_group_name`, `category_name`, `category_display_order`, `item_text`, `item_display_order`, `item_weight`, `item_id` NULL, `category_id` NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | Append-only; never updated |
| **evaluations** | `id` BIGINT PK, `evaluation_period_id` FK CASCADE, `semester_id` FK CASCADE (legacy), `evaluator_id` (Auth sub), `evaluatee_id` (Auth sub), `faculty_subject_id`, `source` NULL, `status` (DRAFT\|SUBMITTED, default DRAFT), `is_invalid` default false, `submitted_at` NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(evaluation_period_id, evaluator_id, faculty_subject_id) | |
| **evaluation_ratings** | `id` BIGINT PK, `evaluation_id` FK CASCADE, `item_id` FK → rubric_items CASCADE, `rating` INT 1–5, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(evaluation_id, item_id) | |
| **evaluation_comments** | `id` BIGINT PK, `evaluation_id` FK CASCADE, `comment`, `sentiment_score` DECIMAL(5,4) NULL, `sentiment_label` NULL, `sentiment_analyzed_at` NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | Sentiment analyzer deferred |
| **evaluation_results** | `id` BIGINT PK, `evaluation_period_id` FK CASCADE, `semester_id` FK CASCADE (legacy), `faculty_id` (Auth sub), `department_id` NULL → departments SET NULL, `subject_id`, `total_respondents` default 0, 9× category DECIMAL(5,2) NULL, `general_rating` NULL, `remarks` NULL, `is_results_visible` default false, `computed_at`, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(evaluation_period_id, faculty_id, subject_id) | |

## 3.4 Support

| Table | Columns | Constraints | Notes |
|-------|---------|-------------|-------|
| **audit_logs** | `id` UUID PK, `user_id` string NULL (Auth sub), `user_email` NULL, `action`, `details` TEXT/JSON NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | No org FK (single tenant); composite index (email, action, created_at) |
| **bug_reports** | `id` BIGINT PK, `user_id`, `user_email`, `url`, `description`, `status` (open\|resolved, default open), `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | Next.js-owned; ported for parity, no Laravel endpoints |

---

# 4. App Users Cache Table (no FK)

> Profile cache only. **No PK referenced by other tables.** Consult domain rows store opaque Auth sub strings as TEXT; the app_users cache exists so queries can resolve name/email locally without calling Auth API per row. Student/faculty profiles are optional FK → app_users (§3.1.1, §3.1.2).

**app_users** — `id` uuid PK `HasUuids`, `name`, `email` UNIQUE (sync key), `department_id` NULL `// opaque Auth sub`, `course_id` NULL → `department_courses`, `semester_id` NULL `// opaque Auth sub`, `is_disabled` default false, `last_login_at` NULL, `deleted_at` NULL (soft-delete backing `/deleted`, `/restore`, `/soft-delete`, bulk), `onboarding_version` default 0, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub). No `evaluation_eligible` — named in README only, never migrated, not ported.

**Sync rule:** On every authenticated request, upsert by `email` from JWT claims (`name`, `email`, `groups`). Profile fields (`department_id`, `course_id`) are written by admin endpoints (import, user management), not by JWT sync. Student/faculty-specific attributes (`student_number`, `employee_number`) live on their respective profile tables.

Drop: `password_hash`, `token_version`, `has_logged_in_before` (auth-owned). No password/resets/sessions tables (§5).

---

# 5. Dropped Tables (auth-owned or dead)

`role`, `userrole`, `group_access`, `user_permissions`, `password_reset_tokens`, `accounts`, `sessions`, `verification_tokens`. Group/role concepts live exclusively in auth-app tenant groups; access config lives in the Auth catalog/grants.

---

# 6. Conventions (cert-verified)

- PKs: `$table->uuid('id')->primary()` + `HasUuids` (no manual `Str::uuid` boot).
- Opaque-sub columns: `string()->nullable()` + `// opaque Auth sub`; harden later with backfill-guard migrations (cert `2026_09_15` pattern), never at initial port.
- **No FK constraints to app_users.** All `faculty_id`, `student_id`, `evaluator_id`, `evaluatee_id`, `dean_id`, `user_id` columns are plain TEXT — the app_users cache is read-only for lookups, never joined.
- Audit writes on mutations (CREATE/UPDATE/DELETE/activate), mirroring source `logAuditEvent` call sites — action vocabulary ported in module specs.
- Carry source indexes (appointments faculty/status/date composite, enrollment/rubric/evaluation FK indexes, audit composite). No index is dropped without a module-spec note.
- One migration per table, date-prefixed (`2026_xx_xxxxxx_create_*`), reversible `down()` (cert `database/migrations` pattern, 16 files).

---

# 7. Seeds

None required. Unlike cert (FK-1452 org-row trap), consult has no mandatory seed row: departments/semesters/periods are admin-managed data created through the API. Dev may ship an optional seeder (one semester; ADMIN access via Auth-side group membership, never a local flag); production seeds nothing.

---

# 8. Migration Order (FK-safe)

`app_users` → `students` → `faculty` → `departments` → `department_courses` → `subjects` → `sections` → `semesters` → `evaluation_periods` → `rating_scales` → `rubric_groups` → `rubric_categories` → `rubric_items` → `rubric_group_snapshots` → `faculty_subjects` → `student_enrollments` → `student_sections` → `appointments` → `time_slots` → `attendees` → `files` → `availability_rules` → `evaluations` → `ratings` → `comments` → `results` → `audit_logs` → `bug_reports`.

> No circular FKs. The old `departments.dean_id` ↔ `app_users.department_id` circular FK is eliminated: `departments.dean_id` is now a plain TEXT `// opaque Auth sub` with no constraint. `students` and `faculty` are optional profiles FK → `app_users` — a user can have zero, one, or both. `student_enrollments` references `subject_id` (core enrollment), not `section_id`. `sections` are logistical (classroom/schedule), loosely coupled — students may not be assigned to any section.

---

## Document Control

- **Status:** Final v1.0
- **Created:** 2026-09-18
- **Updated:** 2026-09-19 — Redesigned academic model: subject is binding entity, section is logistical (classroom/schedule, loosely coupled). `student_enrollments` now references `subject_id` (not `section_id`). `faculty_subjects` references `subject_id` only (not `section_id`). New `student_sections` table for optional section assignment. `sections` reframed: `name`, `subject_id`, `schedule`, `room` (removed `department_course_id`, `program`). Promoted `students` and `faculty` to first-class entity profiles (FK → app_users) with email domain conventions. Removed `employee_no` from app_users (moved to `faculty.employee_number`).
- **Source:** `supabase-schema.sql` final migrated shape; relationship pattern verified against cert migrations (`// opaque Auth sub`, uuid PKs, audit shape, seeder policy)
- **Build-time carry-forward (not blocking Final):** `evaluations.faculty_subject_id`, `evaluation_results.subject_id` exact shapes; `faculty_subjects.semester_id` + `student_enrollments.faculty_subject_id`/`semester_id` (repo-layer fields absent from DDL); `countBySemesterId` join paths for enrollments/sections — all flagged in module specs, resolve at first-migration build without inventing columns.
- **Next:** academic domain specs (student.md, faculty.md, faculty-loading.md) → Promote education domain specs to Final → Update migrations/models for new schema
