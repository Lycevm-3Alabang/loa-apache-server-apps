<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\RubricCategory;
use App\Models\RubricGroup;
use App\Models\RubricItem;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PeriodsRubricsTest extends TestCase
{
    use RefreshDatabase;

    private const PATTERNS = [
        '/api/v1/evaluation-periods',
        '/api/v1/evaluation-periods/{id}',
        '/api/v1/evaluation-periods/{id}/activate',
        '/api/v1/evaluation-periods/{id}/reset',
        '/api/v1/evaluation-periods/{id}/rubric',
        '/api/v1/evaluation-periods/{id}/rubric/copy',
        '/api/v1/evaluation-periods/{id}/rubrics/items',
        '/api/v1/evaluation-periods/{id}/rubrics/items/{itemId}',
        '/api/v1/rubric-groups',
        '/api/v1/rubric-groups/{id}',
        '/api/v1/rubric-groups/{id}/items',
        '/api/v1/rubric-groups/{id}/items/{itemId}',
        '/api/v1/rubric-groups/{id}/duplicate',
        '/api/v1/rubric-groups/{id}/snapshot',
        '/api/v1/rubric-groups/{id}/categories',
    ];

    private function auth(): array
    {
        $secret = config('jwt.secret');
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $permissions = array_map(fn ($p) => "admin:$p", self::PATTERNS);
        $payload = [
            'sub' => 'admin-1', 'email' => 'a@lyceumalabang.edu.ph', 'name' => 'Admin',
            'type' => 'access', 'tenant' => ['id' => 't-1', 'slug' => 'loa'],
            'groups' => ['ADMIN'], 'permissions' => $permissions,
            'iat' => time(), 'exp' => time() + 900,
        ];
        $encoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$encoded", $secret, true)), '+/', '-_'), '=');
        return ['Authorization' => "Bearer $header.$encoded.$sig", 'Accept' => 'application/json'];
    }

    private function semester(): Semester
    {
        return Semester::create(['title' => 'S1']);
    }

    private function group(string $name = 'G', bool $seed = false): RubricGroup
    {
        return RubricGroup::create(['name' => $name, 'seed' => $seed]);
    }

    private function category(RubricGroup $g, string $name = 'Cat'): RubricCategory
    {
        return RubricCategory::create(['rubric_group_id' => $g->id, 'name' => $name, 'display_order' => 0]);
    }

    private function item(RubricCategory $c, string $text = 'Item'): RubricItem
    {
        return RubricItem::create(['category_id' => $c->id, 'text' => $text, 'display_order' => 0, 'weight' => 1]);
    }

    public function test_period_crud_guards(): void
    {
        $h = $this->auth();
        $sem = $this->semester();

        $this->postJson('/api/v1/evaluation-periods', ['name' => 'P'], $h)->assertStatus(400);
        $this->postJson('/api/v1/evaluation-periods', ['semesterId' => 999999, 'name' => 'P'], $h)->assertNotFound();
        $id = $this->postJson('/api/v1/evaluation-periods', ['semesterId' => $sem->id, 'name' => 'P'], $h)
            ->assertCreated()->json('period.id');

        $this->getJson('/api/v1/evaluation-periods/999999', $h)->assertNotFound();
        $this->getJson('/api/v1/evaluation-periods?semesterId=' . $sem->id, $h)
            ->assertOk()->assertJsonCount(1, 'periods');

        $this->deleteJson('/api/v1/evaluation-periods/' . $id, [], $h)
            ->assertOk()->assertJson(['success' => true]);
    }

    public function test_period_update_rubric_swap_guarded(): void
    {
        $h = $this->auth();
        $sem = $this->semester();
        $g1 = $this->group('G1');
        $g2 = $this->group('G2');
        $period = EvaluationPeriod::create(['semester_id' => $sem->id, 'name' => 'P', 'rubric_group_id' => $g1->id]);

        $student = $this->person(Student::class, 's@itmlyceumalabang.onmicrosoft.com');
        $employee = $this->person(Employee::class, 'e@lyceumalabang.edu.ph');
        Evaluation::create([
            'evaluation_period_id' => $period->id, 'semester_id' => $sem->id,
            'evaluator_id' => $student->id, 'evaluatee_id' => $employee->id,
        ]);

        $this->putJson('/api/v1/evaluation-periods/' . $period->id, ['rubricGroupId' => $g2->id], $h)
            ->assertStatus(400)
            ->assertJson(['error' => 'Cannot change the rubric group on a period with existing evaluations. Use Reset first.']);
        $this->putJson('/api/v1/evaluation-periods/' . $period->id, ['name' => 'Renamed'], $h)
            ->assertOk()
            ->assertJsonPath('period.name', 'Renamed');
    }

    public function test_activate_exclusive_and_snapshots(): void
    {
        $h = $this->auth();
        $sem = $this->semester();
        $g = $this->group('G');
        $cat = $this->category($g);
        $this->item($cat, 'I1');
        $this->item($cat, 'I2');

        $p1 = EvaluationPeriod::create(['semester_id' => $sem->id, 'name' => 'P1', 'rubric_group_id' => $g->id]);
        $p2 = EvaluationPeriod::create(['semester_id' => $sem->id, 'name' => 'P2', 'is_active' => true]);

        $this->postJson('/api/v1/evaluation-periods/' . $p1->id . '/activate', [], $h)->assertOk();

        $this->assertEquals(1, EvaluationPeriod::where('is_active', true)->count());
        $this->getJson('/api/v1/evaluation-periods/' . $p1->id . '/rubric', $h)
            ->assertOk()->assertJsonCount(2, 'rubric');
        // Copy misnomer: identical fetch.
        $this->postJson('/api/v1/evaluation-periods/' . $p1->id . '/rubric/copy', [], $h)
            ->assertOk()->assertJsonCount(2, 'rubric');
        $this->getJson('/api/v1/rubric-groups/' . $g->id . '/snapshot', $h)
            ->assertOk()->assertJsonCount(2, 'snapshot');
    }

    public function test_reset_invalidates_and_clears(): void
    {
        $h = $this->auth();
        $sem = $this->semester();
        $g = $this->group('G');
        $period = EvaluationPeriod::create([
            'semester_id' => $sem->id, 'name' => 'P', 'is_active' => true, 'rubric_group_id' => $g->id,
        ]);
        $student = $this->person(Student::class, 's@itmlyceumalabang.onmicrosoft.com');
        $employee = $this->person(Employee::class, 'e@lyceumalabang.edu.ph');
        $eval = Evaluation::create([
            'evaluation_period_id' => $period->id, 'semester_id' => $sem->id,
            'evaluator_id' => $student->id, 'evaluatee_id' => $employee->id,
        ]);

        $this->postJson('/api/v1/evaluation-periods/' . $period->id . '/reset', [], $h)
            ->assertOk()->assertJson(['success' => true]);

        $this->assertTrue($eval->fresh()->is_invalid);
        $this->assertFalse($period->fresh()->is_active);
    }

    public function test_period_items_quirk_ignores_period_id(): void
    {
        $h = $this->auth();
        $cat = $this->category($this->group('G'));

        // Wrong period id still creates by categoryId (preserved quirk).
        $this->postJson('/api/v1/evaluation-periods/999999/rubrics/items', [
            'categoryId' => $cat->id, 'text' => 'Q', 'displayOrder' => 1,
        ], $h)->assertCreated()->assertJsonStructure(['item']);

        $itemId = RubricItem::first()->id;
        $this->patchJson('/api/v1/evaluation-periods/1/rubrics/items/' . $itemId, ['text' => 'Q2'], $h)
            ->assertOk()->assertJsonPath('item.text', 'Q2');
        $this->deleteJson('/api/v1/evaluation-periods/1/rubrics/items/' . $itemId, [], $h)
            ->assertOk()->assertJson(['success' => true]);
        $this->deleteJson('/api/v1/evaluation-periods/1/rubrics/items/999999', [], $h)->assertNotFound();
    }

    public function test_rubric_seed_and_locked_guards(): void
    {
        $h = $this->auth();
        $seed = $this->group('Seed', true);
        $plain = $this->group('Plain');

        $this->patchJson('/api/v1/rubric-groups/' . $seed->id, ['name' => 'X'], $h)
            ->assertStatus(409)
            ->assertJson(['error' => 'This is the original rubric group and cannot be edited. Duplicate it to create your own version.']);
        $this->deleteJson('/api/v1/rubric-groups/' . $seed->id, [], $h)
            ->assertStatus(409)
            ->assertJson(['error' => 'This is the original rubric group and cannot be deleted. Duplicate it to create your own version.']);

        $sem = $this->semester();
        EvaluationPeriod::create([
            'semester_id' => $sem->id, 'name' => 'P', 'is_active' => true, 'rubric_group_id' => $plain->id,
        ]);
        $this->patchJson('/api/v1/rubric-groups/' . $plain->id, ['name' => 'X'], $h)
            ->assertStatus(409)
            ->assertJson(['error' => 'Rubric group is locked (assigned to an active evaluation period). Duplicate it to make changes.']);
        $this->postJson('/api/v1/rubric-groups/' . $plain->id . '/duplicate', ['name' => 'Copy'], $h)
            ->assertStatus(409)
            ->assertJson(['error' => 'Rubric group is locked. Duplicate it to make changes.']);
    }

    public function test_rubric_crud_and_duplicate(): void
    {
        $h = $this->auth();
        $this->postJson('/api/v1/rubric-groups', [], $h)->assertStatus(400);
        $id = $this->postJson('/api/v1/rubric-groups', ['name' => 'G'], $h)->assertCreated()->json('group.id');

        $catId = $this->postJson('/api/v1/rubric-groups/' . $id . '/categories', ['name' => 'C'], $h)
            ->assertCreated()->json('category.id');
        $this->deleteJson('/api/v1/rubric-groups/' . $id . '/categories', [], $h)
            ->assertStatus(400)
            ->assertJson(['error' => 'categoryId is required']);

        $itemId = $this->postJson('/api/v1/rubric-groups/' . $id . '/items', [
            'categoryId' => $catId, 'text' => 'Q',
        ], $h)->assertCreated()->json('item.id');

        // Seed duplication allowed (its purpose).
        $seed = $this->group('Seed', true);
        $seedCat = $this->category($seed);
        $this->item($seedCat);
        $copyId = $this->postJson('/api/v1/rubric-groups/' . $seed->id . '/duplicate', ['name' => 'Seed Copy'], $h)
            ->assertCreated()->json('group.id');
        $this->assertEquals(1, RubricCategory::where('rubric_group_id', $copyId)->count());
        $this->assertEquals(1, RubricItem::whereIn('category_id',
            RubricCategory::where('rubric_group_id', $copyId)->pluck('id'))->count());

        $this->deleteJson('/api/v1/rubric-groups/' . $id . '/items/' . $itemId, [], $h)->assertOk();
        $this->deleteJson('/api/v1/rubric-groups/' . $id . '/categories', ['categoryId' => $catId], $h)->assertOk();
        $this->deleteJson('/api/v1/rubric-groups/' . $id, [], $h)->assertOk()->assertJson(['success' => true]);
    }

    private function person(string $class, string $email): object
    {
        $m = new $class();
        $m->id = (string) Str::uuid();
        $m->name = 'Person';
        $m->email = $email;
        if ($class === Student::class) {
            $m->student_number = 'SSO-' . substr((string) Str::uuid(), 0, 6);
        }
        $m->is_active = true;
        $m->save();
        return $m;
    }
}
