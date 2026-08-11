<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ClassTeacherStudentRequest;
use App\Http\Requests\Academic\StudentRequest;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    /**
     * List students for the logged-in user's school.
     * Supports optional ?school_class_id= filter.
     */
    /**
     * Lists students. Supports:
     *  - ?school_class_id= — narrow to one class (already small, no
     *    pagination needed — used by attendance/score-entry rosters)
     *  - ?search= — matches first name, last name, or admission number
     *  - ?page= — when present, returns a paginated response instead
     *    of a plain array (opt-in, so existing callers that expect a
     *    flat array — attendance/score-entry, always class-scoped —
     *    are unaffected). The admin Students page uses this for the
     *    school-wide, unfiltered view where the list can run into the
     *    hundreds or thousands.
     */
    public function index()
    {
        $query = Student::with(['schoolClass', 'guardians:id,name,email', 'user:id,name,email']);

        if (request()->filled('school_class_id')) {
            $query->where('school_class_id', request()->input('school_class_id'));
        }

        if (request()->filled('search')) {
            $search = request()->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('admission_number', 'like', "%{$search}%");
            });
        }

        $query->orderBy('last_name');

        if (request()->filled('page')) {
            return $query->paginate(request()->input('per_page', 25));
        }

        return $query->get();
    }

    /**
     * Creates a student record AND automatically creates their login
     * account in the same step — a student should never need a
     * separate "invite" or "link an account" flow (that used to
     * exist and was confusing: it made students show up in the
     * general Staff list, which they aren't). If no login email is
     * supplied, a placeholder one is generated from the admission
     * number so accounts still work for students without a real
     * email address.
     */
    public function store(StudentRequest $request, \App\Services\StudentEnrollmentService $enrollmentService)
    {
        [$student, $temporaryPassword] = $enrollmentService->enroll($request->validated(), Auth::user()->school);

        return response()->json([
            'student' => $student->load('user:id,name,email'),
            'temporary_password' => $temporaryPassword,
            'login_email' => $student->user->email,
        ], 201);
    }

    public function show(Student $student)
    {
        return $student->load('schoolClass', 'user');
    }

    public function update(StudentRequest $request, Student $student)
    {
        $student->update($request->validated());

        return $student;
    }

    public function destroy(Student $student)
    {
        $student->delete();

        return response()->json(['message' => 'Student deleted.']);
    }

    /**
     * Return the roster for every class where the authenticated teacher
     * is explicitly assigned as Class Teacher. This is intentionally
     * separate from the general students index so a teacher cannot turn
     * a class id/search parameter into access to another class.
     */
    public function myClass()
    {
        $user = Auth::user();

        $classIds = TeacherAssignment::query()
            ->where('user_id', $user->id)
            ->where('is_class_teacher', true)
            ->pluck('school_class_id')
            ->filter()
            ->unique()
            ->values();

        if ($classIds->isEmpty()) {
            return response()->json([
                'message' => 'You are not assigned as a Class Teacher to any class.',
            ], 403);
        }

        $students = Student::query()
            ->with(['schoolClass'])
            ->whereIn('school_class_id', $classIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return response()->json($students);
    }

    /**
     * Update only routine student/contact details for a Class Teacher.
     * Class, admission number, account, status and promotion fields are
     * deliberately excluded from ClassTeacherStudentRequest.
     */
    public function updateMyClassStudent(ClassTeacherStudentRequest $request, Student $student)
    {
        $user = Auth::user();

        $isClassTeacher = TeacherAssignment::query()
            ->where('user_id', $user->id)
            ->where('is_class_teacher', true)
            ->where('school_class_id', $student->school_class_id)
            ->exists();

        if (! $isClassTeacher) {
            return response()->json([
                'message' => 'You are not the Class Teacher for this student.',
            ], 403);
        }

        $student->update($request->validated());

        return response()->json($student->fresh()->load('schoolClass'));
    }

    /**
     * Link (or replace the list of) parent/guardian LOGIN ACCOUNTS
     * for this student. Body: { "user_ids": [1, 2] } — replaces the
     * current list, same "sync" pattern as SchoolClassController's
     * subject assignment.
     */
    public function syncGuardians(Student $student)
    {
        $validated = request()->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        // sync() only writes the two ID columns by default — but the
        // pivot table also has a required school_id column (it's
        // BelongsToSchool-scoped like everything else), so each row
        // needs that attached explicitly or the insert violates the
        // NOT NULL constraint.
        $student->guardians()->sync(
            collect($validated['user_ids'])->mapWithKeys(fn ($id) => [
                $id => ['school_id' => $student->school_id],
            ])
        );

        return $student->load('guardians:id,name,email');
    }

    /**
     * Links an already-invited "student" role account to this Student
     * record, so that account can access their own attendance/results/
     * fees via the self-service endpoints in Api\Family. Requires the
     * target user to actually hold the `student` role and belong to
     * the same school.
     */
    /**
     * For students created BEFORE auto-login existed (or any record
     * that somehow has no linked account) — generates one now, same
     * logic as store(). Replaces the old "search for an existing
     * invited student account and link it" flow, which was confusing
     * (students shouldn't be invited via Staff at all).
     */
    public function createLogin(Student $student)
    {
        if ($student->user_id) {
            return response()->json(['message' => 'This student already has a login.'], 422);
        }

        $school = $student->school;
        $temporaryPassword = Str::random(10);
        $loginEmail = strtolower($student->admission_number) . '@' . $school->slug . '.students.local';

        $loginUser = User::create([
            'school_id' => $school->id,
            'name' => trim($student->first_name . ' ' . $student->last_name),
            'email' => $loginEmail,
            'password' => Hash::make($temporaryPassword),
            'status' => 'approved',
        ]);
        $loginUser->assignRole('student');

        $student->update(['user_id' => $loginUser->id]);

        return response()->json([
            'student' => $student->load('user:id,name,email'),
            'temporary_password' => $temporaryPassword,
            'login_email' => $loginEmail,
        ]);
    }
}
