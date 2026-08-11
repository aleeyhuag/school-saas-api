<?php

namespace App\Http\Controllers\Api\Grading;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grading\SubjectScoreRequest;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\SubjectScore;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\TermResultApproval;
use App\Services\ResultService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SubjectScoreController extends Controller
{
    public function __construct(protected ResultService $resultService) {}

    /**
     * Enter/update one student's scores for one subject/term.
     * Subject-score entry is intentionally narrower than class-teacher
     * review: a teacher must have an assignment for this exact class+subject.
     * A class-teacher assignment by itself grants review/approval access,
     * not score-entry access.
     */
    public function store(SubjectScoreRequest $request)
    {
        $validated = $request->validated();
        $user = Auth::user();

        $student = Student::whereKey($validated['student_id'])->firstOrFail();
        $schoolClass = SchoolClass::whereKey($validated['school_class_id'])->firstOrFail();
        $term = Term::whereKey($validated['term_id'])->firstOrFail();

        // Defence in depth: all four records must describe the same tenant.
        if (
            (int) $student->school_id !== (int) $user->school_id ||
            (int) $schoolClass->school_id !== (int) $user->school_id ||
            (int) $term->school_id !== (int) $user->school_id
        ) {
            abort(403, 'The supplied academic records do not belong to your school.');
        }

        // Never allow a score row to claim that a student is in a class
        // different from the student's actual current class.
        if ((int) $student->school_class_id !== (int) $schoolClass->id) {
            throw ValidationException::withMessages([
                'school_class_id' => ['The selected student does not belong to this class.'],
            ]);
        }

        // The subject must actually be attached to this class.
        $subjectBelongsToClass = $schoolClass->subjects()
            ->whereKey($validated['subject_id'])
            ->exists();

        if (! $subjectBelongsToClass) {
            throw ValidationException::withMessages([
                'subject_id' => ['This subject is not attached to the selected class.'],
            ]);
        }

        $isLocked = TermResultApproval::where('school_class_id', $schoolClass->id)
            ->where('term_id', $term->id)
            ->whereNotNull('locked_at')
            ->exists();

        if ($isLocked) {
            throw ValidationException::withMessages([
                'student_id' => ['Results for this class/term have been permanently published — scores can no longer be edited.'],
            ]);
        }

        if (! $user->hasRole(['proprietor', 'principal', 'exam_officer'])) {
            $isAssigned = TeacherAssignment::where('user_id', $user->id)
                ->where('school_class_id', $schoolClass->id)
                ->where('subject_id', $validated['subject_id'])
                ->exists();

            if (! $isAssigned) {
                throw ValidationException::withMessages([
                    'subject_id' => ['You are not assigned to teach this subject in this class.'],
                ]);
            }
        }

        $score = SubjectScore::updateOrCreate(
            [
                'student_id' => $validated['student_id'],
                'subject_id' => $validated['subject_id'],
                'term_id' => $validated['term_id'],
            ],
            array_merge($validated, [
                'school_id' => $user->school_id,
                'entered_by' => $user->id,
            ])
        );

        return response()->json($this->resultService->computeSubjectResult($score), 201);
    }

    /**
     * Subject-teacher view: one class + one subject + one term.
     * A subject teacher can only read the exact assignment they teach.
     * A class teacher can read any subject belonging to their own class.
     */
    public function index()
    {
        request()->validate([
            'school_class_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'term_id' => ['required', 'integer'],
        ]);

        $schoolClassId = (int) request()->input('school_class_id');
        $subjectId = (int) request()->input('subject_id');
        $termId = (int) request()->input('term_id');
        $user = Auth::user();

        $schoolClass = SchoolClass::with('subjects')->findOrFail($schoolClassId);
        $term = Term::findOrFail($termId);

        if ((int) $schoolClass->school_id !== (int) $user->school_id || (int) $term->school_id !== (int) $user->school_id) {
            abort(403, 'This academic resource does not belong to your school.');
        }

        if (! $schoolClass->subjects->contains('id', $subjectId)) {
            abort(422, 'This subject is not attached to the selected class.');
        }

        if (! $user->hasRole(['proprietor', 'principal', 'exam_officer'])) {
            $isSubjectTeacher = TeacherAssignment::where('user_id', $user->id)
                ->where('school_class_id', $schoolClassId)
                ->where('subject_id', $subjectId)
                ->exists();

            $isClassTeacher = TeacherAssignment::where('user_id', $user->id)
                ->where('school_class_id', $schoolClassId)
                ->where('is_class_teacher', true)
                ->exists();

            if (! $isSubjectTeacher && ! $isClassTeacher) {
                abort(403, 'You are not authorized to view scores for this class and subject.');
            }
        }

        $scores = SubjectScore::with('student')
            ->where('school_class_id', $schoolClassId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->get();

        $positions = $this->resultService->computeSubjectPositions($schoolClassId, $subjectId, $termId);

        return $scores->map(function ($score) use ($positions) {
            $result = $this->resultService->computeSubjectResult($score);
            $result['student_id'] = $score->student_id;
            $result['student_name'] = $score->student->full_name;
            $result['position'] = $positions->get($score->student_id)['position'] ?? null;
            $result['teacher_comment'] = $score->teacher_comment;
            return $result;
        })->sortBy('position')->values();
    }

    /**
     * Whole-class marksheet for a class teacher.
     * This replaces the frontend's N separate subject-score requests.
     * One authorization decision is made for the class, then the backend
     * returns every student and every attached subject. Missing scores are
     * represented as null cells instead of making the entire marksheet fail.
     */
    public function classMarksheet(int $schoolClassId)
    {
        request()->validate([
            'term_id' => ['required', 'integer'],
        ]);

        $termId = (int) request()->input('term_id');
        $user = Auth::user();

        $schoolClass = SchoolClass::with('subjects')->findOrFail($schoolClassId);
        $term = Term::findOrFail($termId);

        if ((int) $schoolClass->school_id !== (int) $user->school_id || (int) $term->school_id !== (int) $user->school_id) {
            abort(403, 'This academic resource does not belong to your school.');
        }

        if (! $user->hasRole(['proprietor', 'principal', 'exam_officer'])) {
            $isClassTeacher = TeacherAssignment::where('user_id', $user->id)
                ->where('school_class_id', $schoolClassId)
                ->where('is_class_teacher', true)
                ->exists();

            if (! $isClassTeacher) {
                abort(403, 'Only this class\'s class teacher or school management can view the whole-class marksheet.');
            }
        }

        $students = Student::where('school_class_id', $schoolClassId)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        $subjectIds = $schoolClass->subjects->pluck('id');
        $scores = SubjectScore::where('school_class_id', $schoolClassId)
            ->where('term_id', $termId)
            ->whereIn('subject_id', $subjectIds)
            ->with('student:id,first_name,last_name')
            ->get()
            ->groupBy('subject_id');

        $rows = $students->map(function ($student) use ($schoolClass, $subjectIds, $scores) {
            $studentScores = [];

            foreach ($subjectIds as $subjectId) {
                $score = $scores->get($subjectId, collect())->firstWhere('student_id', $student->id);
                if ($score) {
                    $studentScores[(string) $subjectId] = $this->resultService->computeSubjectResult($score) + [
                        'student_id' => $student->id,
                        'student_name' => $student->full_name,
                        'teacher_comment' => $score->teacher_comment,
                    ];
                }
            }

            return [
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'scores' => $studentScores,
            ];
        });

        return response()->json([
            'school_class_id' => $schoolClass->id,
            'term_id' => $termId,
            'students' => $rows->values(),
            'subjects' => $schoolClass->subjects->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
            ])->values(),
        ]);
    }
}
