<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\PaystackService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    public function __construct(protected SubscriptionService $subscriptionService) {}

    /**
     * Current subscription status + the bank details/reference code
     * a Proprietor needs to actually pay — everything the Billing
     * page needs in one call.
     */
    public function status()
    {
        $school = Auth::user()->school;
        $subscription = $school->subscription;

        return response()->json([
            'status' => $subscription?->status,
            'trial_ends_at' => $subscription?->trial_ends_at,
            'current_period_ends_at' => $subscription?->current_period_ends_at,
            'grace_ends_at' => $subscription?->grace_ends_at,
            'plan' => $subscription?->plan,
            'payment_reference_code' => $school->payment_reference_code,
            'bank_details' => [
                'bank_name' => config('billing.bank_name'),
                'account_name' => config('billing.account_name'),
                'account_number' => config('billing.account_number'),
            ],
            'paystack_enabled' => config('billing.paystack_enabled'),
            'recent_payments' => $school->payments()->latest()->limit(10)->get(),
        ]);
    }

    public function plans()
    {
        return response()->json(Plan::where('is_active', true)->orderBy('amount_kobo')->get());
    }

    /**
     * "I've made this transfer" — proof upload is required (confirmed
     * decision), not just a declared reference code.
     */
    public function submitPayment()
    {
        $validated = request()->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'proof' => ['required', 'image', 'max:5120'], // 5MB
        ]);

        $school = Auth::user()->school;
        $plan = Plan::findOrFail($validated['plan_id']);

        if (! $plan->is_active) {
            throw ValidationException::withMessages(['plan_id' => ['This plan is no longer available.']]);
        }

        $proofPath = request()->file('proof')->store('payment-proofs', 'local');

        $payment = $this->subscriptionService->submitBankTransferPayment($school, $plan, $proofPath);

        return response()->json([
            'message' => "Payment submitted for review. We'll confirm it against your transfer within 1-2 business days.",
            'payment' => $payment,
        ], 201);
    }

    /**
     * Not usable until BILLING_PAYSTACK_ENABLED=true — hidden on the
     * frontend until then too, but this check is what actually
     * enforces it.
     */
    public function initiatePaystack(PaystackService $paystack)
    {
        if (! config('billing.paystack_enabled')) {
            abort(404);
        }

        $validated = request()->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $user = Auth::user();
        $school = $user->school;
        $plan = Plan::findOrFail($validated['plan_id']);

        $result = $paystack->initializeTransaction($user->email, $plan->amount_kobo, [
            'school_id' => $school->id,
            'plan_id' => $plan->id,
        ]);

        $subscription = $this->subscriptionService->ensureSubscription($school);

        $referenceCode = $this->subscriptionService->ensurePaymentReferenceCode($school);

        Payment::create([
            'school_id' => $school->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'amount_kobo' => $plan->amount_kobo,
            'method' => 'paystack',
            'status' => 'pending_review',
            'reference_code' => $referenceCode,
            'paystack_reference' => $result['reference'],
        ]);

        return response()->json(['authorization_url' => $result['authorization_url']]);
    }
}
