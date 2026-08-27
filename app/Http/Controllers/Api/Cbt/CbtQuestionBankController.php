<?php

namespace App\Http\Controllers\Api\Cbt;

use App\Http\Controllers\Controller;
use App\Models\CbtQuestionBank;
use App\Models\TeacherAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CbtQuestionBankController extends Controller
{
    private function user()
    {
        return Auth::user();
    }

    private function ensureManager(): void
    {
        abort_unless($this->user()->hasAnyRole(['exam_officer', 'teacher']), 403);
    }

    private function canUseSubject(int $subjectId): bool
    {
        $user = $this->user();
        if ($user->hasRole('exam_officer')) return true;

        return TeacherAssignment::where('school_id', $user->school_id)
            ->where('user_id', $user->id)
            ->where(function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId)
                  ->orWhere(function ($q2) use ($subjectId) {
                      $q2->where('is_class_teacher', true)
                          ->whereHas('schoolClass.subjects', fn ($s) => $s->where('subjects.id', $subjectId));
                  });
            })->exists();
    }

    private function validateOptions(array $options): void
    {
        if (count($options) < 2 || count($options) > 10) {
            throw ValidationException::withMessages(['options' => ['A question must have between 2 and 10 options.']]);
        }
        if (collect($options)->filter(fn ($o) => filter_var($o['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))->count() !== 1) {
            throw ValidationException::withMessages(['options' => ['Select exactly one correct option.']]);
        }
    }

    public function index(Request $request)
    {
        $this->ensureManager();
        $user = $this->user();
        $query = CbtQuestionBank::with(['subject', 'options'])
            ->where('school_id', $user->school_id)
            ->latest();

        if ($request->filled('subject_id')) {
            $subjectId = (int) $request->input('subject_id');
            abort_unless($this->canUseSubject($subjectId), 403);
            $query->where('subject_id', $subjectId);
        } elseif ($user->hasRole('teacher')) {
            $subjectIds = TeacherAssignment::where('school_id', $user->school_id)
                ->where('user_id', $user->id)
                ->where(function ($q) {
                    $q->whereNotNull('subject_id')
                      ->orWhere('is_class_teacher', true);
                })->pluck('subject_id')->filter()->unique();
            $classIds = TeacherAssignment::where('school_id', $user->school_id)
                ->where('user_id', $user->id)->where('is_class_teacher', true)->pluck('school_class_id');
            $classSubjectIds = DB::table('class_subject')->whereIn('school_class_id', $classIds)->pluck('subject_id');
            $query->whereIn('subject_id', $subjectIds->merge($classSubjectIds)->unique());
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $this->ensureManager();
        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'question_text' => ['required', 'string'],
            'topic' => ['nullable', 'string', 'max:255'],
            'marks' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'options' => ['required', 'array'],
            'options.*.option_text' => ['required', 'string'],
            'options.*.is_correct' => ['required'],
        ]);
        $this->validateOptions($data['options']);
        abort_unless(DB::table('subjects')->where('id', $data['subject_id'])->where('school_id', $this->user()->school_id)->exists(), 422, 'Subject does not belong to this school.');
        abort_unless($this->canUseSubject((int) $data['subject_id']), 403, 'You are not assigned to this subject/class.');

        $question = DB::transaction(function () use ($data) {
            $question = CbtQuestionBank::create([
                'school_id' => $this->user()->school_id,
                'subject_id' => $data['subject_id'],
                'created_by_user_id' => $this->user()->id,
                'question_text' => $data['question_text'],
                'topic' => $data['topic'] ?? null,
                'marks' => $data['marks'],
            ]);
            foreach (array_values($data['options']) as $i => $option) {
                $question->options()->create([
                    'option_text' => $option['option_text'],
                    'is_correct' => filter_var($option['is_correct'], FILTER_VALIDATE_BOOLEAN),
                    'position' => $i + 1,
                ]);
            }
            return $question;
        });

        return response()->json($question->load(['subject', 'options']), 201);
    }

    public function update(Request $request, CbtQuestionBank $cbtQuestionBank)
    {
        $this->ensureManager();
        abort_unless((int) $cbtQuestionBank->school_id === (int) $this->user()->school_id, 404);
        abort_unless($this->canUseSubject((int) $cbtQuestionBank->subject_id), 403);
        $data = $request->validate([
            'question_text' => ['required', 'string'],
            'topic' => ['nullable', 'string', 'max:255'],
            'marks' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'options' => ['required', 'array'],
            'options.*.option_text' => ['required', 'string'],
            'options.*.is_correct' => ['required'],
        ]);
        $this->validateOptions($data['options']);

        DB::transaction(function () use ($cbtQuestionBank, $data) {
            $cbtQuestionBank->update([
                'question_text' => $data['question_text'],
                'topic' => $data['topic'] ?? null,
                'marks' => $data['marks'],
            ]);
            $cbtQuestionBank->options()->delete();
            foreach (array_values($data['options']) as $i => $option) {
                $cbtQuestionBank->options()->create([
                    'option_text' => $option['option_text'],
                    'is_correct' => filter_var($option['is_correct'], FILTER_VALIDATE_BOOLEAN),
                    'position' => $i + 1,
                ]);
            }
        });

        return $cbtQuestionBank->fresh()->load(['subject', 'options']);
    }

    public function destroy(CbtQuestionBank $cbtQuestionBank)
    {
        $this->ensureManager();
        abort_unless((int) $cbtQuestionBank->school_id === (int) $this->user()->school_id, 404);
        abort_unless($this->canUseSubject((int) $cbtQuestionBank->subject_id), 403);
        $cbtQuestionBank->delete();
        return response()->json(['message' => 'Question removed from the question bank.']);
    }

    public function import(Request $request)
    {
        $this->ensureManager();
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $handle = fopen($data['file']->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $expected = ['subject_id', 'question_text', 'topic', 'marks', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option'];
        if (!$header || array_map('strtolower', array_map('trim', $header)) !== $expected) {
            if (is_resource($handle)) fclose($handle);
            throw ValidationException::withMessages(['file' => ['Invalid CSV header. Use the template columns: subject_id, question_text, topic, marks, option_a, option_b, option_c, option_d, correct_option.']]);
        }

        $created = 0; $rowNumber = 1;
        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if (count($row) < count($expected)) continue;
                [$subjectId, $text, $topic, $marks, $a, $b, $c, $d, $correct] = array_map('trim', array_slice($row, 0, 9));
                $options = [$a, $b, $c, $d];
                if (!$subjectId || !$text || !$marks || count(array_filter($options, fn ($v) => $v !== '')) < 2) {
                    throw ValidationException::withMessages(['file' => ["Invalid data on CSV row {$rowNumber}."]]);
                }
                abort_unless(DB::table('subjects')->where('id', $subjectId)->where('school_id', $this->user()->school_id)->exists(), 422, "Subject on CSV row {$rowNumber} does not belong to this school.");
                abort_unless($this->canUseSubject((int) $subjectId), 403, "You are not assigned to the subject on CSV row {$rowNumber}.");
                $correctIndex = match (strtoupper($correct)) {
                    'A', '1' => 0, 'B', '2' => 1, 'C', '3' => 2, 'D', '4' => 3,
                    default => -1,
                };
                $optionRows = [];
                foreach ($options as $i => $value) {
                    if ($value !== '') $optionRows[] = ['option_text' => $value, 'is_correct' => $i === $correctIndex, 'position' => $i + 1];
                }
                if ($correctIndex < 0 || !collect($optionRows)->contains('is_correct', true)) {
                    throw ValidationException::withMessages(['file' => ["Invalid correct_option on CSV row {$rowNumber}. Use A, B, C or D."]]);
                }
                $q = CbtQuestionBank::create([
                    'school_id' => $this->user()->school_id, 'subject_id' => $subjectId,
                    'created_by_user_id' => $this->user()->id, 'question_text' => $text,
                    'topic' => $topic ?: null, 'marks' => $marks,
                ]);
                $q->options()->createMany($optionRows);
                $created++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if (is_resource($handle)) fclose($handle);
            throw $e;
        }
        fclose($handle);
        return response()->json(['message' => "Imported {$created} question(s) into the question bank.", 'created' => $created]);
    }
}
