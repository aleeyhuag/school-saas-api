<?php

use App\Models\CbtAttempt;
use App\Models\CbtExam;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function r3Role(string $name): Role
{
    return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
}

function r3School(string $name): School
{
    return School::create([
        'name' => $name,
        'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
        'is_active' => true,
    ]);
}

function r3User(School $school, string $role, string $email): User
{
    $user = User::create([
        'school_id' => $school->id,
        'name' => ucfirst($role),
        'email' => $email,
        'password' => bcrypt('password'),
        'status' => 'approved',
    ]);
    $user->assignRole(r3Role($role));

    return $user;
}

/**
 * Builds a school, a published single-question CBT exam, and a student
 * account enrolled in the exam's class — the minimum fixture shared by
 * the start/submit tests below.
 */
function r3ExamFixture(): array
{
    $school = r3School('CBT Repair School ' . uniqid());
    $session = DB::table('academic_sessions')->insertGetId([
        'school_id' => $school->id, 'name' => '2026/2027',
        'start_date' => '2026-09-01', 'end_date' => '2027-07-31',
        'is_current' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $term = DB::table('terms')->insertGetId([
        'school_id' => $school->id, 'academic_session_id' => $session,
        'name' => 'First', 'start_date' => '2026-09-01', 'end_date' => '2026-12-15',
        'is_current' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);
    $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1', 'arm' => 'A']);
    DB::table('class_subject')->insert(['school_class_id' => $class->id, 'subject_id' => $subject->id, 'created_at' => now(), 'updated_at' => now()]);

    $examOfficer = r3User($school, 'exam_officer', 'exam-officer-' . uniqid() . '@example.test');

    $exam = CbtExam::create([
        'school_id' => $school->id, 'term_id' => $term, 'subject_id' => $subject->id,
        'title' => 'Repair Test CBT', 'duration_minutes' => 30,
        'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(),
        'pass_mark' => 50, 'published' => true,
    ]);
    $exam->schoolClasses()->attach($class->id);
    $question = $exam->questions()->create(['question_text' => '2 + 2 = ?', 'marks' => 1, 'position' => 1]);
    $question->options()->createMany([
        ['option_text' => '3', 'is_correct' => false, 'position' => 1],
        ['option_text' => '4', 'is_correct' => true, 'position' => 2],
    ]);

    $studentUser = r3User($school, 'student', 'student-' . uniqid() . '@example.test');
    $student = Student::create([
        'school_id' => $school->id, 'user_id' => $studentUser->id, 'school_class_id' => $class->id,
        'admission_number' => 'REP-' . uniqid(), 'first_name' => 'Test', 'last_name' => 'Student',
    ]);

    return compact('school', 'term', 'subject', 'class', 'examOfficer', 'exam', 'question', 'studentUser', 'student');
}

it('opens the exam with questions on the very first Start Exam click', function () {
    $f = r3ExamFixture();
    Sanctum::actingAs($f['studentUser']);

    $response = $this->postJson("/api/student/cbt/exams/{$f['exam']->id}/start");

    $response->assertStatus(200);
    $response->assertJsonPath('status', 'in_progress');
    $response->assertJsonCount(1, 'questions');
});

it('lets a student submit a CBT attempt without a 500 error, and marks it submitted', function () {
    $f = r3ExamFixture();
    Sanctum::actingAs($f['studentUser']);

    $start = $this->postJson("/api/student/cbt/exams/{$f['exam']->id}/start");
    $attemptId = $start->json('id');

    $this->postJson("/api/student/cbt/attempts/{$attemptId}/answers", [
        'question_id' => $f['question']->id,
        'option_id' => $f['question']->options()->where('is_correct', true)->first()->id,
    ])->assertStatus(200);

    $submit = $this->postJson("/api/student/cbt/attempts/{$attemptId}/submit");

    $submit->assertStatus(200);
    $submit->assertJsonPath('status', 'submitted');
    $submit->assertJsonMissingPath('code');
    expect(CbtAttempt::find($attemptId)->status)->toBe('submitted');
});

it('stores a CBT exam start time as the correct UTC instant for a Lagos wall-clock time', function () {
    $f = r3ExamFixture();
    Sanctum::actingAs($f['examOfficer']);

    // 15:00 Lagos time (+01:00), exactly what the frontend's
    // localDateTimeToIso() now sends.
    $response = $this->postJson('/api/cbt/exams', [
        'term_id' => $f['term'], 'subject_id' => $f['subject']->id, 'title' => 'Timezone Check CBT',
        'duration_minutes' => 60, 'starts_at' => '2026-09-15T15:00:00+01:00', 'ends_at' => '2026-09-15T16:00:00+01:00',
        'pass_mark' => 50, 'school_class_ids' => [$f['class']->id],
    ]);

    $response->assertStatus(201);

    $stored = CbtExam::find($response->json('id'));
    // 15:00 Lagos (+01:00) must be stored as 14:00 UTC — if this stores
    // "15:00:00" verbatim (the pre-fix bug), this assertion catches it.
    expect($stored->starts_at->utc()->format('H:i'))->toBe('14:00');
    expect($response->json('starts_at'))->toContain('14:00:00');
});
