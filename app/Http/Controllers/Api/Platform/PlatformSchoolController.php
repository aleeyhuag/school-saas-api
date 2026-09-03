<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CreateSchoolRequest;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolRegistrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Platform-wide school management — ONLY reachable by super_admin
 * (see routes/api-platform.php). This is intentionally the one place
 * in the whole API that queries across every school at once; every
 * other controller works within a single tenant.
 */
class PlatformSchoolController extends Controller
{
    /**
     * List every school on the platform, with basic counts. Supports
     * ?search= (matches school name) since this list is meant to
     * grow — search rather than scroll, same principle used
     * everywhere else large lists show up in this app.
     */
    public function index()
    {
        // withCount() builds a correlated subquery per school — that
        // correlation alone already scopes each count correctly per
        // row, so the models' own BelongsToSchool scope isn't needed
        // here and must be bypassed: with no acting-user school
        // context (Super Admin has none), that scope now blocks
        // everything by design (see Stage 38's security fix) — left
        // alone, every school's counts would show as 0, not just be
        // merely unscoped.
        $query = School::withCount([
            'users' => fn ($q) => $q->withoutGlobalScope('school'),
            'students' => fn ($q) => $q->withoutGlobalScope('school'),
        ]);

        if (request()->filled('search')) {
            $query->where('name', 'like', '%' . request()->input('search') . '%');
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Full detail payload for the Super Admin school detail page.
     * Every user/student query below explicitly bypasses the
     * BelongsToSchool scope for the same reason as index() above —
     * there's no acting-user school context in a Super Admin session.
     */
    public function show(School $school)
    {
        $school->load([
            'academicSessions' => fn ($q) => $q->withoutGlobalScope('school')->latest('start_date')->limit(1),
            'subscription.plan',
        ]);

        $staff = User::where('school_id', $school->id)->with('roles:id,name')->get();

        $proprietor = $staff->first(fn ($u) => $u->roles->contains('name', 'proprietor'));

        $staffByRole = $staff
            ->groupBy(fn ($u) => $u->roles->pluck('name')->first() ?? 'unassigned')
            ->map->count();

        $recentPayments = $school->payments()
            ->with('plan:id,name')
            ->latest()
            ->limit(10)
            ->get(['id', 'plan_id', 'amount_kobo', 'duration_months', 'method', 'status', 'reference_code', 'reviewed_at', 'created_at']);

        return response()->json([
            'school' => $school,
            'proprietor' => $proprietor ? [
                'id' => $proprietor->id,
                'name' => $proprietor->name,
                'email' => $proprietor->email,
                'phone' => $proprietor->phone,
                'status' => $proprietor->status,
                'created_at' => $proprietor->created_at,
            ] : null,
            'staff_summary' => [
                'total' => $staff->count(),
                'by_role' => $staffByRole,
            ],
            'students_summary' => [
                'total' => Student::withoutGlobalScope('school')->where('school_id', $school->id)->count(),
            ],
            'billing' => [
                'subscription' => $school->subscription,
                'recent_payments' => $recentPayments,
            ],
        ]);
    }

    /**
     * Onboard a school ON THEIR BEHALF — e.g. during a sales call with
     * one of your pilot schools, rather than waiting for them to
     * self-register via the public /auth/register-school endpoint.
     * Generates a temporary password for the proprietor account,
     * shown once here so you can share it with them directly — same
     * pattern as inviting staff within a school.
     */
    public function store(CreateSchoolRequest $request, SchoolRegistrationService $registrationService)
    {
        [$school, $user, $temporaryPassword] = $registrationService->register($request->validated());
        $role = $request->validated('admin_role') ?? 'proprietor';

        return response()->json([
            'message' => "School created. Share the temporary password with the {$role} securely.",
            'school' => $school,
            'proprietor' => $user->only(['id', 'name', 'email']),
            'admin_role' => $role,
            'temporary_password' => $temporaryPassword,
        ], 201);
    }

    /**
     * Activate/deactivate a school in one click. When deactivated:
     *  - LoginController blocks new logins for anyone in that school
     *    with a clear message.
     *  - The EnsureSchoolIsActive middleware blocks every OTHER
     *    request too, so someone already logged in gets cut off
     *    immediately rather than continuing to work until their
     *    token happens to expire.
     */
    public function toggleActive(School $school)
    {
        $school->update(['is_active' => ! $school->is_active]);

        $school->users()
            ->whereHas('roles', fn ($q) => $q->where('name', 'proprietor'))
            ->get()
            ->each(fn ($proprietor) => $proprietor->notify(
                new \App\Notifications\SchoolStatusChangedNotification($school->is_active, $school->name)
            ));

        return response()->json([
            'message' => $school->is_active ? 'School activated.' : 'School deactivated.',
            'school' => $school,
        ]);
    }

    /**
     * Permanently delete a school and everything in it — confirmed
     * by the Super Admin typing the school's exact name on the
     * frontend (defence in depth against a misclick; this is not
     * reversible). Every school-scoped table cascades at the DB
     * level via school_id foreignId('...')->cascadeOnDelete(), EXCEPT
     * `users.school_id`, which is deliberately nullOnDelete (a
     * Super Admin's own account has school_id = null and must never
     * be affected by any school's foreign key). Left alone, that
     * means every staff/student/parent login for this school would
     * survive the delete as an orphaned account with school_id null
     * instead of actually being removed — so we explicitly delete
     * those users ourselves before removing the school row.
     *
     * One exception within that: a Proprietor who manages more than
     * one branch (school_group) is a single shared login whose
     * users.school_id merely points at whichever branch they're
     * currently switched into — deleting THAT row would sign them
     * out of every branch they own, not just this one. So for a
     * multi-branch Proprietor we only revoke access to this branch
     * and, if their active school_id happens to be this school,
     * switch them onto another branch they still have — we never
     * delete their account.
     */
    public function destroy(School $school)
    {
        $validated = request()->validate([
            'confirm_name' => ['required', 'string'],
        ]);

        if ($validated['confirm_name'] !== $school->name) {
            throw ValidationException::withMessages([
                'confirm_name' => ['The name you typed does not match this school\'s name exactly.'],
            ]);
        }

        DB::transaction(function () use ($school) {
            // User doesn't use the BelongsToSchool trait (it can't —
            // a Super Admin's own account has no school_id at all),
            // so this is a plain, unscoped query as-is.
            $users = User::where('school_id', $school->id)->get();

            foreach ($users as $user) {
                $otherBranchId = $user->accessibleSchools()
                    ->where('schools.id', '!=', $school->id)
                    ->value('schools.id');

                if ($otherBranchId) {
                    // Multi-branch proprietor who still has another
                    // branch left — keep the login, just move them off
                    // the branch being deleted. proprietor_school_access
                    // for this branch is cleaned up automatically by
                    // the school row's cascade below.
                    $user->update(['school_id' => $otherBranchId]);
                } else {
                    $user->delete();
                }
            }

            if ($school->logo_path) {
                Storage::disk('public')->delete($school->logo_path);
            }

            // Payment proof-of-payment images live on disk, not just in
            // the DB row — clean those up too so deleting a school
            // doesn't leave payment screenshots behind forever.
            $school->payments()->whereNotNull('proof_path')->pluck('proof_path')
                ->each(function ($path) {
                    Storage::disk('private')->delete($path);
                    Storage::disk('public')->delete($path);
                });

            // Every remaining school-scoped table (academic_sessions,
            // students, subjects, fees, results, timetables, billing,
            // announcements, etc.) cascades automatically from here.
            $school->delete();
        });

        return response()->json([
            'message' => 'School and all of its data have been permanently deleted.',
        ]);
    }
}
