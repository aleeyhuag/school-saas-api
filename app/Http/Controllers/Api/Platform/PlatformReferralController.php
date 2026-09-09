<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\ReferralCommission;
use App\Models\ReferralPartner;
use App\Models\ReferralSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformReferralController extends Controller
{
    public function index()
    {
        return response()->json(
            ReferralPartner::withCount('referredSchools')
                ->withSum(['commissions as commission_total_kobo'], 'amount_kobo')
                ->withSum(['commissions as commission_paid_kobo' => fn ($q) => $q->where('status', 'paid')], 'amount_kobo')
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function store()
    {
        $validated = request()->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $partner = ReferralPartner::create($validated);

        return response()->json($partner, 201);
    }

    public function update(ReferralPartner $referralPartner)
    {
        $validated = request()->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
        ]);

        $referralPartner->update($validated);

        return response()->json($referralPartner->fresh());
    }

    public function show(ReferralPartner $referralPartner)
    {
        $referralPartner->load(['referredSchools:id,name,is_active,created_at,referred_by_partner_id']);

        $commissions = $referralPartner->commissions()
            ->with('school:id,name')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'partner' => $referralPartner,
            'commissions' => $commissions,
        ]);
    }

    public function destroy(ReferralPartner $referralPartner)
    {
        $validated = request()->validate([
            'confirm_name' => ['required', 'string'],
        ]);

        if ($validated['confirm_name'] !== $referralPartner->name) {
            throw ValidationException::withMessages([
                'confirm_name' => ["The name you typed does not match this partner's name exactly."],
            ]);
        }

        DB::transaction(function () use ($referralPartner) {
            // Referred schools survive partner deletion; their attribution
            // becomes null because referred_by_partner_id is nullOnDelete.
            // Commission rows are deliberately removed by cascade because
            // they are records owned by the deleted partner.
            $referralPartner->tokens()->delete();
            $referralPartner->delete();
        });

        return response()->json(['message' => 'Referral partner and partner-owned commission records have been permanently deleted.']);
    }

    public function markCommissionPaid(ReferralCommission $referralCommission)
    {
        abort_if($referralCommission->status === 'paid', 422, 'This commission is already marked paid.');
        $referralCommission->update(['status' => 'paid', 'paid_at' => now()]);
        return response()->json($referralCommission);
    }

    public function getSettings()
    {
        return response()->json(ReferralSetting::current());
    }

    public function updateSettings()
    {
        $validated = request()->validate([
            'year_one_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'year_two_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'enabled' => ['required', 'boolean'],
        ]);

        $settings = ReferralSetting::current();
        $settings->update($validated);
        return response()->json($settings);
    }
}
