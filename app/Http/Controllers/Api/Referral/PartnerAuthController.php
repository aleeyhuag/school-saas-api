<?php

namespace App\Http\Controllers\Api\Referral;

use App\Http\Controllers\Controller;
use App\Models\ReferralPartner;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PartnerAuthController extends Controller
{
    public function register()
    {
        $v = request()->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:referral_partners,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $partner = ReferralPartner::create([
            ...$v,
            'email' => strtolower(trim($v['email'])),
            'password' => Hash::make($v['password']),
            'status' => 'active',
        ]);

        $token = $partner->createToken('partner-web')->plainTextToken;

        return response()->json([
            'partner' => $partner->only([
                'id', 'name', 'email', 'phone', 'bank_name', 'account_name', 'account_number',
                'referral_code', 'status', 'created_at',
            ]),
            'token' => $token,
        ], 201);
    }

    public function login()
    {
        $v = request()->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $partner = ReferralPartner::where('email', strtolower(trim($v['email'])))->first();

        if (! $partner || ! $partner->password || ! Hash::check($v['password'], $partner->password) || $partner->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['The referral partner credentials are incorrect or the account is inactive.'],
            ]);
        }

        $partner->tokens()->delete();

        return response()->json([
            'partner' => $partner->only([
                'id', 'name', 'email', 'phone', 'bank_name', 'account_name', 'account_number',
                'referral_code', 'status', 'created_at',
            ]),
            'token' => $partner->createToken('partner-web')->plainTextToken,
        ]);
    }

    public function me()
    {
        $partner = request()->user();
        abort_unless($partner instanceof ReferralPartner, 403, 'Referral partner authentication required.');

        return response()->json([
            'partner' => $partner->only([
                'id', 'name', 'email', 'phone', 'bank_name', 'account_name', 'account_number',
                'referral_code', 'status', 'created_at',
            ]),
            'referred_schools' => $partner->referredSchools()
                ->select('id', 'name', 'is_active', 'created_at')
                ->latest()
                ->get(),
            'commissions' => $partner->commissions()->with('school:id,name')->latest()->get(),
        ]);
    }

    public function updateProfile()
    {
        $partner = request()->user();
        abort_unless($partner instanceof ReferralPartner, 403, 'Referral partner authentication required.');

        $validated = request()->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
        ]);

        if (array_key_exists('account_number', $validated) && $validated['account_number'] !== null) {
            $validated['account_number'] = preg_replace('/\s+/', '', trim($validated['account_number']));
        }

        $partner->update($validated);

        return response()->json([
            'message' => 'Partner details updated.',
            'partner' => $partner->fresh()->only([
                'id', 'name', 'email', 'phone', 'bank_name', 'account_name', 'account_number',
                'referral_code', 'status', 'created_at',
            ]),
        ]);
    }

    public function logout()
    {
        abort_unless(request()->user() instanceof ReferralPartner, 403, 'Referral partner authentication required.');
        request()->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }
}
