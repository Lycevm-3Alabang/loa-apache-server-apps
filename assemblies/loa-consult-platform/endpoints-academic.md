# LOA Consult Platform — Academic Module
## Product Assembly Component Specification

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-consult-platform`)
**Audience:** Architects, Engineers, AI Development Agents

> Covers `api-endpoints.md` §5.4 (Academic) + §5.5 (Semesters). Every contract below is read from the route handler — no invented fields.
> Group gates name Auth tenant groups (§4.0): "ADMIN holders" = callers holding the ADMIN tenant group (ex-`requireAdmin`); "ADMIN-or-DEAN holders" (ex-`requireAdminOrDean`).

---

# 1. Dependency Chain (setup order)

Semesters → Departments → Department courses → Subjects → Sections → Faculty-subject mappings → Enrollments. (Matches the admin UI setup guide.) Creation validates the parent exists, except department-courses POST which relies on the FK (500 on bad id — preserved quirk, see §3).

# 2. Cross-Cutting Rules

- **Duplicate → 409** with entity message (`23505` mapped per entity; never raw).
- **Validation → 400** (`{error}`); **not found → 404**; unexpected → 500.
- **Audit on every mutation** (`CREATE_*`, `UPDATE_*`, `DELETE_*`, `ACTIVATE_SEMESTER`, `REASSIGN_FACULTY_SUBJECT`).
- **Codes uppercased** (department, subject, section names trimmed+upper).
- **Response shapes preserved** (frontend untouched): bare arrays, direct resources, `{data}`, `{success:true}` — no envelope migration.
- **Deletion policy:** departments are never deleted (disable via PATCH `isDisabled`); courses hard-delete (CASCADE); enrollments delete with evaluation side effects (§6).

# 3. Departments

## `GET /api/v1/admin/departments` — read, ADMIN-or-DEAN holders
Returns bare array of departments (with dean linkage). 500 `{error}`.

## `POST /api/v1/admin/departments` — admin, ADMIN holders
Body `{name, code, deanId?}` (name+code required). `code` uppercased, `deanId` null if empty. 201 created directly. 409 "Department code already exists". Audit `CREATE_DEPARTMENT`.

## `PATCH /api/v1/admin/departments/{id}` — admin, ADMIN holders
Partial `{name?, code?→upper, deanId? (empty clears), isDisabled?}`. 404 "Department not found". 400 "No changes provided". 409 on code clash. Returns updated directly. Audit `UPDATE_DEPARTMENT` with field list.

# 4. Department Courses

## `GET /api/v1/admin/department-courses` — read, ADMIN-or-DEAN holders
Bare array, each course with embedded `department` (joined in handler via departments map — Laravel: `with('department)`).

## `POST /api/v1/admin/department-courses` — write, ADMIN-or-DEAN holders
Body `{departmentId, name, code}` all required. Parent NOT verified (FK enforces; bad id → 500 — preserved quirk, do not "fix" without frontend sign-off). Response = created + `department:{name,code}` (null if parent missing). 409 "Course code already exists for this department". Audit `CREATE_DEPARTMENT_COURSE`. No 201 (200 default) — preserved.

## `DELETE /api/v1/admin/department-courses/{id}` — admin (+ DEAN grant to preserve code), ADMIN-or-DEAN holders
404 "Course not found". Hard delete (CASCADE to sections). Returns `{success:true}`. Audit `DELETE_DEPARTMENT_COURSE`.

# 5. Subjects

## `POST /api/v1/admin/subjects` — admin, ADMIN holders
Body `{code→upper, name}` required. 201 data directly. 409 "Subject code already exists". Audit `CREATE_SUBJECT`.

## `PATCH /api/v1/admin/subjects/{id}` — admin, ADMIN holders
Partial `{code?→upper, name?, isDisabled?}`. 404 "Subject not found". 400 "No changes provided". 409 on clash. Returns data directly. Audit `UPDATE_SUBJECT`.

# 6. Sections

## `POST /api/v1/admin/sections` — admin, ADMIN holders
Body `{name, departmentCourseId}` required. Parent verified — 400 "Invalid department course". Name upper+trim; `program` derived from `course.code`. 201 data. 409 `"${code}-${name}" already exists`. Audit `CREATE_SECTION`.

## `PATCH /api/v1/admin/sections/{id}` — admin, ADMIN holders
Partial `{name?→upper/trim, departmentCourseId? (revalidates + re-derives program), isDisabled?}`. 404 "Section not found". 400 "No changes" / "Invalid department course". 409 "Section with this name and program already exists". Audit `UPDATE_SECTION`.

## `POST /api/v1/admin/sections/fix-names` — admin, ADMIN holders
Batch normalization: strips a leading `"<program>-"` / `"<program> "` prefix (only when the course code still matches `program`, non-empty remainder). Returns `{fixed, fixes:[{id,oldName,newName,program}]}`. Audits `UPDATE_SECTION` only when fixes > 0.

# 7. Faculty-Subject Mappings

## `POST /api/v1/admin/faculty-subjects` — admin, ADMIN holders
Body `{faculty_id, subject_id, section_id}` (snake_case as implemented) + optional `semesterId` (repo-layer field — DDL lacks it; verify at build, do not drop silently). 201 `{data}`. 409 "This mapping already exists" (UNIQUE subject+section). Audit `CREATE_FACULTY_SUBJECT`.

## `POST /api/v1/admin/faculty-subjects/reassign` — admin, ADMIN holders
Body `{oldFacultySubjectId, newFacultyId}`. 404 "Faculty-subject mapping not found". 400 same-faculty. 409 "already assigned to another faculty". Side effects in order: update mapping → `invalidateByFacultySubject` evaluations (remarks cite acting admin + reason) → recompute results for all periods of the mapping's semester (if present). Returns `{success:true}`. Audit `REASSIGN_FACULTY_SUBJECT`.

# 8. Enrollments

## `POST /api/v1/admin/student-enrollments` — admin, ADMIN holders
Body passed to `createEnrollment(body, actorId)`; validation lives in the service — `EnrollmentError` carries its own `{message, status}`. 201 `{data}`. (Repo rows carry `faculty_subject_id` + `semesterId` beyond the DDL — verify at build.)

## `DELETE /api/v1/admin/student-enrollments/{id}` — admin, ADMIN holders
404 "Enrollment not found". Side effects when `faculty_subject_id` present: invalidate that student's evaluations for the mapping (remarks cite admin + removal) → recompute results for all periods of the enrollment's semester (if present). Then delete. Returns `{success:true}`. Audit `DELETE_ENROLLMENT`.

# 9. Semesters

## `GET /api/v1/semesters` — read (hardening delta: code has NO auth check)
Returns `{data: semesters}`. Laravel gates `read`. `force-dynamic` equivalent (no caching).

## `POST /api/v1/semesters` — admin, ADMIN holders
Body `{title}` required. 201 `{data}`. Audit `CREATE_SEMESTER`.

## `GET /api/v1/semesters/{id}` — read
`{data}` or 404. (Any session in code; `read` under middleware.)

## `PATCH /api/v1/semesters/{id}` — admin, ADMIN holders
Partial `{title?, isActive?, evalStartDate?, evalEndDate?}`. 400 "No fields to update". Returns `{data}`. Audit `UPDATE_SEMESTER`. (Code gates ADMIN — spec corrected from `write`.)

## `DELETE /api/v1/semesters/{id}` — admin, ADMIN holders
Returns `{success:true}`. Audit `DELETE_SEMESTER`. (No impact pre-check in code — impacts endpoint exists separately; deactivation vs delete policy is UI-side.)

## `POST /api/v1/semesters/{id}` — admin, ADMIN holders (activate action)
Calls `activateSemester(id)`; returns `{data}`. Audit `ACTIVATE_SEMESTER`. (Not an update alias — distinct action.)

## `GET /api/v1/semesters/{id}/impacts` — admin, ADMIN holders
Parallel counts `{facultySubjects, enrollments, evaluations, results, sections}` via `countBySemesterId` repo methods. **Build note:** enrollments/sections carry no `semesterId` in DDL — counts must resolve through section→course or mapping joins; confirm join path at build, do not invent columns.

## `GET /api/v1/semesters/count-active` — public
Returns `{count}` (used by the frontend lock gate). No auth in code or Laravel.

---

## Document Control

- **Status:** Final v1.0
- **Created:** 2026-09-18
- **Updated:** 2026-09-19 — Promoted v0.1 → Final v1.0: verified against data-model v1.0 + `api-endpoints.md` Final v1.0 §5.4/§5.5 (levels match; academic POST/PATCH + impacts `admin`, dept-courses POST `write`, semesters GET hardened `read`).
- **Source:** 14 academic route handlers read verbatim
- **Corrects parent spec:** academic POST/PATCH levels `write`→`admin` (code gates ADMIN); impacts `read`→`admin`; dept-courses DELETE carries DEAN grant; semesters GET hardened `read`
- **Build-time carry-forward:** faculty-subjects/enrollments repo-only fields (`semesterId`, `faculty_subject_id`); `countBySemesterId` join paths; `createEnrollment` service rules (`EnrollmentError` vocabulary) — resolve at domain slice B build, do not invent columns.
