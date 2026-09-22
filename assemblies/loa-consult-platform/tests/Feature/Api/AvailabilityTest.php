<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\FacultyAvailabilityRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AvailabilityTest extends TestCase
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

    private function rule(string $facultyId, int $day = 1, string $start = '2026-10-01'): FacultyAvailabilityRule
    {
        $r = new FacultyAvailabilityRule();
        $r->faculty_id = $facultyId;
        $r->day_of_week = $day;
        $r->start_date = $start;
        $r->is_blocked = false;
        $r->save();
        return $r;
    }

    private function auth(string $email, array $groups, string $level, string $method = 'GET'): array
    {
        $path = '/api/v1/availability-rules';
        return ['Authorization' => 'Bearer ' . $this->token($email, $groups, ["$level:$path"]), 'Accept' => 'application/json'];
    }

    public function test_list_tokenless_is_401(): void
    {
        $this->getJson('/api/v1/availability-rules')->assertUnauthorized();
    }

    public function test_list_no_query_returns_own_rules(): void
    {
        $me = $this->employee('me@lyceumalabang.edu.ph');
        $other = $this->employee('other@lyceumalabang.edu.ph', 'Other');
        $this->rule($me->id);
        $this->rule($other->id);

        $response = $this->getJson('/api/v1/availability-rules', $this->auth('me@lyceumalabang.edu.ph', ['FACULTY'], 'read'));

        $response->assertOk()->assertJsonCount(1, 'rules');
        $response->assertJsonPath('rules.0.faculty_id', $me->id);
    }

    public function test_list_no_query_student_is_401(): void
    {
        $this->getJson(
            '/api/v1/availability-rules',
            $this->auth('s@itmlyceumalabang.onmicrosoft.com', ['STUDENT'], 'read')
        )->assertStatus(401);
    }

    public function test_list_other_faculty_as_admin(): void
    {
        $other = $this->employee('other@lyceumalabang.edu.ph', 'Other');
        $this->rule($other->id);
        $this->employee('boss@lyceumalabang.edu.ph', 'Boss');

        $response = $this->getJson(
            '/api/v1/availability-rules?facultyId=' . $other->id,
            $this->auth('boss@lyceumalabang.edu.ph', ['ADMIN'], 'read')
        );

        $response->assertOk()->assertJsonCount(1, 'rules');
    }

    public function test_list_other_faculty_as_faculty_is_403(): void
    {
        $me = $this->employee('me@lyceumalabang.edu.ph');
        $other = $this->employee('other@lyceumalabang.edu.ph', 'Other');
        $this->rule($me->id);

        $this->getJson(
            '/api/v1/availability-rules?facultyId=' . $other->id,
            $this->auth('me@lyceumalabang.edu.ph', ['FACULTY'], 'read')
        )->assertForbidden();
    }

    public function test_store_creates_own_rule(): void
    {
        $me = $this->employee('me@lyceumalabang.edu.ph');

        $response = $this->postJson('/api/v1/availability-rules', [
            'dayOfWeek' => 2,
            'startDate' => '2026-10-06',
            'startTime' => '09:00',
            'endTime' => '17:00',
        ], $this->auth('me@lyceumalabang.edu.ph', ['FACULTY'], 'write', 'POST'));

        $response->assertOk()->assertJsonPath('rule.faculty_id', $me->id);
        $this->assertDatabaseHas('faculty_availability_rules', [
            'faculty_id' => $me->id,
            'day_of_week' => 2,
            'start_date' => '2026-10-06',
        ]);
    }

    public function test_store_upserts_same_triple(): void
    {
        $me = $this->employee('me@lyceumalabang.edu.ph');
        $headers = $this->auth('me@lyceumalabang.edu.ph', ['FACULTY'], 'write', 'POST');
        $body = ['dayOfWeek' => 2, 'startDate' => '2026-10-06', 'isBlocked' => false];

        $this->postJson('/api/v1/availability-rules', $body, $headers)->assertOk();
        $this->postJson('/api/v1/availability-rules', array_merge($body, ['isBlocked' => true]), $headers)->assertOk();

        $this->assertEquals(1, FacultyAvailabilityRule::where('faculty_id', $me->id)->count());
        $this->assertDatabaseHas('faculty_availability_rules', ['faculty_id' => $me->id, 'is_blocked' => true]);
    }

    public function test_store_admin_may_write_other(): void
    {
        $other = $this->employee('other@lyceumalabang.edu.ph', 'Other');
        $this->employee('boss@lyceumalabang.edu.ph', 'Boss');

        $this->postJson('/api/v1/availability-rules', [
            'dayOfWeek' => 3,
            'startDate' => '2026-10-07',
            'facultyId' => $other->id,
        ], $this->auth('boss@lyceumalabang.edu.ph', ['ADMIN'], 'write', 'POST'))->assertOk();

        $this->assertDatabaseHas('faculty_availability_rules', ['faculty_id' => $other->id]);
    }

    public function test_store_non_admin_faculty_id_forced_to_self(): void
    {
        $me = $this->employee('me@lyceumalabang.edu.ph');
        $other = $this->employee('other@lyceumalabang.edu.ph', 'Other');

        $this->postJson('/api/v1/availability-rules', [
            'dayOfWeek' => 4,
            'startDate' => '2026-10-08',
            'facultyId' => $other->id,
        ], $this->auth('me@lyceumalabang.edu.ph', ['FACULTY'], 'write', 'POST'))->assertOk();

        $this->assertDatabaseHas('faculty_availability_rules', ['faculty_id' => $me->id]);
        $this->assertDatabaseMissing('faculty_availability_rules', ['faculty_id' => $other->id]);
    }

    public function test_store_rejects_bad_input(): void
    {
        $this->employee('me@lyceumalabang.edu.ph');
        $headers = $this->auth('me@lyceumalabang.edu.ph', ['FACULTY'], 'write', 'POST');

        $this->postJson('/api/v1/availability-rules', ['dayOfWeek' => 9, 'startDate' => '2026-10-06'], $headers)
            ->assertStatus(400);
        $this->postJson('/api/v1/availability-rules', ['dayOfWeek' => 2], $headers)
            ->assertStatus(400);
    }
}
