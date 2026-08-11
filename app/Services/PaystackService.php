<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Deliberately a thin wrapper around plain HTTP calls rather than a
 * third-party Paystack package — the surface area needed here
 * (initialize a transaction, verify it, check a webhook signature)
 * is small enough that a direct REST call is simpler and more
 * transparent than pulling in a dependency for it.
 *
 * Not usable until BILLING_PAYSTACK_ENABLED=true and real API keys
 * are set — every method here throws if called while disabled, so a
 * misconfigured deploy fails loudly instead of silently no-op'ing.
 */
class PaystackService
{
    protected function secretKey(): string
    {
        $key = config('billing.paystack_secret_key');

        if (! config('billing.paystack_enabled') || ! $key) {
            throw new RuntimeException('Paystack is not enabled — set BILLING_PAYSTACK_ENABLED=true and PAYSTACK_SECRET_KEY once the account is approved.');
        }

        return $key;
    }

    /**
     * Starts a checkout — returns the URL to redirect the Proprietor
     * to. $reference is generated here and must be stored by the
     * caller (on the Payment row) to match up the later webhook call.
     */
    public function initializeTransaction(string $email, int $amountKobo, array $metadata = []): array
    {
        $reference = 'PSK-'.Str::upper(Str::random(12));

        $response = Http::withToken($this->secretKey())
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $email,
                'amount' => $amountKobo,
                'reference' => $reference,
                'callback_url' => config('app.frontend_url').'/proprietor/settings/billing?paystack=callback',
                'metadata' => $metadata,
            ])
            ->throw()
            ->json();

        return [
            'reference' => $reference,
            'authorization_url' => $response['data']['authorization_url'] ?? null,
        ];
    }

    public function verifyTransaction(string $reference): array
    {
        return Http::withToken($this->secretKey())
            ->get("https://api.paystack.co/transaction/verify/{$reference}")
            ->throw()
            ->json();
    }

    /**
     * Paystack signs every webhook with HMAC-SHA512 of the raw body
     * using your secret key — this must match the `x-paystack-
     * signature` header exactly, or the request isn't really from
     * Paystack and must be rejected.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        if (! $signatureHeader) {
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, config('billing.paystack_secret_key') ?? '');

        return hash_equals($expected, $signatureHeader);
    }
}
