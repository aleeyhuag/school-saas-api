<?php

use App\Models\AcademicSession;
use App\Models\FeeStructure;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectScore;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function phase1Role(string $name): Role
{
    return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
}

function phase1School(string $name): School
{
    return School::create([
        'name' => $name,
        'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
        'is_active' => true,
    ]);
}

function phase1User(School $school, string $role, string $email): User
{
    $user = User::create([
        'school_id' => $school->id,
        'name' => ucfirst($role),
        'email' => $email,
        'password' => bcrypt('password'),
        'status' => 'approved',
    ]);

    $user->assignRole(phase1Role($role));

    return $user;
}

function phase1Term(School $school): Term
{
    $session = AcademicSession::create([
        'school_id' => $school->id,
        'name' => '2026/2027',
        'start_date' => '2026-09-01',
        'end_date' => '2027-07-31',
        'is_current' => true,
    ]);

    return Term::create([
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'name' => 'First',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-20',
        'is_current' => true,
    ]);
}

it('rejects a student update using a class from another school', function () {
    $schoolA = phase1School('Security School A');
    $schoolB = phase1School('Security School B');
    $admin = phase1User($schoolA, 'principal', 'principal-a@example.test');
    $classA = SchoolClass::create(['school_id' => $schoolA->id, 'name' => 'JSS 1', 'arm' => 'A']);
    $classB = SchoolClass::create(['school_id' => $schoolB->id, 'name' => 'JSS 1', 'arm' => 'A']);
    $student = Student::create([
        'school_id' => $schoolA->id,
        'school_class_id' => $classA->id,
        'admission_number' => 'A-001',
        'first_name' => 'Test',
        'last_name' => 'Student',
    ]);

    Sanctum::actingAs($admin);

    $this->putJson('/api/students/' . $student->id, [
        'school_class_id' => $classB->id,
        'admission_number' => $student->admission_number,
        'first_name' => $student->first_name,
        'last_name' => $student->last_name,
    ])->assertStatus(422);
});

it('rejects score entry when the student belongs to another class', function () {
    $school = phase1School('Score Security School');
    $teacher = phase1User($school, 'teacher', 'teacher-score@example.test');
    $classA = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1', 'arm' => 'A']);
    $classB = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1', 'arm' => 'B']);
    $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);
    $classA->subjects()->attach($subject->id);
    $term = phase1Term($school);
    $student = Student::create([
        'school_id' => $school->id,
        'school_class_id' => $classB->id,
        'admission_number' => 'B-001',
        'first_name' => 'Wrong',
        'last_name' => 'Class',
    ]);
    TeacherAssignment::create([
        'school_id' => $school->id,
        'user_id' => $teacher->id,
        'school_class_id' => $classA->id,
        'subject_id' => $subject->id,
        'is_class_teacher' => false,
    ]);

    Sanctum::actingAs($teacher);

    $this->postJson('/api/subject-scores', [
        'term_id' => $term->id,
        'school_class_id' => $classA->id,
        'subject_id' => $subject->id,
        'student_id' => $student->id,
        'ca_score' => 10,
        'ca_max' => 20,
        'assignment_score' => 10,
        'assignment_max' => 10,
        'exam_score' => 50,
        'exam_max' => 70,
    ])->assertStatus(422);
});

it('allows a class teacher to read a subject they do not teach', function () {
    $school = phase1School('Class Teacher School');
    $teacher = phase1User($school, 'teacher', 'class-teacher@example.test');
    $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 2', 'arm' => 'A']);
    $subject = Subject::create(['school_id' => $school->id, 'name' => 'English']);
    $class->subjects()->attach($subject->id);
    $term = phase1Term($school);
    Student::create([
        'school_id' => $school->id,
        'school_class_id' => $class->id,
        'admission_number' => 'CT-001',
        'first_name' => 'Class',
        'last_name' => 'Student',
    ]);
    TeacherAssignment::create([
        'school_id' => $school->id,
        'user_id' => $teacher->id,
        'school_class_id' => $class->id,
        'subject_id' => null,
        'is_class_teacher' => true,
    ]);

    Sanctum::actingAs($teacher);

    $this->getJson('/api/subject-scores?school_class_id=' . $class->id . '&subject_id=' . $subject->id . '&term_id=' . $term->id)
        ->assertOk();
});

