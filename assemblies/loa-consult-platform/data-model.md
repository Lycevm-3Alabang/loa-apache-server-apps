# LOA Consult Platform — Data Model
## Product Assembly Component Specification

**ID:** SPEC-CONSULT-DM
**Version:** 1.2
**Status:** Final
**Layer:** Product Assembly (`loa-consult-platform`)
**Audience:** Architects, Engineers, AI Development Agents
**Normative source:** This file. Source of shape: `D:\loa\e-consultation\supabase-schema.sql` (final migrated shape incl. Migrations 17/19/21/26/27/30/31).

> The keywords **MUST**, **MUST NOT**, **SHOULD** in this spec are to be interpreted as described in RFC 2119.

> Consult owns its MySQL database. **Students** and **Employees** are first-class entities (name, email, domain attributes) — the source of truth for "who can use this tenant." Auth-platform promotion is optional (same pattern as cert `event_attendees`). Junction tables use FK constraints to `students.id` / `employees.id`, not opaque Auth sub strings.
> Source: `D:\loa\e-consultation\supabase-schema.sql` (2081 lines, final migrated shape). Column renames camelCase→snake_case cascade through queries per the e-consultation AGENTS lesson — module specs must map every quoted identifier.

---

# 1. Relationship Rule

1. Database `loa_consult` (MySQL 8) holds **domain data only**.
2. **No identity tables.** Dropped: `role`, `userrole`, `group_access`, `user_permissions`, `password_reset_tokens`, `accounts`, `sessions`, `verification_tokens` (see §5).
3. **No app_users cache.** Students and Employees are first-class entities — the source of truth for tenant membership. Auth-platform promotion is optional (by email).
4. **No cross-database reads.** Identity beyond claims comes via the Auth API, never SQL.
5. **No RLS.** Authorization enforced in the API layer (`jwt.auth` + `jwt.endpoint` + controller scoping).
6. **No consultation↔evaluation FK.** The two contexts MUST share only academic actors (`students`/`employees`) and reference data. Correlation is via domain events only (see assembly `AGENTS.md` §2).
7. **Owner per section.** §3.1 tables are owned by Education domains; §3.2 by the Consultation context; §3.3 by the Evaluation context; §3.4 is shared support. No section redefines another's tables.

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
| `DEFERRABLE` circular FK | Eliminated — no circular FKs |
| `exec_sql()` RPC (reset-db helper) | DROPPED — reset-db becomes Laravel truncates in FK-safe order (module spec) |
| `ON CONFLICT` seed upserts | Laravel seeders, idempotent |

---

# 3. Tables (final shapes, snake_case)

## 3.1 Academic infrastructure

