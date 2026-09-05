<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Services\AccountActionTokenService;
use App\Notifications\AccountSetupNotification;
use Illuminate\Validation\ValidationException;

/**
 * Lists existing staff/parent accounts within the CURRENT user's
 * school. NOTE: the User model does NOT use the BelongsToSchool
 * trait (it can't — a super_admin's whole point is having no single
 * school), so scoping here is done manually via school_id, unlike
 * every other tenant-scoped controller in the app.
 */
class StaffController extends Controller
{
    /**
     * Lists staff. Supports ?search= (name or email) and ?role= —
     * used by the Family modal's parent/student-account pickers so
     * they can search on demand instead of loading every staff member
     * in the school at once (which doesn't scale once a school has
     * hundreds of invited parents).
     */
    public function index()
    {
        $query = User::where('school_id', Auth::user()->school_id);

        if (request()->filled('search')) {
            $search = request()->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (request()->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', request()->input('role')));
        } else {
            // The general "Staff" list should only ever show real
            // staff — student and parent accounts have their own
            // dedicated pages (Students, Parents) and would otherwise
            // confusingly show up here even though they aren't staff.
            // An explicit ?role=parent (used by the Parents page and
            // the Family modal's guardian search) still works — this
            // exclusion only applies to the unfiltered "show me
            // everyone" call.
            $query->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['student', 'parent']));
        }

        return $query->with('roles:id,name')
            ->orderBy('name')
            ->limit(request()->filled('search') || request()->filled('role') ? 20 : 500)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'roles' => $user->roles->pluck('name'),
                ];
            });
    }

    /**
     * Disable/re-enable a staff account — e.g. a teacher leaves the
     * school. Disabled users are blocked at login (see
     * Stage 3's LoginController, which already checks `status`).
     */
    public function toggleStatus(User $user)
    {
        if ($user->school_id !== Auth::user()->school_id) {
            abort(404);
        }

        $user->update([
            'status' => $user->status === 'disabled' ? 'approved' : 'disabled',
        ]);

        return response()->json([
            'message' => $user->status === 'disabled' ? 'Account disabled.' : 'Account re-enabled.',
            'status' => $user->status,
        ]);
    }

    /**
     * Admin-triggered password reset — generates a new temporary
     * password, emails it to the staff member directly, and also
     * returns it to the admin as a fallback in case the email
     * doesn't land. A real self-serve "forgot password" flow also
     * exists now (see ForgotPasswordController) — this is for when
     * an admin resets it on someone's behalf instead.
     */
    public function resetPassword(User $user)
    {
        if ($user->school_id !== Auth::user()->school_id) {
            abort(404);
        }

        // Generate a fresh random temporary credential server-side so the
        // account remains usable while the setup token is being delivered.
        // Never return the credential to the browser.
        $temporaryPassword = Str::random(12);
        $user->update(['password' => Hash::make($temporaryPassword)]);

        try {
            $token = app(AccountActionTokenService::class)->issue($user, 'account_setup');
            $user->notify(new AccountSetupNotification($token, Auth::user()->school->name));
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'The password was not changed because the setup email could not be sent. Please try again after checking email settings.',
            ], 422);
        }

        return response()->json([
            'message' => 'A secure password setup link has been emailed to the staff member.',
            'setup_link_sent' => true,
        ]);
    }

    /**
     * Grants an ADDITIONAL role to an existing staff member — e.g.
     * picking up 'teacher' alongside an existing senior role, without
     * re-inviting them.
     *
     * Two rules enforced here:
     *  1. Only a Proprietor or Principal can grant a senior role
     *     (principal/bursar/exam_officer) — same peers who can already
     *     manage billing and staff elsewhere in the app.
     *  2. Senior roles don't stack with EACH OTHER. Someone who's
     *     already a Principal, Bursar, or Exam Officer can only ever
     *     pick up 'teacher' as a second role — never a second senior
     *     role (a Principal can't also become Bursar, etc.). The
     *     Proprietor is exempt from this — in a small school it's
     *     common for the owner to also directly handle the books.
     */
    public function addRole(User $user)
    {
        if ($user->school_id !== Auth::user()->school_id) {
            abort(404);
        }

        $validated = request()->validate([
            'role' => ['required', 'string', 'in:proprietor,principal,bursar,exam_officer,teacher,parent'],
        ]);

        $seniorRoles = ['principal', 'bursar', 'exam_officer'];

        if (in_array($validated['role'], $seniorRoles) && ! Auth::user()->hasAnyRole(['proprietor', 'principal'])) {
            throw ValidationException::withMessages([
                'role' => ['Only the proprietor or principal can grant a principal, bursar, or exam officer role.'],
            ]);
        }

        if (in_array($validated['role'], $seniorRoles) && $user->hasAnyRole($seniorRoles)) {
            throw ValidationException::withMessages([
                'role' => ['This person already holds a senior role (principal, bursar, or exam officer). Only one senior role per person — they can still be given the teacher role alongside it.'],
            ]);
        }

        if ($user->hasRole($validated['role'])) {
            throw ValidationException::withMessages([
                'role' => ['This person already has that role.'],
            ]);
        }

        $user->assignRole($validated['role']);

        return response()->json([
            'message' => 'Role added.',
            'roles' => $user->fresh()->getRoleNames(),
        ]);
    }

    /**
     * Removes ONE role from a staff member who holds more than one —
     * the undo for addRole(). Refuses to remove someone's only
     * remaining role (use disable/toggleStatus for that instead) and
     * can never remove the proprietor role (there must always be an
     * owner).
     */
    public function removeRole(User $user)
    {
        if ($user->school_id !== Auth::user()->school_id) {
            abort(404);
        }

        $validated = request()->validate([
            'role' => ['required', 'string'],
        ]);

        if ($validated['role'] === 'proprietor') {
            throw ValidationException::withMessages([
                'role' => ['The proprietor role cannot be removed.'],
            ]);
        }

        if ($user->getRoleNames()->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => ['This is their only role — disable the account instead if they should lose access entirely.'],
            ]);
        }

        $user->removeRole($validated['role']);

        return response()->json([
            'message' => 'Role removed.',
            'roles' => $user->fresh()->getRoleNames(),
        ]);
    }
}
