<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StudentPromotionRequest;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPromotion;
use App\Services\StudentPromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StudentPromotionController extends Controller
{
    public function __construct(protected StudentPromotionService $service) {}

    public function options()
    {
        $schoolId = Auth::user()->school_id;
        return response()->json([
            'sessions' => AcademicSession::where('school_id',$schoolId)->with('terms:id,academic_session_id,name,is_current')->orderByDesc('start_date')->get(),
            'classes' => SchoolClass::where('school_id',$schoolId)->withCount('students')->orderBy('level')->orderBy('name')->orderBy('arm')->get(),
        ]);
    }

    public function students(Request $request)
    {
        $request->validate([
            'school_class_id' => ['required','integer', Rule::exists('school_classes', 'id')->where('school_id', Auth::user()->school_id)],
            'status' => ['nullable','in:active,transferred,withdrawn'],
            'search' => ['nullable','string','max:100'],
        ]);
        $query = Student::with('schoolClass')->where('school_class_id',$request->integer('school_class_id'));
        if ($request->filled('status')) $query->where('status',$request->input('status'));
        else $query->where('status','active');
        if ($request->filled('search')) {
            $s=$request->input('search');
            $query->where(fn($q)=>$q->where('first_name','like',"%{$s}%")->orWhere('last_name','like',"%{$s}%")->orWhere('admission_number','like',"%{$s}%"));
        }
        return $query->orderBy('last_name')->orderBy('first_name')->get();
    }

    public function execute(StudentPromotionRequest $request)
    {
        return response()->json($this->service->execute($request->validated()), 201);
    }

    public function history(Request $request)
    {
        return $this->service->history($request->only(['to_academic_session_id','from_academic_session_id','school_class_id','action','per_page']));
    }
}
