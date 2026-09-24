<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FacultyAvailabilityRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityRuleController extends Controller
{
    /**
     * Group gates name Auth tenant groups (api-endpoints.md §4.0): membership
     * is read from the JWT `groups` claim (case-insensitive) — never a local
     * role. `GET /api/v1/availability-rules` — read.
     */
    public function index(Request $request): JsonResponse
    {
        $claims = $request->attributes->get('jwt_claims', []);
        $groups = $this->groups($claims);

        $facultyId = $request->query('facultyId');
        $self = $this->selfEmployee($claims);

        if ($facultyId === null || $facultyId === '') {
            if (!array_intersect(['FACULTY', 'DEAN'], $groups) || $self === null) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            $facultyId = $self->id;
        } elseif ($self === null || $facultyId !== $self->id) {
            if (!in_array('ADMIN', $groups, true)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $rules = FacultyAvailabilityRule::where('faculty_id', $facultyId)->get();

        return response()->json(['rules' => $rules]);
    }

    /**
     * `POST /api/v1/availability-rules` — write, FACULTY/DEAN/ADMIN holders.
     * Upsert per (faculty_id, day_of_week, start_date). Non-ADMIN holders
     * always write own (facultyId forced to self); ADMIN holders may set
     * facultyId for others (audited UPDATE_AVAILABILITY_RULE, fail-soft).
     */
    public function store(Request $request): JsonResponse
    {
        $claims = $request->attributes->get('jwt_claims', []);
        $groups = $this->groups($claims);

        if (count(array_intersect(['FACULTY', 'DEAN', 'ADMIN'], $groups)) === 0) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $isAdmin = in_array('ADMIN', $groups, true);
        $self = $this->selfEmployee($claims);

        if (!$isAdmin && $self === null) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $dayOfWeek = $request->input('dayOfWeek');
        $startDate = $request->input('startDate');

        if (!is_numeric($dayOfWeek) || (int) $dayOfWeek < 0 || (int) $dayOfWeek > 6) {
            return response()->json(['error' => 'dayOfWeek must be an integer 0–6'], 400);
        }

        if (!is_string($startDate) || !$this->isDate($startDate)) {
            return response()->json(['error' => 'startDate is required (YYYY-MM-DD)'], 400);
        }

        $facultyId = $self?->id;
        if ($isAdmin && is_string($request->input('facultyId')) && $request->input('facultyId') !== '') {
            $target = Employee::find($request->input('facultyId'));
            if ($target === null) {
                return response()->json(['error' => 'Faculty not found'], 404);
            }
            $facultyId = $target->id;
        }

        $rule = FacultyAvailabilityRule::firstOrNew([
            'faculty_id' => $facultyId,
            'day_of_week' => (int) $dayOfWeek,
            'start_date' => $startDate,
        ]);
        $rule->fill([
            'is_blocked' => (bool) $request->input('isBlocked', $rule->is_blocked ?? false),
            'start_time' => $request->input('startTime', $rule->start_time),
            'end_time' => $request->input('endTime', $rule->end_time),
            'end_date' => $request->input('endDate', $rule->end_date),
        ]);
        $rule->save();

        if ($isAdmin && $self !== null && $facultyId !== $self->id) {
            try {
                app(\App\Services\AuditLogger::class)->fromClaims(
                    'UPDATE_AVAILABILITY_RULE',
                    $claims,
                    ['faculty_id' => $facultyId, 'rule_id' => $rule->id]
                );
            } catch (\Throwable $e) {
                \Log::warning('Audit log failed: ' . $e->getMessage(), ['exception' => $e]);
            }
        }

        return response()->json(['rule' => $rule]);
    }

    /** JWT `groups` claim, uppercased. */
    private function groups(array $claims): array
    {
        $groups = $claims['groups'] ?? [];

        if (!is_array($groups)) {
            return [];
        }

        return array_map(fn ($g) => strtoupper((string) $g), $groups);
    }

    /** Local employee row for the caller, matched by JWT email. */
    private function selfEmployee(array $claims): ?Employee
    {
        $email = strtolower(trim((string) ($claims['email'] ?? '')));

        if ($email === '') {
            return null;
        }

        return Employee::where('email', $email)->first();
    }

    private function isDate(string $value): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value;
    }
}
