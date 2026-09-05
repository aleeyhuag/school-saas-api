<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CreateSuperAdminRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manage super_admin accounts — the platform owner's own team.
 * ONLY reachable by an existing super_admin (see routes/api-platform.php),
 * same restriction as the rest of this namespace.
 */
class PlatformSuperAdminController extends Controller
{
    /**
     * Every super_admin account on the platform. Deliberately a plain
     * query, not scoped by BelongsToSchool — super_admin accounts have
     * no school_id at all (see the trait's own docblock on why that
     * matters), so there's nothing to scope here.
     */
    public function index()
    {
        $superAdmins = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'status', 'created_at']);

        return response()->json(['admins' => $superAdmins, 'current_user_id' => request()->user()->id]);
    }

    /**
     * Create another super_admin account. Same "generate a temporary
     * password, show it once" pattern as onboarding a school's
     * proprietor (PlatformSchoolController::store()) — you're creating
     * this on the new admin's behalf, so there's nothing for them to
     * choose upfront. Share the password with them securely; they can
     * change it after their first login via the normal account
     * settings page.
     */
    public function toggleStatus(User $user)
    {
        $this->assertSuperAdmin($user);
        $actor = request()->user();
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['user' => ['You cannot deactivate your own Super Admin account.']]);
        }

        if ($user->status === 'disabled') {
            $user->update(['status' => 'approved']);
            return response()->json(['message' => 'Super Admin reactivated.', 'status' => 'approved']);
        }

        DB::transaction(function () use ($user) {
            $activeAdmins = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
                ->where('status', '!=', 'disabled')->lockForUpdate()->count();
            if ($activeAdmins <= 1) {
                throw ValidationException::withMessages(['user' => ['The last active Super Admin cannot be deactivated.']]);
            }
            $user->update(['status' => 'disabled']);
            $user->tokens()->delete();
        });
        return response()->json(['message' => 'Super Admin deactivated and active sessions revoked.', 'status' => 'disabled']);
    }

    public function destroy(User $user)
    {
        $this->assertSuperAdmin($user);
        if ($user->is(request()->user())) {
            throw ValidationException::withMessages(['user' => ['You cannot delete your own Super Admin account.']]);
        }
        $validated = request()->validate(['confirm_email' => ['required', 'email']]);
        if (strcasecmp($validated['confirm_email'], $user->email) !== 0) {
            throw ValidationException::withMessages(['confirm_email' => ['The confirmation email does not match this Super Admin.']]);
        }

        DB::transaction(function () use ($user) {
            $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
                ->lockForUpdate()->get(['id']);
            if ($admins->count() <= 1) {
                throw ValidationException::withMessages(['user' => ['The last remaining Super Admin cannot be deleted.']]);
            }
            $user->tokens()->delete();
            $user->syncRoles([]);
            $user->delete();
        });
        return response()->json(['message' => 'Super Admin deleted.']);
    }

    private function assertSuperAdmin(User $user): void
    {
        abort_unless($user->hasRole('super_admin'), 404);
    }

    public function store(CreateSuperAdminRequest $request)
    {
        $plainPassword = Str::random(12);

        $user = User::create([
            'school_id' => null,
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($plainPassword),
            'status' => 'approved',
        ]);

        $user->assignRole('super_admin');

        return response()->json([
            'message' => 'Super admin account created. Share the temporary password securely — it will not be shown again.',
            'user' => $user->only(['id', 'name', 'email', 'created_at']),
            'temporary_password' => $plainPassword,
        ], 201);
    }
}

