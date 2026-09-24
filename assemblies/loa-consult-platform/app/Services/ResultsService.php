<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationComment;
use App\Models\EvaluationPeriod;
use App\Models\EvaluationRating;
use App\Models\EvaluationResult;
use App\Models\FacultySubject;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * Evaluation results computation + aggregation reads (module spec §5–6,
 * legacy-verified against evaluation-results.repository + route handlers).
 *
 * Conventions: 2-decimal rounding throughout; remarks scale shared;
 * response keys stay camelCase (frontend untouched); averages skip nulls.
 */
class ResultsService
{
    public const CATEGORY_MAP = [
        'Professional Manner' => 'professional_manner',
        'Communication with Students' => 'communication_with_student',
        'Student Engagement' => 'student_engagement',
        'Learning Materials' => 'learning_materials',
        'Time Management' => 'time_management',
        'Experiential Learning Provided to Students' => 'experiential_learning',
        'Experiential Learning' => 'experiential_learning',
        'Respect the Uniqueness of the Students' => 'respect_uniqueness',
        'Respect for Uniqueness' => 'respect_uniqueness',
        'Assessment and Feedback' => 'assessment_and_feedback',
    ];

    public static function remark(?float $general): ?string
    {
        if ($general === null) {
            return null;
        }
        if ($general >= 4.5) {
            return 'Outstanding';
        }
        if ($general >= 3.5) {
            return 'Very Satisfactory';
        }
        if ($general >= 2.5) {
            return 'Satisfactory';
        }
        if ($general >= 1.5) {
            return 'Unsatisfactory';
        }

        return 'Poor';
    }

    public static function r2(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }

    public static function highestLowest(array $averages): array
    {
        $entries = [];
        foreach ($averages as $key => $score) {
            if ($score !== null) {
                $entries[] = ['key' => $key, 'label' => $key, 'score' => $score];
            }
        }
        if (count($entries) === 0) {
            return ['highest' => [], 'lowest' => []];
        }
        $scores = array_column($entries, 'score');

        return [
            'highest' => array_values(array_filter($entries, fn ($e) => $e['score'] === max($scores))),
            'lowest' => array_values(array_filter($entries, fn ($e) => $e['score'] === min($scores))),
        ];
    }

    /**
     * Full recompute for a period (optionally one faculty): SUBMITTED +
     * enabled + mapped evaluations → per-faculty rows → stale prune.
     */
    public function computeAll(string $periodId, ?string $facultyId = null): void
    {
        $period = EvaluationPeriod::find($periodId);
        if ($period === null) {
            return;
        }

        $query = Evaluation::where('evaluation_period_id', $periodId)
            ->where('status', 'SUBMITTED')
            ->where('is_disabled', false)
            ->whereNotNull('faculty_subject_id');
        if ($facultyId !== null) {
            $query->where('evaluatee_id', $facultyId);
        }
        $evals = $query->get(['id', 'evaluatee_id', 'faculty_subject_id']);
        if ($evals->isEmpty()) {
            return;
        }

        $byFaculty = [];
        foreach ($evals as $ev) {
            $byFaculty[$ev->evaluatee_id][] = $ev->id;
        }

        $ratings = EvaluationRating::with('item.category')
            ->whereIn('evaluation_id', $evals->pluck('id'))
            ->get();

        $ratingsByEval = [];
        foreach ($ratings as $r) {
            $ratingsByEval[$r->evaluation_id][] = $r;
        }

        $deptOf = $this->facultyDepartments(array_keys($byFaculty), $period->semester_id);
        $existing = EvaluationResult::where('evaluation_period_id', $periodId)
            ->whereIn('faculty_id', array_keys($byFaculty))
            ->get()->keyBy('faculty_id');

        foreach ($byFaculty as $facId => $evalIds) {
            $catVals = [];
            foreach ($evalIds as $eid) {
                foreach ($ratingsByEval[$eid] ?? [] as $r) {
                    $catName = $r->item?->category?->name;
                    if ($catName) {
                        $catVals[$catName][] = $r->rating;
                    }
                }
            }

            $averages = [];
            foreach ($catVals as $cat => $vals) {
                $averages[$cat] = array_sum($vals) / count($vals);
            }
            $general = count($averages) > 0
                ? array_sum($averages) / count($averages)
                : null;

            $data = [
                'semester_id' => $period->semester_id,
                'total_respondents' => count($evalIds),
                'general_rating' => self::r2($general),
                'remarks' => self::remark($general !== null ? self::r2($general) : null),
                'department_id' => $deptOf[$facId] ?? null,
                'computed_at' => now(),
            ];
            foreach (self::CATEGORY_MAP as $catName => $col) {
                if (isset($averages[$catName])) {
                    $data[$col] = self::r2($averages[$catName]);
                }
            }

            if (isset($existing[$facId])) {
                $existing[$facId]->update($data);
            } else {
                EvaluationResult::create(array_merge([
                    'evaluation_period_id' => $periodId,
                    'faculty_id' => $facId,
                ], $data));
            }
        }

        // Prune stale rows (faculty with no qualifying evaluations left).
        EvaluationResult::where('evaluation_period_id', $periodId)
            ->whereNotIn('faculty_id', array_keys($byFaculty))
            ->delete();
    }