| Table | Columns | Constraints | Notes |
|-------|---------|-------------|-------|
| **departments** | `id` BIGINT PK, `name`, `code`, `dean_id` NULL (Auth sub), `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(code) | |
| **department_courses** | `id` BIGINT PK, `department_id` FK CASCADE, `name`, `code`, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(department_id, code) | No data seeds ported |
| **subjects** | `id` BIGINT PK, `code`, `name`, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(code) | |
| **sections** | `id` BIGINT PK, `name`, `program` (derived from course code, see `endpoints-academic.md` §6), `department_course_id` FK → department_courses CASCADE NOT NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(name, program) | Logistical: classroom grouping within a program. Source M17/M24 — links a course, never a subject; no schedule/room columns. |
| **faculty_subjects** | `id` BIGINT PK, `faculty_id` FK → employees CASCADE NOT NULL, `subject_id` FK → subjects CASCADE NOT NULL, `section_id` FK → sections CASCADE NULL, `semester_id` FK → semesters CASCADE NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(subject_id, section_id, semester_id) | Faculty Loading: faculty teaches a subject (optionally in a section and semester). Source M19 — `semester_id` NULL means cross-semester mapping; reassign flows treat a NULL semester as "all periods". |
| **student_enrollments** | `id` BIGINT PK, `student_id` FK → students CASCADE NOT NULL, `section_id` FK → sections CASCADE NULL, `semester_id` FK → semesters CASCADE NULL, `faculty_subject_id` FK → faculty_subjects CASCADE NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(student_id, faculty_subject_id, semester_id) | Core enrollment: student in a section for a semester under a faculty mapping. Source M17/M19/M21 — section-based (never `subject_id`); NULL `faculty_subject_id` = unenrolled-from-mapping (evaluation bypass per module spec). |
| **semesters** | `id` BIGINT PK, `title`, `eval_start_date` DATE NULL, `eval_end_date` DATE NULL, `is_active` default false, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | Invariant: exactly one active |

> `student_sections` (v1.1) is DROPPED — no source table, no endpoint. Section assignment lives on `student_enrollments.section_id`.

### 3.1.1 Students (first-class entity)

| Table | Columns | Constraints | Notes |
|-------|---------|-------------|-------|
| **students** | `id` UUID PK, `name`, `email` UNIQUE, `student_number` UNIQUE, `course_id` FK → department_courses NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(email), UNIQUE(student_number) | Email: `*@itmlyceumalabang.onmicrosoft.com`. Source of truth for tenant student membership. |

> SSO callback upserts by `email` from JWT claims. `student_number` is the academic identifier; `course_id` is the enrolled program. Junction tables (`student_enrollments`) reference `students.id` via FK.

### 3.1.2 Employees (first-class entity)

| Table | Columns | Constraints | Notes |
|-------|---------|-------------|-------|
| **employees** | `id` UUID PK, `name`, `email` UNIQUE, `employee_number` UNIQUE NULL, `department_id` FK → departments NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(email), UNIQUE(employee_number) | Email: `*@lyceumalabang.edu.ph`. Source of truth for tenant employee membership. |

> SSO callback upserts by `email` from JWT claims. `employee_number` is optional; `department_id` is the home department. Junction tables (`faculty_subjects`) reference `employees.id` via FK. A person can be **both** student and employee.

## 3.2 Consultation

| Table | Columns | Constraints | Notes |
|-------|---------|-------------|-------|
| **appointments** | `id` BIGINT PK, `student_id` NULL FK → students, `faculty_id` FK → employees, `session_group_id` NULL, `created_by_email`, `meeting_type` (CONSULTATION\|INTERNAL, default CONSULTATION), `date`, `start_time`, `end_time` TEXT, `title`, `description` NULL, `status` (PENDING\|APPROVED\|REJECTED\|COMPLETED\|CANCELLED, default PENDING), `action_taken` NULL, `additional_remarks` NULL, `teams_link` NULL, `teams_sync_status` (UNWRITTEN\|WRITTEN\|FAILED, default UNWRITTEN), `teams_sync_retries` default 0, `teams_sync_error` NULL, `teams_sync_last_attempt` NULL, `requested_at`, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | student_id null = faculty internal meeting |
| **appointment_time_slots** | `id` BIGINT PK, `appointment_id` FK CASCADE, `date`, `start_time`, `end_time`, `teams_link` NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(appointment_id, date, start_time) | |
| **appointment_attendees** | `id` BIGINT PK, `appointment_id` FK CASCADE NOT NULL, `user_id` FK → employees CASCADE NOT NULL, `status` (INVITED\|ACCEPTED\|DECLINED, default INVITED), `is_mandatory` default true, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(appointment_id, user_id) | Attendees are faculty/employees (additional faculty on batch bookings). Source: `userId` NOT NULL. |
| **appointment_files** | `id` BIGINT PK, `appointment_id` FK CASCADE, `file_name`, `file_type`, `file_data` (base64), `file_size` INT, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | 5 MB / image-only / dedup in module spec |
| **faculty_availability_rules** | `id` BIGINT PK, `faculty_id` FK → employees, `day_of_week` INT, `is_blocked` default false, `start_time`/`end_time` NULL, `start_date`, `end_date` NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(faculty_id, day_of_week, start_date) | |

## 3.3 Evaluation

| Table | Columns | Constraints | Notes |
|-------|---------|-------------|-------|
| **evaluation_periods** | `id` BIGINT PK, `semester_id` FK CASCADE NOT NULL, `name`, `source` NULL, `start_date`/`end_date` DATE NULL, `is_active` default false, `rubric_group_id` NULL → rubric_groups (no FK change without spec), `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | One active period rule in module spec. Source M30 — a semester MAY have many periods (Pre/Post-Semester). |
| **rating_scales** | `id` BIGINT PK, `semester_id` FK CASCADE NOT NULL, `evaluation_period_id` FK → evaluation_periods CASCADE NULL, `name`, `value` INT ≥1, `display_order`, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(semester_id, value) | Legacy semester link kept; period link per source M30. |
| **rubric_groups** | `id` BIGINT PK, `name`, `description` NULL, `seed` default false, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | seed = immutable original |
| **rubric_categories** | `id` BIGINT PK, `rubric_group_id` FK CASCADE, `name`, `display_order`, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | |
| **rubric_items** | `id` BIGINT PK, `category_id` FK CASCADE, `text`, `display_order`, `weight` DECIMAL(5,2) default 1.00, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | |
| **rubric_group_snapshots** | `id` BIGINT PK, `evaluation_period_id` FK CASCADE, `rubric_group_id` (no FK, point-in-time), `rubric_group_name`, `category_name`, `category_display_order`, `item_text`, `item_display_order`, `item_weight`, `item_id` NULL, `category_id` NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | Append-only; never updated |
| **evaluations** | `id` BIGINT PK, `evaluation_period_id` FK CASCADE NULL, `semester_id` FK CASCADE (legacy) NOT NULL, `evaluator_id` FK → students CASCADE NOT NULL, `evaluatee_id` FK → employees CASCADE NOT NULL, `faculty_subject_id` FK → faculty_subjects SET NULL NULL, `source` NULL, `status` (DRAFT\|SUBMITTED, default DRAFT; INVALID set only by period reset), `is_invalid` default false, `is_disabled` default false, `remarks` NULL (invalidation reason), `submitted_at` NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(evaluation_period_id, evaluator_id, faculty_subject_id) | Subject-level: one row per student per mapping per period. Source M26/M30/M33 — `faculty_subject_id` SET NULL (reassign keeps history); legacy `semesterId` grain kept for migration. |
| **evaluation_ratings** | `id` BIGINT PK, `evaluation_id` FK CASCADE, `item_id` FK → rubric_items CASCADE, `rating` INT 1–5, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(evaluation_id, item_id) | |
| **evaluation_comments** | `id` BIGINT PK, `evaluation_id` FK CASCADE, `comment`, `sentiment_score` DECIMAL(5,4) NULL, `sentiment_label` NULL, `sentiment_analyzed_at` NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | Sentiment analyzer deferred |
| **evaluation_results** | `id` BIGINT PK, `evaluation_period_id` FK CASCADE NULL, `semester_id` FK CASCADE (legacy) NOT NULL, `faculty_id` FK → employees CASCADE NOT NULL, `department_id` NULL → departments SET NULL, `subject_id` FK → subjects CASCADE NULL, `total_respondents` default 0, 9× category DECIMAL(5,2) NULL, `general_rating` NULL, `remarks` NULL, `is_results_visible` default false, `computed_at`, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | UNIQUE(evaluation_period_id, faculty_id, subject_id) | Subject-level results written by `computeAll`. Source M27/M30 — `subject_id` denormalized from the mapping at compute time. |

