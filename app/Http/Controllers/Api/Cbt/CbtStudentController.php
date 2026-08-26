<?php

namespace App\Http\Controllers\Api\Cbt;

use App\Http\Controllers\Controller;
use App\Models\CbtAnswer;
use App\Models\CbtAttempt;
use App\Models\CbtExam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        $attempt->load(['exam.questions.options', 'answers']);
        $score = 0; $attempted = 0;
        $questionCount = $attempt->exam->questions->count();
        foreach ($attempt->answers as $answer) {
            if ($answer->cbt_question_option_id) $attempted++;
            $option = $answer->option;
            if ($option?->is_correct) $score += (float) $answer->question->marks;
        }
        $total = (float) $attempt->exam->questions->sum('marks');
        $percentage = $total > 0 ? round(($score / $total) * 100, 2) : 0;
        $submittedAt = now();
        $started = $attempt->started_at;
        $attempt->update([
            'status' => 'submitted', 'submitted_at' => $submittedAt,
            'score' => $score, 'percentage' => $percentage,
            'passed' => $percentage >= (float) $attempt->exam->pass_mark,
            'attempted_count' => $attempted, 'unanswered_count' => max(0, $questionCount - $attempted),
            'time_used_seconds' => min($started->diffInSeconds($submittedAt), $attempt->exam->duration_minutes * 60),
        ]);
        return $attempt->fresh();
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
        return CbtExam::with(['subject', 'term.academicSession', 'schoolClasses'])
            ->where('published', true)
            ->whereHas('schoolClasses', fn ($q) => $q->where('school_classes.id', $student->school_class_id))
            ->where('starts_at', '<=', $now)->where('ends_at', '>=', $now)
            ->get()->map(function ($exam) use ($student) {
                $attempt = CbtAttempt::where('cbt_exam_id', $exam->id)->where('student_id', $student->id)->first();
                return ['id' => $exam->id, 'title' => $exam->title, 'subject' => $exam->subject, 'term' => $exam->term, 'instructions' => $exam->instructions, 'duration_minutes' => $exam->duration_minutes, 'starts_at' => $exam->starts_at, 'ends_at' => $exam->ends_at, 'pass_mark' => $exam->pass_mark, 'question_count' => $exam->questions()->count(), 'attempt_status' => $attempt?->status, 'attempt_id' => $attempt?->id];
            });
    }

    public function start(CbtExam $cbtExam)
    {
        $student = $this->student();
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
        abort_unless($cbtAttempt->student_id === $student->id, 403);
        if ($cbtAttempt->status === 'in_progress' && now()->gte($cbtAttempt->expires_at)) $cbtAttempt = $this->finish($cbtAttempt);
        return $this->attemptResponse($cbtAttempt);
    }

    public function saveAnswer(Request $request, CbtAttempt $cbtAttempt)
    {
        $student = $this->student();
        abort_unless($cbtAttempt->student_id === $student->id, 403);
        abort_if($cbtAttempt->status !== 'in_progress', 422, 'This attempt is already submitted.');
        if (now()->gte($cbtAttempt->expires_at)) return response()->json(['message' => 'Time has expired.', 'expired' => true], 422);
        $data = $request->validate(['question_id' => ['required', 'integer'], 'option_id' => ['nullable', 'integer']]);
        $question = $cbtAttempt->exam->questions()->whereKey($data['question_id'])->firstOrFail();
        if ($data['option_id'] !== null) $question->options()->whereKey($data['option_id'])->firstOrFail();
        CbtAnswer::updateOrCreate(['cbt_attempt_id' => $cbtAttempt->id, 'cbt_question_id' => $question->id], ['cbt_question_option_id' => $data['option_id']]);
        return ['saved' => true];
    }

    public function submit(CbtAttempt $cbtAttempt)
    {
        $student = $this->student();
        abort_unless($cbtAttempt->student_id === $student->id, 403);
        if ($cbtAttempt->status === 'submitted') return $this->attemptResponse($cbtAttempt);
        return $this->attemptResponse($this->finish($cbtAttempt));
    }

    public function results()
    {
        $student = $this->student();
        return CbtAttempt::with(['exam.subject', 'exam.term'])
            ->where('student_id', $student->id)->where('status', 'submitted')->latest('submitted_at')->get();
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
