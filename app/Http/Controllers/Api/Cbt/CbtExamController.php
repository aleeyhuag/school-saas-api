<?php

namespace App\Http\Controllers\Api\Cbt;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\CbtExam;
use App\Models\CbtQuestion;
use App\Models\CbtQuestionBank;
use App\Models\TeacherAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CbtExamController extends Controller
{
    private function user() { return Auth::user(); }

    private function ensureManager(): void
    {
        abort_unless($this->user()->hasAnyRole(['exam_officer', 'teacher']), 403);
    }

    private function ensureExamSchool(CbtExam $exam): void
    {
        abort_unless((int) $exam->school_id === (int) $this->user()->school_id, 404);
    }

    private function teacherCanUseExam(CbtExam $exam): bool
    {
        $user = $this->user();
        if ($user->hasRole('exam_officer')) return true;

        $classIds = $exam->schoolClasses()->pluck('school_classes.id');
        return TeacherAssignment::where('school_id', $user->school_id)
            ->where('user_id', $user->id)
            ->whereIn('school_class_id', $classIds)
            ->where(function ($q) use ($exam) {
                $q->where('subject_id', $exam->subject_id)->orWhere('is_class_teacher', true);
            })->exists();
    }

    private function ensureTeacherCanUseExam(CbtExam $exam): void
    {
        $this->ensureExamSchool($exam);
        abort_unless($this->teacherCanUseExam($exam), 403, 'You are not assigned to this CBT exam.');
    }

    private function ensureCreateScope(int $subjectId, array $classIds): void
    {
        $user = $this->user();
        if ($user->hasRole('exam_officer')) return;

        $assignments = TeacherAssignment::where('school_id', $user->school_id)
            ->where('user_id', $user->id)->whereIn('school_class_id', $classIds)->get();
        abort_unless($assignments->isNotEmpty(), 403, 'You are not assigned to any selected class.');

        foreach ($classIds as $classId) {
            $ok = $assignments->contains(function ($a) use ($classId, $subjectId) {
                return (int) $a->school_class_id === (int) $classId
                    && ((bool) $a->is_class_teacher || (int) $a->subject_id === $subjectId);
            });
            abort_unless($ok, 403, 'You are not assigned to the selected subject in every selected class.');
            if ($assignments->where('school_class_id', $classId)->contains('is_class_teacher', true)) {
                abort_unless(DB::table('class_subject')->where('school_class_id', $classId)->where('subject_id', $subjectId)->exists(), 422, 'The selected subject is not attached to one of your class-teacher classes.');
            }
        }
    }

    private function ensureOwnedIds(array $ids, string $table): void
    {
        $count = DB::table($table)->whereIn('id', $ids)->where('school_id', $this->user()->school_id)->count();
        abort_if($count !== count(array_unique($ids)), 422, "One or more selected {$table} do not belong to this school.");
    }

    public function index()
    {
        $this->ensureManager();
        $user = $this->user();
        $query = CbtExam::with(['subject', 'term.academicSession', 'schoolClasses', 'questions.options'])
            ->withCount('attempts')->where('school_id', $user->school_id)->latest();
        if ($user->hasRole('teacher')) {
            $assignments = TeacherAssignment::where('school_id', $user->school_id)->where('user_id', $user->id)->get();
            $classIds = $assignments->pluck('school_class_id')->unique();
            $subjectIds = $assignments->whereNotNull('subject_id')->pluck('subject_id')->unique();
            $classTeacherIds = $assignments->where('is_class_teacher', true)->pluck('school_class_id')->unique();
            $query->whereHas('schoolClasses', fn ($c) => $c->whereIn('school_classes.id', $classIds))
                ->where(function ($q) use ($subjectIds, $classTeacherIds) {
                    $q->whereIn('subject_id', $subjectIds)
                      ->orWhereHas('schoolClasses', fn ($c) => $c->whereIn('school_classes.id', $classTeacherIds));
                });
        }
        return $query->get();
    }

    public function store(Request $request)
    {
        $this->ensureManager();
        $data = $request->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'], 'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'], 'instructions' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:480'], 'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'], 'pass_mark' => ['required', 'numeric', 'min:0', 'max:100'],
            'randomize_questions' => ['boolean'], 'randomize_options' => ['boolean'],
            'school_class_ids' => ['required', 'array', 'min:1'], 'school_class_ids.*' => ['integer', 'exists:school_classes,id'],
        ]);
        $this->ensureOwnedIds([$data['term_id']], 'terms');
        $this->ensureOwnedIds([$data['subject_id']], 'subjects');
        $this->ensureOwnedIds($data['school_class_ids'], 'school_classes');
        $this->ensureCreateScope((int) $data['subject_id'], array_map('intval', $data['school_class_ids']));

        $exam = DB::transaction(function () use ($data) {
            $exam = CbtExam::create([
                'school_id' => $this->user()->school_id, 'term_id' => $data['term_id'], 'subject_id' => $data['subject_id'],
                'title' => $data['title'], 'instructions' => $data['instructions'] ?? null, 'duration_minutes' => $data['duration_minutes'],
                'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'], 'pass_mark' => $data['pass_mark'],
                'randomize_questions' => $data['randomize_questions'] ?? false, 'randomize_options' => $data['randomize_options'] ?? false,
                'published' => false,
            ]);
            $exam->schoolClasses()->sync($data['school_class_ids']);
            return $exam;
        });
        return response()->json($exam->load(['subject', 'term.academicSession', 'schoolClasses', 'questions.options']), 201);
    }

    public function show(CbtExam $cbtExam)
    {
        $this->ensureManager(); $this->ensureTeacherCanUseExam($cbtExam);
        return $cbtExam->load(['subject', 'term.academicSession', 'schoolClasses', 'questions.options']);
    }

    public function update(Request $request, CbtExam $cbtExam)
    {
        $this->ensureManager(); $this->ensureTeacherCanUseExam($cbtExam);

        $hasAttempts = $cbtExam->attempts()->exists();
        $hasInProgressAttempts = $cbtExam->attempts()->where('status', 'in_progress')->exists();
        $data = $request->validate([
            'term_id' => ['sometimes', 'integer', 'exists:terms,id'], 'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
            'title' => ['sometimes', 'string', 'max:255'], 'instructions' => ['nullable', 'string'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:480'], 'starts_at' => ['sometimes', 'date'], 'ends_at' => ['sometimes', 'date'],
            'pass_mark' => ['sometimes', 'numeric', 'min:0', 'max:100'], 'randomize_questions' => ['sometimes', 'boolean'],
            'randomize_options' => ['sometimes', 'boolean'], 'school_class_ids' => ['sometimes', 'array', 'min:1'],
            'school_class_ids.*' => ['integer', 'exists:school_classes,id'],
        ]);

        if ($hasAttempts) {
            $allowedAfterAttempt = ['title', 'instructions', 'starts_at', 'ends_at'];
            $forbidden = array_diff(array_keys($data), $allowedAfterAttempt);
            abort_if($forbidden, 422, 'This exam already has student attempts. Only the title, instructions and schedule can be changed so saved marks remain consistent.');
            if ($hasInProgressAttempts && (array_key_exists('starts_at', $data) || array_key_exists('ends_at', $data))) {
                abort(422, 'This exam currently has a student taking it. Finish or wait for the active attempt before changing the examination schedule.');
            }
        } else {
            abort_if($cbtExam->published && now()->gte($cbtExam->starts_at), 422, 'A running or started exam cannot be edited.');
        }

        $start = Carbon::parse($data['starts_at'] ?? $cbtExam->starts_at);
        $end = Carbon::parse($data['ends_at'] ?? $cbtExam->ends_at);
        abort_if($end->lte($start), 422, 'The examination end time must be after the start time.');

        if (!$hasAttempts) {
            $subjectId = (int) ($data['subject_id'] ?? $cbtExam->subject_id);
            $classIds = array_map('intval', $data['school_class_ids'] ?? $cbtExam->schoolClasses()->pluck('school_classes.id')->all());
            $this->ensureOwnedIds([$subjectId], 'subjects');
            $this->ensureOwnedIds($classIds, 'school_classes');
            $this->ensureCreateScope($subjectId, $classIds);
            $cbtExam->update($data);
            if (isset($data['school_class_ids'])) $cbtExam->schoolClasses()->sync($data['school_class_ids']);
        } else {
            $cbtExam->update(array_intersect_key($data, array_flip(['title', 'instructions', 'starts_at', 'ends_at'])));
        }

        return $cbtExam->fresh()->load(['subject', 'term.academicSession', 'schoolClasses', 'questions.options']);
    }

    public function destroy(CbtExam $cbtExam)
    {
        $this->ensureManager(); $this->ensureTeacherCanUseExam($cbtExam);
        $cbtExam->delete();
        return response()->json(['message' => 'CBT exam removed from active examinations. Student attempts and saved marks have been preserved.']);
    }

    public function publish(CbtExam $cbtExam)
    {
        $this->ensureManager(); $this->ensureTeacherCanUseExam($cbtExam);
        if (!$cbtExam->published) {
            abort_if($cbtExam->questions()->count() === 0, 422, 'Add at least one question before publishing.');
            abort_if($cbtExam->questions()->withCount('options')->get()->contains(fn ($q) => $q->options_count < 2), 422, 'Every question must have at least two options.');
            abort_if($cbtExam->questions()->whereHas('options', fn ($q) => $q->where('is_correct', true))->count() !== $cbtExam->questions()->count(), 422, 'Every question must have one correct answer.');
        }
        $cbtExam->update(['published' => !$cbtExam->published]);
        return response()->json(['published' => $cbtExam->published]);
    }

    public function addQuestion(Request $request, CbtExam $cbtExam)
    {
        $this->ensureManager(); $this->ensureTeacherCanUseExam($cbtExam);
        abort_if($cbtExam->published && now()->gte($cbtExam->starts_at), 422, 'Questions cannot be changed after the exam starts.');

        if ($request->filled('bank_question_id')) {
            $bank = CbtQuestionBank::with('options')->findOrFail($request->integer('bank_question_id'));
            abort_unless((int) $bank->school_id === (int) $this->user()->school_id, 404);
            abort_unless((int) $bank->subject_id === (int) $cbtExam->subject_id, 422, 'The question bank question must belong to the exam subject.');
            $question = DB::transaction(fn () => $this->cloneBankQuestion($cbtExam, $bank));
            return response()->json($question->load('options'), 201);
        }

        $data = $request->validate([
            'question_text' => ['required', 'string'], 'topic' => ['nullable', 'string', 'max:255'], 'marks' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'options' => ['required', 'array', 'min:2', 'max:10'], 'options.*.option_text' => ['required', 'string'], 'options.*.is_correct' => ['required'],
        ]);
        abort_if(collect($data['options'])->filter(fn ($o) => filter_var($o['is_correct'], FILTER_VALIDATE_BOOLEAN))->count() !== 1, 422, 'Select exactly one correct option.');
        $question = DB::transaction(function () use ($cbtExam, $data) {
            $question = $cbtExam->questions()->create(['question_text' => $data['question_text'], 'topic' => $data['topic'] ?? null, 'marks' => $data['marks'], 'position' => $cbtExam->questions()->max('position') + 1]);
            foreach (array_values($data['options']) as $i => $option) $question->options()->create(['option_text' => $option['option_text'], 'is_correct' => filter_var($option['is_correct'], FILTER_VALIDATE_BOOLEAN), 'position' => $i + 1]);
            return $question;
        });
        return response()->json($question->load('options'), 201);
    }

    private function cloneBankQuestion(CbtExam $exam, CbtQuestionBank $bank): CbtQuestion
    {
        $question = $exam->questions()->create(['question_text' => $bank->question_text, 'topic' => $bank->topic, 'marks' => $bank->marks, 'position' => $exam->questions()->max('position') + 1]);
        foreach ($bank->options as $option) $question->options()->create(['option_text' => $option->option_text, 'is_correct' => $option->is_correct, 'position' => $option->position]);
        return $question;
    }

    public function updateQuestion(Request $request, CbtQuestion $cbtQuestion)
    {
        $this->ensureManager(); $exam = $cbtQuestion->exam; abort_unless($exam, 404); $this->ensureTeacherCanUseExam($exam);
        abort_if($exam->published && now()->gte($exam->starts_at), 422, 'Questions cannot be changed after the exam starts.');
        $data = $request->validate(['question_text' => ['required', 'string'], 'topic' => ['nullable', 'string', 'max:255'], 'marks' => ['required', 'numeric', 'min:0.01', 'max:1000'], 'options' => ['required', 'array', 'min:2', 'max:10'], 'options.*.option_text' => ['required', 'string'], 'options.*.is_correct' => ['required']]);
        abort_if(collect($data['options'])->filter(fn ($o) => filter_var($o['is_correct'], FILTER_VALIDATE_BOOLEAN))->count() !== 1, 422, 'Select exactly one correct option.');
        DB::transaction(function () use ($cbtQuestion, $data) {
            $cbtQuestion->update(['question_text' => $data['question_text'], 'topic' => $data['topic'] ?? null, 'marks' => $data['marks']]);
            $cbtQuestion->options()->delete();
            foreach (array_values($data['options']) as $i => $option) $cbtQuestion->options()->create(['option_text' => $option['option_text'], 'is_correct' => filter_var($option['is_correct'], FILTER_VALIDATE_BOOLEAN), 'position' => $i + 1]);
        });
        return $cbtQuestion->fresh()->load('options');
    }

    public function deleteQuestion(CbtQuestion $cbtQuestion)
    {
        $this->ensureManager(); $exam = $cbtQuestion->exam; abort_unless($exam, 404); $this->ensureTeacherCanUseExam($exam);
        abort_if($exam->published && now()->gte($exam->starts_at), 422, 'Questions cannot be changed after the exam starts.');
        $cbtQuestion->delete(); return response()->json(['message' => 'Question deleted.']);
    }

    public function results(CbtExam $cbtExam)
    {
        $this->ensureManager(); $this->ensureTeacherCanUseExam($cbtExam);
        $query = $cbtExam->attempts()->with(['student.schoolClass'])->where('school_id', $this->user()->school_id)->where('status', 'submitted');
        if ($this->user()->hasRole('teacher')) {
            $assignments = TeacherAssignment::where('school_id', $this->user()->school_id)->where('user_id', $this->user()->id)->get();
            $classIds = $assignments->filter(fn ($a) => $a->is_class_teacher || (int) $a->subject_id === (int) $cbtExam->subject_id)->pluck('school_class_id')->unique();
            $query->whereHas('student', fn ($s) => $s->whereIn('school_class_id', $classIds));
        }
        $rows = $query->orderByDesc('percentage')->get();
        $scores = $rows->pluck('percentage')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
        return response()->json([
            'exam' => $cbtExam->load(['subject', 'schoolClasses']),
            'summary' => ['students' => $rows->count(), 'average_percentage' => $scores->count() ? round($scores->avg(), 2) : 0, 'highest_percentage' => $scores->count() ? $scores->max() : 0, 'passed' => $rows->where('passed', true)->count(), 'failed' => $rows->where('passed', false)->count()],
            'results' => $rows->map(fn ($a) => ['id' => $a->id, 'student' => ['id' => $a->student->id, 'name' => $a->student->full_name, 'admission_number' => $a->student->admission_number, 'class' => $a->student->schoolClass?->full_name], 'score' => $a->score, 'percentage' => $a->percentage, 'passed' => $a->passed, 'attempted_count' => $a->attempted_count, 'unanswered_count' => $a->unanswered_count, 'time_used_seconds' => $a->time_used_seconds, 'submitted_at' => $a->submitted_at])->values(),
        ]);
    }
}
