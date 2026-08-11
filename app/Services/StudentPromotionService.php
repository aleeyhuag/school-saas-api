<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPromotion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentPromotionService
{
    public const ACTIONS = ['promoted', 'repeated', 'skipped', 'transferred', 'withdrawn'];

    public function execute(array $validated): array
    {
        $user = Auth::user();
        $schoolId = $user->school_id;

        $fromSession = AcademicSession::where('school_id', $schoolId)->findOrFail($validated['from_academic_session_id']);
        $toSession = AcademicSession::where('school_id', $schoolId)->findOrFail($validated['to_academic_session_id']);

        if ($fromSession->id === $toSession->id) {
            throw ValidationException::withMessages([
                'to_academic_session_id' => ['The target academic session must be different from the source session.'],
            ]);
        }

        $results = DB::transaction(function () use ($validated, $schoolId, $user, $fromSession, $toSession) {
            $created = [];

            foreach ($validated['changes'] as $change) {
                $action = $change['action'];
                $toClassId = $change['to_school_class_id'] ?? null;

                if (in_array($action, ['promoted', 'repeated', 'skipped'], true) && ! $toClassId) {
                    throw ValidationException::withMessages([
                        'changes' => ["A target class is required for the {$action} action."],
                    ]);
                }

                if ($toClassId) {
                    SchoolClass::where('school_id', $schoolId)->findOrFail($toClassId);
                }

                foreach ($change['student_ids'] as $studentId) {
                    $student = Student::where('school_id', $schoolId)->findOrFail($studentId);
                    $fromClassId = $student->school_class_id;

                    if (! empty($change['from_school_class_id']) && (int) $change['from_school_class_id'] !== (int) $fromClassId) {
                        throw ValidationException::withMessages([
                            'changes' => ["Student {$student->admission_number} is no longer in the selected source class."],
                        ]);
                    }

                    // A student should not be processed twice for the same target session.
                    if (StudentPromotion::where('school_id', $schoolId)
                        ->where('student_id', $student->id)
                        ->where('to_academic_session_id', $toSession->id)
                        ->exists()) {
                        throw ValidationException::withMessages([
                            'changes' => ["{$student->full_name} already has a promotion decision for {$toSession->name}."],
                        ]);
                    }

                    $status = match ($action) {
                        'transferred' => 'transferred',
                        'withdrawn' => 'withdrawn',
                        default => 'active',
                    };

                    if ($toClassId && in_array($action, ['promoted', 'repeated', 'skipped'], true)) {
                        $student->school_class_id = $toClassId;
                    }
                    $student->status = $status;
                    $student->save();

                    $created[] = StudentPromotion::create([
                        'school_id' => $schoolId,
                        'student_id' => $student->id,
                        'from_academic_session_id' => $fromSession->id,
                        'to_academic_session_id' => $toSession->id,
                        'from_school_class_id' => $fromClassId,
                        'to_school_class_id' => $toClassId,
                        'action' => $action,
                        'note' => $change['note'] ?? null,
                        'performed_by' => $user->id,
                    ]);
                }
            }

            return $created;
        });

        return [
            'message' => count($results).' student promotion decision(s) saved.',
            'count' => count($results),
            'promotions' => collect($results)->map(fn ($p) => $p->load(['student:id,first_name,last_name,admission_number','fromClass:id,name,arm','toClass:id,name,arm','performedBy:id,name']))->values(),
        ];
    }

    public function history(array $filters)
    {
        $query = StudentPromotion::with([
            'student:id,first_name,last_name,admission_number,status',
            'fromSession:id,name', 'toSession:id,name',
            'fromClass:id,name,arm', 'toClass:id,name,arm',
            'performedBy:id,name',
        ])->latest();

        if (! empty($filters['to_academic_session_id'])) $query->where('to_academic_session_id', $filters['to_academic_session_id']);
        if (! empty($filters['from_academic_session_id'])) $query->where('from_academic_session_id', $filters['from_academic_session_id']);
        if (! empty($filters['school_class_id'])) $query->where(function ($q) use ($filters) {
            $q->where('from_school_class_id', $filters['school_class_id'])->orWhere('to_school_class_id', $filters['school_class_id']);
        });
        if (! empty($filters['action'])) $query->where('action', $filters['action']);

        return $query->paginate(min((int) ($filters['per_page'] ?? 25), 100));
    }
}
