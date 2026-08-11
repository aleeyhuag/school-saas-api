<?php

namespace App\Http\Controllers\Api\Fees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\FeeStructureRequest;
use App\Models\FeeStructure;

class FeeStructureController extends Controller
{
    /**
     * List fee structures. Supports optional ?term_id= and
     * ?school_class_id= filters.
     */
    public function index()
    {
        $query = FeeStructure::with('schoolClass');

        if (request()->filled('term_id')) {
            $query->where('term_id', request()->input('term_id'));
        }

        if (request()->filled('school_class_id')) {
            $query->where(function ($q) {
                $q->where('school_class_id', request()->input('school_class_id'))
                    ->orWhereNull('school_class_id');
            });
        }

        return $query->orderBy('name')->get();
    }

    public function store(FeeStructureRequest $request)
    {
        $structure = FeeStructure::create($request->validated());

        return response()->json($structure->load('schoolClass'), 201);
    }

    public function update(FeeStructureRequest $request, FeeStructure $feeStructure)
    {
        $feeStructure->update($request->validated());

        return $feeStructure->load('schoolClass');
    }

    public function destroy(FeeStructure $feeStructure)
    {
        $feeStructure->delete();

        return response()->json(['message' => 'Fee structure deleted.']);
    }
}
