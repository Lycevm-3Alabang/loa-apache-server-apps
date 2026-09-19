<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SemesterController extends Controller
{
    public function index(): JsonResponse
    {
        $semesters = Semester::all();
        return response()->json(['data' => $semesters]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string',
        ]);

        $semester = Semester::create([
            'title' => $request->title,
        ]);

        return response()->json(['data' => $semester], 201);
    }

    public function show(string $id): JsonResponse
    {
        $semester = Semester::findOrFail($id);
        return response()->json(['data' => $semester]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $semester = Semester::findOrFail($id);

        $request->validate([
            'title' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'eval_start_date' => 'nullable|date',
            'eval_end_date' => 'nullable|date|after_or_equal:eval_start_date',
        ]);

        $semester->update($request->only([
            'title', 'is_active', 'eval_start_date', 'eval_end_date',
        ]));

        return response()->json(['data' => $semester]);
    }

    public function destroy(string $id): JsonResponse
    {
        $semester = Semester::findOrFail($id);
        $semester->delete();

        return response()->json(['success' => true]);
    }

    public function activate(string $id): JsonResponse
    {
        $semester = Semester::findOrFail($id);

        DB::table('semesters')->update(['is_active' => false]);
        $semester->update(['is_active' => true]);

        return response()->json(['data' => $semester]);
    }

    public function impacts(string $id): JsonResponse
    {
        $semester = Semester::findOrFail($id);

        $facultySubjects = DB::table('faculty_subjects')
            ->join('sections', 'faculty_subjects.section_id', '=', 'sections.id')
            ->join('department_courses', 'sections.department_course_id', '=', 'department_courses.id')
            ->count();

        $enrollments = DB::table('student_enrollments')
            ->join('sections', 'student_enrollments.section_id', '=', 'sections.id')
            ->count();

        $evaluations = DB::table('evaluations')
            ->where('semester_id', $id)
            ->count();

        $results = DB::table('evaluation_results')
            ->where('semester_id', $id)
            ->count();

        $sections = DB::table('sections')
            ->join('department_courses', 'sections.department_course_id', '=', 'department_courses.id')
            ->count();

        return response()->json([
            'facultySubjects' => $facultySubjects,
            'enrollments' => $enrollments,
            'evaluations' => $evaluations,
            'results' => $results,
            'sections' => $sections,
        ]);
    }

    public function countActive(): JsonResponse
    {
        $count = Semester::where('is_active', true)->count();
        return response()->json(['count' => $count]);
    }
}
