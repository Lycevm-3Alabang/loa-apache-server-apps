<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Employee;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private const PATTERNS = [
        // Single effective level per path (write covers read via ordinals),
        // mirroring Auth-issued claims. NOTE: granting both read+write for one
        // path breaks the level resolver (first path-match wins, method-blind).
        'GET /api/v1/appointments' => 'write',
        'POST /api/v1/appointments' => 'write',
        'GET /api/v1/appointments/faculty-booked' => 'write',
        'POST /api/v1/appointments/batch' => 'write',
        'GET /api/v1/appointments/{id}' => 'write',
        'POST /api/v1/appointments/{id}/{action}' => 'write',
        'POST /api/v1/appointments/{id}/files' => 'write',
        'POST /api/v1/appointments/{id}/retry-sync' => 'write',
        'POST /api/v1/appointments/{id}/student-cancel' => 'write',
        'POST /api/v1/appointments/slots/{slotId}/teams-link' => 'write',
    ];

    private function token(string $email, array $groups): string
    {
        $secret = config('jwt.secret');
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $permissions = [];
        foreach (self::PATTERNS as $pattern => $level) {
            [$m, $p] = explode(' ', $pattern, 2);
            $permissions[] = "$level:$p";
        }
        $payload = [
            'sub' => 'user-1',
            'email' => $email,
            'name' => 'Test User',
            'type' => 'access',
            'tenant' => ['id' => 'tenant-1', 'slug' => 'loa-consultation'],
            'groups' => $groups,
            'permissions' => $permissions,
            'iat' => time(),
            'exp' => time() + 900,
        ];
        $encoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$encoded", $secret, true)), '+/', '-_'), '=');
        return "$header.$encoded.$sig";
    }

    private function auth(string $email, array $groups): array
    {
        return ['Authorization' => 'Bearer ' . $this->token($email, $groups), 'Accept' => 'application/json'];
    }

    private function employee(string $email, string $name = 'Prof One'): Employee
    {
        $e = new Employee();
        $e->id = (string) Str::uuid();
        $e->name = $name;
        $e->email = $email;
        $e->is_active = true;
        $e->save();
        return $e;
    }

    private function student(string $email, string $name = 'Stud One'): Student
    {
        $s = new Student();
        $s->id = (string) Str::uuid();
        $s->name = $name;
        $s->email = $email;
        $s->student_number = 'SSO-' . substr((string) Str::uuid(), 0, 6);
        $s->is_active = true;
        $s->save();
        return $s;
    }

    private function book(Student $student, Employee $faculty, string $status = 'PENDING', string $date = '2026-11-02'): Appointment
    {
        $a = new Appointment();
        $a->student_id = $student->id;
        $a->faculty_id = $faculty->id;
        $a->created_by_email = $student->email;
        $a->meeting_type = 'CONSULTATION';
        $a->date = $date;
        $a->start_time = '09:00';
        $a->end_time = '10:00';
        $a->title = 'Thesis consult';
        $a->status = $status;
        $a->requested_at = now();
        $a->save();
        $a->timeSlots()->create(['date' => $date, 'start_time' => '09:00', 'end_time' => '10:00']);
        return $a;
    }

    public function test_list_tokenless_is_401(): void
    {
        $this->getJson('/api/v1/appointments')->assertUnauthorized();
    }

    public function test_student_books_self(): void
    {
        $student = $this->student('s@itmlyceumalabang.onmicrosoft.com');
        $faculty = $this->employee('f@lyceumalabang.edu.ph');

        $response = $this->postJson('/api/v1/appointments', [
            'facultyId' => $faculty->id,
            'date' => '2026-11-02',
            'startTime' => '09:00',
            'endTime' => '10:00',
            'title' => 'Thesis consult',
        ], $this->auth('s@itmlyceumalabang.onmicrosoft.com', ['STUDENT']));

        $response->assertCreated()
            ->assertJsonPath('conflicts', [])
            ->assertJsonStructure(['appointment' => ['id', 'student', 'faculty', 'attendees', 'timeSlots', 'files']]);

        $this->assertDatabaseHas('appointments', ['student_id' => $student->id, 'faculty_id' => $faculty->id]);
    }

    public function test_creator_cannot_be_student_participant(): void
    {
        // Same email in both tables (dual person) booking their own student row.
        $email = 'dual@lyceumalabang.edu.ph';
        $this->employee($email, 'Dual Person');
        $student = $this->student($email, 'Dual Person');

        $faculty = $this->employee('other@lyceumalabang.edu.ph', 'Other');
        $this->postJson('/api/v1/appointments', [
            'facultyId' => $faculty->id,
            'studentId' => $student->id,
            'date' => '2026-11-02',
            'startTime' => '09:00',
            'endTime' => '10:00',
            'title' => 'Self book',
        ], $this->auth($email, ['FACULTY']))
            ->assertStatus(400)
            ->assertJson(['error' => 'Creator cannot be the student participant']);
    }

    public function test_batch_books_single_session(): void
    {
        $f1 = $this->employee('f1@lyceumalabang.edu.ph', 'F One');
        $f2 = $this->employee('f2@lyceumalabang.edu.ph', 'F Two');
        $this->employee('dean@lyceumalabang.edu.ph', 'Dean');

        $response = $this->postJson('/api/v1/appointments/batch', [
            'facultyIds' => [$f1->id, $f2->id],
            'date' => '2026-11-03',
            'startTime' => '13:00',
            'endTime' => '14:00',
            'title' => 'Panel',
        ], $this->auth('dean@lyceumalabang.edu.ph', ['DEAN']));

        $response->assertCreated()->assertJsonStructure(['appointment', 'sessionGroupId', 'conflicts']);
        $this->assertNotEmpty($response->json('sessionGroupId'));
        $this->assertDatabaseHas('appointment_attendees', [
            'appointment_id' => $response->json('appointment.id'),
            'user_id' => $f2->id,
        ]);
    }

    public function test_list_is_role_split(): void
    {
        $s1 = $this->student('s1@itmlyceumalabang.onmicrosoft.com', 'S One');
        $s2 = $this->student('s2@itmlyceumalabang.onmicrosoft.com', 'S Two');
        $f = $this->employee('f@lyceumalabang.edu.ph');
        $this->book($s1, $f);
        $this->book($s2, $f);

        $response = $this->getJson('/api/v1/appointments', $this->auth('s1@itmlyceumalabang.onmicrosoft.com', ['STUDENT']));
        $response->assertOk()->assertJsonCount(1, 'appointments');
    }

    public function test_faculty_booked_validates_and_returns_lightweight(): void
    {
        $s = $this->student('s@itmlyceumalabang.onmicrosoft.com');
        $f = $this->employee('f@lyceumalabang.edu.ph');
        $this->book($s, $f);

        $headers = $this->auth('f@lyceumalabang.edu.ph', ['FACULTY']);
        $this->getJson('/api/v1/appointments/faculty-booked', $headers)->assertStatus(400);

        $response = $this->getJson(
            '/api/v1/appointments/faculty-booked?facultyId=' . $f->id . '&startDate=2026-11-01&endDate=2026-11-30',
            $headers
        );
        $response->assertOk()->assertJsonCount(1, 'appointments');
        $response->assertJsonStructure(['appointments' => [['date', 'startTime', 'endTime']]]);
    }

    public function test_detail_enriched_and_404(): void
    {
        $s = $this->student('s@itmlyceumalabang.onmicrosoft.com');
        $f = $this->employee('f@lyceumalabang.edu.ph');
        $a = $this->book($s, $f);
        $headers = $this->auth('s@itmlyceumalabang.onmicrosoft.com', ['STUDENT']);

        $this->getJson('/api/v1/appointments/' . $a->id, $headers)
            ->assertOk()
            ->assertJsonStructure(['appointment' => ['student', 'faculty', 'attendees', 'timeSlots', 'files']]);
        $this->getJson('/api/v1/appointments/nope', $headers)->assertNotFound();
    }

    public function test_action_dispatch(): void
    {
        $s = $this->student('s@itmlyceumalabang.onmicrosoft.com');
        $f = $this->employee('f@lyceumalabang.edu.ph');
        $a = $this->book($s, $f);
        $headers = $this->auth('f@lyceumalabang.edu.ph', ['FACULTY']);

        $this->postJson('/api/v1/appointments/' . $a->id . '/accept', [], $headers)
            ->assertOk()
            ->assertJsonPath('appointment.status', 'APPROVED');
        $this->postJson('/api/v1/appointments/' . $a->id . '/bogus', [], $headers)
            ->assertStatus(400)
            ->assertJson(['error' => 'Invalid action']);
    }

    public function test_student_cancel_own_and_other(): void
    {
        $s1 = $this->student('s1@itmlyceumalabang.onmicrosoft.com', 'S One');
        $s2 = $this->student('s2@itmlyceumalabang.onmicrosoft.com', 'S Two');
        $f = $this->employee('f@lyceumalabang.edu.ph');
        $own = $this->book($s1, $f);
        $other = $this->book($s2, $f, 'PENDING', '2026-11-04');

        $this->postJson('/api/v1/appointments/' . $own->id . '/student-cancel', [], $this->auth('s1@itmlyceumalabang.onmicrosoft.com', ['STUDENT']))
            ->assertOk()
            ->assertJsonPath('appointment.status', 'CANCELLED');
        $this->postJson('/api/v1/appointments/' . $other->id . '/student-cancel', [], $this->auth('s1@itmlyceumalabang.onmicrosoft.com', ['STUDENT']))
            ->assertForbidden();
    }

    public function test_files_owner_rule_and_validation(): void
    {
        $s = $this->student('s@itmlyceumalabang.onmicrosoft.com');
        $owner = $this->employee('owner@lyceumalabang.edu.ph', 'Owner');
        $stranger = $this->employee('stranger@lyceumalabang.edu.ph', 'Stranger');
        $a = $this->book($s, $owner);
        $payload = ['files' => [[
            'fileName' => 'shot.png',
            'fileType' => 'image/png',
            'fileData' => base64_encode('fake-bytes'),
            'fileSize' => 10,
        ]]];

        $this->postJson('/api/v1/appointments/' . $a->id . '/files', $payload, $this->auth('stranger@lyceumalabang.edu.ph', ['FACULTY']))
            ->assertForbidden();

        $this->postJson('/api/v1/appointments/' . $a->id . '/files', $payload, $this->auth('owner@lyceumalabang.edu.ph', ['FACULTY']))
            ->assertOk()
            ->assertJsonCount(1, 'files');

        $bad = ['files' => [[
            'fileName' => 'doc.pdf',
            'fileType' => 'application/pdf',
            'fileData' => base64_encode('fake-bytes'),
            'fileSize' => 10,
        ]]];
        $this->postJson('/api/v1/appointments/' . $a->id . '/files', $bad, $this->auth('owner@lyceumalabang.edu.ph', ['FACULTY']))
            ->assertStatus(400);
    }

    public function test_teams_link_and_retry_sync(): void
    {
        $s = $this->student('s@itmlyceumalabang.onmicrosoft.com');
        $f = $this->employee('f@lyceumalabang.edu.ph');
        $a = $this->book($s, $f);
        $slotId = $a->timeSlots()->first()->id;
        $headers = $this->auth('f@lyceumalabang.edu.ph', ['FACULTY']);

        $this->postJson('/api/v1/appointments/slots/' . $slotId . '/teams-link', ['teamsLink' => 'http://bad'], $headers)
            ->assertStatus(400);
        $this->postJson('/api/v1/appointments/slots/999999/teams-link', ['teamsLink' => 'https://teams.microsoft.com/l/meetup'], $headers)
            ->assertNotFound();
        $this->postJson('/api/v1/appointments/slots/' . $slotId . '/teams-link', ['teamsLink' => 'https://teams.microsoft.com/l/meetup'], $headers)
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->postJson('/api/v1/appointments/' . $a->id . '/retry-sync', [], $headers)
            ->assertOk()
            ->assertJsonPath('appointment.teams_sync_retries', 1);
    }

    public function test_overlap_conflict_returns_400_with_conflicts(): void
    {
        $s = $this->student('s@itmlyceumalabang.onmicrosoft.com');
        $f = $this->employee('f@lyceumalabang.edu.ph');
        $this->book($s, $f, 'APPROVED');

        $this->postJson('/api/v1/appointments', [
            'facultyId' => $f->id,
            'date' => '2026-11-02',
            'startTime' => '09:30',
            'endTime' => '10:30',
            'title' => 'Overlap attempt',
        ], $this->auth('s@itmlyceumalabang.onmicrosoft.com', ['STUDENT']))
            ->assertStatus(400)
            ->assertJsonStructure(['error', 'conflicts']);
    }
}