    /** Recompute when stored rows lack category data (lazy-read rule). */
    public function lazyEnsure(string $periodId): void
    {
        $rows = EvaluationResult::where('evaluation_period_id', $periodId)->get();
        if ($rows->isEmpty()) {
            $this->computeAll($periodId);

            return;
        }

        $cols = array_merge(EvaluationResult::CATEGORIES, ['general_rating']);
        foreach ($rows as $row) {
            foreach ($cols as $col) {
                if ($row->{$col} !== null) {
                    return;
                }
            }
        }

        $this->computeAll($periodId);
    }

    public function setVisibility(string $periodId, array $facultyIds, bool $visible): void
    {
        $period = EvaluationPeriod::find($periodId);
        $existing = EvaluationResult::where('evaluation_period_id', $periodId)
            ->whereIn('faculty_id', $facultyIds)
            ->pluck('faculty_id')->all();

        if (count($existing) > 0) {
            EvaluationResult::where('evaluation_period_id', $periodId)
                ->whereIn('faculty_id', $existing)
                ->update(['is_results_visible' => $visible]);
        }

        foreach (array_diff($facultyIds, $existing) as $fid) {
            EvaluationResult::create([
                'evaluation_period_id' => $periodId,
                'semester_id' => $period?->semester_id,
                'faculty_id' => $fid,
                'is_results_visible' => $visible,
                'total_respondents' => 0,
            ]);
        }
    }

    public function visibilityMap(string $periodId): array
    {
        return EvaluationResult::where('evaluation_period_id', $periodId)
            ->pluck('is_results_visible', 'faculty_id')->all();
    }

    /** Avg comment sentiment per faculty over submitted period evaluations. */
    public function sentimentByFaculty(string $periodId): array
    {
        $evalIds = Evaluation::where('evaluation_period_id', $periodId)
            ->where('status', 'SUBMITTED')
            ->where('is_disabled', false)
            ->pluck('id', 'id');
        if ($evalIds->isEmpty()) {
            return [];
        }

        $evalFaculty = Evaluation::whereIn('id', $evalIds->keys())
            ->pluck('evaluatee_id', 'id');

        $byFaculty = [];
        foreach (EvaluationComment::whereIn('evaluation_id', $evalIds->keys())->get() as $c) {
            if ($c->sentiment_score !== null && isset($evalFaculty[$c->evaluation_id])) {
                $byFaculty[$evalFaculty[$c->evaluation_id]][] = (float) $c->sentiment_score;
            }
        }

        $avg = [];
        foreach ($byFaculty as $fid => $scores) {
            $avg[$fid] = self::r2(array_sum($scores) / count($scores));
        }

        return $avg;
    }

