<?php

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\SubjectScore;
use App\Models\SyncOperation;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\TermResultApproval;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Stage 52 — Offline-First Foundation.
 *
 * These endpoints exist ALONGSIDE AttendanceController::store() and
 * SubjectScoreController::store() (both untouched by this stage) rather
 * than replacing them. That's a deliberate choice: those two are
 * already-verified, live-entry code paths, and duplicating their
 * authorization/business logic here — rather than refactoring them to
 * share it — keeps this new offline-sync surface fully isolated from
 * them. If the two paths ever drift, that's the tradeoff; it was judged
 * safer than touching verified code to extract a shared service. Worth
 * revisiting once this has run in production for a while.
 *
 * Both sync methods follow the same shape:
 *   1. Idempotency check on client_uuid — a retried request (response
 *      was lost, not that the write failed) replays the stored result
 *      instead of re-applying.
 *   2. The same authorization/tenant checks the live endpoint has.
 *   3. Conflict check: has this record changed on the server SINCE the
 *      client's `recorded_at` — i.e. since the teacher actually made
 *      this edit on their device, not since it happened to sync? If
 *      so, and the values actually differ, that's a real conflict —
 *      don't silently overwrite it.
 */
class SyncController extends Controller
{
    /**
     * Batch attendance sync — same payload shape as
     * AttendanceController::store(), plus `client_uuid` and
     * `recorded_at`. Per-student conflicts are possible within one
     * batch (most of a class syncs cleanly, one or two students'
     * marks were also changed elsewhere) — the response reports each
     * outcome individually rather than failing the whole batch.
     */
    public function attendance()
    {
        $validated = request()->validate([
            'client_uuid' => ['required', 'uuid'],
            'recorded_at' => ['required', 'date'],
            'term_id' => ['required', 'integer'],
            'school_class_id' => ['required', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'date' => ['required', 'date'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer'],
            'records.*.status' => ['required', 'in:present,absent,late,excused'],
        ]);

        $user = Auth::user();

        if ($existing = SyncOperation::where('client_uuid', $validated['client_uuid'])->first()) {
            return $this->replay($existing);
        }

        $schoolId = $user->school_id;
        $recordedAt = $validated['recorded_at'];

        // --- authorization (mirrors AttendanceController::store()) ---
        if (! $user->hasRole(['proprietor', 'principal'])) {
            $isWholeDayMark = empty($validated['subject_id']);

            $isAssigned = TeacherAssignment::where('user_id', $user->id)
                ->where('school_class_id', $validated['school_class_id'])
                ->when($isWholeDayMark, fn ($q) => $q->where('is_class_teacher', true), fn ($q) => $q->where('subject_id', $validated['subject_id']))
                ->exists();

            if (! $isAssigned) {
                throw ValidationException::withMessages([
                    'school_class_id' => [$isWholeDayMark
                        ? 'Only this class\'s class teacher can mark whole-day attendance.'
                        : 'You are not assigned to teach this subject in this class.'],
                ]);
            }
        }

        $schoolClass = SchoolClass::findOrFail($validated['school_class_id']);
        $term = Term::findOrFail($validated['term_id']);

        if ($term->school_id !== $schoolClass->school_id || $schoolClass->school_id !== $schoolId) {
            throw ValidationException::withMessages(['term_id' => ['The term and class must belong to your school.']]);
        }

        $studentIds = collect($validated['records'])->pluck('student_id')->unique();
        $studentsInClass = Student::where('school_class_id', $schoolClass->id)->whereIn('id', $studentIds)->count();
        if ($studentsInClass !== $studentIds->count()) {
            throw ValidationException::withMessages(['records' => ['Every attendance student must belong to the selected class.']]);
        }

        $subjectId = $validated['subject_id'] ?? null;

        // --- apply, tracking conflicts per student ---
        $applied = [];
        $conflicts = [];

        DB::transaction(function () use ($validated, $subjectId, $recordedAt, $user, $schoolId, &$applied, &$conflicts) {
            foreach ($validated['records'] as $record) {
                $existingRow = Attendance::where('student_id', $record['student_id'])
                    ->where('school_class_id', $validated['school_class_id'])
                    ->where('subject_id', $subjectId)
                    ->where('date', $validated['date'])
                    ->first();

                $isStaleConflict = $existingRow
                    && $existingRow->updated_at->gt($recordedAt)
                    && $existingRow->status !== $record['status'];

                if ($isStaleConflict) {
                    $conflicts[] = [
                        'student_id' => $record['student_id'],
                        'local_status' => $record['status'],
                        'server_status' => $existingRow->status,
                        'server_updated_at' => $existingRow->updated_at->toIso8601String(),
                    ];
                    continue;
                }

                $row = Attendance::updateOrCreate(
                    [
                        'student_id' => $record['student_id'],
                        'school_class_id' => $validated['school_class_id'],
                        'subject_id' => $subjectId,
                        'date' => $validated['date'],
                    ],
                    [
                        'school_id' => $schoolId,
                        'term_id' => $validated['term_id'],
                        'status' => $record['status'],
                        'marked_by' => $user->id,
                    ]
                );

                $applied[] = ['student_id' => $record['student_id'], 'status' => $row->status];
            }
        });

        $status = empty($conflicts) ? 'applied' : (empty($applied) ? 'conflict' : 'partial');

        if (! empty($conflicts)) {
            // Diagnostic aid: a conflict discovered on this ONLINE path
            // (not the offline-queue path, where it's expected and
            // normal) means the server had a newer change than the
            // client knew about at the moment it saved — which is
            // unusual for a same-session save. Logging the comparison
            // inputs makes it possible to tell a real conflict apart
            // from a bug (e.g. a clock/timezone issue) if this shows
            // up more than the occasional legitimate case.
            Log::info('Attendance sync conflict', [
                'school_id' => $schoolId,
                'user_id' => $user->id,
                'school_class_id' => $validated['school_class_id'],
                'date' => $validated['date'],
                'client_recorded_at' => $recordedAt,
                'conflicts' => $conflicts,
            ]);
        }

        $operation = SyncOperation::create([
            'client_uuid' => $validated['client_uuid'],
            'school_id' => $schoolId,
            'user_id' => $user->id,
            'type' => 'attendance_mark',
            'recorded_at' => $recordedAt,
            'payload' => $validated,
            'status' => $status,
            'result' => ['applied' => $applied],
            'conflict_data' => empty($conflicts) ? null : $conflicts,
        ]);

        return response()->json($operation, 201);
    }

    /**
     * Single-student score sync — same payload shape as
     * SubjectScoreController::store(), plus `client_uuid` and
     * `recorded_at`.
     */
    public function score()
    {
        $validated = request()->validate([
            'client_uuid' => ['required', 'uuid'],
            'recorded_at' => ['required', 'date'],
            'term_id' => ['required', 'integer'],
            'school_class_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'student_id' => ['required', 'integer'],
            'ca_score' => ['nullable', 'numeric', 'min:0'],
            'ca_max' => ['nullable', 'numeric', 'min:1'],
            'assignment_score' => ['nullable', 'numeric', 'min:0'],
            'assignment_max' => ['nullable', 'numeric', 'min:1'],
            'exam_score' => ['nullable', 'numeric', 'min:0'],
            'exam_max' => ['nullable', 'numeric', 'min:1'],
            'teacher_comment' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();

        if ($existing = SyncOperation::where('client_uuid', $validated['client_uuid'])->first()) {
            return $this->replay($existing);
        }

        $student = Student::whereKey($validated['student_id'])->firstOrFail();
        $schoolClass = SchoolClass::whereKey($validated['school_class_id'])->firstOrFail();
        $term = Term::whereKey($validated['term_id'])->firstOrFail();

        if (
            (int) $student->school_id !== (int) $user->school_id ||
            (int) $schoolClass->school_id !== (int) $user->school_id ||
            (int) $term->school_id !== (int) $user->school_id
        ) {
            abort(403, 'The supplied academic records do not belong to your school.');
        }

        if ((int) $student->school_class_id !== (int) $schoolClass->id) {
            throw ValidationException::withMessages(['school_class_id' => ['The selected student does not belong to this class.']]);
        }

        if (! $schoolClass->subjects()->whereKey($validated['subject_id'])->exists()) {
            throw ValidationException::withMessages(['subject_id' => ['This subject is not attached to the selected class.']]);
        }

        $isLocked = TermResultApproval::where('school_class_id', $schoolClass->id)
            ->where('term_id', $term->id)
            ->whereNotNull('locked_at')
            ->exists();

        if ($isLocked) {
            throw ValidationException::withMessages(['student_id' => ['Results for this class/term have been permanently published — scores can no longer be edited.']]);
        }

        if (! $user->hasRole(['proprietor', 'principal', 'exam_officer'])) {
            $isAssigned = TeacherAssignment::where('user_id', $user->id)
                ->where('school_class_id', $schoolClass->id)
                ->where('subject_id', $validated['subject_id'])
                ->exists();

            if (! $isAssigned) {
                throw ValidationException::withMessages(['subject_id' => ['You are not assigned to teach this subject in this class.']]);
            }
        }

        $scoreFields = ['ca_score', 'ca_max', 'assignment_score', 'assignment_max', 'exam_score', 'exam_max', 'teacher_comment'];
        $existingRow = SubjectScore::where('student_id', $validated['student_id'])
            ->where('subject_id', $validated['subject_id'])
            ->where('term_id', $validated['term_id'])
            ->first();

        $recordedAt = $validated['recorded_at'];
        $hasDifferentValues = $existingRow && collect($scoreFields)->contains(fn ($field) => $existingRow->{$field} != ($validated[$field] ?? null));
        $isStaleConflict = $existingRow && $existingRow->updated_at->gt($recordedAt) && $hasDifferentValues;

        if ($isStaleConflict) {
            Log::info('Score sync conflict', [
                'school_id' => $user->school_id,
                'user_id' => $user->id,
                'student_id' => $validated['student_id'],
                'subject_id' => $validated['subject_id'],
                'term_id' => $validated['term_id'],
                'client_recorded_at' => $recordedAt,
                'server_updated_at' => $existingRow->updated_at->toIso8601String(),
            ]);

            $operation = SyncOperation::create([
                'client_uuid' => $validated['client_uuid'],
                'school_id' => $user->school_id,
                'user_id' => $user->id,
                'type' => 'score_save',
                'recorded_at' => $recordedAt,
                'payload' => $validated,
                'status' => 'conflict',
                'conflict_data' => [
                    'local' => collect($validated)->only($scoreFields),
                    'server' => collect($existingRow->toArray())->only($scoreFields),
                    'server_updated_at' => $existingRow->updated_at->toIso8601String(),
                ],
            ]);

            return response()->json($operation, 201);
        }

        $score = SubjectScore::updateOrCreate(
            [
                'student_id' => $validated['student_id'],
                'subject_id' => $validated['subject_id'],
                'term_id' => $validated['term_id'],
            ],
            array_merge(collect($validated)->only(array_merge($scoreFields, ['school_class_id']))->toArray(), [
                'school_id' => $user->school_id,
                'entered_by' => $user->id,
            ])
        );

        $operation = SyncOperation::create([
            'client_uuid' => $validated['client_uuid'],
            'school_id' => $user->school_id,
            'user_id' => $user->id,
            'type' => 'score_save',
            'recorded_at' => $recordedAt,
            'payload' => $validated,
            'status' => 'applied',
            'result' => ['subject_score_id' => $score->id],
        ]);

        return response()->json($operation, 201);
    }

    /**
     * This user's recent sync operations — powers the frontend's
     * "pending / conflicts" panel, and lets a conflict be reviewed
     * even after a page reload (the payload + conflict_data needed to
     * show "local vs server" both live here, not just in IndexedDB).
     */
    public function index()
    {
        $query = SyncOperation::where('user_id', Auth::id())->orderByDesc('created_at');

        if (request()->filled('status')) {
            $query->where('status', request()->input('status'));
        }

        return $query->limit(100)->get();
    }

    /**
     * Resolve a conflicted operation. `keep_local` re-applies the
     * originally-submitted values, overwriting whatever is on the
     * server now. `keep_server` just closes the conflict out — the
     * server's current value already stands, nothing to write.
     */
    public function resolve(SyncOperation $operation)
    {
        $user = Auth::user();

        if ($operation->user_id !== $user->id) {
            abort(403, 'You can only resolve your own sync conflicts.');
        }

        if ($operation->status !== 'conflict') {
            abort(422, 'This operation is not a pending conflict.');
        }

        $validated = request()->validate([
            'resolution' => ['required', 'in:keep_local,keep_server'],
        ]);

        if ($validated['resolution'] === 'keep_local') {
            $payload = $operation->payload;

            if ($operation->type === 'attendance_mark') {
                $subjectId = $payload['subject_id'] ?? null;
                foreach ($operation->conflict_data as $conflict) {
                    Attendance::updateOrCreate(
                        [
                            'student_id' => $conflict['student_id'],
                            'school_class_id' => $payload['school_class_id'],
                            'subject_id' => $subjectId,
                            'date' => $payload['date'],
                        ],
                        [
                            'school_id' => $operation->school_id,
                            'term_id' => $payload['term_id'],
                            'status' => $conflict['local_status'],
                            'marked_by' => $user->id,
                        ]
                    );
                }
            } elseif ($operation->type === 'score_save') {
                $scoreFields = ['ca_score', 'ca_max', 'assignment_score', 'assignment_max', 'exam_score', 'exam_max', 'teacher_comment'];
                SubjectScore::updateOrCreate(
                    [
                        'student_id' => $payload['student_id'],
                        'subject_id' => $payload['subject_id'],
                        'term_id' => $payload['term_id'],
                    ],
                    array_merge(collect($payload)->only($scoreFields)->toArray(), [
                        'school_class_id' => $payload['school_class_id'],
                        'school_id' => $operation->school_id,
                        'entered_by' => $user->id,
                    ])
                );
            }
        }

        $operation->update([
            'status' => 'resolved',
            'resolution' => $validated['resolution'],
        ]);

        return $operation;
    }

    /**
     * A retried request for an already-processed client_uuid replays
     * the stored result rather than reprocessing — this is what makes
     * these endpoints safe to call again after a lost response.
     */
    protected function replay(SyncOperation $operation)
    {
        if ($operation->user_id !== Auth::id()) {
            abort(403, 'This sync operation does not belong to you.');
        }

        return response()->json($operation, 200);
    }
}
