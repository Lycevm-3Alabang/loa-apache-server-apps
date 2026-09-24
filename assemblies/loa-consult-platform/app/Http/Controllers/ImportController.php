<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentCourse;
use App\Models\Employee;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    private const DOMAINS = ['departments-courses', 'faculties', 'students'];

    private function validateRows(string $domain, array $rows): array
    {
        $errors = [];

        foreach ($rows as $i => $row) {
            if ($domain === 'students') {
                if (empty($row['name']) || empty($row['email'])) {
                    $errors[] = ['index' => $i, 'error' => 'name and email are required'];
                } elseif (empty($row['student_number']) && !Student::where('email', $row['email'])->exists()) {
                    $errors[] = ['index' => $i, 'error' => 'student_number is required for new students'];
                }
                if (!empty($row['course_id']) && !DepartmentCourse::find($row['course_id'])) {
                    $errors[] = ['index' => $i, 'error' => 'Invalid course_id'];
                }
            } elseif ($domain === 'faculties') {
                if (empty($row['name']) || empty($row['email'])) {
                    $errors[] = ['index' => $i, 'error' => 'name and email are required'];
                }
                if (!empty($row['department_id']) && !Department::find($row['department_id'])) {
                    $errors[] = ['index' => $i, 'error' => 'Invalid department_id'];
                }
            } else {
                if (empty($row['name']) || empty($row['code']) || empty($row['department_id'])) {
                    $errors[] = ['index' => $i, 'error' => 'name, code and department_id are required'];
                } elseif (!Department::find($row['department_id'])) {
                    $errors[] = ['index' => $i, 'error' => 'Invalid department_id'];
                }
            }
        }

        return $errors;
    }

    private function applyRows(string $domain, array $rows): array
    {
        $written = 0;
        $updated = 0;

        foreach ($rows as $row) {
            if ($domain === 'students') {
                $existing = Student::where('email', $row['email'])->first();
                if ($existing) {
                    $existing->fill([
                        'name' => $row['name'],
                        'student_number' => $row['student_number'] ?? $existing->student_number,
                        'course_id' => $row['course_id'] ?? $existing->course_id,
                        'is_active' => $row['is_active'] ?? $existing->is_active,
                    ])->save();
                    $updated++;
                } else {
                    $s = new Student();
                    $s->id = (string) Str::uuid();
                    $s->name = $row['name'];
                    $s->email = $row['email'];
                    $s->student_number = $row['student_number'];
                    $s->course_id = $row['course_id'] ?? null;
                    $s->is_active = $row['is_active'] ?? true;
                    $s->save();
                    $written++;
                }
            } elseif ($domain === 'faculties') {
                $existing = Employee::where('email', $row['email'])->first();
                if ($existing) {
                    $existing->fill([
                        'name' => $row['name'],
                        'employee_number' => $row['employee_number'] ?? $existing->employee_number,
                        'department_id' => $row['department_id'] ?? $existing->department_id,
                        'is_active' => $row['is_active'] ?? $existing->is_active,
                    ])->save();
                    $updated++;
                } else {
                    $e = new Employee();
                    $e->id = (string) Str::uuid();
                    $e->name = $row['name'];
                    $e->email = $row['email'];
                    $e->employee_number = $row['employee_number'] ?? null;
                    $e->department_id = $row['department_id'] ?? null;
                    $e->is_active = $row['is_active'] ?? true;
                    $e->save();
                    $written++;
                }
            } else {
                $existing = DepartmentCourse::where('department_id', $row['department_id'])
                    ->where('code', $row['code'])->first();
                DepartmentCourse::updateOrCreate(
                    ['department_id' => $row['department_id'], 'code' => $row['code']],
                    ['name' => $row['name'], 'is_active' => $row['is_active'] ?? true]
                );
                $existing ? $updated++ : $written++;
            }
        }

        return [$written, $updated];
    }

    public function preview(Request $request)
    {
        $domain = $request->input('domain');
        $rows = $request->input('rows', []);

        if (!in_array($domain, self::DOMAINS, true) || !is_array($rows)) {
            return response()->json(['error' => 'domain must be one of departments-courses, faculties, students with rows array'], 400);
        }

        $errors = $this->validateRows($domain, $rows);

        return response()->json(['data' => [
            'domain' => $domain,
            'valid' => empty($errors),
            'errors' => $errors,
            'would_write' => empty($errors) ? count($rows) : 0,
        ]]);
    }

    public function importDomain(Request $request, string $domain)
    {
        if (!in_array($domain, self::DOMAINS, true)) {
            return response()->json(['error' => 'Unknown domain'], 404);
        }

        $rows = $request->input('rows', []);
        if (!is_array($rows)) {
            return response()->json(['error' => 'rows array is required'], 400);
        }

        $errors = $this->validateRows($domain, $rows);
        if (!empty($errors)) {
            return response()->json(['error' => 'Validation failed', 'errors' => $errors], 422);
        }

        [$written, $updated] = $this->applyRows($domain, $rows);

        return response()->json(['data' => [
            'domain' => $domain,
            'written' => $written,
            'updated' => $updated,
            'errors' => [],
        ]]);
    }

    public function referenceCourses()
    {
        $rows = DepartmentCourse::orderBy('code')->get(['id', 'department_id', 'code', 'name']);

        return response()->json(['data' => ['domain' => 'departments-courses', 'columns' => ['department_id', 'code', 'name'], 'rows' => $rows]]);
    }

    public function referenceFaculties()
    {
        $rows = Employee::orderBy('email')->get(['id', 'name', 'email', 'employee_number', 'department_id']);

        return response()->json(['data' => ['domain' => 'faculties', 'columns' => ['name', 'email', 'employee_number', 'department_id'], 'rows' => $rows]]);
    }

    public function referenceStudents()
    {
        $rows = Student::orderBy('email')->get(['id', 'name', 'email', 'student_number', 'course_id']);

        return response()->json(['data' => ['domain' => 'students', 'columns' => ['name', 'email', 'student_number', 'course_id'], 'rows' => $rows]]);
    }

    public function listStudents()
    {
        $rows = Student::orderBy('email')->get(['id', 'name', 'email', 'student_number', 'course_id']);

        return response()->json(['data' => $rows]);
    }

    public function referenceSubjects()
    {
        $rows = Subject::orderBy('code')->get(['id', 'code', 'name']);

        return response()->json(['data' => ['domain' => 'subjects', 'columns' => ['code', 'name'], 'rows' => $rows]]);
    }

    public function referenceSections()
    {
        $rows = Section::orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => ['domain' => 'sections', 'columns' => ['name'], 'rows' => $rows]]);
    }
}