    /**
     * Admin-base department aggregates (legacy-verified keys, camelCase).
     */
    public function departmentAggregates(string $periodId): array
    {
        $this->lazyEnsure($periodId);

        $results = EvaluationResult::where('evaluation_period_id', $periodId)->get();
        if ($results->isEmpty()) {
            return [];
        }

        $sentMap = $this->sentimentByFaculty($periodId);
        $depts = Department::all()->keyBy('id');
        $cats = EvaluationResult::CATEGORIES;

        $groups = [];
        foreach ($results as $row) {
            $deptId = $row->department_id ?? '__unknown__';
            $groups[$deptId] ??= [
                'faculty' => [], 'respondents' => 0, 'generals' => [],
                'cats' => [], 'sentiments' => [],
            ];
            $g = &$groups[$deptId];
            $g['faculty'][$row->faculty_id] = true;
            $g['respondents'] += $row->total_respondents;
            if ($row->general_rating !== null) {
                $g['generals'][] = (float) $row->general_rating;
            }
            foreach ($cats as $col) {
                if ($row->{$col} !== null) {
                    $g['cats'][$col][] = (float) $row->{$col};
                }
            }
            if (isset($sentMap[$row->faculty_id])) {
                $g['sentiments'][] = $sentMap[$row->faculty_id];
            }
            unset($g);
        }

        $out = [];
        foreach ($groups as $deptId => $g) {
            $avg = count($g['generals']) > 0 ? self::r2(array_sum($g['generals']) / count($g['generals'])) : null;
            $catAvgs = [];
            foreach ($cats as $col) {
                $vals = $g['cats'][$col] ?? [];
                $catAvgs[$col] = count($vals) > 0 ? self::r2(array_sum($vals) / count($vals)) : null;
            }
            $sent = count($g['sentiments']) > 0 ? self::r2(array_sum($g['sentiments']) / count($g['sentiments'])) : null;
            $dept = $depts->get($deptId);
            $rubrics = self::highestLowest($catAvgs);

            $out[] = [
                'departmentId' => $deptId,
                'departmentName' => $dept?->name ?? ($deptId === '__unknown__' ? 'Unassigned' : 'Unknown'),
                'departmentCode' => $dept?->code ?? '',
                'facultyCount' => count($g['faculty']),
                'totalRespondents' => $g['respondents'],
                'avgRating' => $avg,
                'remarks' => self::remark($avg),
                'highestRubrics' => $rubrics['highest'],
                'lowestRubrics' => $rubrics['lowest'],
                'sentimentScore' => $sent,
            ];
        }

        return $out;
    }

