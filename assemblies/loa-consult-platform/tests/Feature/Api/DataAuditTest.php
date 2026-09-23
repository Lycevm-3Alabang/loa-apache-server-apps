<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Employee;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataAuditTest extends TestCase
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

    private function student(string $email): Student
    {
        $s = new Student();
        $s->id = (string) Str::uuid();
        $s->name = 'Student One';
        $s->email = $email;
        $s->student_number = 'S-' . Str::random(6);
        $s->is_active = true;
        $s->save();
        return $s;
    }

    private function employee(string $email): Employee
    {
        $e = new Employee();
        $e->id = (string) Str::uuid();
        $e->name = 'Faculty One';
        $e->email = $email;
        $e->is_active = true;
        $e->save();
        return $e;
    }

    public function test_delete_students_tokenless_is_401(): void
    {
        $this->postJson('/api/v1/data/delete-students', ['confirm' => true])->assertUnauthorized();
    }

    public function test_delete_students_without_confirm_is_422(): void
    {
        $this->student('s@lyceumalabang.edu.ph');

        $response = $this->postJson('/api/v1/data/delete-students', [], $this->auth('me@lyceumalabang.edu.ph', ['aces-admin'], 'admin', '/api/v1/data/delete-students'));

        $response->assertStatus(422);
        $this->assertSame(1, Student::count());
    }

    public function test_delete_students_with_confirm_deletes(): void
    {
        $this->student('s@lyceumalabang.edu.ph');
        $this->employee('f@lyceumalabang.edu.ph');

        $response = $this->postJson('/api/v1/data/delete-students', ['confirm' => true], $this->auth('me@lyceumalabang.edu.ph', ['aces-admin'], 'admin', '/api/v1/data/delete-students'));

        $response->assertOk()->assertJsonPath('data.deleted', 1);
        $this->assertSame(0, Student::count());
        $this->assertSame(1, Employee::count());
    }

    public function test_reset_db_without_confirm_is_422(): void
    {
        $response = $this->postJson('/api/v1/data/reset-db', [], $this->auth('me@lyceumalabang.edu.ph', ['aces-admin'], 'admin', '/api/v1/data/reset-db'));

        $response->assertStatus(422);
    }

    public function test_reset_db_with_confirm_wipes_domain_tables(): void
    {
        $this->student('s@lyceumalabang.edu.ph');
        $this->employee('f@lyceumalabang.edu.ph');

        $response = $this->postJson('/api/v1/data/reset-db', ['confirm' => true], $this->auth('me@lyceumalabang.edu.ph', ['aces-admin'], 'admin', '/api/v1/data/reset-db'));

        $response->assertOk()->assertJsonPath('data.reset', true);
        $this->assertSame(0, Student::count());
        $this->assertSame(0, Employee::count());
        $this->assertSame(0, Appointment::count());
    }

    public function test_export_consultations_returns_count_and_rows(): void
    {
        $response = $this->postJson('/api/v1/data/export-consultations', [], $this->auth('me@lyceumalabang.edu.ph', ['aces-admin'], 'admin', '/api/v1/data/export-consultations'));

        $response->assertOk()->assertJsonPath('data.count', 0);
    }

    public function test_evaluation_mappings_returns_list(): void
    {
        $response = $this->getJson('/api/v1/data/evaluation-mappings', $this->auth('me@lyceumalabang.edu.ph', ['aces-faculty'], 'read', '/api/v1/data/evaluation-mappings'));

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_audit_logs_absent_by_gate(): void
    {
        $headers = $this->auth('me@lyceumalabang.edu.ph', ['aces-admin'], 'read', '/api/v1/audit-logs');

        $this->getJson('/api/v1/audit-logs', $headers)->assertNotFound();
    }
}
