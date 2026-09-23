<?php

namespace Tests\Feature\Api;

use App\Models\Department;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private function token(string $email, array $groups, array $permissions): string
    {
        $secret = config('jwt.secret');
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = [
            'sub' => 'user-1',
            'email' => $email,
            'name' => 'Test User',
            'type' => 'access',
            'tenant' => ['id' => 'tenant-1', 'slug' => 'loa'],
            'groups' => $groups,
            'permissions' => $permissions,
            'iat' => time(),
            'exp' => time() + 900,
        ];
        $encoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$encoded", $secret, true)), '+/', '-_'), '=');
        return "$header.$encoded.$sig";
    }

    private function auth(string $email, array $groups, string $level, string $path): array
    {
        return ['Authorization' => 'Bearer ' . $this->token($email, $groups, ["$level:$path"]), 'Accept' => 'application/json'];
    }

    private function department(): Department
    {
        $d = new Department();
        $d->name = 'Engineering';
        $d->code = 'ENG';
        $d->save();
        return $d;
    }

    public function test_preview_tokenless_is_401(): void
    {
        $this->postJson('/api/v1/import/preview', [])->assertUnauthorized();
    }

    public function test_preview_validates_without_writing(): void
    {
        $before = Student::count();

        $response = $this->postJson('/api/v1/import/preview', [
            'domain' => 'students',
            'rows' => [['name' => 'New Student', 'email' => 'new@lyceumalabang.edu.ph', 'student_number' => 'S-101']],
        ], $this->auth('me@lyceumalabang.edu.ph', ['aces-admin'], 'admin', '/api/v1/import/preview'));

        $response->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.would_write', 1);
        $this->assertSame($before, Student::count());
    }

    public function test_preview_reports_row_errors(): void
    {
        $response = $this->postJson('/api/v1/import/preview', [
            'domain' => 'students',
            'rows' => [['name' => 'No Email']],
        ], $this->auth('me@lyceumalabang.edu.ph', ['aces-admin'], 'admin', '/api/v1/import/preview'));

        $response->assertOk()->assertJsonPath('data.valid', false);
    }

    public function test_apply_writes_students(): void
    {
        $response = $this->postJson('/api/v1/import/students', [
            'rows' => [['name' => 'Applied', 'email' => 'applied@lyceumalabang.edu.ph', 'student_number' => 'S-100']],
        ], $this->auth('me@lyceumalabang.edu.ph', ['aces-admin'], 'admin', '/api/v1/import/students'));

        $response->assertOk()
            ->assertJsonPath('data.written', 1)
            ->assertJsonPath('data.updated', 0);
        $this->assertDatabaseHas('students', ['email' => 'applied@lyceumalabang.edu.ph']);
    }

    public function test_apply_is_idempotent_on_retry(): void
    {
        $headers = $this->auth('me@lyceumalabang.edu.ph', ['aces-admin'], 'admin', '/api/v1/import/students');
        $payload = ['rows' => [['name' => 'Retry', 'email' => 'retry@lyceumalabang.edu.ph', 'student_number' => 'S-200']]];

        $this->postJson('/api/v1/import/students', $payload, $headers)->assertOk();
        $retry = $this->postJson('/api/v1/import/students', $payload, $headers)->assertOk();

        $retry->assertJsonPath('data.written', 0)->assertJsonPath('data.updated', 1);
        $this->assertSame(1, Student::where('email', 'retry@lyceumalabang.edu.ph')->count());
    }

    public function test_reference_round_trips_into_preview(): void
    {
        $d = $this->department();
        $headers = $this->auth('me@lyceumalabang.edu.ph', ['aces-admin'], 'read', '/api/v1/import/departments-courses/reference');

        $ref = $this->getJson('/api/v1/import/departments-courses/reference', $headers)->assertOk();
        $columns = $ref->json('data.columns');
        $this->assertContains('code', $columns);

        $preview = $this->postJson('/api/v1/import/preview', [
            'domain' => 'departments-courses',
            'rows' => [['name' => 'Civil', 'code' => 'CIV', 'department_id' => $d->id]],
        ], $this->auth('me@lyceumalabang.edu.ph', ['aces-admin'], 'admin', '/api/v1/import/preview'));

        $preview->assertOk()->assertJsonPath('data.valid', true);
    }

    public function test_users_reference_absent_by_design(): void
    {
        $headers = $this->auth('me@lyceumalabang.edu.ph', ['aces-admin'], 'read', '/api/v1/import/users/reference');

        $this->getJson('/api/v1/import/users/reference', $headers)->assertNotFound();
    }
}
