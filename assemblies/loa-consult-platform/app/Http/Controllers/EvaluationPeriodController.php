<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\RubricCategory;
use App\Models\RubricGroup;
use App\Models\RubricGroupSnapshot;
use App\Models\RubricItem;
use App\Models\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationPeriodController extends Controller
{
    /** `GET /api/v1/evaluation-periods` — read. `{periods}` with counts. */
    public function index(Request $request): JsonResponse
    {
        $query = EvaluationPeriod::withCount('evaluations as evaluationCount')->orderBy('id');

        $semesterId = $request->query('semesterId', $request->query('semester_id'));
        if ($semesterId !== null && $semesterId !== '') {
            $query->where('semester_id', $semesterId);
        }

        return response()->json(['periods' => $query->get()]);
    }

    /** `POST /api/v1/evaluation-periods` — admin. 201 `{period}`. */
    public function store(Request $request): JsonResponse
    {
        $semesterId = $request->input('semesterId', $request->input('semester_id'));
        $name = $request->input('name');

        if (!$semesterId || !$name) {
            return response()->json(['error' => 'semesterId and name are required'], 400);
        }
        if (Semester::find($semesterId) === null) {
            return response()->json(['error' => 'Semester not found'], 404);
        }

        $period = EvaluationPeriod::create([
            'semester_id' => $semesterId,
            'name' => $name,
            'source' => $request->input('source'),
            'start_date' => $request->input('startDate', $request->input('start_date')),
            'end_date' => $request->input('endDate', $request->input('end_date')),
            'is_active' => (bool) $request->input('isActive', $request->input('is_active', false)),
            'rubric_group_id' => $request->input('rubricGroupId', $request->input('rubric_group_id')),
        ]);

        return response()->json(['period' => $period], 201);
    }

    /** `GET /api/v1/evaluation-periods/{id}` — read. */
    public function show(string $id): JsonResponse
    {
        $period = EvaluationPeriod::find($id);
        if ($period === null) {
            return response()->json(['error' => 'Evaluation period not found'], 404);
        }

        return response()->json(['period' => $period]);
    }

    /** `PUT /api/v1/evaluation-periods/{id}` — admin. Rubric swap guarded. */
    public function update(Request $request, string $id): JsonResponse
    {
        $period = EvaluationPeriod::find($id);
        if ($period === null) {
            return response()->json(['error' => 'Evaluation period not found'], 404);
        }

        $rubricGroupId = $request->input('rubricGroupId', $request->input('rubric_group_id'));
        if ($rubricGroupId !== null
            && (string) $rubricGroupId !== (string) $period->rubric_group_id
            && Evaluation::where('evaluation_period_id', $id)->exists()) {
            return response()->json(
                ['error' => 'Cannot change the rubric group on a period with existing evaluations. Use Reset first.'],
                400
            );
        }

        $period->fill(array_filter([
            'name' => $request->input('name'),
            'source' => $request->input('source'),
            'start_date' => $request->input('startDate', $request->input('start_date')),
            'end_date' => $request->input('endDate', $request->input('end_date')),
            'rubric_group_id' => $rubricGroupId,
        ], fn ($v) => $v !== null));

        if ($request->exists('isActive') || $request->exists('is_active')) {
            $period->is_active = (bool) $request->input('isActive', $request->input('is_active'));
        }
        if ($request->exists('semesterId') || $request->exists('semester_id')) {
            $period->semester_id = $request->input('semesterId', $request->input('semester_id'));
        }
        $period->save();

        return response()->json(['period' => $period]);
    }

    /** `DELETE /api/v1/evaluation-periods/{id}` — admin. FK cascade. */
    public function destroy(string $id): JsonResponse
    {
        $period = EvaluationPeriod::find($id);
        if ($period === null) {
            return response()->json(['error' => 'Evaluation period not found'], 404);
        }
        $period->delete();

        return response()->json(['success' => true]);
    }

    /**
     * `POST /api/v1/evaluation-periods/{id}/activate` — admin.
     * Global exclusivity + snapshot capture (legacy-verified).
     */
    public function activate(string $id): JsonResponse
    {
        $period = EvaluationPeriod::find($id);
        if ($period === null) {
            return response()->json(['error' => 'Evaluation period not found'], 404);
        }

        try {
            EvaluationPeriod::where('id', '!=', $id)->update(['is_active' => false]);
            $period->update(['is_active' => true]);
            if ($period->rubric_group_id) {
                $this->captureSnapshot($period->fresh());
            }
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Activation failed', 'detail' => $e->getMessage()], 500);
        }

        return response()->json(['period' => $period->fresh()]);
    }

    /** `POST /api/v1/evaluation-periods/{id}/reset` — admin. Invalid lineage. */
    public function reset(string $id): JsonResponse
    {
        $period = EvaluationPeriod::find($id);
        if ($period === null) {
            return response()->json(['error' => 'Evaluation period not found'], 404);
        }

        Evaluation::where('evaluation_period_id', $id)->update(['is_invalid' => true]);
        $period->update(['is_active' => false]);
        RubricGroupSnapshot::where('evaluation_period_id', $id)->delete();

        return response()->json(['success' => true]);
    }

    /** `GET /api/v1/evaluation-periods/{id}/rubric` — read. Raw snapshot rows. */
    public function rubric(string $id): JsonResponse
    {
        if (EvaluationPeriod::find($id) === null) {
            return response()->json(['error' => 'Evaluation period not found'], 404);
        }

        return response()->json(['rubric' => $this->snapshotRows($id)]);
    }

    /**
     * `POST /api/v1/evaluation-periods/{id}/rubric/copy` — read.
     * Misnomer preserved: identical snapshot fetch, no duplication occurs.
     */
    public function rubricCopy(string $id): JsonResponse
    {
        return $this->rubric($id);
    }

    /**
     * `POST /api/v1/evaluation-periods/{id}/rubrics/items` — admin.
     * Period `{id}` ignored (creates by categoryId) — preserved quirk.
     */
    public function storeItem(Request $request): JsonResponse
    {
        if (!$request->input('categoryId') || !$request->input('text')) {
            return response()->json(['error' => 'categoryId and text are required'], 400);
        }
        if (RubricCategory::find($request->input('categoryId')) === null) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        $item = RubricItem::create([
            'category_id' => $request->input('categoryId'),
            'text' => $request->input('text'),
            'display_order' => (int) $request->input('displayOrder', $request->input('display_order', 0)),
            'weight' => $request->input('weight', 1),
        ]);

        return response()->json(['item' => $item], 201);
    }

    /** `PATCH /api/v1/evaluation-periods/{id}/rubrics/items/{itemId}` — admin. */
    public function updateItem(Request $request, string $id, string $itemId): JsonResponse
    {
        $item = RubricItem::find($itemId);
        if ($item === null) {
            return response()->json(['error' => 'Rubric item not found'], 404);
        }

        $item->fill(array_filter([
            'text' => $request->input('text'),
            'display_order' => $request->input('displayOrder', $request->input('display_order')),
            'weight' => $request->input('weight'),
        ], fn ($v) => $v !== null));
        $item->save();

        return response()->json(['item' => $item]);
    }

    /** `DELETE /api/v1/evaluation-periods/{id}/rubrics/items/{itemId}` — admin. */
    public function destroyItem(string $id, string $itemId): JsonResponse
    {
        $item = RubricItem::find($itemId);
        if ($item === null) {
            return response()->json(['error' => 'Rubric item not found'], 404);
        }
        $item->delete();

        return response()->json(['success' => true]);
    }

    /** Snapshot capture: replace period rows with ordered group rows. */
    public function captureSnapshot(EvaluationPeriod $period): void
    {
        RubricGroupSnapshot::where('evaluation_period_id', $period->id)->delete();

        $group = RubricGroup::with('categories.items')->find($period->rubric_group_id);
        if ($group === null) {
            return;
        }

        $rows = [];
        foreach ($group->categories->sortBy('display_order') as $cat) {
            foreach ($cat->items->sortBy('display_order') as $item) {
                $rows[] = [
                    'evaluation_period_id' => $period->id,
                    'rubric_group_id' => $group->id,
                    'rubric_group_name' => $group->name,
                    'category_name' => $cat->name,
                    'category_display_order' => $cat->display_order,
                    'category_id' => $cat->id,
                    'item_text' => $item->text,
                    'item_display_order' => $item->display_order,
                    'item_weight' => $item->weight,
                    'item_id' => $item->id,
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            RubricGroupSnapshot::insert($chunk);
        }
    }

    private function snapshotRows(string $periodId): array
    {
        return RubricGroupSnapshot::where('evaluation_period_id', $periodId)
            ->orderBy('category_display_order')
            ->orderBy('item_display_order')
            ->get()
            ->toArray();
    }
}
