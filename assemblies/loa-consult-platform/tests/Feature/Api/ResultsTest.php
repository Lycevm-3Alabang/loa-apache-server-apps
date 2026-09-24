<?php

namespace Tests\Feature\Api;

use App\Models\Department;
use App\Models\DepartmentCourse;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationComment;
use App\Models\EvaluationPeriod;
use App\Models\EvaluationRating;
use App\Models\FacultySubject;
use App\Models\RubricCategory;
use App\Models\RubricGroup;
use App\Models\RubricItem;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResultsTest extends TestCase
{
    use RefreshDatabase;

    private const PATTERNS = [
        '/api/v1/evaluation-results',
        '/api/v1/evaluation-results/department',
        '/api/v1/evaluation-results/details',
        '/api/v1/evaluation-results/subjects',
        '/api/v1/evaluation-results/subjects/{facultySubjectId}',
        '/api/v1/evaluation-results/departments/{departmentId}',
        '/api/v1/evaluation-results/faculty/{facultyId}',
        '/api/v1/evaluation-results/groups/{facultySubjectId}',
        '/api/v1/evaluation-results/invalidate',
        '/api/v1/evaluation-results/visibility',
        '/api/v1/evaluations/disabled',
        '/api/v1/evaluations/disabled/restore',
        '/api/v1/evaluations/{evaluationId}/details',
        '/api/v1/evaluations/{evaluationId}/invalidate',
    ];

    private function auth(string $email, array $groups): array
    {
        $secret = config('jwt.secret');
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $permissions = array_map(fn ($p) => "admin:$p", self::PATTERNS);
        $payload = [
            'sub' => $email === 'dean@lyceumalabang.edu.ph' ? 'dean-sub-1' : 'u-1',
            'email' => $email, 'name' => 'Test User',
            'type' => 'access', 'tenant' => ['id' => 't-1', 'slug' => 'loa-consultation'],
            'groups' => $groups, 'permissions' => $permissions,
            'iat' => time(), 'exp' => time() + 900,
        ];
        $encoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$encoded", $secret, true)), '+/', '-_'), '=');
        return ['Authorization' => "Bearer $header.$encoded.$sig", 'Accept' => 'application/json'];
    }

    private function person(string $class, string $email, string $name = 'Person'): object
    {
        $m = new $class();
        $m->id = (string) Str::uuid();
        $m->name = $name;
        $m->email = $email;
        if ($class === Student::class) {
            $m->student_number = 'SSO-' . substr((string) Str::uuid(), 0, 6);
        }
        $m->is_active = true;
        $m->save();
        return $m;
    }

    private function world(): array
    {
        $sem = Semester::create(['title' => 'S1']);
        $period = EvaluationPeriod::create(['semester_id' => $sem->id, 'name' => 'P1', 'is_active' => true]);
        $dept = Department::create(['name' => 'Tech', 'code' => 'CITE', 'dean_id' => 'dean-sub-1']);
        $course = DepartmentCourse::create(['department_id' => $dept->id, 'name' => 'IT', 'code' => 'BSIT']);
        $subject = Subject::create(['code' => 'IT101', 'name' => 'Intro']);
        $section = Section::create(['name' => 'A', 'program' => 'BSIT', 'department_course_id' => $course->id]);

        $group = RubricGroup::create(['name' => 'Seed Rubric', 'seed' => true]);
        $cat = RubricCategory::create(['rubric_group_id' => $group->id, 'name' => 'Professional Manner']);
        $item = RubricItem::create(['category_id' => $cat->id, 'text' => 'Q1', 'weight' => 1]);

        $faculty = $this->person(Employee::class, 'f@lyceumalabang.edu.ph', 'Prof');
        $faculty->department_id = $dept->id;
        $faculty->save();
        $student = $this->person(Student::class, 's@itmlyceumalabang.onmicrosoft.com', 'Stud');
        $mapping = FacultySubject::create([
            'faculty_id' => $faculty->id, 'subject_id' => $subject->id,
            'section_id' => $section->id, 'semester_id' => $sem->id,
        ]);
        StudentEnrollment::create([
            'student_id' => $student->id, 'section_id' => $section->id,
            'semester_id' => $sem->id, 'faculty_subject_id' => $mapping->id,
        ]);
        $eval = Evaluation::create([
            'evaluation_period_id' => $period->id, 'semester_id' => $sem->id,
            'evaluator_id' => $student->id, 'evaluatee_id' => $faculty->id,
            'faculty_subject_id' => $mapping->id, 'status' => 'SUBMITTED',
        ]);
        EvaluationRating::create(['evaluation_id' => $eval->id, 'item_id' => $item->id, 'rating' => 5]);
        EvaluationComment::create(['evaluation_id' => $eval->id, 'comment' => 'Great', 'sentiment_score' => 0.9, 'sentiment_label' => 'positive']);

        return compact('sem', 'period', 'dept', 'course', 'subject', 'section', 'group', 'cat', 'item', 'faculty', 'student', 'mapping', 'eval');
    }

    public function test_admin_base_empty_then_computed(): void
    {
        $w = $this->world();
        $h = $this->auth('a@lyceumalabang.edu.ph', ['aces-admin']);

        // Lazy compute fires through the read path (no rows stored yet).
        $response = $this->getJson('/api/v1/evaluation-results?evaluationPeriodId=' . $w['period']->id, $h);
        $response->assertOk()->assertJsonCount(1, 'departments');
        $dept = $response->json('departments.0');
        $this->assertEquals('CITE', $dept['departmentCode']);
        $this->assertEquals(5.0, $dept['avgRating']);
        $this->assertEquals('Outstanding', $dept['remarks']);
        $this->assertEquals(0.9, $dept['sentimentScore']);
        $this->assertEquals(1, $dept['facultyCount']);

        $this->getJson('/api/v1/evaluation-results', $h)->assertStatus(400);
    }

    public function test_admin_drill_downs(): void
    {
        $w = $this->world();
        $h = $this->auth('a@lyceumalabang.edu.ph', ['aces-admin']);
        $q = '?evaluationPeriodId=' . $w['period']->id;

        $this->getJson('/api/v1/evaluation-results/departments/999999' . $q, $h)->assertNotFound();
        $this->getJson('/api/v1/evaluation-results/departments/' . $w['dept']->id . $q, $h)
            ->assertOk()->assertJsonStructure(['department', 'subjects']);
        $this->getJson('/api/v1/evaluation-results/faculty/999999' . $q, $h)->assertNotFound();
        $this->getJson('/api/v1/evaluation-results/faculty/' . $w['faculty']->id . $q, $h)
            ->assertOk()->assertJsonStructure(['faculty', 'subjects']);
        $this->getJson('/api/v1/evaluation-results/groups/' . $w['mapping']->id . $q, $h)
            ->assertOk()->assertJsonStructure(['facultySubject', 'subjects', 'comments']);
    }

    public function test_scoped_index_by_group(): void
    {
        $w = $this->world();
        $q = '?evaluationPeriodId=' . $w['period']->id;

        $this->getJson('/api/v1/evaluation-results' . $q, $this->auth('a@lyceumalabang.edu.ph', ['aces-admin']))
            ->assertOk()->assertJsonCount(1, 'departments');
        $this->getJson('/api/v1/evaluation-results' . $q, $this->auth('dean@lyceumalabang.edu.ph', ['aces-dean']))
            ->assertOk()->assertJsonCount(1, 'departments');
        $this->getJson('/api/v1/evaluation-results/departments/999999' . $q, $this->auth('dean@lyceumalabang.edu.ph', ['aces-dean']))
            ->assertForbidden();
        $this->getJson('/api/v1/evaluation-results/faculty/' . $w['faculty']->id . $q, $this->auth('f@lyceumalabang.edu.ph', ['aces-faculty']))
            ->assertOk()->assertJsonStructure(['faculty', 'subjects']);
        $this->getJson('/api/v1/evaluation-results' . $q, $this->auth('s@itmlyceumalabang.onmicrosoft.com', ['aces-user']))
            ->assertForbidden();
    }

    public function test_faculty_visibility_gate_and_reads(): void
    {
        $w = $this->world();
        $h = $this->auth('f@lyceumalabang.edu.ph', ['aces-faculty']);
        $q = '?evaluationPeriodId=' . $w['period']->id;

        $this->getJson('/api/v1/evaluation-results' . $q, $h)->assertForbidden();
        $this->getJson('/api/v1/evaluation-results/subjects' . $q, $h)->assertForbidden();

        $admin = $this->auth('a@lyceumalabang.edu.ph', ['aces-admin']);
        $this->postJson('/api/v1/evaluation-results/visibility', [
            'evaluationPeriodId' => $w['period']->id,
            'facultyIds' => [$w['faculty']->id],
            'visible' => true,
        ], $admin)->assertOk()->assertJson(['success' => true]);

        $this->getJson('/api/v1/evaluation-results' . $q, $h)
            ->assertOk()->assertJsonStructure(['results', 'facultyNames'])
            ->assertJsonPath('results.0.totalRespondents', 1);
        $this->getJson('/api/v1/evaluation-results/subjects' . $q, $h)
            ->assertOk()->assertJsonCount(1, 'subjects');
        $this->getJson('/api/v1/evaluation-results/subjects/' . $w['mapping']->id . $q, $h)
            ->assertOk()->assertJsonStructure(['subject']);
        $this->getJson('/api/v1/evaluation-results/subjects/999999' . $q, $h)->assertNotFound();
    }

    public function test_dean_scoping_and_details(): void
    {
        $w = $this->world();
        $dean = $this->auth('dean@lyceumalabang.edu.ph', ['aces-dean']);
        $q = '?evaluationPeriodId=' . $w['period']->id;

        $this->getJson('/api/v1/evaluation-results' . $q, $dean)
            ->assertOk()->assertJsonCount(1, 'departments');
        $this->getJson('/api/v1/evaluation-results/department', $dean)
            ->assertOk()->assertJson(['departmentId' => $w['dept']->id]);
        $this->getJson('/api/v1/evaluation-results/departments/999999', $dean)->assertForbidden();

        $details = $this->getJson(
            '/api/v1/evaluation-results/details?evaluationPeriodId=' . $w['period']->id . '&facultyId=' . $w['faculty']->id,
            $dean
        )->assertOk()->assertJsonPath('students.0.id', 'S1');
        $this->assertArrayHasKey('ratings', $details->json('students.0'));
    }

    public function test_mutations_and_disabled_set(): void
    {
        $w = $this->world();
        $h = $this->auth('a@lyceumalabang.edu.ph', ['aces-admin']);

        $this->postJson('/api/v1/evaluation-results/invalidate', [
            'evaluationPeriodId' => $w['period']->id,
        ], $h)->assertStatus(400);
        $this->postJson('/api/v1/evaluation-results/invalidate', [
            'evaluationPeriodId' => $w['period']->id, 'facultyId' => $w['faculty']->id,
        ], $h)->assertOk()->assertJson(['success' => true]);
        $this->assertTrue($w['eval']->fresh()->is_disabled);

        $this->getJson('/api/v1/evaluations/disabled', $h)
            ->assertOk()->assertJsonCount(1, 'evaluations');

        $this->postJson('/api/v1/evaluations/disabled/restore', ['ids' => []], $h)->assertStatus(400);
        $this->postJson('/api/v1/evaluations/disabled/restore', ['ids' => [$w['eval']->id]], $h)
            ->assertOk();
        $this->assertFalse($w['eval']->fresh()->is_disabled);

        $this->deleteJson('/api/v1/evaluations/disabled', [], $h)->assertStatus(400);
        $this->postJson('/api/v1/evaluations/' . $w['eval']->id . '/invalidate', [
            'evaluationPeriodId' => $w['period']->id, 'reason' => 'test',
        ], $h)->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('INVALID', $w['eval']->fresh()->status);

        $this->getJson('/api/v1/evaluations/' . $w['eval']->id . '/details', $h)
            ->assertOk()
            ->assertJsonStructure(['evaluationId', 'categories', 'comment', 'sentimentScore', 'isDisabled']);
        $this->getJson('/api/v1/evaluations/999999/details', $h)->assertNotFound();

        $this->deleteJson('/api/v1/evaluations/disabled', ['all' => true], $h)
            ->assertOk()->assertJson(['success' => true]);
        $this->assertEquals(0, Evaluation::where('is_disabled', true)->count());
    }
}
