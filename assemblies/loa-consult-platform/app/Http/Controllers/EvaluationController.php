<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeSentiment;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationComment;
use App\Models\EvaluationPeriod;
use App\Models\EvaluationRating;
use App\Models\FacultySubject;
use App\Models\RubricGroupSnapshot;
use App\Models\RubricItem;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    // Group gates are JWT levels via the catalog mirror (§4.0 — no local
    // roles). Owner identity resolves via JWT email → students row (Auth sub
    // is not a local id). 404-masking preserved on student reads.

    // ── Lists ────────────────────────────────────────────────────

    /** `GET /api/v1/evaluations` — read, STUDENT. Own evaluations, enriched. */
    public function index(Request $request): JsonResponse
    {
        $claims = $this->claims($request);
        if (!$this->inGroups($claims, ['STUDENT'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $period = $this->activePeriod();
        if ($period === null) {
            return response()->json(['error' => 'No active evaluation period'], 400);
        }

        $student = $this->selfStudent($claims);
        $evaluations = $student === null ? collect() : Evaluation::where('evaluator_id', $student->id)
            ->where('evaluation_period_id', $period->id)
            ->orderByDesc('id')
            ->get();

        return response()->json(['evaluations' => $evaluations->map(fn ($e) => $this->enriched($e))->all()]);
    }

    /**
     * `POST /api/v1/evaluations` — write, STUDENT.
     * With `{id}`: owner fetch (200). Else get-or-create (200, not 201).
     */
    public function store(Request $request): JsonResponse
    {
        $claims = $this->claims($request);
        if (!$this->inGroups($claims, ['STUDENT'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $student = $this->selfStudent($claims);

        if ($request->input('id')) {
            $existing = Evaluation::find($request->input('id'));
            if ($existing === null || $student === null || $existing->evaluator_id !== $student->id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            return response()->json(['evaluation' => $this->enriched($existing)]);
        }

        $period = $this->resolvePeriod($request);
        if ($period === null) {
            return response()->json(['error' => 'No active evaluation period'], 400);
        }
        if (!EvaluationPeriod::find($period->id)) {
            return response()->json(['error' => 'Invalid evaluation period'], 400);
        }

        $mappingId = $request->input('facultySubjectId');
        if (($request->input('source') ?? null) !== 'unenrolled') {
            $enrollment = $this->findExisting($student?->id, $mappingId, $period->semester_id);
            if ($enrollment === null) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $evaluateeId = $request->input('evaluateeId');
        if (!$evaluateeId && $mappingId) {
            $evaluateeId = FacultySubject::find($mappingId)?->faculty_id;
        }
        if (!$evaluateeId) {
            return response()->json(['error' => 'evaluateeId is required'], 400);
        }

        $evaluation = Evaluation::where('evaluation_period_id', $period->id)
            ->where('evaluator_id', $student?->id)
            ->where('faculty_subject_id', $mappingId)
            ->first();

        if ($evaluation === null) {
            $evaluation = Evaluation::create([
                'evaluation_period_id' => $period->id,
                'semester_id' => $period->semester_id,
                'evaluator_id' => $student?->id,
                'evaluatee_id' => $evaluateeId,
                'faculty_subject_id' => $mappingId,
                'source' => $request->input('source'),
            ]);
        }

        return response()->json(['evaluation' => $this->enriched($evaluation)]);
    }

    /** `GET /api/v1/evaluations/{id}` — read, owner-masked 404. */
    public function show(Request $request, string $id): JsonResponse
    {
        $evaluation = $this->owned($request, $id);
        if ($evaluation === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $body = ['evaluation' => $this->enriched($evaluation)];
        $includes = collect(explode(',', (string) $request->query('include', '')))
            ->map(fn ($s) => trim($s))->filter();

        if ($includes->contains('ratings')) {
            $body['ratings'] = EvaluationRating::where('evaluation_id', $id)
                ->get(['item_id', 'rating']);
        }
        if ($includes->contains('comments')) {
            $body['comment'] = EvaluationComment::where('evaluation_id', $id)->first();
        }
        if ($includes->contains('rubric')) {
            $body['rubric'] = $this->groupedSnapshots($evaluation->evaluation_period_id);
        }

        return response()->json($body);
    }

    /** `GET /api/v1/evaluations/{id}/ratings` — read, owner-masked. */
    public function ratings(Request $request, string $id): JsonResponse
    {
        if ($this->owned($request, $id) === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json(['ratings' => EvaluationRating::where('evaluation_id', $id)
            ->get(['item_id', 'rating'])]);
    }

    /** `PUT /api/v1/evaluations/{id}/ratings` — write, STUDENT + owner-masked. */
    public function saveRatings(Request $request, string $id): JsonResponse
    {
        if (!$this->inGroups($this->claims($request), ['STUDENT'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($this->owned($request, $id) === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        EvaluationRating::where('evaluation_id', $id)->delete();
        foreach ($request->input('ratings', []) as $row) {
            EvaluationRating::create([
                'evaluation_id' => $id,
                'item_id' => $row['itemId'],
                'rating' => $row['rating'],
            ]);
        }

        return response()->json(['success' => true]);
    }

    /** `GET /api/v1/evaluations/{id}/comments` — read, owner-masked. */
    public function comment(Request $request, string $id): JsonResponse
    {
        if ($this->owned($request, $id) === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json(['comment' => EvaluationComment::where('evaluation_id', $id)->first()]);
    }

    /**
     * `POST /api/v1/evaluations/{id}/comments` — write, STUDENT + owner-masked.
     * Sentiment runs fire-and-forget (placeholder pipeline, best-effort).
     */
    public function storeComment(Request $request, string $id): JsonResponse
    {
        if (!$this->inGroups($this->claims($request), ['STUDENT'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($this->owned($request, $id) === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $created = EvaluationComment::create([
            'evaluation_id' => $id,
            'comment' => $request->input('comment'),
        ]);

        try {
            AnalyzeSentiment::dispatch($created->id, (string) $request->input('comment'));
        } catch (\Throwable $e) {
            // Fire-and-forget — swallowed per legacy.
        }

        return response()->json(['comment' => $created], 201);
    }

    /** `POST /api/v1/evaluations/{id}/submit` — write, STUDENT + owner-masked. */
    public function submit(Request $request, string $id): JsonResponse
    {
        if (!$this->inGroups($this->claims($request), ['STUDENT'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $evaluation = $this->owned($request, $id);
        if ($evaluation === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $evaluation->status = 'SUBMITTED';
        $evaluation->submitted_at = now();
        $evaluation->save();

        return response()->json(['evaluation' => $evaluation->fresh()]);
    }

    /** `GET /api/v1/evaluations/pending` — read, STUDENT. */
    public function pending(Request $request): JsonResponse
    {
        $claims = $this->claims($request);
        if (!$this->inGroups($claims, ['STUDENT'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $period = $this->activePeriod();
        if ($period === null) {
            return response()->json(['error' => 'No active evaluation period'], 400);
        }

        $student = $this->selfStudent($claims);
        if ($student === null) {
            return response()->json(['pending' => []]);
        }

        return response()->json(['pending' => $this->enrichPending($this->findPending($student->id, $period))]);
    }

    /**
     * `GET /api/v1/student/evaluations/bootstrap` — write (level), STUDENT.
     * Read-only aggregate; proceeds with nulls when no active period.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $claims = $this->claims($request);
        if (!$this->inGroups($claims, ['STUDENT'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $periods = EvaluationPeriod::orderBy('id')->get();
        $period = $this->activePeriod();
        $student = $this->selfStudent($claims);

        $pending = ($period && $student) ? $this->enrichPending($this->findPending($student->id, $period)) : [];
        $evaluations = ($period && $student)
            ? Evaluation::where('evaluator_id', $student->id)
                ->where('evaluation_period_id', $period->id)
                ->orderByDesc('id')->get()->map(fn ($e) => $this->enriched($e))->all()
            : [];
        $rubric = $period ? $this->groupedSnapshots($period->id) : null;

        return response()->json([
            'periods' => $periods,
            'activePeriodId' => $period?->id,
            'activePeriodName' => $period?->name,
            'pending' => $pending,
            'evaluations' => $evaluations,
            'rubric' => $rubric,
        ]);
    }

    /**
     * `POST /api/v1/evaluations/dispute` — write, STUDENT.
     * Mail to ADMIN holders is behind the dispute-mail flag and needs the
     * (unscheduled) mail service — skipped until it lands; audit persists.
     */
    public function dispute(Request $request): JsonResponse
    {
        $claims = $this->claims($request);
        if (!$this->inGroups($claims, ['STUDENT'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $mappingId = $request->input('facultySubjectId');
        $evaluateeId = $request->input('evaluateeId');
        if (!$mappingId || !$evaluateeId) {
            return response()->json(['error' => 'facultySubjectId and evaluateeId are required'], 400);
        }

        $period = $this->activePeriod();
        if ($period === null) {
            return response()->json(['error' => 'No active evaluation period'], 400);
        }
        if (!EvaluationPeriod::find($period->id)) {
            return response()->json(['error' => 'Invalid evaluation period'], 400);
        }

        $student = $this->selfStudent($claims);
        if ($this->findExisting($student?->id, $mappingId, $period->semester_id) === null) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $remarks = 'Reported as wrong section/subject : '
            . ($request->input('subjectName') ?: 'Unknown')
            . ' for Faculty: '
            . ($request->input('evaluateeName') ?: $evaluateeId);

        $existing = Evaluation::where('evaluation_period_id', $period->id)
            ->where('evaluator_id', $student?->id)
            ->where('faculty_subject_id', $mappingId)
            ->first();

        if ($existing) {
            $existing->update(['is_disabled' => true, 'status' => 'INVALID', 'remarks' => $remarks]);
        } else {
            Evaluation::create([
                'evaluation_period_id' => $period->id,
                'semester_id' => $period->semester_id,
                'evaluator_id' => $student?->id,
                'evaluatee_id' => $evaluateeId,
                'faculty_subject_id' => $mappingId,
                'source' => 'dispute',
            ]);
            Evaluation::where('evaluator_id', $student?->id)
                ->where('faculty_subject_id', $mappingId)
                ->where('evaluation_period_id', $period->id)
                ->update(['is_disabled' => true, 'status' => 'INVALID', 'remarks' => $remarks]);
        }

        try {
            app(\App\Services\AuditLogger::class)->fromClaims('EVALUATION_DISPUTE', $claims, [
                'facultySubjectId' => $mappingId,
                'currentFacultyId' => $evaluateeId,
                'currentFacultyName' => $request->input('evaluateeName'),
                'subjectName' => $request->input('subjectName'),
                'studentName' => $claims['name'] ?? null,
                'studentEmail' => $claims['email'] ?? null,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Audit log failed: ' . $e->getMessage(), ['exception' => $e]);
        }

        return response()->json(['success' => true]);
    }

    /** `GET /api/v1/evaluation-comments` — read. Filtered list w/ linkage. */
    public function allComments(Request $request): JsonResponse
    {
        $periodId = $request->query(
            'evaluationPeriodId',
            $request->query('semesterId', $request->query('periodId'))
        );
        $label = $request->query('sentimentLabel');

        $query = EvaluationComment::with('evaluation');
        if ($periodId) {
            $query->whereHas('evaluation', fn ($q) => $q->where('evaluation_period_id', $periodId));
        }
        if ($label) {
            $query->where('sentiment_label', $label);
        }

        return response()->json(['comments' => $query->get()]);
    }

    // ── Internals ────────────────────────────────────────────────

    private function claims(Request $request): array
    {
        return $request->attributes->get('jwt_claims', []);
    }

    private function inGroups(array $claims, array $want): bool
    {
        $groups = $claims['groups'] ?? [];

        return is_array($groups)
            && count(array_intersect(array_map(fn ($g) => strtoupper((string) $g), $groups), $want)) > 0;
    }

    private function selfStudent(array $claims): ?Student
    {
        $email = strtolower(trim((string) ($claims['email'] ?? '')));

        return $email === '' ? null : Student::where('email', $email)->first();
    }

    /** Exactly-one-active semantics (legacy findActive). */
    private function activePeriod(): ?EvaluationPeriod
    {
        $active = EvaluationPeriod::where('is_active', true)->get();

        return $active->count() === 1 ? $active->first() : null;
    }

    /** Aliases: periodId / evaluationPeriodId / semesterId (prefer period). */
    private function resolvePeriod(Request $request): ?EvaluationPeriod
    {
        $given = $request->input('periodId', $request->input('evaluationPeriodId'));

        if ($given) {
            return EvaluationPeriod::find($given);
        }

        $semesterId = $request->input('semesterId');
        if ($semesterId) {
            return EvaluationPeriod::where('semester_id', $semesterId)
                ->where('is_active', true)->first() ?? $this->activePeriod();
        }

        return $this->activePeriod();
    }

    private function findExisting(?string $studentId, mixed $mappingId, mixed $semesterId): ?StudentEnrollment
    {
        if (!$studentId || !$mappingId) {
            return null;
        }

        $query = StudentEnrollment::where('student_id', $studentId)
            ->where('faculty_subject_id', $mappingId);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        } else {
            $query->whereNull('semester_id');
        }

        return $query->first();
    }

    /** Owner + DRAFT|SUBMITTED + not disabled, else null (404-masking). */
    private function owned(Request $request, string $id): ?Evaluation
    {
        $evaluation = Evaluation::find($id);
        if ($evaluation === null) {
            return null;
        }

        $student = $this->selfStudent($this->claims($request));
        if ($student === null || $evaluation->evaluator_id !== $student->id) {
            return null;
        }
        if (!in_array($evaluation->status, ['DRAFT', 'SUBMITTED'], true)) {
            return null;
        }
        if ($evaluation->is_disabled) {
            return null;
        }

        return $evaluation;
    }

    private function enriched(Evaluation $evaluation): array
    {
        $mapping = $evaluation->faculty_subject_id
            ? FacultySubject::find($evaluation->faculty_subject_id)
            : null;
        $faculty = $mapping ? Employee::find($mapping->faculty_id) : Employee::find($evaluation->evaluatee_id);
        $subject = $mapping ? Subject::find($mapping->subject_id) : null;
        $section = ($mapping && $mapping->section_id) ? Section::find($mapping->section_id) : null;

        return array_merge($evaluation->toArray(), [
            'evaluateeName' => $faculty?->name ?? 'Unknown',
            'subjectId' => $mapping?->subject_id ?? '',
            'subjectCode' => $subject?->code ?? '',
            'subjectName' => $subject?->name ?? '',
            'sectionName' => $section?->name ?? '',
        ]);
    }

    /**
     * Pending mappings for a student (legacy-verified): direct mapping links
     * first, section fallback otherwise; stale evaluatee rows auto-disabled;
     * active + disabled sets skipped.
     */
    private function findPending(string $studentId, EvaluationPeriod $period): array
    {
        $enrollments = StudentEnrollment::where('student_id', $studentId)->get();
        if ($enrollments->isEmpty()) {
            return [];
        }

        $existing = Evaluation::where('evaluator_id', $studentId)
            ->where('evaluation_period_id', $period->id)
            ->get();

        $byMapping = [];
        $active = [];
        $disabled = [];
        foreach ($existing as $ev) {
            if (!$ev->faculty_subject_id) {
                continue;
            }
            $byMapping[$ev->faculty_subject_id] = $ev;
            if ($ev->is_disabled) {
                $disabled[$ev->faculty_subject_id] = true;
            } else {
                $active[$ev->faculty_subject_id] = true;
            }
        }

        $directIds = $enrollments->map(fn ($e) => $e->faculty_subject_id)->filter()->unique()->values();

        if ($directIds->isNotEmpty()) {
            $mappings = FacultySubject::whereIn('id', $directIds)->get();

            return $this->pendingFromMappings($mappings, $byMapping, $active, $disabled);
        }

        $sectionIds = $enrollments->map(fn ($e) => $e->section_id)->filter()->unique()->values();
        $mappings = FacultySubject::whereIn('section_id', $sectionIds)
            ->where('semester_id', $period->semester_id)
            ->get();

        return $this->pendingFromMappings($mappings, $byMapping, $active, $disabled);
    }

    private function pendingFromMappings($mappings, array $byMapping, array $active, array $disabled): array
    {
        $stale = [];
        foreach ($mappings as $fs) {
            $ev = $byMapping[$fs->id] ?? null;
            if ($ev && $ev->evaluatee_id !== $fs->faculty_id && !$ev->is_disabled) {
                $stale[] = $ev->id;
            }
        }
        if ($stale !== []) {
            Evaluation::whereIn('id', $stale)->update(['is_disabled' => true]);
            foreach ($stale as $id) {
                unset($active[$byMapping[$id]->faculty_subject_id ?? '']);
            }
        }

        $skip = array_merge(array_keys($active), array_keys($disabled));
        foreach ($stale as $id) {
            $fsId = $byMapping[$id]->faculty_subject_id ?? null;
            if ($fsId) {
                $skip = array_diff($skip, [$fsId]);
            }
        }

        return $mappings->reject(fn ($fs) => in_array($fs->id, $skip, true))->values()->all();
    }

    private function enrichPending(array $mappings): array
    {
        $facultyIds = collect($mappings)->map(fn ($fs) => $fs->faculty_id)->unique()->values();
        $subjectIds = collect($mappings)->map(fn ($fs) => $fs->subject_id)->unique()->values();
        $faculty = Employee::whereIn('id', $facultyIds)->get()->keyBy('id');
        $subjects = Subject::whereIn('id', $subjectIds)->get()->keyBy('id');

        return array_map(function ($fs) use ($faculty, $subjects) {
            $f = $faculty->get($fs->faculty_id);
            $s = $subjects->get($fs->subject_id);

            return [
                'evaluateeId' => $fs->faculty_id,
                'evaluateeName' => $f?->name ?? 'Unknown',
                'evaluateeEmail' => $f?->email ?? '',
                'facultySubjectId' => $fs->id,
                'subjectId' => $fs->subject_id,
                'subjectCode' => $s?->code ?? '',
                'subjectName' => $s?->name ?? '',
            ];
        }, $mappings);
    }

    private function groupedSnapshots(string $periodId): array
    {
        $rows = RubricGroupSnapshot::where('evaluation_period_id', $periodId)
            ->orderBy('category_display_order')
            ->orderBy('item_display_order')
            ->get();

        $cats = [];
        foreach ($rows as $row) {
            $key = $row->category_name;
            if (!isset($cats[$key])) {
                $cats[$key] = [
                    'id' => $row->category_id ?? "snap_{$row->rubric_group_id}_{$row->category_display_order}",
                    'name' => $row->category_name,
                    'displayOrder' => $row->category_display_order,
                    'items' => [],
                ];
            }
            $cats[$key]['items'][] = [
                'id' => $row->item_id ?? "snap_item_{$row->rubric_group_id}_{$row->category_display_order}_{$row->item_display_order}",
                'text' => $row->item_text,
                'displayOrder' => $row->item_display_order,
                'weight' => $row->item_weight,
            ];
        }

        return array_values($cats);
    }
}
