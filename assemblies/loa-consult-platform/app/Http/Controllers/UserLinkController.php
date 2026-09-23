<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Student;
use Illuminate\Http\Request;

class UserLinkController extends Controller
{
    private function linkForEmail(?string $email): ?array
    {
        if (!$email) {
            return null;
        }

        $student = Student::where('email', $email)->first();
        if ($student) {
            return ['type' => 'student', 'row' => $student];
        }

        $employee = Employee::where('email', $email)->first();
        if ($employee) {
            return ['type' => 'employee', 'row' => $employee];
        }

        return null;
    }

    public function primary(Request $request)
    {
        $user = $request->attributes->get('consult_user', []);
        $link = $this->linkForEmail($user['email'] ?? null);

        return response()->json(['data' => [
            'auth' => [
                'sub' => $user['sub'] ?? null,
                'email' => $user['email'] ?? null,
                'name' => $user['name'] ?? null,
                'groups' => $user['groups'] ?? [],
            ],
            'link' => $link,
            'linked' => $link !== null,
        ]]);
    }

    public function attendees()
    {
        $rows = Employee::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $rows]);
    }

    public function relatedData(Request $request, string $id)
    {
        $user = $request->attributes->get('consult_user', []);

        if (($user['sub'] ?? null) === $id) {
            $link = $this->linkForEmail($user['email'] ?? null);

            return response()->json(['data' => [
                'auth' => [
                    'sub' => $user['sub'] ?? null,
                    'email' => $user['email'] ?? null,
                    'name' => $user['name'] ?? null,
                    'groups' => $user['groups'] ?? [],
                ],
                'link' => $link,
                'linked' => $link !== null,
            ]]);
        }

        $student = Student::find($id);
        if ($student) {
            return response()->json(['data' => [
                'auth' => null,
                'link' => ['type' => 'student', 'row' => $student],
                'linked' => false,
            ]]);
        }

        $employee = Employee::find($id);
        if ($employee) {
            return response()->json(['data' => [
                'auth' => null,
                'link' => ['type' => 'employee', 'row' => $employee],
                'linked' => false,
            ]]);
        }

        return response()->json(['error' => 'User not found'], 404);
    }
}
