<?php

namespace App\Http\Controllers\Api\Timetable;

use App\Http\Controllers\Controller;
use App\Models\ExamTimetableEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Exam timetable — owned by Exam Officer (create/edit/delete).
 * Proprietor, Principal, teachers, students, parents can all view.
 * Bursar cannot view either timetable (excluded at the route level).
 */
class ExamTimetableController extends Controller
{
    /**
     * All exam entries for a term — optionally filtered by
     * school_class_id so a student/parent/teacher sees only the
     * exams that involve their class.
     */
    public function index()
    {
        request()->validate([
            'term_id' => ['required', 'integer'],
            'school_class_id' => ['nullable', 'integer'],
        ]);

        $query = ExamTimetableEntry::with(['subject', 'schoolClasses'])
            ->where('term_id', request()->integer('term_id'));

        if (request()->filled('school_class_id')) {
            $query->whereHas('schoolClasses', function ($q) {
                $q->where('school_classes.id', request()->integer('school_class_id'));
            });
        }

        return $query->orderBy('exam_date')->orderBy('start_time')->get();
    }

    public function store()
    {
        $schoolId = Auth::user()->school_id;

        $validated = request()->validate([
            'term_id' => ['required', 'integer', Rule::exists('terms', 'id')->where('school_id', $schoolId)],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('school_id', $schoolId)],
            'school_class_ids' => ['required', 'array', 'min:1'],
            'school_class_ids.*' => ['integer', Rule::exists('school_classes', 'id')->where('school_id', $schoolId)],
            'exam_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'venue' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $entry = ExamTimetableEntry::create([
            ...$validated,
            'school_id' => $schoolId,
        ]);

        $entry->schoolClasses()->sync($validated['school_class_ids']);

        return response()->json($entry->load(['subject', 'schoolClasses']), 201);
    }

    public function update(ExamTimetableEntry $examTimetableEntry)
    {
        $validated = request()->validate([
            'term_id' => ['sometimes', 'integer', Rule::exists('terms', 'id')->where('school_id', $examTimetableEntry->school_id)],
            'subject_id' => ['sometimes', 'integer', Rule::exists('subjects', 'id')->where('school_id', $examTimetableEntry->school_id)],
            'school_class_ids' => ['sometimes', 'array', 'min:1'],
            'school_class_ids.*' => ['integer', Rule::exists('school_classes', 'id')->where('school_id', $examTimetableEntry->school_id)],
            'exam_date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $examTimetableEntry->update($validated);

        if (isset($validated['school_class_ids'])) {
            $examTimetableEntry->schoolClasses()->sync($validated['school_class_ids']);
        }

        return $examTimetableEntry->load(['subject', 'schoolClasses']);
    }

    public function destroy(ExamTimetableEntry $examTimetableEntry)
    {
        $examTimetableEntry->delete();
        return response()->json(['message' => 'Exam entry removed.']);
    }
}
