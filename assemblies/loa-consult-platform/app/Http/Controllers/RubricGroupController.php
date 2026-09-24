<?php

namespace App\Http\Controllers;

use App\Models\EvaluationPeriod;
use App\Models\RubricCategory;
use App\Models\RubricGroup;
use App\Models\RubricGroupSnapshot;
use App\Models\RubricItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RubricGroupController extends Controller
{
    /**
     * Seed/lock rule (legacy-verified messages). Seed message differs per op
     * (edited/modified/deleted); locked always 409 here (legacy mapped some
     * paths to 500 — module spec rates locked 409 everywhere).
     *
     * @return array{0: ?RubricGroup, 1: ?JsonResponse}
     */
    private function editable(string $id, string $seedVerb): array
    {
        $group = RubricGroup::find($id);
        if ($group === null) {
            return [null, response()->json(['error' => 'Rubric group not found'], 404)];
        }
        if ($group->seed) {
            return [null, response()->json(
                ['error' => "This is the original rubric group and cannot be $seedVerb. Duplicate it to create your own version."],
                409
            )];
        }
        if ($this->locked($id)) {
            return [null, response()->json(
                ['error' => 'Rubric group is locked (assigned to an active evaluation period). Duplicate it to make changes.'],
                409
            )];
        }

        return [$group, null];
    }

    private function locked(string $id): bool
    {
        return EvaluationPeriod::where('rubric_group_id', $id)
            ->where('is_active', true)
            ->exists();
    }

    /** `GET /api/v1/rubric-groups` — read. `{groups}` with editor payload. */
    public function index(): JsonResponse
    {
        $groups = RubricGroup::with('categories.items')->orderBy('id')->get();

        return response()->json(['groups' => $groups]);
    }

    /** `POST /api/v1/rubric-groups` — admin. 201 `{group}`. */
    public function store(Request $request): JsonResponse
    {
        if (!$request->input('name')) {
            return response()->json(['error' => 'name is required'], 400);
        }

        $group = RubricGroup::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        return response()->json(['group' => $group], 201);
    }

    /** `GET /api/v1/rubric-groups/{id}` — read. */
    public function show(string $id): JsonResponse
    {
        $group = RubricGroup::with('categories.items')->find($id);
        if ($group === null) {
            return response()->json(['error' => 'Rubric group not found'], 404);
        }

        return response()->json(['group' => $group]);
    }

    /** `PATCH /api/v1/rubric-groups/{id}` — admin + editable. */
    public function update(Request $request, string $id): JsonResponse
    {
        [$group, $error] = $this->editable($id, 'edited');
        if ($error !== null) {
            return $error;
        }

        $group->fill(array_filter([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ], fn ($v) => $v !== null));
        $group->save();

        return response()->json(['group' => $group]);
    }

    /** `DELETE /api/v1/rubric-groups/{id}` — admin + editable. */
    public function destroy(string $id): JsonResponse
    {
        [$group, $error] = $this->editable($id, 'deleted');
        if ($error !== null) {
            return $error;
        }
        $group->delete();

        return response()->json(['success' => true]);
    }

    /** `POST /api/v1/rubric-groups/{id}/items` — admin + editable. 201. */
    public function storeItem(Request $request, string $id): JsonResponse
    {
        [, $error] = $this->editable($id, 'modified');
        if ($error !== null) {
            return $error;
        }

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

    /** `PATCH /api/v1/rubric-groups/{id}/items/{itemId}` — admin + editable. */
    public function updateItem(Request $request, string $id, string $itemId): JsonResponse
    {
        [, $error] = $this->editable($id, 'modified');
        if ($error !== null) {
            return $error;
        }

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

    /** `DELETE /api/v1/rubric-groups/{id}/items/{itemId}` — admin + editable. */
    public function destroyItem(string $id, string $itemId): JsonResponse
    {
        [, $error] = $this->editable($id, 'modified');
        if ($error !== null) {
            return $error;
        }

        $item = RubricItem::find($itemId);
        if ($item === null) {
            return response()->json(['error' => 'Rubric item not found'], 404);
        }
        $item->delete();

        return response()->json(['success' => true]);
    }

    /**
     * `POST /api/v1/rubric-groups/{id}/duplicate` — admin. Locked → 409;
     * seed duplication allowed (that is its purpose). Deep copy.
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        $group = RubricGroup::with('categories.items')->find($id);
        if ($group === null) {
            return response()->json(['error' => 'Rubric group not found'], 404);
        }
        if ($this->locked($id)) {
            return response()->json(
                ['error' => 'Rubric group is locked. Duplicate it to make changes.'],
                409
            );
        }
        if (!$request->input('name')) {
            return response()->json(['error' => 'name is required'], 400);
        }

        $copy = RubricGroup::create([
            'name' => $request->input('name'),
            'description' => $group->description,
        ]);

        foreach ($group->categories as $cat) {
            $newCat = RubricCategory::create([
                'rubric_group_id' => $copy->id,
                'name' => $cat->name,
                'display_order' => $cat->display_order,
            ]);
            foreach ($cat->items as $item) {
                RubricItem::create([
                    'category_id' => $newCat->id,
                    'text' => $item->text,
                    'display_order' => $item->display_order,
                    'weight' => $item->weight,
                ]);
            }
        }

        return response()->json(['group' => $copy], 201);
    }

    /** `GET /api/v1/rubric-groups/{id}/snapshot` — read. Stored rows. */
    public function snapshot(string $id): JsonResponse
    {
        if (RubricGroup::find($id) === null) {
            return response()->json(['error' => 'Rubric group not found'], 404);
        }

        $rows = RubricGroupSnapshot::where('rubric_group_id', $id)
            ->orderBy('category_display_order')
            ->orderBy('item_display_order')
            ->get();

        return response()->json(['snapshot' => $rows]);
    }

    /** `POST /api/v1/rubric-groups/{id}/categories` — admin + editable. */
    public function storeCategory(Request $request, string $id): JsonResponse
    {
        [, $error] = $this->editable($id, 'modified');
        if ($error !== null) {
            return $error;
        }

        if (!$request->input('name')) {
            return response()->json(['error' => 'name is required'], 400);
        }

        $category = RubricCategory::create([
            'rubric_group_id' => $id,
            'name' => $request->input('name'),
            'display_order' => (int) $request->input('displayOrder', $request->input('display_order', 0)),
        ]);

        return response()->json(['category' => $category], 201);
    }

    /** `DELETE /api/v1/rubric-groups/{id}/categories` — admin + editable. */
    public function destroyCategory(Request $request, string $id): JsonResponse
    {
        [, $error] = $this->editable($id, 'modified');
        if ($error !== null) {
            return $error;
        }

        if (!$request->input('categoryId')) {
            return response()->json(['error' => 'categoryId is required'], 400);
        }

        $category = RubricCategory::find($request->input('categoryId'));
        if ($category === null) {
            return response()->json(['error' => 'Category not found'], 404);
        }
        $category->delete();

        return response()->json(['success' => true]);
    }
}
