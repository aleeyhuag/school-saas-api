<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaystackService;
use App\Services\SubscriptionService;
use Illuminate\Http\Response;

class PaystackWebhookController extends Controller
{
    public function handle(PaystackService $paystack, SubscriptionService $subscriptionService)
    {
        $rawBody = request()->getContent();
        $signature = request()->header('x-paystack-signature');

        if (! $paystack->verifyWebhookSignature($rawBody, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], Response::HTTP_UNAUTHORIZED);
        }

        $event = request()->input('event');
        $reference = request()->input('data.reference');

        if ($event === 'charge.success' && $reference) {
            $payment = Payment::where('paystack_reference', $reference)
                ->where('status', 'pending_review')
                ->first();

            if ($payment) {
                // No human reviewed this — Paystack itself is the
                // source of truth for a card charge succeeding.
                $subscriptionService->confirmPayment($payment, null);
            }
        }

        // Always 200 — Paystack retries on anything else, and there's
        // nothing useful to do differently for event types we don't
        // handle yet.
        return response()->json(['received' => true]);
    }
}
