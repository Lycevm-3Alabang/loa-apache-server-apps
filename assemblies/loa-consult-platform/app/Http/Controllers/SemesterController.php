<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SemesterController extends Controller
{
    /** `GET /api/v1/semesters` — read. `{data}`. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => Semester::all()]);
    }

    /** `POST /api/v1/semesters` — admin. 201 `{data}`. */
    public function store(Request $request): JsonResponse
    {
        if (!$request->input('title')) {
            return response()->json(['error' => 'title is required'], 400);
        }

        $semester = Semester::create(['title' => $request->input('title')]);
        $this->audit($request, 'CREATE_SEMESTER', ['id' => $semester->id]);

        return response()->json(['data' => $semester], 201);
    }

    /** `GET /api/v1/semesters/{id}` — read. `{data}` or 404. */
    public function show(string $id): JsonResponse
    {
        $semester = Semester::find($id);
        if ($semester === null) {
            return response()->json(['error' => 'Semester not found'], 404);
        }

        return response()->json(['data' => $semester]);
    }

    /** `PATCH /api/v1/semesters/{id}` — admin. 400 when nothing to update. */
    public function update(Request $request, string $id): JsonResponse
    {
        $semester = Semester::find($id);
        if ($semester === null) {
            return response()->json(['error' => 'Semester not found'], 404);
        }

        $fields = ['title', 'isActive', 'is_active', 'evalStartDate', 'eval_start_date', 'evalEndDate', 'eval_end_date'];
        $present = array_values(array_filter($fields, fn ($f) => $request->exists($f)));
        if (count($present) === 0) {
            return response()->json(['error' => 'No fields to update'], 400);
        }

        $data = [];
        if ($request->input('title') !== null) {
            $data['title'] = $request->input('title');
        }
        if ($request->exists('isActive')) {
            $data['is_active'] = $this->toBool($request->input('isActive'));
        } elseif ($request->exists('is_active')) {
            $data['is_active'] = $this->toBool($request->input('is_active'));
        }
        foreach ([['evalStartDate', 'eval_start_date'], ['evalEndDate', 'eval_end_date']] as [$camel, $snake]) {
            $value = $request->input($camel, $request->input($snake));
            if ($value !== null) {
                $data[$snake] = $value;
            }
        }
        $semester->update($data);
        $this->audit($request, 'UPDATE_SEMESTER', ['id' => $id]);

        return response()->json(['data' => $semester]);
    }

    /** `DELETE /api/v1/semesters/{id}` — admin. `{success:true}`. */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $semester = Semester::find($id);
        if ($semester === null) {
            return response()->json(['error' => 'Semester not found'], 404);
        }
        $semester->delete();
        $this->audit($request, 'DELETE_SEMESTER', ['id' => $id]);

        return response()->json(['success' => true]);
    }

    /** `POST /api/v1/semesters/{id}` — admin. Distinct activate action. */
    public function activate(Request $request, string $id): JsonResponse
    {
        $semester = Semester::find($id);
        if ($semester === null) {
            return response()->json(['error' => 'Semester not found'], 404);
        }

        DB::table('semesters')->update(['is_active' => false]);
        $semester->update(['is_active' => true]);
        $this->audit($request, 'ACTIVATE_SEMESTER', ['id' => $id]);

        return response()->json(['data' => $semester]);
    }

    /**
     * `GET /api/v1/semesters/{id}/impacts` — admin. Per-semester counts.
     * evaluations/evaluation_results land in slice C — guarded to 0 until
     * then. Sections resolve via the semester's enrollments + mappings
     * (sections carry no semester link in the DDL).
     */
    public function impacts(string $id): JsonResponse
    {
        $semester = Semester::find($id);
        if ($semester === null) {
            return response()->json(['error' => 'Semester not found'], 404);
        }

        $facultySubjects = DB::table('faculty_subjects')
            ->where('semester_id', $id)
            ->count();

        $enrollments = DB::table('student_enrollments')
            ->where('semester_id', $id)
            ->count();

        $evaluations = Schema::hasTable('evaluations')
            ? DB::table('evaluations')->where('semester_id', $id)->count()
            : 0;

        $results = Schema::hasTable('evaluation_results')
            ? DB::table('evaluation_results')->where('semester_id', $id)->count()
            : 0;

        $enrSections = DB::table('student_enrollments')
            ->where('semester_id', $id)
            ->pluck('section_id');
        $mapSections = DB::table('faculty_subjects')
            ->where('semester_id', $id)
            ->pluck('section_id');
        $sections = $enrSections->merge($mapSections)->filter()->unique()->count();

        return response()->json([
            'facultySubjects' => $facultySubjects,
            'enrollments' => $enrollments,
            'evaluations' => $evaluations,
            'results' => $results,
            'sections' => $sections,
        ]);
    }

    /** `GET /api/v1/semesters/count-active` — public. `{count}`. */
    public function countActive(): JsonResponse
    {
        return response()->json(['count' => Semester::where('is_active', true)->count()]);
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
