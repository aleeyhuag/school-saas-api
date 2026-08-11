<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Manual Bank Transfer Details
    |--------------------------------------------------------------------------
    |
    | Shown to a Proprietor on the Billing page as the primary (today,
    | only) way to pay while the Paystack account is under review.
    |
    */

    'bank_name' => env('BILLING_BANK_NAME', ''),
    'account_name' => env('BILLING_ACCOUNT_NAME', ''),
    'account_number' => env('BILLING_ACCOUNT_NUMBER', ''),

    /*
    |--------------------------------------------------------------------------
    | Paystack
    |--------------------------------------------------------------------------
    |
    | Flip BILLING_PAYSTACK_ENABLED to true once the Paystack account
    | is approved — this alone reveals the "Pay with card" option
    | alongside bank transfer on the Billing page. No other code
    | change needed; PaystackService and the webhook endpoint are
    | already built and ready.
    |
    */

    'paystack_enabled' => env('BILLING_PAYSTACK_ENABLED', false),
    'paystack_secret_key' => env('PAYSTACK_SECRET_KEY'),
    'paystack_public_key' => env('PAYSTACK_PUBLIC_KEY'),

];
