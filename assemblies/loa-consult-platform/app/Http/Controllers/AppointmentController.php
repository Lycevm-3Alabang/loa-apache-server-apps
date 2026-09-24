<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentAttendee;
use App\Models\AppointmentFile;
use App\Models\AppointmentTimeSlot;
use App\Models\Employee;
use App\Models\Student;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
    private const IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    private const MAX_FILE_BYTES = 5 * 1024 * 1024;

    // ── Lists & detail ───────────────────────────────────────────────

    /** `GET /api/v1/appointments` — read. Faculty/dean → faculty view, else student view. */
    public function index(Request $request): JsonResponse
    {
        $claims = $this->claims($request);
        $groups = $this->groups($claims);

        if (array_intersect(['FACULTY', 'DEAN'], $groups)) {
            $self = $this->selfEmployee($claims);
            $query = Appointment::with(['student', 'faculty'])->where('faculty_id', $self?->id);
        } else {
            $self = $this->selfStudent($claims);
            $query = Appointment::with(['student', 'faculty'])->where('student_id', $self?->id);
        }

        $appointments = $query->get();
        $q = strtolower(trim((string) $request->query('q', '')));

        if ($q !== '') {
            $appointments = $appointments->filter(fn ($a) =>
                str_contains(strtolower($a->title ?? ''), $q)
                || str_contains(strtolower($a->student?->name ?? ''), $q)
                || str_contains(strtolower($a->faculty?->name ?? ''), $q)
            )->values();
        }

        return response()->json(['appointments' => $appointments]);
    }

    /** `GET /api/v1/appointments/faculty-booked` — read. Lightweight calendar slots. */
    public function facultyBooked(Request $request): JsonResponse
    {
        $facultyId = $request->query('facultyId');
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        if (!$facultyId || !$startDate || !$endDate) {
            return response()->json(['error' => 'facultyId, startDate and endDate are required'], 400);
        }

        $slots = Appointment::where('faculty_id', $facultyId)
            ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->get(['date', 'start_time', 'end_time'])
            ->map(fn ($a) => [
                'date' => $a->date,
                'startTime' => $a->start_time,
                'endTime' => $a->end_time,
            ]);

        return response()->json(['appointments' => $slots]);
    }

    /** `GET /api/v1/appointments/{id}` — read. Enriched detail. */
    public function show(string $id): JsonResponse
    {
        try {
            $appointment = Appointment::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Appointment not found'], 404);
        }

        return response()->json(['appointment' => $this->enriched($appointment)]);
    }

    // ── Booking ──────────────────────────────────────────────────────

    /** `POST /api/v1/appointments` — write. 201 `{appointment, conflicts}`. */
    public function store(Request $request): JsonResponse
    {
        $claims = $this->claims($request);
        $groups = $this->groups($claims);

        if (count(array_intersect(['STUDENT', 'FACULTY', 'DEAN'], $groups)) === 0) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $faculty = Employee::find($request->input('facultyId'));
        if ($faculty === null) {
            return response()->json(['error' => 'Faculty not found'], 404);
        }

        try {
            $studentId = $this->resolveStudentId($groups, $claims, $request->input('studentId'));
            $slots = $this->resolveTimeSlots($request);
            $this->validateTimeSlots($slots);
            $meetingType = $this->resolveMeetingType($groups, $request->input('meetingType'));

            $appointment = $this->createBooking(
                $claims, $groups, $studentId, $faculty->id, $meetingType,
                $request->input('sessionGroupId'), $request, $slots,
                $request->input('attendeeIds', []), $request->input('attendeeOptions', []),
            );
        } catch (BookingException $e) {
            return response()->json(
                array_filter(['error' => $e->getMessage(), 'conflicts' => $e->conflicts]),
                400
            );
        }

        return response()->json([
            'appointment' => $this->enriched($appointment),
            'conflicts' => [],
        ], 201);
    }

    /** `POST /api/v1/appointments/batch` — write. Single booking across facultyIds. */
    public function batch(Request $request): JsonResponse
    {
        $claims = $this->claims($request);
        $groups = $this->groups($claims);

        if (count(array_intersect(['STUDENT', 'FACULTY', 'DEAN'], $groups)) === 0) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $facultyIds = $request->input('facultyIds', []);
        if (!is_array($facultyIds) || count($facultyIds) === 0) {
            return response()->json(['error' => 'facultyIds must be a non-empty array'], 400);
        }

        $primary = Employee::find($facultyIds[0]);
        if ($primary === null) {
            return response()->json(['error' => 'Faculty not found'], 404);
        }
        foreach (array_slice($facultyIds, 1) as $fid) {
            if (Employee::find($fid) === null) {
                return response()->json(['error' => 'Faculty not found'], 404);
            }
        }

        try {
            $studentId = $this->resolveStudentId($groups, $claims, $request->input('studentId'));
            $slots = $this->resolveTimeSlots($request);
            $this->validateTimeSlots($slots);
            $meetingType = $this->resolveMeetingType($groups, $request->input('meetingType'));

            $extraAttendees = array_map(
                fn ($fid) => ['userId' => $fid, 'isMandatory' => true],
                array_slice($facultyIds, 1)
            );

            $appointment = $this->createBooking(
                $claims, $groups, $studentId, $primary->id, $meetingType,
                (string) Str::uuid(), $request, $slots,
                $request->input('attendeeIds', []),
                array_merge($request->input('attendeeOptions', []), $extraAttendees),
            );
        } catch (BookingException $e) {
            return response()->json(
                array_filter(['error' => $e->getMessage(), 'conflicts' => $e->conflicts]),
                400
            );
        }

        return response()->json([
            'appointment' => $this->enriched($appointment),
            'sessionGroupId' => $appointment->session_group_id,
            'conflicts' => [],
        ], 201);
    }

    // ── Actions ──────────────────────────────────────────────────────

    /** `POST /api/v1/appointments/{id}/{action}` — write. Action dispatch. */
    public function action(Request $request, string $id, string $action): JsonResponse
    {
        try {
            $appointment = Appointment::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Appointment not found'], 404);
        }

        $claims = $this->claims($request);

        try {
            switch ($action) {
                case 'accept':
                case 'approve':
                    $appointment->status = 'APPROVED';
                    $appointment->save();
                    return response()->json(['appointment' => $this->enriched($appointment->fresh())]);
                case 'decline':
                case 'reject':
                    $appointment->status = 'REJECTED';
                    $appointment->save();
                    return response()->json(['appointment' => $this->enriched($appointment->fresh())]);
                case 'complete':
                    $appointment->status = 'COMPLETED';
                    if ($request->input('actionTaken') !== null) {
                        $appointment->action_taken = $request->input('actionTaken');
                    }
                    $appointment->save();
                    return response()->json(['appointment' => $this->enriched($appointment->fresh())]);
                case 'cancel':
                    $appointment->status = 'CANCELLED';
                    $appointment->save();
                    return response()->json(['appointment' => $this->enriched($appointment->fresh())]);
                case 'teams-link':
                    $appointment->teams_link = $request->input('teamsLink');
                    $appointment->save();
                    return response()->json(['appointment' => $this->enriched($appointment->fresh())]);
                case 'attendee-accept':
                case 'attendee-decline':
                    $employee = $this->selfEmployee($claims);
                    $attendee = $employee === null ? null : AppointmentAttendee::where('appointment_id', $appointment->id)
                        ->where('user_id', $employee->id)
                        ->first();
                    if ($attendee === null) {
                        return response()->json(['error' => 'Attendee not found'], 400);
                    }
                    $attendee->status = $action === 'attendee-accept' ? 'ACCEPTED' : 'DECLINED';
                    $attendee->save();
                    return response()->json(['attendee' => $attendee]);
                default:
                    return response()->json(['error' => 'Invalid action'], 400);
            }
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Action failed'], 400);
        }
    }

    /** `POST /api/v1/appointments/{id}/student-cancel` — write. Own bookings only. */
    public function studentCancel(Request $request, string $id): JsonResponse
    {
        try {
            $appointment = Appointment::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Appointment not found'], 404);
        }

        $student = $this->selfStudent($this->claims($request));
        if ($student === null || $appointment->student_id !== $student->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $appointment->status = 'CANCELLED';
        $appointment->save();

        return response()->json(['appointment' => $this->enriched($appointment->fresh())]);
    }

    /**
     * `POST /api/v1/appointments/{id}/retry-sync` — write.
     * STUB: records the attempt (retries + timestamp). Real Graph sync lands
     * with the mail/queue service extraction (unscheduled).
     */
    public function retrySync(string $id): JsonResponse
    {
        try {
            $appointment = Appointment::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Appointment not found'], 404);
        }

        $appointment->teams_sync_retries = ($appointment->teams_sync_retries ?? 0) + 1;
        $appointment->teams_sync_last_attempt = now();
        $appointment->save();

        return response()->json(['appointment' => $this->enriched($appointment->fresh())]);
    }

    /** `POST /api/v1/appointments/slots/{slotId}/teams-link` — write, FACULTY/DEAN. */
    public function slotTeamsLink(Request $request, string $slotId): JsonResponse
    {
        $groups = $this->groups($this->claims($request));
        if (count(array_intersect(['FACULTY', 'DEAN'], $groups)) === 0) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $teamsLink = trim((string) $request->input('teamsLink', ''));
        if ($teamsLink === '' || !str_starts_with($teamsLink, 'https://teams.microsoft.com/')) {
            return response()->json(['error' => 'A valid Teams link is required'], 400);
        }

        try {
            $slot = AppointmentTimeSlot::findOrFail($slotId);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Time slot not found'], 404);
        }

        $slot->teams_link = $teamsLink;
        $slot->save();

        return response()->json(['success' => true]);
    }

    /** `POST /api/v1/appointments/{id}/files` — write, FACULTY/DEAN + owner. */
    public function files(Request $request, string $id): JsonResponse
    {
        $claims = $this->claims($request);
        $groups = $this->groups($claims);
        if (count(array_intersect(['FACULTY', 'DEAN'], $groups)) === 0) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        try {
            $appointment = Appointment::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Appointment not found'], 404);
        }

        $self = $this->selfEmployee($claims);
        if ($self === null || $appointment->faculty_id !== $self->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $files = $request->input('files', []);
        if (!is_array($files) || count($files) === 0) {
            return response()->json(['error' => 'files are required'], 400);
        }

        $created = [];
        foreach ($files as $file) {
            $name = $file['fileName'] ?? null;
            $type = $file['fileType'] ?? null;
            $data = $file['fileData'] ?? null;
            $size = $file['fileSize'] ?? null;

            if (!$name || !$type || !$data || $size === null) {
                return response()->json(['error' => 'fileName, fileType, fileData and fileSize are required'], 400);
            }
            if (!in_array($type, self::IMAGE_MIMES, true)) {
                return response()->json(['error' => 'Only PNG, JPEG, GIF and WebP images are allowed'], 400);
            }
            $decoded = base64_decode((string) $data, true);
            if ($decoded === false || strlen($decoded) > self::MAX_FILE_BYTES) {
                return response()->json(['error' => 'Invalid file data or file exceeds 5 MB'], 400);
            }
            $exists = AppointmentFile::where('appointment_id', $appointment->id)
                ->where('file_name', $name)
                ->where('file_size', (int) $size)
                ->exists();
            if ($exists) {
                continue;
            }
            $created[] = AppointmentFile::create([
                'appointment_id' => $appointment->id,
                'file_name' => $name,
                'file_type' => $type,
                'file_data' => (string) $data,
                'file_size' => (int) $size,
            ]);
        }

        return response()->json(['files' => $created]);
    }

    // ── Booking internals (legacy-verified rules) ────────────────────

    /**
     * @throws BookingException
     */
    private function createBooking(
        array $claims, array $groups, ?string $studentId, string $facultyId,
        string $meetingType, ?string $sessionGroupId, Request $request,
        array $slots, array $attendeeIds, array $attendeeOptions,
    ): Appointment {
        $creatorEmail = strtolower(trim((string) ($claims['email'] ?? '')));

        if ($studentId !== null) {
            $student = Student::find($studentId);
            if ($student === null) {
                throw new BookingException('Student not found');
            }
            // Staff booking on behalf must not take the participant slot
            // themselves. STUDENT holders always book as self — exempt.
            if (!in_array('STUDENT', $groups, true)
                && strtolower(trim($student->email)) === $creatorEmail && $creatorEmail !== '') {
                throw new BookingException('Creator cannot be the student participant');
            }
        } elseif (!in_array('STUDENT', $groups, true)
            && count($attendeeIds) === 0 && count($attendeeOptions) === 0) {
            throw new BookingException('Meetings must have at least one attendee');
        }

        $this->checkConflicts($groups, $claims, $studentId, $facultyId, $slots, $sessionGroupId, $attendeeIds, $attendeeOptions);

        $appointment = Appointment::create([
            'student_id' => $studentId,
            'faculty_id' => $facultyId,
            'session_group_id' => $sessionGroupId,
            'created_by_email' => $claims['email'] ?? '',
            'meeting_type' => $meetingType,
            'date' => $slots[0]['date'],
            'start_time' => $slots[0]['startTime'],
            'end_time' => end($slots)['endTime'],
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'teams_link' => $request->input('teamsLink'),
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        foreach ($slots as $slot) {
            AppointmentTimeSlot::create([
                'appointment_id' => $appointment->id,
                'date' => $slot['date'],
                'start_time' => $slot['startTime'],
                'end_time' => $slot['endTime'],
            ]);
        }

        // Attendees are employees only (data-model L96: user_id FK → employees
        // NOT NULL). The student participant lives on appointments.student_id
        // and is never inserted here — legacy merged it only because its user
        // table was unified.
        $candidateIds = array_values(array_unique(array_filter(array_merge(
            [$facultyId],
            $attendeeIds,
            array_map(fn ($a) => $a['userId'] ?? null, $attendeeOptions),
        ))));

        foreach ($candidateIds as $userId) {
            if (Employee::find($userId) === null) {
                throw new BookingException('Attendee must be a faculty member');
            }
        }

        foreach ($candidateIds as $userId) {
            $mandatory = true;
            foreach ($attendeeOptions as $opt) {
                if (($opt['userId'] ?? null) === $userId && isset($opt['isMandatory'])) {
                    $mandatory = (bool) $opt['isMandatory'];
                }
            }
            AppointmentAttendee::create([
                'appointment_id' => $appointment->id,
                'user_id' => $userId,
                'status' => 'INVITED',
                'is_mandatory' => $mandatory,
            ]);
        }

        return $appointment;
    }

    /**
     * STUDENT holders book as themselves; FACULTY/DEAN on behalf or internal.
     *
     * @throws BookingException
     */
    private function resolveStudentId(array $groups, array $claims, mixed $inputStudentId): ?string
    {
        if (in_array('STUDENT', $groups, true)) {
            $self = $this->selfStudent($claims);
            if ($self === null) {
                throw new BookingException('Student record not found');
            }
            return $self->id;
        }

        if ($inputStudentId === null || $inputStudentId === '') {
            return null;
        }

        return (string) $inputStudentId;
    }

    private function resolveMeetingType(array $groups, mixed $input): string
    {
        if (in_array('STUDENT', $groups, true)) {
            return 'CONSULTATION';
        }

        return $input === 'INTERNAL' ? 'INTERNAL' : 'CONSULTATION';
    }

    /**
     * @throws BookingException
     */
    private function resolveTimeSlots(Request $request): array
    {
        $slots = $request->input('timeSlots', []);
        if (!is_array($slots) || count($slots) === 0) {
            if ($request->input('date') && $request->input('startTime') && $request->input('endTime')) {
                $slots = [[
                    'date' => $request->input('date'),
                    'startTime' => $request->input('startTime'),
                    'endTime' => $request->input('endTime'),
                ]];
            }
        }

        if (!is_array($slots) || count($slots) === 0) {
            throw new BookingException('At least one timeslot (date, startTime, endTime) is required');
        }

        return array_values($slots);
    }

    /**
     * Legacy-verified: 15-minute grid, 30–480 min, no intra-booking overlap.
     *
     * @throws BookingException
     */
    private function validateTimeSlots(array $slots): void
    {
        foreach ($slots as $slot) {
            if (!$this->isQuarterHour($slot['startTime'] ?? '') || !$this->isQuarterHour($slot['endTime'] ?? '')) {
                throw new BookingException('Time must be on a 15-minute boundary');
            }
            $duration = $this->minutes($slot['endTime']) - $this->minutes($slot['startTime']);
            if ($duration < 30 || $duration > 480) {
                throw new BookingException('Invalid duration');
            }
        }

        foreach ($slots as $i => $a) {
            foreach (array_slice($slots, $i + 1) as $b) {
                if (($a['date'] ?? null) === ($b['date'] ?? null)
                    && ($a['startTime'] ?? '') < ($b['endTime'] ?? '')
                    && ($a['endTime'] ?? '') > ($b['startTime'] ?? '')) {
                    throw new BookingException('Timeslots cannot overlap within the same appointment');
                }
            }
        }
    }

    /**
     * Legacy-verified conflict rules: own overlaps always block; other-side
     * blocks only APPROVED bookings outside this session group.
     *
     * @throws BookingException
     */
    private function checkConflicts(
        array $groups, array $claims, ?string $studentId, string $facultyId,
        array $slots, ?string $sessionGroupId, array $attendeeIds, array $attendeeOptions,
    ): void {
        $isStudent = in_array('STUDENT', $groups, true);

        if ($isStudent) {
            $allIds = array_unique(array_filter(array_merge(
                $attendeeIds,
                array_map(fn ($a) => $a['userId'] ?? null, $attendeeOptions),
            )));
            $self = $this->selfStudent($claims);
            foreach ($allIds as $uid) {
                if ($uid !== ($self?->id ?? null) && Student::find($uid) !== null) {
                    throw new BookingException('Students cannot invite other students to appointments');
                }
            }

            if ($self !== null) {
                foreach ($slots as $slot) {
                    $overlap = $this->overlappingSlots(null, $self->id, $slot, $sessionGroupId);
                    if (count($overlap) > 0) {
                        throw new BookingException(
                            'You already have an appointment that overlaps with this time',
                            [[
                                'userId' => $self->id,
                                'userName' => 'You',
                                'message' => 'You already have an appointment that overlaps with this time',
                                'appointments' => $this->conflictEntries($overlap),
                            ]]
                        );
                    }
                }
            }
        }

        // Other-side check: student creators check the booking faculty;
        // faculty creators check their own faculty row (legacy-verified).
        $scopeFacultyId = $isStudent
            ? $facultyId
            : ($this->selfEmployee($claims)?->id ?? $facultyId);
        foreach ($slots as $slot) {
            $overlap = $this->overlappingSlots($scopeFacultyId, null, $slot, $sessionGroupId);
            $blocking = array_filter($overlap, fn ($s) =>
                $s['status'] === 'APPROVED' && ($s['session_group_id'] ?? null) !== $sessionGroupId
            );
            if (count($blocking) > 0) {
                $faculty = Employee::find($facultyId);
                $name = $faculty?->name ?? 'Faculty';
                throw new BookingException(
                    "$name is already booked at this time",
                    [[
                        'userId' => $facultyId,
                        'userName' => $name,
                        'message' => "$name is already booked at this time",
                        'appointments' => $this->conflictEntries($blocking),
                    ]]
                );
            }
        }
    }

    private function overlappingSlots(
        ?string $facultyId, ?string $studentId, array $slot, ?string $sessionGroupId,
    ): array {
        $query = AppointmentTimeSlot::query()
            ->join('appointments', 'appointments.id', '=', 'appointment_time_slots.appointment_id')
            ->where('appointment_time_slots.date', $slot['date'])
            ->where('appointment_time_slots.start_time', '<', $slot['endTime'])
            ->where('appointment_time_slots.end_time', '>', $slot['startTime']);

        if ($facultyId !== null) {
            $query->where('appointments.faculty_id', $facultyId);
        }
        if ($studentId !== null) {
            $query->where('appointments.student_id', $studentId);
        }
        if ($sessionGroupId !== null) {
            $query->where(function ($q) use ($sessionGroupId) {
                $q->whereNull('appointments.session_group_id')
                    ->orWhere('appointments.session_group_id', '!=', $sessionGroupId);
            });
        }

        return $query->get([
            'appointment_time_slots.date as date',
            'appointment_time_slots.start_time as startTime',
            'appointment_time_slots.end_time as endTime',
            'appointments.id as appointmentId',
            'appointments.title as title',
            'appointments.meeting_type as meetingType',
            'appointments.status as status',
            'appointments.session_group_id as session_group_id',
        ])->map(fn ($r) => [
            'appointmentId' => (string) $r->appointmentId,
            'title' => $r->title,
            'meetingType' => $r->meetingType ?? 'MEETING',
            'date' => $r->date,
            'startTime' => $r->startTime,
            'endTime' => $r->endTime,
            'status' => $r->status,
            'session_group_id' => $r->session_group_id,
        ])->all();
    }

    private function conflictEntries(array $slots): array
    {
        return array_map(fn ($s) => [
            'appointmentId' => $s['appointmentId'],
            'title' => $s['title'],
            'meetingType' => $s['meetingType'],
            'date' => $s['date'],
            'startTime' => $s['startTime'],
            'endTime' => $s['endTime'],
        ], $slots);
    }

    private function enriched(Appointment $appointment): array
    {
        $appointment->loadMissing(['student', 'faculty', 'timeSlots', 'files', 'attendees.user']);

        return [
            'appointment' => $appointment->makeHidden([])->toArray()
                + ['student' => $appointment->student ? $appointment->student->only(['id', 'name', 'email']) : null]
                + ['faculty' => $appointment->faculty ? $appointment->faculty->only(['id', 'name', 'email']) : null]
                + ['attendees' => $appointment->attendees->map(fn ($a) => $a->toArray()
                    + ['user' => $a->user ? $a->user->only(['id', 'name', 'email']) : null])->all()]
                + ['timeSlots' => $appointment->timeSlots->toArray()]
                + ['files' => $appointment->files->toArray()],
        ]['appointment'];
    }

    // ── Claims helpers ───────────────────────────────────────────────

    private function claims(Request $request): array
    {
        return $request->attributes->get('jwt_claims', []);
    }

    private function groups(array $claims): array
    {
        $groups = $claims['groups'] ?? [];

        return is_array($groups) ? array_map(fn ($g) => strtoupper((string) $g), $groups) : [];
    }

    private function selfStudent(array $claims): ?Student
    {
        $email = strtolower(trim((string) ($claims['email'] ?? '')));

        return $email === '' ? null : Student::where('email', $email)->first();
    }

    private function selfEmployee(array $claims): ?Employee
    {
        $email = strtolower(trim((string) ($claims['email'] ?? '')));

        return $email === '' ? null : Employee::where('email', $email)->first();
    }

    private function isQuarterHour(string $time): bool
    {
        if (!preg_match('/^\d{1,2}:(\d{2})/', $time, $m)) {
            return false;
        }

        return in_array((int) $m[1], [0, 15, 30, 45], true);
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [0, 0]);

        return $h * 60 + $m;
    }
}

class BookingException extends \RuntimeException
{
    public function __construct(string $message = '', public readonly array $conflicts = [])
    {
        parent::__construct($message);
    }
}
