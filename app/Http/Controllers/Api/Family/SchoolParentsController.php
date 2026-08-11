<?php

namespace App\Http\Controllers\Api\Family;

use App\Http\Controllers\Controller;
use App\Models\Term;
use App\Models\User;
use App\Notifications\FeeReminderNotification;
use App\Services\FeeService;
use Illuminate\Support\Facades\Auth;

/**
 * The dedicated Parents page — proprietor/principal/bursar (per the
 * design: parents are a distinct concept from "staff", not something
 * to bury in the Staff list, per the earlier restructuring). Shows
 * every invited parent, their linked children, and — since a bursar's
 * whole reason for viewing this page is chasing unpaid fees — each
 * child's current fee-default status for the term.
 *
 * NOTE: this is registered as a bare invokable route
 * (Route::get('parents', SchoolParentsController::class)), which
 * requires the handler method to be named __invoke(), not index() —
 * fixed here after that mismatch broke route registration entirely.
 */
class SchoolParentsController extends Controller
{
    public function __invoke(FeeService $feeService)
    {
        $schoolId = Auth::user()->school_id;

        $termId = request()->input('term_id')
            ?? Term::where('school_id', $schoolId)->where('is_current', true)->value('id');

        $query = User::where('school_id', $schoolId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'parent'))
            ->with(['children.schoolClass']);

        if (request()->filled('search')) {
            $search = request()->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->get()->map(function ($parent) use ($feeService, $termId) {
            $children = $parent->children->map(function ($child) use ($feeService, $termId) {
                $feeStatus = $termId ? $feeService->studentFeeStatus($child, $termId) : null;

                return [
                    'id' => $child->id,
                    'name' => $child->full_name,
                    'class' => trim(($child->schoolClass->name ?? '') . ' ' . ($child->schoolClass->arm ?? '')),
                    'fee_balance' => $feeStatus['total_balance'] ?? null,
                ];
            });

            return [
                'id' => $parent->id,
                'name' => $parent->name,
                'email' => $parent->email,
                'phone' => $parent->phone,
                'status' => $parent->status,
                'children' => $children,
                'has_defaulting_child' => $children->contains(fn ($c) => ($c['fee_balance'] ?? 0) > 0),
            ];
        });
    }

    /**
     * Actually SENDS the fee reminder (email now; other channels can
     * be added later without this endpoint changing) instead of just
     * copying text to the clipboard for the Bursar to paste manually
     * somewhere themselves.
     */
    public function sendReminder(User $parent, FeeService $feeService)
    {
        if ($parent->school_id !== Auth::user()->school_id || ! $parent->hasRole('parent')) {
            abort(404);
        }

        $termId = request()->input('term_id')
            ?? Term::where('school_id', $parent->school_id)->where('is_current', true)->value('id');

        $owingChildren = $parent->children()
            ->with('schoolClass')
            ->get()
            ->map(function ($child) use ($feeService, $termId) {
                $feeStatus = $termId ? $feeService->studentFeeStatus($child, $termId) : null;

                return [
                    'name' => $child->full_name,
                    'class' => trim(($child->schoolClass->name ?? '').' '.($child->schoolClass->arm ?? '')),
                    'balance' => $feeStatus['total_balance'] ?? 0,
                ];
            })
            ->filter(fn ($c) => $c['balance'] > 0)
            ->values()
            ->all();

        if (empty($owingChildren)) {
            return response()->json(['message' => 'This parent has no outstanding balance right now.'], 422);
        }

        $parent->notify(new FeeReminderNotification($owingChildren, Auth::user()->school->name));

        return response()->json(['message' => "Reminder sent to {$parent->email}."]);
    }
}