it('lets a class teacher read the complete class marksheet without subject assignment', function () {
    $school = phase1School('Marksheet Security School');
    $teacher = phase1User($school, 'teacher', 'marksheet-teacher@example.test');
    $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1', 'arm' => 'A']);
    $math = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);
    $english = Subject::create(['school_id' => $school->id, 'name' => 'English']);
    $class->subjects()->attach([$math->id, $english->id]);
    $term = phase1Term($school);
    $student = Student::create([
        'school_id' => $school->id,
        'school_class_id' => $class->id,
        'admission_number' => 'MS-001',
        'first_name' => 'Mark',
        'last_name' => 'Student',
    ]);
    TeacherAssignment::create([
        'school_id' => $school->id,
        'user_id' => $teacher->id,
        'school_class_id' => $class->id,
        'subject_id' => null,
        'is_class_teacher' => true,
    ]);
    SubjectScore::create([
        'school_id' => $school->id,
        'term_id' => $term->id,
        'school_class_id' => $class->id,
        'subject_id' => $math->id,
        'student_id' => $student->id,
        'ca_score' => 10,
        'ca_max' => 20,
        'assignment_score' => 10,
        'assignment_max' => 10,
        'exam_score' => 50,
        'exam_max' => 70,
    ]);

    Sanctum::actingAs($teacher);

    $response = $this->getJson('/api/classes/' . $class->id . '/marksheet?term_id=' . $term->id)
        ->assertOk()
        ->assertJsonCount(1)
        ->json('0');

    expect($response['student_id'])->toBe($student->id)
        ->and($response['scores'][(string) $math->id]['total_score'])->toBe(70.0)
        ->and($response['scores'][(string) $english->id])->toBeNull();
});

it('rejects a class teacher marksheet request for another class', function () {
    $school = phase1School('Cross Class Marksheet School');
    $teacher = phase1User($school, 'teacher', 'marksheet-cross@example.test');
    $classA = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1', 'arm' => 'A']);
    $classB = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1', 'arm' => 'B']);
    TeacherAssignment::create([
        'school_id' => $school->id,
        'user_id' => $teacher->id,
        'school_class_id' => $classA->id,
        'subject_id' => null,
        'is_class_teacher' => true,
    ]);
    $term = phase1Term($school);

    Sanctum::actingAs($teacher);

    $this->getJson('/api/classes/' . $classB->id . '/marksheet?term_id=' . $term->id)
        ->assertForbidden();
});

it('rejects a fee payment using a fee structure from another school', function () {
    $schoolA = phase1School('Fee School A');
    $schoolB = phase1School('Fee School B');
    $bursar = phase1User($schoolA, 'bursar', 'bursar-fee@example.test');
    $classA = SchoolClass::create(['school_id' => $schoolA->id, 'name' => 'JSS 1', 'arm' => 'A']);
    $classB = SchoolClass::create(['school_id' => $schoolB->id, 'name' => 'JSS 1', 'arm' => 'A']);
    $termB = phase1Term($schoolB);
    $studentA = Student::create([
        'school_id' => $schoolA->id,
        'school_class_id' => $classA->id,
        'admission_number' => 'F-001',
        'first_name' => 'Fee',
        'last_name' => 'Student',
    ]);
    $feeB = FeeStructure::create([
        'school_id' => $schoolB->id,
        'term_id' => $termB->id,
        'school_class_id' => $classB->id,
        'name' => 'Tuition',
        'amount' => 10000,
    ]);

    Sanctum::actingAs($bursar);

    $this->postJson('/api/fee-payments', [
        'student_id' => $studentA->id,
        'fee_structure_id' => $feeB->id,
        'amount_paid' => 1000,
        'method' => 'cash',
        'paid_at' => '2026-09-10',
    ])->assertStatus(422);
});