## 3.4 Support

| Table | Columns | Constraints | Notes |
|-------|---------|-------------|-------|
| **audit_logs** | `id` UUID PK, `user_id` string NULL (Auth sub), `user_email` NULL, `action`, `details` TEXT/JSON NULL, `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | No org FK (single tenant); composite index (email, action, created_at) |
| **bug_reports** | `id` BIGINT PK, `user_id`, `user_email`, `url`, `description`, `status` (open\|resolved, default open), `is_active` default true, `created_at`, `updated_at`, `created_by` NULL (Auth sub), `updated_by` NULL (Auth sub) | | Next.js-owned; ported for parity, no Laravel endpoints |

---

# 4. Dropped Tables (auth-owned or dead)

`app_users` — removed. Students and Employees are first-class entities (§3.1.1, §3.1.2).
`role`, `userrole`, `group_access`, `user_permissions`, `password_reset_tokens`, `accounts`, `sessions`, `verification_tokens`. Group/role concepts live exclusively in auth-app tenant groups; access config lives in the Auth catalog/grants.

---

# 5. Conventions

- PKs: `$table->uuid('id')->primary()` + `HasUuids` (no manual `Str::uuid` boot).
- **FK constraints** to `students.id` / `employees.id` — junction tables reference domain entities, not opaque strings.
- Audit writes on mutations (CREATE/UPDATE/DELETE/activate), mirroring source `logAuditEvent` call sites — action vocabulary ported in module specs.
- Carry source indexes (appointments faculty/status/date composite, enrollment/rubric/evaluation FK indexes, audit composite). No index is dropped without a module-spec note.
- One migration per table, date-prefixed (`2026_xx_xxxxxx_create_*`), reversible `down()` (cert `database/migrations` pattern).
- **Semester scoping is columnar, not join-inferred.** `faculty_subjects.semester_id`, `student_enrollments.semester_id`, `student_enrollments.faculty_subject_id` MUST exist as NULLABLE columns (source M19/M21) — semester `NULL` means cross-semester/unscoped. `impacts`/`countBySemesterId` counts filter on these columns directly; joins are only for drill-down display (`endpoints-academic.md` §9, `endpoints-evaluations.md` §5).

---

# 6. Seeds

None required. Unlike cert (FK-1452 org-row trap), consult has no mandatory seed row: departments/semesters/periods are admin-managed data created through the API. Dev may ship an optional seeder (one semester; ADMIN access via Auth-side group membership, never a local flag); production seeds nothing.

---

# 7. Migration Order (FK-safe)

`departments` → `department_courses` → `subjects` → `sections` → `students` → `employees` → `semesters` → `evaluation_periods` → `rating_scales` → `rubric_groups` → `rubric_categories` → `rubric_items` → `rubric_group_snapshots` → `faculty_subjects` → `student_enrollments` → `appointments` → `time_slots` → `attendees` → `files` → `availability_rules` → `evaluations` → `ratings` → `comments` → `results` → `audit_logs` → `bug_reports`.

> No circular FKs. `students` and `employees` are first-class entities. `student_enrollments` is section-based with nullable semester/mapping links. `sections` are logistical (course grouping), loosely coupled — an enrollment without a mapping (`faculty_subject_id` NULL) is valid.

> **Baseline delta (Type A at slice build):** migrations `2026_09_19_000001–000010` predate this Final — follow-up migrations MUST add `sections.department_course_id` (+`program`, drop `subject_id`/`schedule`/`room`), `faculty_subjects.semester_id`, `student_enrollments.section_id`/`semester_id`/`faculty_subject_id` (drop `subject_id`), `evaluations.is_disabled`/`remarks`, `rating_scales.evaluation_period_id`, and drop `student_sections`. No new tables until these land.

---

## Document Control

- **Status:** Final v1.2
- **Created:** 2026-09-18
- **Updated:** 2026-09-22 — Promoted v1.1 → Final v1.2: all carry-forward resolved against source `supabase-schema.sql` (M17 section model, M19 semester columns, M21 enrollment mapping link, M26/M27 subject-level evaluations/results, M30/M31 periods + rubric decoupling). Dropped invented `student_sections`; fixed `sections` to course-linked; specified `subject_id`→`subjects`; added evaluation `is_disabled`/`remarks`, `rating_scales.evaluation_period_id`; Owner + no-cross-FK rules (§1.6–1.7).
- **Source:** `supabase-schema.sql` final migrated shape; relationship pattern verified against cert migrations (uuid PKs, audit shape, seeder policy)
- **Next:** TDD-baseline existing migrations/models against this Final (see §7 delta) → slices B/C
