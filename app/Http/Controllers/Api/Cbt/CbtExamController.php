<?php

namespace App\Http\Controllers\Api\Cbt;

use App\Http\Controllers\Controller;
use App\Models\CbtExam;
use App\Models\CbtQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CbtExamController extends Controller
{
    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['proprietor', 'principal', 'exam_officer']), 403);
    }

    private function ensureOwnedIds(array $ids, string $table): void
    {
        $count = DB::table($table)
            ->whereIn('id', $ids)
            ->where('school_id', Auth::user()->school_id)
            ->count();
        abort_if($count !== count(array_unique($ids)), 422, "One or more selected {$table} do not belong to this school.");
    }

    public function index()
    {
        $this->ensureAdmin();
        return CbtExam::with(['subject', 'term.academicSession', 'schoolClasses', 'questions.options'])
            ->withCount('attempts')->latest()->get();
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();
        $data = $request->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'pass_mark' => ['required', 'numeric', 'min:0', 'max:100'],
            'randomize_questions' => ['boolean'],
            'randomize_options' => ['boolean'],
            'school_class_ids' => ['required', 'array', 'min:1'],
            'school_class_ids.*' => ['integer', 'exists:school_classes,id'],
        ]);

        $this->ensureOwnedIds([$data['term_id'],], 'terms');
        $this->ensureOwnedIds([$data['subject_id']], 'subjects');
        $this->ensureOwnedIds($data['school_class_ids'], 'school_classes');

        $exam = DB::transaction(function () use ($data) {
            $exam = CbtExam::create([
                'term_id' => $data['term_id'], 'subject_id' => $data['subject_id'],
                'title' => $data['title'], 'instructions' => $data['instructions'] ?? null,
                'duration_minutes' => $data['duration_minutes'], 'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'], 'pass_mark' => $data['pass_mark'],
                'randomize_questions' => $data['randomize_questions'] ?? false,
                'randomize_options' => $data['randomize_options'] ?? false,
                'published' => false,
            ]);
            $exam->schoolClasses()->sync($data['school_class_ids']);
            return $exam;
        });

        return response()->json($exam->load(['subject', 'term.academicSession', 'schoolClasses', 'questions.options']), 201);
    }

    public function show(CbtExam $cbtExam)
    {
        $this->ensureAdmin();
        return $cbtExam->load(['subject', 'term.academicSession', 'schoolClasses', 'questions.options']);
    }

    public function update(Request $request, CbtExam $cbtExam)
    {
        $this->ensureAdmin();
        abort_if($cbtExam->published && now()->gte($cbtExam->starts_at), 422, 'A running or started exam cannot be edited.');
        $data = $request->validate([
            'term_id' => ['sometimes', 'integer', 'exists:terms,id'],
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
            'title' => ['sometimes', 'string', 'max:255'], 'instructions' => ['nullable', 'string'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:480'],
            'starts_at' => ['sometimes', 'date'], 'ends_at' => ['sometimes', 'date'],
            'pass_mark' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'randomize_questions' => ['sometimes', 'boolean'], 'randomize_options' => ['sometimes', 'boolean'],
            'school_class_ids' => ['sometimes', 'array', 'min:1'], 'school_class_ids.*' => ['integer', 'exists:school_classes,id'],
        ]);
        foreach (['term_id' => 'terms', 'subject_id' => 'subjects'] as $field => $table) {
            if (isset($data[$field])) $this->ensureOwnedIds([$data[$field]], $table);
        }
        if (isset($data['school_class_ids'])) $this->ensureOwnedIds($data['school_class_ids'], 'school_classes');
        $cbtExam->update($data);
        if (isset($data['school_class_ids'])) $cbtExam->schoolClasses()->sync($data['school_class_ids']);
        return $cbtExam->fresh()->load(['subject', 'term.academicSession', 'schoolClasses', 'questions.options']);
    }

    public function destroy(CbtExam $cbtExam)
    {
        $this->ensureAdmin();
        abort_if($cbtExam->attempts()->exists(), 422, 'An exam with student attempts cannot be deleted. Unpublish it instead.');
        $cbtExam->delete();
        return response()->json(['message' => 'CBT exam deleted.']);
    }

    public function publish(CbtExam $cbtExam)
    {
        $this->ensureAdmin();
        abort_if($cbtExam->questions()->withCount('options')->get()->contains(fn ($q) => $q->options_count < 2), 422, 'Every question must have at least two options.');
        abort_if($cbtExam->questions()->whereHas('options', fn ($q) => $q->where('is_correct', true))->count() !== $cbtExam->questions()->count(), 422, 'Every question must have one correct answer.');
        $cbtExam->update(['published' => !$cbtExam->published]);
        return response()->json(['published' => $cbtExam->published]);
    }

    public function addQuestion(Request $request, CbtExam $cbtExam)
    {
        $this->ensureAdmin();
        abort_if($cbtExam->published && now()->gte($cbtExam->starts_at), 422, 'Questions cannot be changed after the exam starts.');
        $data = $request->validate([
            'question_text' => ['required', 'string'], 'topic' => ['nullable', 'string', 'max:255'],
            'marks' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'options' => ['required', 'array', 'min:2', 'max:10'],
            'options.*.option_text' => ['required', 'string'], 'options.*.is_correct' => ['required', 'boolean'],
        ]);
        abort_if(collect($data['options'])->where('is_correct', true)->count() !== 1, 422, 'Select exactly one correct option.');
        $question = DB::transaction(function () use ($cbtExam, $data) {
            $question = $cbtExam->questions()->create([
                'question_text' => $data['question_text'], 'topic' => $data['topic'] ?? null,
                'marks' => $data['marks'], 'position' => $cbtExam->questions()->max('position') + 1,
            ]);
            foreach ($data['options'] as $i => $option) {
                $question->options()->create([
                    'option_text' => $option['option_text'], 'is_correct' => $option['is_correct'], 'position' => $i + 1,
                ]);
            }
            return $question;
        });
        return response()->json($question->load('options'), 201);
    }

    public function deleteQuestion(CbtQuestion $cbtQuestion)
    {
        $this->ensureAdmin();
        abort_unless($cbtQuestion->exam && $cbtQuestion->exam->school_id === Auth::user()->school_id, 404);
        abort_if($cbtQuestion->exam->published && now()->gte($cbtQuestion->exam->starts_at), 422, 'Questions cannot be changed after the exam starts.');
        $cbtQuestion->delete();
        return response()->json(['message' => 'Question deleted.']);
    }
}
