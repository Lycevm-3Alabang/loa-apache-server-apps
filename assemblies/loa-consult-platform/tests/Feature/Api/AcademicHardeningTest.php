<?php

namespace Tests\Feature\Api;

use App\Models\Department;
use App\Models\DepartmentCourse;
use App\Models\Employee;
use App\Models\FacultySubject;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademicHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const PATTERNS = [
        '/api/v1/departments',
        '/api/v1/departments/{id}',
        '/api/v1/department-courses',
        '/api/v1/department-courses/{id}',
        '/api/v1/subjects',
        '/api/v1/subjects/{id}',
        '/api/v1/sections',
        '/api/v1/sections/{id}',
        '/api/v1/sections/fix-names',
        '/api/v1/faculty-subjects',
        '/api/v1/faculty-subjects/reassign',
        '/api/v1/student-enrollments',
        '/api/v1/student-enrollments/{id}',
        '/api/v1/semesters',
        '/api/v1/semesters/{id}',
        '/api/v1/semesters/{id}/impacts',
    ];

    private function auth(): array
    {
        $secret = config('jwt.secret');
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $permissions = array_map(fn ($p) => "admin:$p", self::PATTERNS);
        $payload = [
            'sub' => 'admin-1',
            'email' => 'admin@lyceumalabang.edu.ph',
            'name' => 'Admin',
            'type' => 'access',
            'tenant' => ['id' => 'tenant-1', 'slug' => 'loa'],
            'groups' => ['ADMIN'],
            'permissions' => $permissions,
            'iat' => time(),
            'exp' => time() + 900,
        ];
        $encoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$encoded", $secret, true)), '+/', '-_'), '=');
        return ['Authorization' => "Bearer $header.$encoded.$sig", 'Accept' => 'application/json'];
    }

    private function department(string $code = 'CITE'): Department
    {
        return Department::create(['name' => 'Tech', 'code' => $code]);
    }

    private function course(Department $dept, string $code = 'BSIT'): DepartmentCourse
    {
        return DepartmentCourse::create(['department_id' => $dept->id, 'name' => 'IT', 'code' => $code]);
    }

    public function test_departments_crud_guards(): void
    {
        $h = $this->auth();
        $this->postJson('/api/v1/departments', ['name' => 'Tech', 'code' => 'cite'], $h)
            ->assertCreated()
            ->assertJsonPath('code', 'CITE');
        $this->postJson('/api/v1/departments', ['name' => 'Dup', 'code' => 'CITE'], $h)
            ->assertStatus(409)
            ->assertJson(['error' => 'Department code already exists']);
        $this->postJson('/api/v1/departments', ['name' => 'NoCode'], $h)->assertStatus(400);

        $id = Department::where('code', 'CITE')->first()->id;
        $this->patchJson('/api/v1/departments/999999', ['name' => 'X'], $h)
            ->assertNotFound()
            ->assertJson(['error' => 'Department not found']);
        $this->patchJson('/api/v1/departments/' . $id, [], $h)
            ->assertStatus(400)
            ->assertJson(['error' => 'No changes provided']);
    }

    public function test_courses_quirk_and_guards(): void
    {
        $h = $this->auth();
        $dept = $this->department();

        // 200, not 201 — preserved quirk, with embedded department.
        $this->postJson('/api/v1/department-courses', [
            'departmentId' => $dept->id, 'name' => 'IT', 'code' => 'BSIT',
        ], $h)
            ->assertOk()
            ->assertJsonStructure(['department' => ['name', 'code']]);

        // Bad parent → 500 via FK (preserved quirk, never 422/404).
        $this->postJson('/api/v1/department-courses', [
            'departmentId' => 999999, 'name' => 'X', 'code' => 'XXX',
        ], $h)->assertStatus(500);

        $this->postJson('/api/v1/department-courses', [
            'departmentId' => $dept->id, 'name' => 'Dup', 'code' => 'BSIT',
        ], $h)->assertStatus(409);

        $this->deleteJson('/api/v1/department-courses/999999', [], $h)
            ->assertNotFound()
            ->assertJson(['error' => 'Course not found']);
    }

    public function test_subjects_guards(): void
    {
        $h = $this->auth();
        $this->postJson('/api/v1/subjects', ['code' => 'it101', 'name' => 'Intro'], $h)
            ->assertCreated()
            ->assertJsonPath('code', 'IT101');
        $this->postJson('/api/v1/subjects', ['code' => 'IT101', 'name' => 'Dup'], $h)->assertStatus(409);

        $id = Subject::where('code', 'IT101')->first()->id;
        $this->patchJson('/api/v1/subjects/999999', ['name' => 'X'], $h)->assertNotFound();
        $this->patchJson('/api/v1/subjects/' . $id, [], $h)
            ->assertStatus(400)
            ->assertJson(['error' => 'No changes provided']);
    }

    public function test_sections_guards(): void
    {
        $h = $this->auth();
        $course = $this->course($this->department());

        $this->postJson('/api/v1/sections', ['name' => '  Block A ', 'departmentCourseId' => $course->id], $h)
            ->assertCreated()
            ->assertJson(['name' => 'BLOCK A', 'program' => 'BSIT']);
        $this->postJson('/api/v1/sections', ['name' => 'Nope', 'departmentCourseId' => 999999], $h)
            ->assertStatus(400)
            ->assertJson(['error' => 'Invalid department course']);
        $this->postJson('/api/v1/sections', ['name' => 'block a', 'departmentCourseId' => $course->id], $h)
            ->assertStatus(409);

        $id = Section::first()->id;
        $this->patchJson('/api/v1/sections/999999', ['name' => 'X'], $h)->assertNotFound();
        $this->patchJson('/api/v1/sections/' . $id, [], $h)
            ->assertStatus(400)
            ->assertJson(['error' => 'No changes']);
    }

    public function test_fix_names_shape(): void
    {
        $h = $this->auth();
        $course = $this->course($this->department());
        Section::create(['name' => 'BSIT-Block B', 'program' => 'BSIT', 'department_course_id' => $course->id]);

        $this->postJson('/api/v1/sections/fix-names', [], $h)
            ->assertOk()
            ->assertJson(['fixed' => 1])
            ->assertJsonStructure(['fixes' => [['id', 'oldName', 'newName', 'program']]]);
    }

    public function test_mappings_guards(): void
    {
        $h = $this->auth();
        $course = $this->course($this->department());
        $subject = Subject::create(['code' => 'IT101', 'name' => 'Intro']);
        $section = Section::create(['name' => 'A', 'program' => 'BSIT', 'department_course_id' => $course->id]);
        $e1 = $this->employee('f1@lyceumalabang.edu.ph', 'F One');
        $e2 = $this->employee('f2@lyceumalabang.edu.ph', 'F Two');

        $response = $this->postJson('/api/v1/faculty-subjects', [
            'faculty_id' => $e1->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
        ], $h);
        $response->assertCreated()->assertJsonStructure(['data']);
        $this->postJson('/api/v1/faculty-subjects', [
            'faculty_id' => $e2->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
        ], $h)->assertStatus(409);

        $mapId = $response->json('data.id');
        $this->postJson('/api/v1/faculty-subjects/reassign', [
            'oldFacultySubjectId' => $mapId, 'newFacultyId' => $e1->id,
        ], $h)->assertStatus(400);
        $this->postJson('/api/v1/faculty-subjects/reassign', [
            'oldFacultySubjectId' => 999999, 'newFacultyId' => $e2->id,
        ], $h)->assertNotFound();
        $this->postJson('/api/v1/faculty-subjects/reassign', [
            'oldFacultySubjectId' => $mapId, 'newFacultyId' => $e2->id,
        ], $h)->assertOk()->assertJson(['success' => true]);
    }

    public function test_enrollments_guards(): void
    {
        $h = $this->auth();
        $course = $this->course($this->department());
        $section = Section::create(['name' => 'A', 'program' => 'BSIT', 'department_course_id' => $course->id]);
        $student = $this->student('s@itmlyceumalabang.onmicrosoft.com');

        $response = $this->postJson('/api/v1/student-enrollments', [
            'student_id' => $student->id, 'section_id' => $section->id,
        ], $h);
        $response->assertCreated()->assertJsonStructure(['data']);

        $this->deleteJson('/api/v1/student-enrollments/999999', [], $h)->assertNotFound();
        $this->deleteJson('/api/v1/student-enrollments/' . $response->json('data.id'), [], $h)
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_semesters_hardened(): void
    {
        $h = $this->auth();
        $this->postJson('/api/v1/semesters', [], $h)->assertStatus(400);
        $id = $this->postJson('/api/v1/semesters', ['title' => 'First'], $h)->assertCreated()->json('data.id');

        $this->patchJson('/api/v1/semesters/' . $id, [], $h)
            ->assertStatus(400)
            ->assertJson(['error' => 'No fields to update']);
        $this->getJson('/api/v1/semesters/999999', $h)->assertNotFound();

        $id2 = $this->postJson('/api/v1/semesters', ['title' => 'Second'], $h)->json('data.id');
        $this->postJson('/api/v1/semesters/' . $id, [], $h)->assertOk();
        $this->assertEquals(1, Semester::where('is_active', true)->count());

        $this->getJson('/api/v1/semesters/' . $id . '/impacts', $h)
            ->assertOk()
            ->assertJsonStructure(['facultySubjects', 'enrollments', 'evaluations', 'results', 'sections']);

        $this->deleteJson('/api/v1/semesters/' . $id2, [], $h)->assertOk()->assertJson(['success' => true]);
    }

    public function test_count_active_public(): void
    {
        $this->getJson('/api/v1/semesters/count-active')
            ->assertOk()
            ->assertJsonStructure(['count']);
    }

    private function employee(string $email, string $name): Employee
    {
        $e = new Employee();
        $e->id = (string) Str::uuid();
        $e->name = $name;
        $e->email = $email;
        $e->is_active = true;
        $e->save();
        return $e;
    }

    private function student(string $email): Student
    {
        $s = new Student();
        $s->id = (string) Str::uuid();
        $s->name = 'Stud';
        $s->email = $email;
        $s->student_number = 'SSO-' . substr((string) Str::uuid(), 0, 6);
        $s->is_active = true;
        $s->save();
        return $s;
    }
}
