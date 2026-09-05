<?php

namespace App\Services;

use App\Models\School;
use App\Models\ReferralPartner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Services\AccountActionTokenService;
use App\Notifications\AccountSetupNotification;

/**
 * Creates a school + its first admin account — Proprietor or
 * Principal, whichever the person actually registering turned out to
 * be. Most first contact with a new school is the Principal, not
 * necessarily an owner who's ever met in person, so this doesn't
 * assume Proprietor by default anymore; it's just the fallback when
 * no explicit choice is given (keeps this backward compatible for any
 * caller that doesn't pass 'admin_role' yet).
 *
 * Shared by:
 *  - RegisterSchoolController (public, self-serve — the school owner
 *    supplies their own password and gets a token back immediately).
 *  - PlatformSchoolController::store (super_admin-created — used when
 *    you're onboarding a school directly, e.g. during a sales call;
 *    generates a temporary password instead, same pattern as inviting
 *    staff).
 */
class SchoolRegistrationService
{
    public function __construct(protected SubscriptionService $subscriptionService, protected AccountActionTokenService $accountActionTokens) {}

    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $referralPartner = null;
            if (! empty($data['referral_code'])) {
                $referralPartner = ReferralPartner::where('referral_code', strtoupper(trim($data['referral_code'])))
                    ->where('status', 'active')
                    ->first();
                // An unrecognized or expired code is never a reason to
                // block someone from registering their school — it
                // just means no commission gets attributed.
            }

            $school = School::create([
                'name' => $data['school_name'],
                'slug' => Str::slug($data['school_name']) . '-' . Str::random(5),
                'email' => $data['school_email'] ?? null,
                'phone' => $data['school_phone'] ?? null,
                'address' => $data['school_address'] ?? null,
                'is_active' => true,
                'payment_reference_code' => 'SCH-'.Str::upper(Str::random(6)),
                'referred_by_partner_id' => $referralPartner?->id,
            ]);

            $this->subscriptionService->startTrial($school);

            $plainPassword = $data['admin_password'] ?? Str::random(10);
            $adminRole = $data['admin_role'] ?? 'proprietor';

            $user = User::create([
                'school_id' => $school->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($plainPassword),
                'status' => 'approved',
            ]);

            $user->assignRole($adminRole);
            $user->accessibleSchools()->attach($school->id);

            DefaultGradeBoundarySeeder::seedFor($school->id);

            // Only email a temp password when one was actually
            // generated — self-registration supplies its own
            // password and gets a token back immediately, no email
            // needed for that path.
            if (! isset($data['admin_password'])) {
                $token = $this->accountActionTokens->issue($user, 'account_setup');
                $user->notify(new AccountSetupNotification($token, $school->name));
            }

            return [$school, $user, $plainPassword, ! isset($data['admin_password'])];
        });
    }
}
