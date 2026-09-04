<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\ReferralCommission;
use App\Models\ReferralPartner;
use App\Models\ReferralSetting;
use Illuminate\Validation\Rule;

/**
 * Super Admin's referral management: partners, their commission
 * history, and the global commission rate. Attribution itself
 * (which school came from which partner) happens at registration —
 * see SchoolRegistrationService — and commission is earned at
 * SubscriptionService::confirmPayment(); this controller only reads
 * and administers what those two produced.
 */
class PlatformReferralController extends Controller
{
    public function index()
    {
        $partners = ReferralPartner::withCount('referredSchools')
            ->withSum(['commissions as commission_total_kobo'], 'amount_kobo')
            ->withSum(['commissions as commission_paid_kobo' => fn ($q) => $q->where('status', 'paid')], 'amount_kobo')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($partners);
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
        ]);

        $referralPartner->update($validated);

        return response()->json($referralPartner);
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

    /**
     * Marks one commission as paid out to the partner — a manual
     * bookkeeping action (Skulag doesn't move money to partners
     * automatically), same spirit as confirming a school's bank
     * transfer payment.
     */
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
