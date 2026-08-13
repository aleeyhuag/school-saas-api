<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password;
use App\Notifications\AccountSetupNotification;

/**
 * Creates a school + its first Proprietor account. Shared by:
 *  - RegisterSchoolController (public, self-serve — the school owner
 *    supplies their own password and gets a token back immediately).
 *  - PlatformSchoolController::store (super_admin-created — used when
 *    you're onboarding a school directly, e.g. during a sales call;
 *    generates a temporary password instead, same pattern as inviting
 *    staff).
 */
class SchoolRegistrationService
{
    public function __construct(protected SubscriptionService $subscriptionService) {}

    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $school = School::create([
                'name' => $data['school_name'],
                'slug' => Str::slug($data['school_name']) . '-' . Str::random(5),
                'email' => $data['school_email'] ?? null,
                'phone' => $data['school_phone'] ?? null,
                'address' => $data['school_address'] ?? null,
                'is_active' => true,
                'payment_reference_code' => 'SCH-'.Str::upper(Str::random(6)),
            ]);

            $this->subscriptionService->startTrial($school);

            $plainPassword = $data['admin_password'] ?? Str::random(10);

            $user = User::create([
                'school_id' => $school->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($plainPassword),
                'status' => 'approved',
            ]);

            $user->assignRole('proprietor');
            $user->accessibleSchools()->attach($school->id);

            DefaultGradeBoundarySeeder::seedFor($school->id);

            // Only email a temp password when one was actually
            // generated — self-registration supplies its own
            // password and gets a token back immediately, no email
            // needed for that path.
            if (! isset($data['admin_password'])) {
                $token = Password::broker()->createToken($user);
                $user->notify(new AccountSetupNotification($token, $school->name));
            }

            return [$school, $user, $plainPassword, ! isset($data['admin_password'])];
        });
    }
}
