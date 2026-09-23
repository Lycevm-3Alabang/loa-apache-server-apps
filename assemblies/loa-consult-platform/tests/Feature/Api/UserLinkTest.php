<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserLinkTest extends TestCase
{
    use RefreshDatabase;

    private function token(string $email, array $groups, array $permissions, string $sub = 'user-1'): string
    {
        $secret = config('jwt.secret');
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = [
            'sub' => $sub,
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

    private function auth(string $email, array $groups, string $level, string $path, string $sub = 'user-1'): array
    {
        return ['Authorization' => 'Bearer ' . $this->token($email, $groups, ["$level:$path"], $sub), 'Accept' => 'application/json'];
    }

    private function student(string $email): Student
    {
        $s = new Student();
        $s->id = (string) Str::uuid();
        $s->name = 'Student One';
        $s->email = $email;
        $s->student_number = 'S-001';
        $s->is_active = true;
        $s->save();
        return $s;
    }

    private function employee(string $email, string $name = 'Faculty One'): Employee
    {
        $e = new Employee();
        $e->id = (string) Str::uuid();
        $e->name = $name;
        $e->email = $email;
        $e->is_active = true;
        $e->save();
        return $e;
    }

    public function test_primary_tokenless_is_401(): void
    {
        $this->getJson('/api/v1/users/primary')->assertUnauthorized();
    }

    public function test_primary_returns_linked_student(): void
    {
        $s = $this->student('stud@lyceumalabang.edu.ph');

        $response = $this->getJson('/api/v1/users/primary', $this->auth('stud@lyceumalabang.edu.ph', ['aces-user'], 'read', '/api/v1/users/primary'));

        $response->assertOk()
            ->assertJsonPath('data.linked', true)
            ->assertJsonPath('data.link.type', 'student')
            ->assertJsonPath('data.link.row.id', $s->id);
    }

    public function test_primary_returns_linked_employee(): void
    {
        $e = $this->employee('fac@lyceumalabang.edu.ph');

        $response = $this->getJson('/api/v1/users/primary', $this->auth('fac@lyceumalabang.edu.ph', ['aces-faculty'], 'read', '/api/v1/users/primary'));

        $response->assertOk()
            ->assertJsonPath('data.linked', true)
            ->assertJsonPath('data.link.type', 'employee')
            ->assertJsonPath('data.link.row.id', $e->id);
    }

    public function test_primary_unlinked_returns_marker(): void
    {
        $response = $this->getJson('/api/v1/users/primary', $this->auth('ghost@lyceumalabang.edu.ph', ['aces-user'], 'read', '/api/v1/users/primary'));

        $response->assertOk()
            ->assertJsonPath('data.linked', false)
            ->assertJsonPath('data.link', null);
    }

    public function test_attendees_returns_active_employees_only(): void
    {
        $this->employee('a@lyceumalabang.edu.ph', 'A Faculty');
        $this->student('s@lyceumalabang.edu.ph');
        $inactive = $this->employee('off@lyceumalabang.edu.ph', 'Off Faculty');
        $inactive->is_active = false;
        $inactive->save();

        $response = $this->getJson('/api/v1/users/attendees', $this->auth('me@lyceumalabang.edu.ph', ['aces-faculty'], 'read', '/api/v1/users/attendees'));

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.email', 'a@lyceumalabang.edu.ph');
    }

    public function test_related_data_self_returns_claims_and_link(): void
    {
        $s = $this->student('self@lyceumalabang.edu.ph');

        $response = $this->getJson('/api/v1/users/user-9/related-data', $this->auth('self@lyceumalabang.edu.ph', ['aces-admin'], 'read', '/api/v1/users/{id}/related-data', 'user-9'));

        $response->assertOk()
            ->assertJsonPath('data.auth.sub', 'user-9')
            ->assertJsonPath('data.linked', true)
            ->assertJsonPath('data.link.row.id', $s->id);
    }

    public function test_related_data_unknown_id_is_404(): void
    {
        $response = $this->getJson('/api/v1/users/nope/related-data', $this->auth('me@lyceumalabang.edu.ph', ['aces-admin'], 'read', '/api/v1/users/{id}/related-data'));

        $response->assertNotFound()->assertJsonPath('error', 'User not found');
    }

    public function test_admin_users_writes_not_implemented(): void
    {
        $headers = $this->auth('me@lyceumalabang.edu.ph', ['aces-admin'], 'admin', '/api/v1/users');

        $this->postJson('/api/v1/users', [], $headers)->assertNotFound();
    }
}
