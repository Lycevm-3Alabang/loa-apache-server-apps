<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentCourse;
use App\Models\FacultySubject;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicController extends Controller
{
    // Group gates are JWT levels via the catalog mirror (api-endpoints.md
    // §4.0 — no local roles). Controllers below enforce shapes, quirks,
    // 409 mapping and fail-soft audit only.

    // ── Departments ──────────────────────────────────────────────

    /** `GET /api/v1/admin/departments` — read. Bare array. */
    public function index(): JsonResponse
    {
        return response()->json(Department::all());
    }

    /** `POST /api/v1/admin/departments` — admin. 201. 409 on code clash. */
    public function store(Request $request): JsonResponse
    {
        if (!$request->input('name') || !$request->input('code')) {
            return response()->json(['error' => 'name and code are required'], 400);
        }

        $deanId = $request->input('deanId', $request->input('dean_id'));
        if ($deanId === '') {
            $deanId = null;
        }

        try {
            $department = Department::create([
                'name' => $request->input('name'),
                'code' => strtoupper($request->input('code')),
                'dean_id' => $deanId,
            ]);
        } catch (QueryException $e) {
            if ($this->isDuplicate($e)) {
                return response()->json(['error' => 'Department code already exists'], 409);
            }
            throw $e;
        }

        $this->audit($request, 'CREATE_DEPARTMENT', ['id' => $department->id]);

        return response()->json($department, 201);
    }

    /** `PATCH /api/v1/admin/departments/{id}` — admin. */
    public function update(Request $request, string $id): JsonResponse
    {
        $department = Department::find($id);
        if ($department === null) {
            return response()->json(['error' => 'Department not found'], 404);
        }

        $has = fn ($k) => $request->exists($k) || $request->exists($this->snake($k));

        if (!$has('name') && !$has('code') && !$has('deanId') && !$has('isDisabled')) {
            return response()->json(['error' => 'No changes provided'], 400);
        }

        if ($has('name')) {
            $department->name = $request->input('name');
        }
        if ($request->input('code') !== null) {
            $code = strtoupper($request->input('code'));
            $clash = Department::where('code', $code)->where('id', '!=', $id)->exists();
            if ($clash) {
                return response()->json(['error' => 'Department code already exists'], 409);
            }
            $department->code = $code;
        }
        if ($has('deanId')) {
            // Empty clears ('' arrives as null via ConvertEmptyStringsToNull).
            $department->dean_id = $request->input('deanId', $request->input('dean_id')) ?: null;
        }
        if ($request->exists('isDisabled')) {
            $department->is_active = !$this->toBool($request->input('isDisabled'));
        } elseif ($request->exists('is_active')) {
            $department->is_active = $this->toBool($request->input('is_active'));
        }
        $department->save();

        $this->audit($request, 'UPDATE_DEPARTMENT', ['id' => $id]);

        return response()->json($department);
    }

    // ── Department Courses ───────────────────────────────────────

    /** `GET /api/v1/admin/department-courses` — read. Embedded department. */
    public function indexCourses(): JsonResponse
    {
        return response()->json(DepartmentCourse::with('department')->get());
    }

    /**
     * `POST /api/v1/admin/department-courses` — write. 200 (no 201, preserved).
     * Parent NOT verified: bad id → 500 via FK (preserved quirk).
     */
    public function storeCourse(Request $request): JsonResponse
    {
        if (!$request->input('departmentId') || !$request->input('name') || !$request->input('code')) {
            return response()->json(['error' => 'departmentId, name and code are required'], 400);
        }

        try {
            $course = DepartmentCourse::create([
                'department_id' => $request->input('departmentId'),
                'name' => $request->input('name'),
                'code' => $request->input('code'),
            ]);
        } catch (QueryException $e) {
            if ($this->isDuplicate($e)) {
                return response()->json(['error' => 'Course code already exists for this department'], 409);
            }
            throw $e;
        }

        $this->audit($request, 'CREATE_DEPARTMENT_COURSE', ['id' => $course->id]);

        return response()->json($course->load('department'));
    }

    /** `DELETE /api/v1/admin/department-courses/{id}` — admin. Hard delete. */
    public function destroyCourse(string $id): JsonResponse
    {
        $course = DepartmentCourse::find($id);
        if ($course === null) {
            return response()->json(['error' => 'Course not found'], 404);
        }
        $course->delete();

        $this->audit(request(), 'DELETE_DEPARTMENT_COURSE', ['id' => $id]);

        return response()->json(['success' => true]);
    }

    // ── Subjects ─────────────────────────────────────────────────

    /** `POST /api/v1/admin/subjects` — admin. 201. */
    public function storeSubject(Request $request): JsonResponse
    {
        if (!$request->input('code') || !$request->input('name')) {
            return response()->json(['error' => 'code and name are required'], 400);
        }

        try {
            $subject = Subject::create([
                'code' => strtoupper($request->input('code')),
                'name' => $request->input('name'),
            ]);
        } catch (QueryException $e) {
            if ($this->isDuplicate($e)) {
                return response()->json(['error' => 'Subject code already exists'], 409);
            }
            throw $e;
        }

        $this->audit($request, 'CREATE_SUBJECT', ['id' => $subject->id]);

        return response()->json($subject, 201);
    }

    /** `PATCH /api/v1/admin/subjects/{id}` — admin. */
    public function updateSubject(Request $request, string $id): JsonResponse
    {
        $subject = Subject::find($id);
        if ($subject === null) {
            return response()->json(['error' => 'Subject not found'], 404);
        }

        $hasCode = $request->input('code') !== null;
        $hasName = $request->input('name') !== null;
        $hasDisabled = $request->exists('isDisabled') || $request->exists('is_active');

        if (!$hasCode && !$hasName && !$hasDisabled) {
            return response()->json(['error' => 'No changes provided'], 400);
        }

        if ($hasCode) {
            $code = strtoupper($request->input('code'));
            $clash = Subject::where('code', $code)->where('id', '!=', $id)->exists();
            if ($clash) {
                return response()->json(['error' => 'Subject code already exists'], 409);
            }
            $subject->code = $code;
        }
        if ($hasName) {
            $subject->name = $request->input('name');
        }
        if ($hasDisabled) {
            if ($request->exists('isDisabled')) {
                $section->is_active = !$this->toBool($request->input('isDisabled'));
            } else {
                $section->is_active = $this->toBool($request->input('is_active'));
            }
        }
        $subject->save();

        $this->audit($request, 'UPDATE_SUBJECT', ['id' => $id]);

        return response()->json($subject);
    }

    // ── Sections ─────────────────────────────────────────────────

    /** `POST /api/v1/admin/sections` — admin. 201. Parent verified (400). */
    public function storeSection(Request $request): JsonResponse
    {
        if (!$request->input('name') || !$request->input('departmentCourseId')) {
            return response()->json(['error' => 'name and departmentCourseId are required'], 400);
        }

        $course = DepartmentCourse::find($request->input('departmentCourseId'));
        if ($course === null) {
            return response()->json(['error' => 'Invalid department course'], 400);
        }

        $name = strtoupper(trim($request->input('name')));
        $clash = Section::where('name', $name)->where('program', $course->code)->exists();
        if ($clash) {
            return response()->json(['error' => "\"{$course->code}-{$name}\" already exists"], 409);
        }

        try {
            $section = Section::create([
                'name' => $name,
                'program' => $course->code,
                'department_course_id' => $course->id,
            ]);
        } catch (QueryException $e) {
            if ($this->isDuplicate($e)) {
                return response()->json(['error' => "\"{$course->code}-{$name}\" already exists"], 409);
            }
            throw $e;
        }

        $this->audit($request, 'CREATE_SECTION', ['id' => $section->id]);

        return response()->json($section, 201);
    }

    /** `PATCH /api/v1/admin/sections/{id}` — admin. */
    public function updateSection(Request $request, string $id): JsonResponse
    {
        $section = Section::find($id);
        if ($section === null) {
            return response()->json(['error' => 'Section not found'], 404);
        }

        $hasName = $request->input('name') !== null;
        $hasCourse = $request->input('departmentCourseId') !== null;
        $hasDisabled = $request->exists('isDisabled') || $request->exists('is_active');

        if (!$hasName && !$hasCourse && !$hasDisabled) {
            return response()->json(['error' => 'No changes'], 400);
        }

        $program = $section->program;
        if ($hasCourse) {
            $course = DepartmentCourse::find($request->input('departmentCourseId'));
            if ($course === null) {
                return response()->json(['error' => 'Invalid department course'], 400);
            }
            $program = $course->code;
            $section->program = $program;
            $section->department_course_id = $course->id;
        }
        if ($hasName) {
            $section->name = strtoupper(trim($request->input('name')));
        }
        if ($hasDisabled) {
            if ($request->exists('isDisabled')) {
                $subject->is_active = !$this->toBool($request->input('isDisabled'));
            } else {
                $subject->is_active = $this->toBool($request->input('is_active'));
            }
        }

        $clash = Section::where('name', $section->name)
            ->where('program', $program)
            ->where('id', '!=', $id)
            ->exists();
        if ($clash) {
            return response()->json(['error' => 'Section with this name and program already exists'], 409);
        }
        $section->save();

        $this->audit($request, 'UPDATE_SECTION', ['id' => $id]);

        return response()->json($section);
    }

    /** `POST /api/v1/admin/sections/fix-names` — admin. Batch normalization. */
    public function fixNames(Request $request): JsonResponse
    {
        $fixes = [];

        foreach (Section::all() as $section) {
            $course = $section->course;
            if (!$course || $section->program !== $course->code) {
                continue;
            }

            foreach ([$course->code . '-', $course->code . ' '] as $prefix) {
                if (str_starts_with($section->name, $prefix)) {
                    $remainder = substr($section->name, strlen($prefix));
                    if ($remainder !== '' && trim($remainder) !== '') {
                        $oldName = $section->name;
                        $section->name = strtoupper($remainder);
                        $section->save();
                        $fixes[] = [
                            'id' => $section->id,
                            'oldName' => $oldName,
                            'newName' => $section->name,
                            'program' => $section->program,
                        ];
                    }
                    break;
                }
            }
        }

        if (count($fixes) > 0) {
            $this->audit($request, 'UPDATE_SECTION', ['fixed' => count($fixes)]);
        }

        return response()->json(['fixed' => count($fixes), 'fixes' => $fixes]);
    }

    // ── Faculty-Subject Mappings ─────────────────────────────────

    /** `POST /api/v1/admin/faculty-subjects` — admin. 201 `{data}`. */
    public function storeFacultySubject(Request $request): JsonResponse
    {
        if (!$request->input('faculty_id') || !$request->input('subject_id') || !$request->input('section_id')) {
            return response()->json(['error' => 'faculty_id, subject_id and section_id are required'], 400);
        }
        if (Subject::find($request->input('subject_id')) === null
            || Section::find($request->input('section_id')) === null) {
            return response()->json(['error' => 'Invalid subject or section'], 400);
        }

        $semesterId = $request->input('semesterId', $request->input('semester_id'));
        if ($semesterId !== null && \App\Models\Semester::find($semesterId) === null) {
            return response()->json(['error' => 'Invalid semester'], 400);
        }

        $dup = FacultySubject::where('subject_id', $request->input('subject_id'))
            ->where('section_id', $request->input('section_id'))
            ->exists();
        if ($dup) {
            return response()->json(['error' => 'This mapping already exists'], 409);
        }

        try {
            $mapping = FacultySubject::create([
                'faculty_id' => $request->input('faculty_id'),
                'subject_id' => $request->input('subject_id'),
                'section_id' => $request->input('section_id'),
                'semester_id' => $semesterId,
            ]);
        } catch (QueryException $e) {
            if ($this->isDuplicate($e)) {
                return response()->json(['error' => 'This mapping already exists'], 409);
            }
            throw $e;
        }

        $this->audit($request, 'CREATE_FACULTY_SUBJECT', ['id' => $mapping->id]);

        return response()->json(['data' => $mapping], 201);
    }

    /**
     * `POST /api/v1/admin/faculty-subjects/reassign` — admin.
     * Evaluation side effects (invalidate + recompute) belong to slice C —
     * skipped until evaluation tables land.
     */
    public function reassignFacultySubject(Request $request): JsonResponse
    {
        if (!$request->input('oldFacultySubjectId') || !$request->input('newFacultyId')) {
            return response()->json(['error' => 'oldFacultySubjectId and newFacultyId are required'], 400);
        }

        $mapping = FacultySubject::find($request->input('oldFacultySubjectId'));
        if ($mapping === null) {
            return response()->json(['error' => 'Faculty-subject mapping not found'], 404);
        }

        if ($mapping->faculty_id === $request->input('newFacultyId')) {
            return response()->json(['error' => 'Cannot reassign to the same faculty'], 400);
        }

        $taken = FacultySubject::where('subject_id', $mapping->subject_id)
            ->where('section_id', $mapping->section_id)
            ->where('faculty_id', $request->input('newFacultyId'))
            ->where('id', '!=', $mapping->id)
            ->exists();
        if ($taken) {
            return response()->json(['error' => 'already assigned to another faculty'], 409);
        }

        $mapping->faculty_id = $request->input('newFacultyId');
        $mapping->save();

        $this->audit($request, 'REASSIGN_FACULTY_SUBJECT', ['id' => $mapping->id]);

        return response()->json(['success' => true]);
    }

    // ── Student Enrollments ──────────────────────────────────────

    /** `POST /api/v1/admin/student-enrollments` — admin. 201 `{data}`. */
    public function storeEnrollment(Request $request): JsonResponse
    {
        if (!$request->input('student_id') || !$request->input('section_id')) {
            return response()->json(['error' => 'student_id and section_id are required'], 400);
        }
        if (\App\Models\Student::find($request->input('student_id')) === null
            || Section::find($request->input('section_id')) === null) {
            return response()->json(['error' => 'Invalid student or section'], 400);
        }

        $semesterId = $request->input('semesterId', $request->input('semester_id'));
        $mappingId = $request->input('facultySubjectId', $request->input('faculty_subject_id'));
        if ($semesterId !== null && \App\Models\Semester::find($semesterId) === null) {
            return response()->json(['error' => 'Invalid semester'], 400);
        }
        if ($mappingId !== null && FacultySubject::find($mappingId) === null) {
            return response()->json(['error' => 'Invalid faculty-subject mapping'], 400);
        }

        $dup = StudentEnrollment::where('student_id', $request->input('student_id'))
            ->where('faculty_subject_id', $mappingId)
            ->where('semester_id', $semesterId)
            ->exists();
        if ($dup) {
            return response()->json(['error' => 'Enrollment already exists'], 409);
        }

        try {
            $enrollment = StudentEnrollment::create([
                'student_id' => $request->input('student_id'),
                'section_id' => $request->input('section_id'),
                'semester_id' => $semesterId,
                'faculty_subject_id' => $mappingId,
            ]);
        } catch (QueryException $e) {
            if ($this->isDuplicate($e)) {
                return response()->json(['error' => 'Enrollment already exists'], 409);
            }
            throw $e;
        }

        $this->audit($request, 'CREATE_ENROLLMENT', ['id' => $enrollment->id]);

        return response()->json(['data' => $enrollment], 201);
    }

    /**
     * `DELETE /api/v1/admin/student-enrollments/{id}` — admin.
     * Evaluation side effects belong to slice C — skipped until then.
     */
    public function destroyEnrollment(Request $request, string $id): JsonResponse
    {
        $enrollment = StudentEnrollment::find($id);
        if ($enrollment === null) {
            return response()->json(['error' => 'Enrollment not found'], 404);
        }
        $enrollment->delete();

        $this->audit($request, 'DELETE_ENROLLMENT', ['id' => $id]);

        return response()->json(['success' => true]);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function isDuplicate(QueryException $e): bool
    {
        // MySQL duplicate entry only (1062). Other 23000s (e.g. FK 1452 on
        // the preserved no-verify quirks) must surface as 500, not 409.
        return ($e->errorInfo[1] ?? null) === 1062
            || str_contains(strtolower($e->getMessage()), 'duplicate entry');
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private function snake(string $camel): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', $camel));
    }

    private function audit(Request $request, string $event, ?array $details = null): void
    {
        try {
            app(AuditLogger::class)->fromClaims(
                $event,
                $request->attributes->get('jwt_claims', []),
                $details
            );
        } catch (\Throwable $e) {
            \Log::warning('Audit log failed: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