    /** Per-faculty subject-split aggregates (faculty + admin/faculty drill). */
    public function subjectSplits(string $periodId, string $facultyId): array
    {
        $evals = Evaluation::where('evaluation_period_id', $periodId)
            ->where('evaluatee_id', $facultyId)
            ->where('status', 'SUBMITTED')
            ->where('is_disabled', false)
            ->get();
        if ($evals->isEmpty()) {
            return [];
        }

        $ratings = EvaluationRating::with('item.category')
            ->whereIn('evaluation_id', $evals->pluck('id'))->get();
        $byEval = [];
        foreach ($ratings as $r) {
            $byEval[$r->evaluation_id][] = $r;
        }

        $comments = EvaluationComment::whereIn('evaluation_id', $evals->pluck('id'))->get();
        $sentByEval = [];
        foreach ($comments as $c) {
            if ($c->sentiment_score !== null) {
                $sentByEval[$c->evaluation_id][] = (float) $c->sentiment_score;
            }
        }

        $mappings = FacultySubject::whereIn('id', $evals->pluck('faculty_subject_id')->filter()->unique())->get()->keyBy('id');
        $subjects = Subject::whereIn('id', $mappings->pluck('subject_id')->filter()->unique())->get()->keyBy('id');

        $byMapping = [];
        foreach ($evals as $ev) {
            $byMapping[$ev->faculty_subject_id ?? $ev->id][] = $ev->id;
        }

        $out = [];
        foreach ($byMapping as $key => $ids) {
            $catVals = [];
            foreach ($ids as $eid) {
                foreach ($byEval[$eid] ?? [] as $r) {
                    $name = $r->item?->category?->name;
                    if ($name) {
                        $catVals[$name][] = $r->rating;
                    }
                }
            }
            $averages = [];
            foreach ($catVals as $cat => $vals) {
                $averages[$cat] = array_sum($vals) / count($vals);
            }
            $general = count($averages) > 0 ? self::r2(array_sum($averages) / count($averages)) : null;

            $cols = [];
            foreach (self::CATEGORY_MAP as $catName => $col) {
                if (isset($averages[$catName])) {
                    $cols[$col] = self::r2($averages[$catName]);
                }
            }

            $sent = [];
            foreach ($ids as $eid) {
                foreach ($sentByEval[$eid] ?? [] as $s) {
                    $sent[] = $s;
                }
            }

            $fs = is_numeric($key) ? $mappings->get($key) : null;
            $subj = $fs ? $subjects->get($fs->subject_id) : null;
            $rubrics = self::highestLowest($cols);

            $out[] = array_merge([
                'facultySubjectId' => $key,
                'subjectCode' => $subj?->code ?? '',
                'subjectName' => $subj?->name ?? '',
                'totalRespondents' => count($ids),
                'avgRating' => $general,
                'remarks' => self::remark($general),
                'highestRubrics' => $rubrics['highest'],
                'lowestRubrics' => $rubrics['lowest'],
                'sentimentScore' => count($sent) > 0 ? self::r2(array_sum($sent) / count($sent)) : null,
            ], $cols);
        }

        return $out;
    }

    /** Anonymized per-student breakdowns for one faculty (dean details). */
    public function studentBreakdowns(string $periodId, string $facultyId): array
    {
        $evals = Evaluation::where('evaluation_period_id', $periodId)
            ->where('evaluatee_id', $facultyId)
            ->where('status', 'SUBMITTED')
            ->where('is_disabled', false)
            ->whereNotNull('faculty_subject_id')
            ->get();
        if ($evals->isEmpty()) {
            return [];
        }

        $ratings = EvaluationRating::with('item.category')
            ->whereIn('evaluation_id', $evals->pluck('id'))->get();
        $byEval = [];
        foreach ($ratings as $r) {
            $byEval[$r->evaluation_id][] = ['text' => $r->item?->text ?? '', 'rating' => $r->rating];
        }

        $comments = EvaluationComment::whereIn('evaluation_id', $evals->pluck('id'))->get()->keyBy('evaluation_id');
        $mappings = FacultySubject::whereIn('id', $evals->pluck('faculty_subject_id')->unique())->get()->keyBy('id');
        $subjects = Subject::whereIn('id', $mappings->pluck('subject_id')->filter()->unique())->get()->keyBy('id');

        $out = [];
        $i = 0;
        foreach ($evals as $ev) {
            $i++;
            $fs = $mappings->get($ev->faculty_subject_id);
            $subj = $fs ? $subjects->get($fs->subject_id) : null;
            $c = $comments->get($ev->id);
            $out[] = [
                'id' => "S$i",
                'subjectCode' => $subj?->code ?? '',
                'subjectName' => $subj?->name ?? '',
                'ratings' => $byEval[$ev->id] ?? [],
                'comment' => $c?->comment,
                'sentimentLabel' => $c?->sentiment_label,
                'sentimentScore' => $c?->sentiment_score !== null ? (float) $c->sentiment_score : null,
            ];
        }

        return $out;
    }

