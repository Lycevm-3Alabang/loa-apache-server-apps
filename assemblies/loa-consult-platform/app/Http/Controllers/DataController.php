<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentAttendee;
use App\Models\AppointmentFile;
use App\Models\AppointmentTimeSlot;
use App\Models\Department;
use App\Models\DepartmentCourse;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationComment;
use App\Models\EvaluationPeriod;
use App\Models\EvaluationRating;
use App\Models\EvaluationResult;
use App\Models\FacultyAvailabilityRule;
use App\Models\FacultySubject;
use App\Models\RubricCategory;
use App\Models\RubricGroup;
use App\Models\RubricGroupSnapshot;
use App\Models\RubricItem;
use App\Models\RatingScale;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DataController extends Controller
{
    public function deleteStudents(Request $request)
    {
        if ($request->input('confirm') !== true) {
            return response()->json(['error' => 'confirm:true is required'], 422);
        }

        Appointment::whereNotNull('student_id')->update(['student_id' => null]);
        $deleted = Student::query()->delete();

        return response()->json(['data' => ['deleted' => $deleted]]);
    }

    public function resetDb(Request $request)
    {
        if ($request->input('confirm') !== true) {
            return response()->json(['error' => 'confirm:true is required'], 422);
        }

        $models = [
            AppointmentFile::class,
            AppointmentAttendee::class,
            AppointmentTimeSlot::class,
            Appointment::class,
            FacultyAvailabilityRule::class,
            EvaluationRating::class,
            EvaluationComment::class,
            EvaluationResult::class,
            Evaluation::class,
            RubricGroupSnapshot::class,
            RubricItem::class,
            RubricCategory::class,
            RubricGroup::class,
            RatingScale::class,
            EvaluationPeriod::class,
            StudentEnrollment::class,
            FacultySubject::class,
            Student::class,
            Employee::class,
            Section::class,
            Subject::class,
            DepartmentCourse::class,
            Semester::class,
            Department::class,
        ];

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($models as $model) {
                $model::query()->delete();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return response()->json(['data' => ['reset' => true, 'tables' => count($models)]]);
    }

    public function exportConsultations()
    {
        $rows = Appointment::orderBy('created_at')->get();

        return response()->json(['data' => [
            'exported_at' => now()->toIso8601String(),
            'count' => $rows->count(),
            'appointments' => $rows,
        ]]);
    }

    public function evaluationMappings()
    {
        $rows = FacultySubject::orderBy('created_at')->get();

        return response()->json(['data' => $rows]);
    }
}
