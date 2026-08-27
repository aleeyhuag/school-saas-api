<?php

namespace App\Http\Controllers\Api\Cbt;

use App\Http\Controllers\Controller;
use App\Models\CbtAnswer;
use App\Models\CbtAttempt;
use App\Models\CbtExam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CbtStudentController extends Controller
{
    private function student()
    {
        $student = Auth::user()->student;
        abort_unless($student && $student->school_id === Auth::user()->school_id, 403, 'Student profile not available.');
        return $student;
    }

    private function finish(CbtAttempt $attempt): CbtAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $locked = CbtAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($locked->status === 'submitted') {
                return $locked->fresh();
            }

            // Calculate directly from the persisted answer/question/option IDs.
            // This avoids depending on nested Eloquent relationship hydration
            // during the most important CBT operation (final submission).
            $questions = $locked->exam->questions()->get(['id', 'marks']);
            $questionIds = $questions->pluck('id');
            $answers = CbtAnswer::query()
                ->where('cbt_attempt_id', $locked->id)
                ->whereIn('cbt_question_id', $questionIds)
                ->get(['cbt_question_id', 'cbt_question_option_id'])
                ->keyBy('cbt_question_id');

            $correctOptionIds = DB::table('cbt_question_options')
                ->whereIn('cbt_question_id', $questionIds)
                ->where('is_correct', true)
                ->pluck('id')
                ->flip();

            $score = 0.0;
            $attempted = 0;
            foreach ($questions as $question) {
                $answer = $answers->get($question->id);
                if ($answer && $answer->cbt_question_option_id !== null) {
                    $attempted++;
                    if ($correctOptionIds->has((int) $answer->cbt_question_option_id)) {
                        $score += (float) $question->marks;
                    }
                }
            }

            $total = (float) $questions->sum('marks');
            $percentage = $total > 0 ? round(($score / $total) * 100, 2) : 0;
            $submittedAt = now();
            $started = $locked->started_at;
            $durationSeconds = (int) $locked->exam->duration_minutes * 60;
            $timeUsed = $started ? min($started->diffInSeconds($submittedAt), $durationSeconds) : 0;

            $locked->update([
                'status' => 'submitted',
                'submitted_at' => $submittedAt,
                'score' => $score,
                'percentage' => $percentage,
                'passed' => $percentage >= (float) $locked->exam->pass_mark,
                'attempted_count' => $attempted,
                'unanswered_count' => max(0, $questions->count() - $attempted),
                'time_used_seconds' => $timeUsed,
            ]);

            return $locked->fresh();
        });
    }

    private function publicQuestions(CbtAttempt $attempt): array
    {
        $exam = $attempt->exam->load(['questions.options']);
        $questions = $exam->questions->map(function ($q) use ($exam, $attempt) {
            $options = $q->options->map(fn ($o) => ['id' => $o->id, 'option_text' => $o->option_text, 'position' => $o->position])->values();
            if ($exam->randomize_options) $options = $options->shuffle()->values();
            $answer = $attempt->answers->firstWhere('cbt_question_id', $q->id);
            return ['id' => $q->id, 'question_text' => $q->question_text, 'topic' => $q->topic, 'marks' => $q->marks, 'options' => $options, 'selected_option_id' => $answer?->cbt_question_option_id];
        });
        if ($exam->randomize_questions) $questions = $questions->shuffle()->values();
        return $questions->all();
    }

    public function available()
    {
        $student = $this->student();
        $now = now();
        return CbtExam::with(['subject', 'term.academicSession'])
            ->where('school_id', $student->school_id)
            ->where('published', true)
            ->whereHas('schoolClasses', fn ($q) => $q->where('school_classes.id', $student->school_class_id))
            ->where('ends_at', '>=', $now)
            ->orderBy('starts_at')
            ->get()->map(function ($exam) use ($student, $now) {
                $attempt = CbtAttempt::where('cbt_exam_id', $exam->id)->where('student_id', $student->id)->first();
                $status = $now->lt($exam->starts_at) ? 'upcoming' : ($now->gte($exam->ends_at) ? 'ended' : 'available');
                return [
                    'id' => $exam->id, 'title' => $exam->title, 'subject' => $exam->subject, 'term' => $exam->term,
                    'instructions' => $exam->instructions, 'duration_minutes' => $exam->duration_minutes,
                    'starts_at' => $exam->starts_at, 'ends_at' => $exam->ends_at, 'pass_mark' => $exam->pass_mark,
                    'question_count' => $exam->questions()->count(), 'availability_status' => $status,
                    'attempt_status' => $attempt?->status, 'attempt_id' => $attempt?->id,
                ];
            });
    }

    public function start(CbtExam $cbtExam)
    {
        $student = $this->student();
        abort_unless((int) $cbtExam->school_id === (int) $student->school_id, 404);
        abort_unless($cbtExam->published, 404);
        abort_unless($cbtExam->schoolClasses()->whereKey($student->school_class_id)->exists(), 403);
        abort_if(now()->lt($cbtExam->starts_at), 422, 'This exam has not started yet.');
        abort_if(now()->gte($cbtExam->ends_at), 422, 'This exam has ended.');
        abort_if($cbtExam->questions()->count() === 0, 422, 'This exam has no questions.');

        $existing = CbtAttempt::where('cbt_exam_id', $cbtExam->id)->where('student_id', $student->id)->first();
        if ($existing) {
            if ($existing->status === 'submitted') return $this->attemptResponse($existing);
            if (now()->gte($existing->expires_at)) return $this->attemptResponse($this->finish($existing));
            return $this->attemptResponse($existing);
        }

        $started = now();
        $expires = $started->copy()->addMinutes($cbtExam->duration_minutes);
        if ($expires->gt($cbtExam->ends_at)) $expires = $cbtExam->ends_at->copy();
        $attempt = CbtAttempt::create(['school_id' => $student->school_id, 'cbt_exam_id' => $cbtExam->id, 'student_id' => $student->id, 'started_at' => $started, 'expires_at' => $expires]);
        return $this->attemptResponse($attempt);
    }

    public function show(CbtAttempt $cbtAttempt)
    {
        $student = $this->student();
        abort_unless((int) $cbtAttempt->school_id === (int) $student->school_id, 403);
        abort_unless($cbtAttempt->student_id === $student->id, 403);
        if ($cbtAttempt->status === 'in_progress' && now()->gte($cbtAttempt->expires_at)) $cbtAttempt = $this->finish($cbtAttempt);
        return $this->attemptResponse($cbtAttempt);
    }

    public function saveAnswer(Request $request, CbtAttempt $cbtAttempt)
    {
        $student = $this->student();
        abort_unless((int) $cbtAttempt->school_id === (int) $student->school_id, 403);
        abort_unless($cbtAttempt->student_id === $student->id, 403);
        abort_if($cbtAttempt->status !== 'in_progress', 422, 'This attempt is already submitted.');
        if (now()->gte($cbtAttempt->expires_at)) return response()->json(['code' => 'cbt_time_expired', 'message' => 'Time has expired. The exam will now be submitted. Please wait for the result.', 'expired' => true], 422);
        $data = $request->validate(['question_id' => ['required', 'integer'], 'option_id' => ['nullable', 'integer']]);
        $question = $cbtAttempt->exam->questions()->whereKey($data['question_id'])->first();
        abort_unless($question, 404, 'That question is no longer available in this examination.');
        if ($data['option_id'] !== null) {
            $optionBelongsToQuestion = $question->options()->whereKey($data['option_id'])->exists();
            abort_unless($optionBelongsToQuestion, 422, 'That answer option does not belong to this question. Please select an option from the current question.');
        }
        CbtAnswer::updateOrCreate(['cbt_attempt_id' => $cbtAttempt->id, 'cbt_question_id' => $question->id], ['cbt_question_option_id' => $data['option_id']]);
        return ['saved' => true];
    }

    public function submit(CbtAttempt $cbtAttempt)
    {
        $student = $this->student();
        abort_unless((int) $cbtAttempt->school_id === (int) $student->school_id, 403);
        abort_unless((int) $cbtAttempt->student_id === (int) $student->id, 403);

        try {
            $finished = $cbtAttempt->status === 'submitted'
                ? $cbtAttempt->fresh()
                : $this->finish($cbtAttempt);

            return $this->resultResponse($finished);
        } catch (Throwable $e) {
            Log::error('CBT submission failed', [
                'attempt_id' => $cbtAttempt->id,
                'exam_id' => $cbtAttempt->cbt_exam_id,
                'student_id' => $student->id,
                'school_id' => $student->school_id,
                'exception' => $e,
            ]);

            // The transaction may have committed before response preparation
            // failed. Never ask a student to submit again if marks are stored.
            $saved = CbtAttempt::query()->find($cbtAttempt->id);
            if ($saved?->status === 'submitted') {
                try {
                    return $this->resultResponse($saved);
                } catch (Throwable $responseError) {
                    Log::error('CBT result response failed after saved submission', [
                        'attempt_id' => $cbtAttempt->id,
                        'exception' => $responseError,
                    ]);
                }
            }

            return response()->json([
                'code' => 'cbt_submission_failed',
                'message' => 'We could not complete your CBT submission right now. Your answers are still saved. Please try again. If the problem continues, contact your teacher.',
            ], 500);
        }
    }

    public function results()
    {
        $student = $this->student();
        return CbtAttempt::with(['exam.subject', 'exam.term'])
            ->where('student_id', $student->id)->where('status', 'submitted')->latest('submitted_at')->get();
    }

    private function resultResponse(CbtAttempt $attempt)
    {
        $exam = CbtExam::query()
            ->with(['subject', 'term.academicSession'])
            ->withTrashed()
            ->findOrFail($attempt->cbt_exam_id);

        return response()->json([
            'id' => $attempt->id,
            'status' => $attempt->status,
            'started_at' => $attempt->started_at,
            'expires_at' => $attempt->expires_at,
            'submitted_at' => $attempt->submitted_at,
            'score' => $attempt->score,
            'percentage' => $attempt->percentage,
            'passed' => $attempt->passed,
            'attempted_count' => $attempt->attempted_count,
            'unanswered_count' => $attempt->unanswered_count,
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'instructions' => $exam->instructions,
                'duration_minutes' => $exam->duration_minutes,
                'pass_mark' => $exam->pass_mark,
                'subject' => $exam->subject,
                'term' => $exam->term,
            ],
            'questions' => [],
        ]);
    }

    private function attemptResponse(CbtAttempt $attempt)
    {
        $attempt->load(['exam.subject', 'exam.term.academicSession']);
        return response()->json([
            'id' => $attempt->id, 'status' => $attempt->status, 'started_at' => $attempt->started_at,
            'expires_at' => $attempt->expires_at, 'submitted_at' => $attempt->submitted_at,
            'score' => $attempt->score, 'percentage' => $attempt->percentage, 'passed' => $attempt->passed,
            'attempted_count' => $attempt->attempted_count, 'unanswered_count' => $attempt->unanswered_count,
            'exam' => ['id' => $attempt->exam->id, 'title' => $attempt->exam->title, 'instructions' => $attempt->exam->instructions, 'duration_minutes' => $attempt->exam->duration_minutes, 'pass_mark' => $attempt->exam->pass_mark, 'subject' => $attempt->exam->subject, 'term' => $attempt->exam->term],
            'questions' => $attempt->status === 'in_progress' ? $this->publicQuestions($attempt) : [],
        ]);
    }
}