    /** Full single-evaluation assembly (admin details, legacy-verified keys). */
    public function evaluationDetails(string $evaluationId): ?array
    {
        $ev = Evaluation::find($evaluationId);
        if ($ev === null) {
            return null;
        }

        $ratings = EvaluationRating::with('item.category')
            ->where('evaluation_id', $evaluationId)->get();

        $grouped = [];
        foreach ($ratings as $r) {
            $catName = $r->item?->category?->name ?? 'Uncategorized';
            $grouped[$catName]['name'] = $catName;
            $grouped[$catName]['items'][] = ['text' => $r->item?->text ?? '', 'rating' => $r->rating];
        }
        $categories = array_values(array_map(fn ($name, $g) => [
            'categoryName' => $name, 'items' => $g['items'],
        ], array_keys($grouped), $grouped));

        $comment = EvaluationComment::where('evaluation_id', $evaluationId)->first();
        $evaluator = $ev->evaluator_id ? Student::find($ev->evaluator_id) : null;

        return [
            'evaluationId' => $ev->id,
            'submittedAt' => $ev->submitted_at,
            'evaluatorName' => $evaluator?->name ?? '',
            'categories' => $categories,
            'comment' => $comment?->comment,
            'sentimentLabel' => $comment?->sentiment_label,
            'sentimentScore' => $comment?->sentiment_score !== null ? (float) $comment->sentiment_score : null,
            'isDisabled' => (bool) $ev->is_disabled,
        ];
    }

    /** Bulk disable SUBMITTED rows for a period (+ optional target). */
    public function bulkDisable(string $periodId, ?string $facultyId, ?string $mappingId): void
    {
        $query = Evaluation::where('evaluation_period_id', $periodId)
            ->where('status', 'SUBMITTED');
        if ($mappingId) {
            $query->where('faculty_subject_id', $mappingId);
        } elseif ($facultyId) {
            $query->where('evaluatee_id', $facultyId);
        }
        $query->update(['is_disabled' => true]);
    }

    /** Invalidate one mapping's evaluations + recompute affected periods. */
    public function invalidateMapping(string $mappingId, string $remarks): void
    {
        $affected = Evaluation::where('faculty_subject_id', $mappingId)
            ->pluck('evaluation_period_id')->filter()->unique()->values();

        Evaluation::where('faculty_subject_id', $mappingId)
            ->update(['status' => 'INVALID', 'remarks' => $remarks, 'is_disabled' => true]);

        foreach ($affected as $pid) {
            $this->computeAll($pid);
        }
    }

    /** Invalidate one student's evaluations for a mapping + recompute. */
    public function invalidateStudentMapping(string $studentId, string $mappingId, string $remarks): void
    {
        $affected = Evaluation::where('evaluator_id', $studentId)
            ->where('faculty_subject_id', $mappingId)
            ->pluck('evaluation_period_id')->filter()->unique()->values();

        Evaluation::where('evaluator_id', $studentId)
            ->where('faculty_subject_id', $mappingId)
            ->update(['status' => 'INVALID', 'remarks' => $remarks, 'is_disabled' => true]);

        foreach ($affected as $pid) {
            $this->computeAll($pid);
        }
    }

    private function facultyDepartments(array $facultyIds, mixed $semesterId): array
    {
        $map = Employee::whereIn('id', $facultyIds)->pluck('department_id', 'id')->all();

        $missing = array_filter($facultyIds, fn ($id) => empty($map[$id]));
        if (count($missing) > 0 && $semesterId) {
            $rows = FacultySubject::whereIn('faculty_id', $missing)
                ->where('semester_id', $semesterId)
                ->get(['faculty_id', 'section_id']);
            $sectionIds = $rows->pluck('section_id')->filter()->unique()->values();
            if ($sectionIds->isNotEmpty()) {
                $sections = Section::whereIn('id', $sectionIds)->get(['id', 'department_course_id']);
                $courses = DB::table('department_courses')
                    ->whereIn('id', $sections->pluck('department_course_id')->filter()->unique())
                    ->pluck('department_id', 'id');
                foreach ($rows as $row) {
                    $sec = $sections->firstWhere('id', $row->section_id);
                    $dept = $sec ? ($courses[$sec->department_course_id] ?? null) : null;
                    if ($dept && empty($map[$row->faculty_id])) {
                        $map[$row->faculty_id] = $dept;
                    }
                }
            }
        }

        return $map;
    }
}
