<?php

namespace Tests\Feature\Api;

use App\Models\Department;
use App\Models\DepartmentCourse;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
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

class EvaluationFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PATTERNS = [
        '/api/v1/evaluations',
        '/api/v1/evaluations/pending',
        '/api/v1/evaluations/dispute',
        '/api/v1/evaluations/{id}',
        '/api/v1/evaluations/{id}/ratings',
        '/api/v1/evaluations/{id}/comments',
        '/api/v1/evaluations/{id}/submit',
        '/api/v1/evaluation-comments',
        '/api/v1/evaluations/bootstrap',
    ];

    private function auth(string $email, array $groups): array
    {
        $secret = config('jwt.secret');
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $permissions = array_map(fn ($p) => "write:$p", self::PATTERNS);
        $payload = [
            'sub' => 'u-1', 'email' => $email, 'name' => 'Test User',
            'type' => 'access', 'tenant' => ['id' => 't-1', 'slug' => 'loa'],
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
        $dept = Department::create(['name' => 'Tech', 'code' => 'CITE']);
        $course = DepartmentCourse::create(['department_id' => $dept->id, 'name' => 'IT', 'code' => 'BSIT']);
        $subject = Subject::create(['code' => 'IT101', 'name' => 'Intro']);
        $section = Section::create(['name' => 'A', 'program' => 'BSIT', 'department_course_id' => $course->id]);
        $faculty = $this->person(Employee::class, 'f@lyceumalabang.edu.ph', 'Prof');
        $student = $this->person(Student::class, 's@itmlyceumalabang.onmicrosoft.com', 'Stud');
        $mapping = FacultySubject::create([
            'faculty_id' => $faculty->id, 'subject_id' => $subject->id,
            'section_id' => $section->id, 'semester_id' => $sem->id,
        ]);
        StudentEnrollment::create([
            'student_id' => $student->id, 'section_id' => $section->id,
            'semester_id' => $sem->id, 'faculty_subject_id' => $mapping->id,
        ]);

        return compact('sem', 'period', 'dept', 'course', 'subject', 'section', 'faculty', 'student', 'mapping');
    }

    private function evaluate(array $w, string $status = 'DRAFT'): Evaluation
    {
        return Evaluation::create([
            'evaluation_period_id' => $w['period']->id,
            'semester_id' => $w['sem']->id,
            'evaluator_id' => $w['student']->id,
            'evaluatee_id' => $w['faculty']->id,
            'faculty_subject_id' => $w['mapping']->id,
            'status' => $status,
        ]);
    }

    public function test_index_guards(): void
    {
        $w = $this->world();
        $this->evaluate($w);

        $this->getJson('/api/v1/evaluations', $this->auth('f@lyceumalabang.edu.ph', ['FACULTY']))
            ->assertForbidden();
        $w['period']->update(['is_active' => false]);
        $this->getJson('/api/v1/evaluations', $this->auth('s@itmlyceumalabang.onmicrosoft.com', ['STUDENT']))
            ->assertStatus(400)
            ->assertJson(['error' => 'No active evaluation period']);
    }

    public function test_index_returns_own_enriched(): void
    {
        $w = $this->world();
        $this->evaluate($w);

        $this->getJson('/api/v1/evaluations', $this->auth('s@itmlyceumalabang.onmicrosoft.com', ['STUDENT']))
            ->assertOk()
            ->assertJsonCount(1, 'evaluations')
            ->assertJsonPath('evaluations.0.evaluateeName', 'Prof')
            ->assertJsonPath('evaluations.0.subjectCode', 'IT101');
    }

    public function test_store_id_branch_and_enrollment_gate(): void
    {
        $w = $this->world();
        $mine = $this->evaluate($w);
        $h = $this->auth('s@itmlyceumalabang.onmicrosoft.com', ['STUDENT']);

        $this->postJson('/api/v1/evaluations', ['id' => $mine->id], $h)
            ->assertOk()->assertJsonStructure(['evaluation']);
        $this->postJson('/api/v1/evaluations', ['id' => 999999], $h)->assertForbidden();

        // No enrollment for this mapping → 403 (different subject => no
        // unique clashes on either mapping constraint).
        $subject2 = Subject::create(['code' => 'IT102', 'name' => 'Advanced']);
        $other = FacultySubject::create([
            'faculty_id' => $w['faculty']->id, 'subject_id' => $subject2->id,
            'section_id' => $w['section']->id, 'semester_id' => $w['sem']->id,
        ]);
        $this->postJson('/api/v1/evaluations', [
            'facultySubjectId' => $other->id, 'evaluateeId' => $w['faculty']->id,
        ], $h)->assertForbidden();

        // Unenrolled source bypasses the gate (200, not 201).
        $this->postJson('/api/v1/evaluations', [
            'facultySubjectId' => $other->id, 'evaluateeId' => $w['faculty']->id, 'source' => 'unenrolled',
        ], $h)->assertOk()->assertJsonStructure(['evaluation']);

        // Enrolled mapping → get-or-create returns the same row twice.
        $first = $this->postJson('/api/v1/evaluations', [
            'facultySubjectId' => $w['mapping']->id, 'evaluateeId' => $w['faculty']->id,
        ], $h)->assertOk()->json('evaluation.id');
        $second = $this->postJson('/api/v1/evaluations', [
            'facultySubjectId' => $w['mapping']->id, 'evaluateeId' => $w['faculty']->id,
        ], $h)->assertOk()->json('evaluation.id');
        $this->assertEquals($first, $second);
    }

    public function test_detail_masking_and_include(): void
    {
        $w = $this->world();
        $mine = $this->evaluate($w);
        $otherStudent = $this->person(Student::class, 'o@itmlyceumalabang.onmicrosoft.com', 'Other');
        $h = $this->auth('s@itmlyceumalabang.onmicrosoft.com', ['STUDENT']);

        $this->getJson('/api/v1/evaluations/' . $mine->id . '?include=ratings,comments,rubric', $h)
            ->assertOk()
            ->assertJsonStructure(['evaluation' => ['evaluateeName', 'subjectCode', 'sectionName'], 'ratings', 'rubric']);
        $this->getJson('/api/v1/evaluations/999999', $h)->assertNotFound();

        $mine->update(['is_disabled' => true]);
        $this->getJson('/api/v1/evaluations/' . $mine->id, $h)->assertNotFound();
        $mine->update(['is_disabled' => false]);

        // Other student's evaluation → 404 (never 403).
        $theirs = Evaluation::create([
            'evaluation_period_id' => $w['period']->id, 'semester_id' => $w['sem']->id,
            'evaluator_id' => $otherStudent->id, 'evaluatee_id' => $w['faculty']->id,
            'faculty_subject_id' => $w['mapping']->id, 'status' => 'DRAFT',
        ]);
        $this->getJson('/api/v1/evaluations/' . $theirs->id, $h)->assertNotFound();
    }

    public function test_ratings_round_trip(): void
    {
        $w = $this->world();
        $mine = $this->evaluate($w);
        $h = $this->auth('s@itmlyceumalabang.onmicrosoft.com', ['STUDENT']);

        $group = RubricGroup::create(['name' => 'G']);
        $cat = RubricCategory::create(['rubric_group_id' => $group->id, 'name' => 'C']);
        $item = RubricItem::create(['category_id' => $cat->id, 'text' => 'Q', 'weight' => 1]);

        $this->putJson('/api/v1/evaluations/' . $mine->id . '/ratings', [
            'ratings' => [['itemId' => $item->id, 'rating' => 5]],
        ], $h)->assertOk()->assertJson(['success' => true]);
        $this->getJson('/api/v1/evaluations/' . $mine->id . '/ratings', $h)
            ->assertOk()->assertJsonCount(1, 'ratings');
        $this->getJson('/api/v1/evaluations/999999/ratings', $h)->assertNotFound();
    }

    public function test_comments_round_trip(): void
    {
        $w = $this->world();
        $mine = $this->evaluate($w);
        $h = $this->auth('s@itmlyceumalabang.onmicrosoft.com', ['STUDENT']);

        $this->getJson('/api/v1/evaluations/' . $mine->id . '/comments', $h)
            ->assertOk()->assertJson(['comment' => null]);
        $this->postJson('/api/v1/evaluations/' . $mine->id . '/comments', ['comment' => 'Great class'], $h)
            ->assertCreated()->assertJsonPath('comment.comment', 'Great class');
    }

    public function test_submit(): void
    {
        $w = $this->world();
        $mine = $this->evaluate($w);
        $h = $this->auth('s@itmlyceumalabang.onmicrosoft.com', ['STUDENT']);

        $this->postJson('/api/v1/evaluations/' . $mine->id . '/submit', [], $h)
            ->assertOk()->assertJsonPath('evaluation.status', 'SUBMITTED');
        $this->assertNotNull($mine->fresh()->submitted_at);
        $this->postJson('/api/v1/evaluations/999999/submit', [], $h)->assertNotFound();
    }

    public function test_pending_and_bootstrap(): void
    {
        $w = $this->world();
        $h = $this->auth('s@itmlyceumalabang.onmicrosoft.com', ['STUDENT']);

        $this->getJson('/api/v1/evaluations/pending', $h)
            ->assertOk()->assertJsonCount(1, 'pending')
            ->assertJsonPath('pending.0.evaluateeName', 'Prof');

        $this->getJson('/api/v1/evaluations/bootstrap', $h)
            ->assertOk()
            ->assertJsonStructure(['periods', 'activePeriodId', 'activePeriodName', 'pending', 'evaluations', 'rubric'])
            ->assertJsonPath('activePeriodName', 'P1');

        $this->evaluate($w);
        $this->getJson('/api/v1/evaluations/pending', $h)->assertOk()->assertJson(['pending' => []]);
    }

    public function test_dispute_flow(): void
    {
        $w = $this->world();
        $h = $this->auth('s@itmlyceumalabang.onmicrosoft.com', ['STUDENT']);

        $this->postJson('/api/v1/evaluations/dispute', ['evaluateeId' => $w['faculty']->id], $h)
            ->assertStatus(400);
        $this->postJson('/api/v1/evaluations/dispute', [
            'facultySubjectId' => $w['mapping']->id, 'evaluateeId' => $w['faculty']->id,
            'subjectName' => 'Intro',
        ], $h)->assertOk()->assertJson(['success' => true]);

        // No prior evaluation → created then invalidated.
        $row = Evaluation::where('evaluator_id', $w['student']->id)->first();
        $this->assertEquals('INVALID', $row->status);
        $this->assertTrue((bool) $row->is_disabled);
    }

    public function test_evaluation_comments_filtered(): void
    {
        $w = $this->world();
        $mine = $this->evaluate($w, 'SUBMITTED');
        $h = $this->auth('s@itmlyceumalabang.onmicrosoft.com', ['STUDENT']);
        $this->postJson('/api/v1/evaluations/' . $mine->id . '/comments', ['comment' => 'OK'], $h)->assertCreated();

        $admin = $this->auth('a@lyceumalabang.edu.ph', ['ADMIN']);
        $this->getJson('/api/v1/evaluation-comments?evaluationPeriodId=' . $w['period']->id, $admin)
            ->assertOk()->assertJsonCount(1, 'comments');
        $this->getJson('/api/v1/evaluation-comments?evaluationPeriodId=999999', $admin)
            ->assertOk()->assertJson(['comments' => []]);
    }
}
