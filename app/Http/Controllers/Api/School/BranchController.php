<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolGroup;
use App\Services\DefaultGradeBoundarySeeder;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Multi-branch support for a Proprietor. Every other role stays tied
 * to exactly one school (users.school_id, unchanged) — only a
 * Proprietor account can have more than one accessible School via
 * the proprietor_school_access pivot. Switching branches is
 * literally just updating THIS user's school_id to a different
 * value: every existing controller, every BelongsToSchool-scoped
 * query, everything already reads Auth::user()->school_id, so none
 * of that had to change for this feature to work.
 */
class BranchController extends Controller
{
    /**
     * Every branch this Proprietor can switch into, flagging which
     * one is currently active.
     */
    public function index()
    {
        $user = Auth::user();

        $branches = $user->accessibleSchools()
            ->select('schools.id', 'schools.name', 'schools.is_active')
            ->orderBy('schools.name')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'is_active' => $s->is_active,
                'is_current' => $s->id === $user->school_id,
            ]);

        return response()->json(['branches' => $branches]);
    }

    /**
     * Adds a new branch under this Proprietor. The first time this
     * is called, their EXISTING school has no group yet — one is
     * created here and their current school is folded into it
     * retroactively, rather than requiring a school to have been set
     * up as "multi-branch" from day one.
     */
    public function store(SubscriptionService $subscriptionService)
    {
        $user = Auth::user();

        $validated = request()->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'group_name' => ['nullable', 'string', 'max:150'],
        ]);

        // Serializes concurrent "add branch" requests for THIS
        // proprietor — the previous approach here only checked
        // "was an identical branch created in the last 15 seconds"
        // right before inserting, which is a check-then-act pattern:
        // if two requests land close enough together, both can pass
        // that check before either has committed its INSERT, and you
        // get two (or three) branches from what was meant to be one
        // submission. A real lock closes that race properly instead
        // of narrowing the window.
        $lock = Cache::lock("add-branch:{$user->id}", 15);

        if (! $lock->get()) {
            return response()->json([
                'message' => 'A branch is already being added for your account — please wait a moment and check the branch menu before retrying.',
            ], 429);
        }

        try {
            return DB::transaction(function () use ($validated, $user, $subscriptionService) {
                $currentSchool = School::findOrFail($user->school_id);

                if ($currentSchool->school_group_id) {
                    $group = $currentSchool->schoolGroup;
                } else {
                    $group = SchoolGroup::create([
                        'name' => $validated['group_name'] ?? ($currentSchool->name.' Group'),
                        'created_by' => $user->id,
                    ]);
                    $currentSchool->update(['school_group_id' => $group->id]);
                }

                // Belt-and-suspenders — this row should already exist
                // from registration, but doesn't hurt to make sure
                // before relying on it for the switcher.
                $user->accessibleSchools()->syncWithoutDetaching([$currentSchool->id]);

                $branch = School::create([
                    'school_group_id' => $group->id,
                    'name' => $validated['name'],
                    'slug' => Str::slug($validated['name']).'-'.Str::random(5),
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'is_active' => true,
                    // Every other school-creation path (self-serve
                    // registration, Super Admin "add school") sets
                    // this and starts a trial — this one didn't,
                    // which is exactly why submitting a payment for a
                    // brand-new branch threw "Attempt to read
                    // property 'id' on null": $school->subscription
                    // was genuinely null, there was never a row.
                    'payment_reference_code' => 'SCH-'.Str::upper(Str::random(6)),
                ]);

                $subscriptionService->startTrial($branch);

                $user->accessibleSchools()->attach($branch->id);

                DefaultGradeBoundarySeeder::seedFor($branch->id);

                return response()->json([
                    'message' => "{$branch->name} added. Switch to it from the branch menu to start setting it up.",
                    'branch' => $branch,
                ], 201);
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Makes a different accessible branch the active one. Nothing
     * fancier than updating school_id on this user's own row — every
     * request from here on (including this very response) reads the
     * new value fresh, no token reissue needed.
     */
    public function switchBranch()
    {
        $user = Auth::user();

        $validated = request()->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
        ]);

        $hasAccess = $user->accessibleSchools()->where('schools.id', $validated['school_id'])->exists();

        if (! $hasAccess) {
            abort(403, "You don't have access to that branch.");
        }

        $user->update(['school_id' => $validated['school_id']]);
        $user->load('school');

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'school_id', 'status']),
            'school' => $user->school,
            'roles' => $user->getRoleNames(),
        ]);
    }
}
