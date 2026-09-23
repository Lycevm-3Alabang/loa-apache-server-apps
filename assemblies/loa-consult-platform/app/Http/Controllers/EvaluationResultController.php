<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationComment;
use App\Models\EvaluationPeriod;
use App\Models\EvaluationResult;
use App\Models\FacultySubject;
use App\Models\Student;
use App\Models\Subject;
use App\Services\ResultsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationResultController extends Controller
{
    public function __construct(private ResultsService $results)
    {
    }

    // ── Admin reads ──────────────────────────────────────────────

    /** `GET /api/v1/admin/evaluation-results` — read. `{departments[]}`. */
    public function index(Request $request): JsonResponse
    {
        $periodId = $this->periodParam($request);
        if (!$periodId) {
            return response()->json(['error' => 'evaluationPeriodId is required'], 400);
        }

        $departments = $this->results->departmentAggregates($periodId);
        if (count($departments) === 0) {
            return response()->json(['departments' => []]);
        }

        return response()->json(['departments' => $departments]);
    }

    /** `GET /api/v1/admin/evaluation-results/departments/{id}` — read. */
    public function department(Request $request, string $departmentId): JsonResponse
    {
        $periodId = $this->periodParam($request);
        if (!$periodId) {
            return response()->json(['error' => 'evaluationPeriodId is required'], 400);
        }

        $dept = Department::find($departmentId);
        if ($dept === null) {
            return response()->json(['error' => 'Department not found'], 404);
        }

        return response()->json([
            'department' => $dept->only(['id', 'name', 'code']),
            'subjects' => $this->deptSubjects($periodId, $departmentId),
        ]);
    }

    /** `GET /api/v1/admin/evaluation-results/faculty/{id}` — read. */
    public function faculty(Request $request, string $facultyId): JsonResponse
    {
        $periodId = $this->periodParam($request);
        if (!$periodId) {
            return response()->json(['error' => 'evaluationPeriodId is required'], 400);
        }

        $faculty = Employee::find($facultyId);
        if ($faculty === null) {
            return response()->json(['error' => 'Faculty not found'], 404);
        }

        return response()->json([
            'faculty' => $faculty->only(['id', 'name', 'email']),
            'subjects' => $this->results->subjectSplits($periodId, $facultyId),
        ]);
    }

    /** `GET /api/v1/admin/evaluation-results/groups/{id}` — read + comments. */
    public function group(Request $request, string $facultySubjectId): JsonResponse
    {
        $periodId = $this->periodParam($request);
        if (!$periodId) {
            return response()->json(['error' => 'evaluationPeriodId is required'], 400);
        }

        $mapping = FacultySubject::find($facultySubjectId);
        if ($mapping === null) {
            return response()->json(['error' => 'Faculty-subject mapping not found'], 404);
        }

        $splits = $this->results->subjectSplits($periodId, $mapping->faculty_id);
        $match = null;
        foreach ($splits as $split) {
            if ((string) $split['facultySubjectId'] === (string) $facultySubjectId) {
                $match = $split;
                break;
            }
        }

        $evalIds = Evaluation::where('evaluation_period_id', $periodId)
            ->where('faculty_subject_id', $facultySubjectId)
            ->pluck('id');
        $comments = EvaluationComment::whereIn('evaluation_id', $evalIds)->get();

        return response()->json([
            'facultySubject' => $mapping->toArray(),
            'subjects' => $match === null ? [] : [$match],
            'comments' => $comments,
        ]);
    }

    // ── Admin mutations ──────────────────────────────────────────

    /** `POST /api/v1/admin/evaluation-results/invalidate` — admin. */
    public function invalidate(Request $request): JsonResponse
    {
        $periodId = $this->periodParam($request);
        if (!$periodId) {
            return response()->json(['error' => 'evaluationPeriodId is required'], 400);
        }

        $facultyId = $request->input('facultyId');
        $mappingId = $request->input('facultySubjectId');
        if (!$facultyId && !$mappingId) {
            return response()->json(['error' => 'facultyId or facultySubjectId is required'], 400);
        }

        $this->results->bulkDisable($periodId, $facultyId, $mappingId);
        $this->results->computeAll($periodId);
        $this->audit($request, 'invalidate_evaluations', [
            'evaluation_period_id' => $periodId,
            'faculty_id' => $facultyId,
            'faculty_subject_id' => $mappingId,
        ]);

        return response()->json(['success' => true]);
    }

    /** `POST /api/v1/admin/evaluation-results/visibility` — admin. */
    public function visibility(Request $request): JsonResponse
    {
        $periodIds = $this->periodIds($request);
        if (count($periodIds) === 0) {
            return response()->json(['error' => 'evaluationPeriodId is required'], 400);
        }

        $facultyIds = $request->input('facultyIds', []);
        if (!is_array($facultyIds) || count($facultyIds) === 0) {
            return response()->json(['error' => 'facultyIds must be a non-empty array'], 400);
        }
        if ($request->input('visible') === null) {
            return response()->json(['error' => 'visible is required'], 400);
        }

        foreach ($periodIds as $pid) {
            $this->results->setVisibility($pid, $facultyIds, (bool) $request->input('visible'));
        }

        return response()->json(['success' => true]);
    }

    /** `GET /api/v1/admin/evaluations/disabled` — read. */
    public function disabled(): JsonResponse
    {
        $rows = Evaluation::with(['evaluator', 'evaluatee', 'mapping.subject', 'mapping.section'])
            ->where('is_disabled', true)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['evaluations' => $rows]);
    }

    /** `DELETE /api/v1/admin/evaluations/disabled` — admin. */
    public function deleteDisabled(Request $request): JsonResponse
    {
        if ($request->input('all') === true) {
            Evaluation::where('is_disabled', true)->delete();

            return response()->json(['success' => true]);
        }

        $ids = $request->input('ids', []);
        if (!is_array($ids) || count($ids) === 0) {
            return response()->json(['error' => 'all or ids is required'], 400);
        }

        Evaluation::where('is_disabled', true)->whereIn('id', $ids)->delete();

        return response()->json(['success' => true]);
    }

    /** `POST /api/v1/admin/evaluations/disabled/restore` — admin. */
    public function restore(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || count($ids) === 0) {
            return response()->json(['error' => 'ids must be a non-empty array'], 400);
        }

        Evaluation::whereIn('id', $ids)->update(['is_disabled' => false]);
        $this->audit($request, 'restore_evaluations', ['ids' => $ids]);

        return response()->json(['success' => true]);
    }

    /** `GET /api/v1/admin/evaluations/{id}/details` — read. Full assembly. */
    public function details(string $evaluationId): JsonResponse
    {
        $details = $this->results->evaluationDetails($evaluationId);
        if ($details === null) {
            return response()->json(['error' => 'Evaluation not found'], 404);
        }

        return response()->json($details);
    }

    /** `POST /api/v1/admin/evaluations/{id}/invalidate` — admin. */
    public function invalidateOne(Request $request, string $evaluationId): JsonResponse
    {
        $periodId = $this->periodParam($request);
        if (!$periodId) {
            return response()->json(['error' => 'evaluationPeriodId is required'], 400);
        }

        $evaluation = Evaluation::find($evaluationId);
        if ($evaluation === null) {
            return response()->json(['error' => 'Evaluation not found'], 404);
        }

        $evaluation->update([
            'is_disabled' => true,
            'status' => 'INVALID',
            'remarks' => $request->input('reason'),
        ]);
        $this->results->computeAll($periodId);
        $this->audit($request, 'invalidate_evaluation', [
            'evaluation_id' => $evaluationId,
            'evaluation_period_id' => $periodId,
        ]);

        return response()->json(['success' => true]);
    }

    // ── Dean reads ───────────────────────────────────────────────

    /** `GET /api/v1/dean/evaluation-results` — read, own department. */
    public function deanIndex(Request $request): JsonResponse
    {
        $periodId = $this->periodParam($request);
        if (!$periodId) {
            return response()->json(['error' => 'evaluationPeriodId is required'], 400);
        }

        $deptId = $this->ownDepartmentId($request);
        if ($deptId === null) {
            return response()->json(['departments' => []]);
        }

        $all = $this->results->departmentAggregates($periodId);

        return response()->json(['departments' => array_values(array_filter(
            $all,
            fn ($d) => (string) $d['departmentId'] === (string) $deptId
        ))]);
    }

    /** `GET /api/v1/dean/evaluation-results/department` — read. */
    public function deanDepartment(Request $request): JsonResponse
    {
        return response()->json(['departmentId' => $this->ownDepartmentId($request)]);
    }

    /** `GET /api/v1/dean/evaluation-results/departments/{id}` — read, own only. */
    public function deanDeptShow(Request $request, string $departmentId): JsonResponse
    {
        if ((string) $this->ownDepartmentId($request) !== (string) $departmentId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $this->department($request, $departmentId);
    }

    /** `GET .../departments/{id}/faculty/{fid}` — read, own only. */
    public function deanFacultyShow(Request $request, string $departmentId, string $facultyId): JsonResponse
    {
        if ((string) $this->ownDepartmentId($request) !== (string) $departmentId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $this->faculty($request, $facultyId);
    }

    /** `GET .../departments/{id}/groups/{fsid}` — read, own only. */
    public function deanGroupShow(Request $request, string $departmentId, string $facultySubjectId): JsonResponse
    {
        if ((string) $this->ownDepartmentId($request) !== (string) $departmentId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $this->group($request, $facultySubjectId);
    }

    /**
     * `GET /api/v1/dean/evaluation-results/details` — read.
     * DEAN/ADMIN any faculty; others own-only; dean without dept → empty.
     */
    public function deanDetails(Request $request): JsonResponse
    {
        $periodId = $request->query('evaluationPeriodId', $request->query('periodId'));
        $facultyId = $request->query('facultyId');
        if (!$periodId || !$facultyId) {
            return response()->json(['error' => 'periodId and facultyId are required'], 400);
        }

        $groups = $this->groups($request);
        $isPrivileged = count(array_intersect(['DEAN', 'ADMIN'], $groups)) > 0;
        if (!$isPrivileged) {
            $self = $this->selfEmployee($request);
            if ($self === null || (string) $self->id !== (string) $facultyId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        } elseif (in_array('DEAN', $groups, true) && $this->ownDepartmentId($request) === null) {
            return response()->json(['students' => []]);
        }

        return response()->json([
            'students' => $this->results->studentBreakdowns($periodId, $facultyId),
        ]);
    }

    // ── Faculty reads ────────────────────────────────────────────

    /** `GET /api/v1/faculty/evaluation-results` — read + visibility gate. */
    public function facultyIndex(Request $request): JsonResponse
    {
        $periodId = $request->query('evaluationPeriodId', $request->query('periodId'));
        if (!$periodId) {
            return response()->json(['error' => 'periodId is required'], 400);
        }

        $self = $this->selfEmployee($request);
        $visible = $self === null
            ? false
            : ($this->results->visibilityMap($periodId)[$self->id] ?? false);
        if (!$visible) {
            return response()->json(
                ['error' => "Results are not visible yet. Admin has not enabled 'Allow User To View Results' for this evaluation period."],
                403
            );
        }

        $evals = Evaluation::where('evaluation_period_id', $periodId)
            ->where('evaluatee_id', $self->id)
            ->where('status', 'SUBMITTED')
            ->where('is_disabled', false)
            ->get();
        if ($evals->isEmpty()) {
            return response()->json(['results' => [], 'facultyNames' => new \stdClass()]);
        }

        $result = $this->liveFacultyResult($periodId, $self);

        return response()->json([
            'results' => [$result],
            'facultyNames' => [$self->id => $self->name],
        ]);
    }

    /** `GET /api/v1/faculty/evaluation-results/subjects` — read + gate. */
    public function facultySubjects(Request $request): JsonResponse
    {
        $periodId = $request->query(
            'evaluationPeriodId',
            $request->query('semesterId', $request->query('periodId'))
        );
        if (!$periodId) {
            return response()->json(['error' => 'evaluationPeriodId is required'], 400);
        }

        $self = $this->selfEmployee($request);
        if ($self === null || !($this->results->visibilityMap($periodId)[$self->id] ?? false)) {
            return response()->json(['error' => 'Evaluation results are not visible yet'], 403);
        }

        return response()->json(['subjects' => $this->results->subjectSplits($periodId, $self->id)]);
    }

    /** `GET /api/v1/faculty/evaluation-results/subjects/{id}` — single group. */
    public function facultySubjectShow(Request $request, string $facultySubjectId): JsonResponse
    {
        $periodId = $request->query(
            'evaluationPeriodId',
            $request->query('semesterId', $request->query('periodId'))
        );
        if (!$periodId) {
            return response()->json(['error' => 'evaluationPeriodId is required'], 400);
        }

        $self = $this->selfEmployee($request);
        if ($self === null || !($this->results->visibilityMap($periodId)[$self->id] ?? false)) {
            return response()->json(['error' => 'Evaluation results are not visible yet'], 403);
        }

        $splits = array_values(array_filter(
            $this->results->subjectSplits($periodId, $self->id),
            fn ($s) => (string) $s['facultySubjectId'] === (string) $facultySubjectId
        ));
        if (count($splits) === 0) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json(['subject' => $splits[0]]);
    }

    // ── Internals ────────────────────────────────────────────────

    private function periodParam(Request $request): ?string
    {
        return $request->query(
            'evaluationPeriodId',
            $request->query('semesterId', $request->query('periodId'))
        ) ?: $request->input(
            'evaluationPeriodId',
            $request->input('semesterId', $request->input('periodId'))
        );
    }

    private function periodIds(Request $request): array
    {
        $given = $request->input(
            'evaluationPeriodId',
            $request->input('semesterId', $request->input('periodId'))
        );
        if (!$given) {
            return [];
        }

        if (EvaluationPeriod::find($given)) {
            return [$given];
        }

        return EvaluationPeriod::where('semester_id', $given)->pluck('id')->all();
    }

    private function groups(Request $request): array
    {
        $groups = $request->attributes->get('jwt_claims', [])['groups'] ?? [];

        return is_array($groups) ? array_map(fn ($g) => strtoupper((string) $g), $groups) : [];
    }

    private function selfEmployee(Request $request): ?Employee
    {
        $email = strtolower(trim((string) ($request->attributes->get('jwt_claims', [])['email'] ?? '')));

        return $email === '' ? null : Employee::where('email', $email)->first();
    }

    private function selfStudent(Request $request): ?Student
    {
        $email = strtolower(trim((string) ($request->attributes->get('jwt_claims', [])['email'] ?? '')));

        return $email === '' ? null : Student::where('email', $email)->first();
    }

    private function ownDepartmentId(Request $request): ?string
    {
        $sub = $request->attributes->get('jwt_claims', [])['sub'] ?? null;
        if (!$sub) {
            return null;
        }

        return Department::where('dean_id', $sub)->value('id');
    }

    private function deptSubjects(string $periodId, string $departmentId): array
    {
        $facultyIds = Employee::where('department_id', $departmentId)->pluck('id');
        $out = [];
        foreach ($facultyIds as $fid) {
            foreach ($this->results->subjectSplits($periodId, $fid) as $split) {
                $out[] = array_merge(['facultyId' => $fid], $split);
            }
        }

        return $out;
    }

    private function liveFacultyResult(string $periodId, Employee $faculty): array
    {
        $evals = Evaluation::where('evaluation_period_id', $periodId)
            ->where('evaluatee_id', $faculty->id)
            ->where('status', 'SUBMITTED')
            ->where('is_disabled', false)
            ->get();

        $ratings = \App\Models\EvaluationRating::with('item.category')
            ->whereIn('evaluation_id', $evals->pluck('id'))->get();

        $catVals = [];
        foreach ($ratings as $r) {
            $name = $r->item?->category?->name;
            if ($name) {
                $catVals[$name][] = $r->rating;
            }
        }
        $averages = [];
        foreach ($catVals as $cat => $vals) {
            $averages[$cat] = array_sum($vals) / count($vals);
        }
        $general = count($averages) > 0
            ? ResultsService::r2(array_sum($averages) / count($averages))
            : null;

        $result = [
            'id' => "{$periodId}_{$faculty->id}",
            'evaluationPeriodId' => $periodId,
            'facultyId' => $faculty->id,
            'departmentId' => $faculty->department_id,
            'totalRespondents' => $evals->count(),
            'generalRating' => $general,
            'remarks' => ResultsService::remark($general),
            'professionalManner' => null,
            'communicationWithStudent' => null,
            'studentEngagement' => null,
            'learningMaterials' => null,
            'timeManagement' => null,
            'experientialLearning' => null,
            'respectUniqueness' => null,
            'assessmentAndFeedback' => null,
        ];
        foreach (ResultsService::CATEGORY_MAP as $catName => $col) {
            if (isset($averages[$catName])) {
                $result[$col] = ResultsService::r2($averages[$catName]);
            }
        }

        return $result;
    }

    private function audit(Request $request, string $event, ?array $details = null): void
    {
        try {
            app(\App\Services\AuditLogger::class)->fromClaims(
                $event,
                $request->attributes->get('jwt_claims', []),
                $details
            );
        } catch (\Throwable $e) {
            \Log::warning('Audit log failed: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
