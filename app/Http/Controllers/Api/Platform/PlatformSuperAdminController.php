<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CreateSuperAdminRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        return response()->json($superAdmins);
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
