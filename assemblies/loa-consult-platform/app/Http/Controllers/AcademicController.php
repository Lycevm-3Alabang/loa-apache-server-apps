<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentCourse;
use App\Models\FacultySubject;
use App\Models\Section;
use App\Models\Semester;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicController extends Controller
{
    // ── Departments ──────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $departments = Department::all();
        return response()->json($departments);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'code' => 'required|string',
            'dean_id' => 'nullable|string',
        ]);

        $department = Department::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'dean_id' => $request->dean_id ?: null,
        ]);

        return response()->json($department, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $department = Department::findOrFail($id);

        $request->validate([
            'name' => 'nullable|string',
            'code' => 'nullable|string',
            'dean_id' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $department->update($request->only([
            'name', 'code', 'dean_id', 'is_active',
        ]));

        if ($request->has('code')) {
            $department->code = strtoupper($department->code);
            $department->save();
        }

        return response()->json($department);
    }

    // ── Department Courses ───────────────────────────────────────

    public function indexCourses(): JsonResponse
    {
        $courses = DepartmentCourse::with('department')->get();
        return response()->json($courses);
    }

    public function storeCourse(Request $request): JsonResponse
    {
        $request->validate([
            'departmentId' => 'required|exists:departments,id',
            'name' => 'required|string',
            'code' => 'required|string',
        ]);

        $course = DepartmentCourse::create([
            'department_id' => $request->departmentId,
            'name' => $request->name,
            'code' => $request->code,
        ]);

        return response()->json($course);
    }

    public function destroyCourse(string $id): JsonResponse
    {
        $course = DepartmentCourse::findOrFail($id);
        $course->delete();

        return response()->json(['success' => true]);
    }

    // ── Subjects ─────────────────────────────────────────────────

    public function storeSubject(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'name' => 'required|string',
        ]);

        $subject = Subject::create([
            'code' => strtoupper($request->code),
            'name' => $request->name,
        ]);

        return response()->json($subject, 201);
    }

    public function updateSubject(Request $request, string $id): JsonResponse
    {
        $subject = Subject::findOrFail($id);

        $request->validate([
            'code' => 'nullable|string',
            'name' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $subject->update($request->only(['code', 'name', 'is_active']));

        if ($request->has('code')) {
            $subject->code = strtoupper($subject->code);
            $subject->save();
        }

        return response()->json($subject);
    }

    // ── Sections ─────────────────────────────────────────────────

    public function storeSection(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'departmentCourseId' => 'required|exists:department_courses,id',
        ]);

        $course = DepartmentCourse::findOrFail($request->departmentCourseId);

        $section = Section::create([
            'name' => strtoupper($request->name),
            'program' => $course->code,
            'department_course_id' => $request->departmentCourseId,
        ]);

        return response()->json($section, 201);
    }

    public function updateSection(Request $request, string $id): JsonResponse
    {
        $section = Section::findOrFail($id);

        $request->validate([
            'name' => 'nullable|string',
            'departmentCourseId' => 'nullable|exists:department_courses,id',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->has('departmentCourseId')) {
            $course = DepartmentCourse::findOrFail($request->departmentCourseId);
            $section->program = $course->code;
            $section->department_course_id = $request->departmentCourseId;
        }

        if ($request->has('name')) {
            $section->name = strtoupper($request->name);
        }

        if ($request->has('is_active')) {
            $section->is_active = $request->is_active;
        }

        $section->save();

        return response()->json($section);
    }

    public function fixNames(): JsonResponse
    {
        $sections = Section::all();
        $fixes = [];

        foreach ($sections as $section) {
            $course = $section->departmentCourse;
            if (!$course) continue;

            $prefix = $course->code . '-';
            $prefixSpace = $course->code . ' ';

            if (str_starts_with($section->name, $prefix)) {
                $remainder = substr($section->name, strlen($prefix));
                if (!empty(trim($remainder))) {
                    $oldName = $section->name;
                    $section->name = strtoupper($remainder);
                    $section->program = $course->code;
                    $section->save();
                    $fixes[] = [
                        'id' => $section->id,
                        'oldName' => $oldName,
                        'newName' => $section->name,
                        'program' => $section->program,
                    ];
                }
            } elseif (str_starts_with($section->name, $prefixSpace)) {
                $remainder = substr($section->name, strlen($prefixSpace));
                if (!empty(trim($remainder))) {
                    $oldName = $section->name;
                    $section->name = strtoupper($remainder);
                    $section->program = $course->code;
                    $section->save();
                    $fixes[] = [
                        'id' => $section->id,
                        'oldName' => $oldName,
                        'newName' => $section->name,
                        'program' => $section->program,
                    ];
                }
            }
        }

        return response()->json([
            'fixed' => count($fixes),
            'fixes' => $fixes,
        ]);
    }

    // ── Faculty-Subject Mappings ─────────────────────────────────

    public function storeFacultySubject(Request $request): JsonResponse
    {
        $request->validate([
            'faculty_id' => 'required|string',
            'subject_id' => 'required|exists:subjects,id',
            'section_id' => 'required|exists:sections,id',
        ]);

        $mapping = FacultySubject::create([
            'faculty_id' => $request->faculty_id,
            'subject_id' => $request->subject_id,
            'section_id' => $request->section_id,
        ]);

        return response()->json(['data' => $mapping], 201);
    }

    public function reassignFacultySubject(Request $request): JsonResponse
    {
        $request->validate([
            'oldFacultySubjectId' => 'required|exists:faculty_subjects,id',
            'newFacultyId' => 'required|string',
        ]);

        $mapping = FacultySubject::findOrFail($request->oldFacultySubjectId);

        if ($mapping->faculty_id === $request->newFacultyId) {
            return response()->json(['error' => 'Cannot reassign to the same faculty'], 400);
        }

        $mapping->faculty_id = $request->newFacultyId;
        $mapping->save();

        return response()->json(['success' => true]);
    }

    // ── Student Enrollments ──────────────────────────────────────

    public function storeEnrollment(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|string',
            'section_id' => 'required|exists:sections,id',
        ]);

        $enrollment = StudentEnrollment::create([
            'student_id' => $request->student_id,
            'section_id' => $request->section_id,
        ]);

        return response()->json(['data' => $enrollment], 201);
    }

    public function destroyEnrollment(string $id): JsonResponse
    {
        $enrollment = StudentEnrollment::findOrFail($id);
        $enrollment->delete();

        return response()->json(['success' => true]);
    }
}
