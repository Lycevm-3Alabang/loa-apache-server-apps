# LOA Consult Platform — Appointments Module
## Product Assembly Component Specification

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-consult-platform`)
**Audience:** Architects, Engineers, AI Development Agents

> Covers `api-endpoints.md` §5.1 (Appointments) + §5.2 (Availability). Every contract below is read from the route handler — no invented fields.
> Group gates name Auth tenant groups (§4.0): STUDENT/FACULTY/DEAN holders = JWT `groups`-claim membership. Actor-ownership rules live inside controller/service functions (signatures take caller id) — verified where the route checks, flagged otherwise.

---

# 1. Booking Model

- STUDENT holders book as themselves (`studentId` = own id, forced).
- FACULTY/DEAN holders book on behalf (`body.studentId`) or create internal meetings (`studentId` null).
- Rule: creator ≠ student participant (400 "Creator cannot be the student participant").
- Batch books one `sessionGroupId` (server-generated UUID) across `facultyIds[0]` (primary) + additional faculty as attendees (`{userId, isMandatory}`).
- Statuses: PENDING → APPROVED (accept/approve) | REJECTED (decline/reject) → COMPLETED (complete + `actionTaken`) | CANCELLED (cancel / student-cancel).
- Conflict detection runs inside `requestAppointment`; conflicts returned alongside, never silently dropped.

# 2. Endpoints

## `GET /api/v1/appointments` — read, any session
Role-split lists (faculty/dean → faculty view; else student view), in-memory `q` filter over title/student/faculty names. Returns `{appointments}`.

## `POST /api/v1/appointments` — write, STUDENT/FACULTY/DEAN holders
Body `{facultyId, sessionGroupId?, date, startTime, endTime, timeSlots?, title, description?, attendeeIds?, meetingType?}`. 201 `{appointment, conflicts}`. 400 on service error.

## `POST /api/v1/appointments/batch` — write, STUDENT/FACULTY/DEAN holders
Body `{facultyIds[] (non-empty), studentId?, date?, startTime?, endTime?, timeSlots?, title?, description?, attendeeOptions?, teamsLink?, slotLinks?, meetingType?}`. Requires timeSlots array OR date+start+end triple. 201 `{appointment, sessionGroupId, conflicts}`. 400 validation/creation errors (with `conflicts` key when present). Single booking, not per-faculty fan-out.

## `GET /api/v1/appointments/{id}` — read, any session
`{appointment}` (enriched detail: slots, files, links). Service throw → 404.

## `POST /api/v1/appointments/{id}/{action}` — write, any session
Dispatch (body optional JSON, tolerated empty):

| `action` | Effect | Response |
|----------|--------|----------|
| `accept` / `approve` | accept as caller → refetch enriched detail | `{appointment}` |
| `decline` / `reject` | decline as caller | `{appointment}` |
| `complete` | complete as caller + optional `actionTaken` → refetch | `{appointment}` |
| `cancel` | cancel as caller (id + email) | `{appointment}` |
| `teams-link` | set appointment-level link (`body.teamsLink`) | `{appointment}` |
| `attendee-accept` / `attendee-decline` | attendee response (direct service return) | service payload |
| other | — | 400 "Invalid action" |

Failures → 400. Actor-ownership enforced inside controller/service (verify exact rules at build).

## `POST /api/v1/appointments/{id}/student-cancel` — write, any session
`cancelAppointment(id, callerId, callerEmail)` → `{appointment}`. Own-booking rule enforced downstream (verify at build).

## `GET /api/v1/appointments/faculty-booked` — read, STUDENT/FACULTY/DEAN holders
Query `facultyId`, `startDate`, `endDate` (all required, 400 otherwise). Returns lightweight calendar slots `{appointments: [{date, startTime, endTime}]}` — never full records.

## `POST /api/v1/appointments/{id}/retry-sync` — write, any session
Caller id passed as facultyId into `retryTeamsSync`. `{appointment}` / 400. (Teams sync orchestration ported in module build; `FEATURE_CREATE_TEAMS_MEETING` equivalent is backend config.)

## `POST /api/v1/appointments/slots/{slotId}/teams-link` — write, FACULTY/DEAN holders
Body `{teamsLink}` required; must be a valid `https://teams.microsoft.com/...` URL (`isValidTeamsLink`, 400 otherwise); trimmed on save. 404 "Time slot not found". Returns `{success:true}`.

## `POST /api/v1/appointments/{id}/files` — write, FACULTY/DEAN holders + owner
Owner check in route: `appointment.facultyId === caller id` (403 otherwise). Body `{files: [{fileName, fileType, fileData (base64), fileSize}]}` (see parent §3.7). 5 MB max, images only (PNG/JPEG/GIF/WebP), dedup by fileName+fileSize (skipped silently). Returns `{files: [created...]}`.

# 3. Availability Rules

## `GET /api/v1/availability-rules` — read, any session
Query `facultyId?`. No query → FACULTY/DEAN holders read own (others 401). Reading another faculty requires ADMIN-group membership (403 otherwise). Returns `{rules}`.

## `POST /api/v1/availability-rules` — write, FACULTY/DEAN/ADMIN holders
Body `{dayOfWeek 0–6, isBlocked?, startTime?, endTime?, startDate (YYYY-MM-DD required), endDate?, facultyId?}`. Upsert per (`faculty_id`, `day_of_week`, `start_date`). Non-ADMIN holders always write own (`facultyId` forced to self); ADMIN holders may set `facultyId` for others (audited `UPDATE_AVAILABILITY_RULE`). Returns `{rule}`.

---

## Document Control

- **Status:** Final v1.0
- **Created:** 2026-09-18
- **Updated:** 2026-09-19 — Promoted v0.1 → Final v1.0: verified against data-model v1.0 + `api-endpoints.md` Final v1.0 §5.1/§5.2 (no level corrections; all mutations `write`, all reads `read`).
- **Source:** 10 appointment route handlers read verbatim
- **No level corrections:** all mutations `write`, all reads `read` — code gates are group-membership only
- **Build-time carry-forward:** actor-ownership inside accept/decline/complete/cancel/student-cancel controller paths; Teams sync orchestration + feature-flag equivalent; `slotLinks`/`meetingType` semantics in batch — resolve at domain slice B build.
