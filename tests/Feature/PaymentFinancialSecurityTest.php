<?php

use App\Models\Payment;
use App\Models\PaymentReview;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function stage49School(string $name): School
{
    return School::create([
        'name' => $name,
        'slug' => strtolower(str_replace(' ', '-', $name)),
        'is_active' => true,
        'payment_reference_code' => 'SCH-'.strtoupper(substr(md5($name), 0, 6)),
    ]);
}

function stage49Plan(): Plan
{
    return Plan::create([
        'name' => 'Stage 49 Monthly',
        'amount_kobo' => 1300000,
        'duration_months' => 1,
        'billing_interval' => 'monthly',
        'is_active' => true,
    ]);
}

it('stores the server-side plan amount and duration on a payment', function () {
    $school = stage49School('Payment Snapshot School');
    $plan = stage49Plan();

    $payment = app(SubscriptionService::class)->submitBankTransferPayment(
        $school,
        $plan,
        'payment-proofs/stage49.jpg',
    );

    expect($payment->amount_kobo)->toBe(1300000)
        ->and($payment->duration_months)->toBe(1);
});

it('rejects a second pending payment for the same school', function () {
    $school = stage49School('Duplicate Payment School');
    $plan = stage49Plan();
    $service = app(SubscriptionService::class);

    $service->submitBankTransferPayment($school, $plan, 'payment-proofs/one.jpg');

    expect(fn () => $service->submitBankTransferPayment(
        $school,
        $plan,
        'payment-proofs/two.jpg',
    ))->toThrow(ValidationException::class);

    expect(Payment::where('school_id', $school->id)->count())->toBe(1);
});

it('records a payment review and confirms a payment only once', function () {
    $school = stage49School('Review Audit School');
    $plan = stage49Plan();
    $payment = app(SubscriptionService::class)->submitBankTransferPayment(
        $school,
        $plan,
        'payment-proofs/review.jpg',
    );

    $reviewer = \App\Models\User::create([
        'school_id' => null,
        'name' => 'Stage 49 Reviewer',
        'email' => 'stage49-reviewer@example.test',
        'password' => bcrypt('password'),
        'status' => 'approved',
    ]);
    $reviewer->assignRole('super_admin');

    $service = app(SubscriptionService::class);
    $service->confirmPayment($payment, $reviewer->id);
    $service->confirmPayment($payment->fresh(), $reviewer->id);

    $subscription = Subscription::where('school_id', $school->id)->firstOrFail();

    expect($payment->fresh()->status)->toBe('success')
        ->and(PaymentReview::where('payment_id', $payment->id)->count())->toBe(1)
        ->and($subscription->status)->toBe('active')
        ->and($subscription->current_period_ends_at)->not->toBeNull();
});

it('records a rejection reason in the payment review audit', function () {
    $school = stage49School('Rejection Audit School');
    $plan = stage49Plan();
    $payment = app(SubscriptionService::class)->submitBankTransferPayment(
        $school,
        $plan,
        'payment-proofs/rejected.jpg',
    );

    $reviewer = \App\Models\User::create([
        'school_id' => null,
        'name' => 'Stage 49 Rejector',
        'email' => 'stage49-rejector@example.test',
        'password' => bcrypt('password'),
        'status' => 'approved',
    ]);
    $reviewer->assignRole('super_admin');

    app(SubscriptionService::class)->rejectPayment(
        $payment,
        $reviewer->id,
        'The receipt does not clearly show the transaction amount.',
    );

    $review = PaymentReview::where('payment_id', $payment->id)->firstOrFail();

    expect($payment->fresh()->status)->toBe('rejected')
        ->and($review->action)->toBe('rejected')
        ->and($review->reason)->toContain('transaction amount');
});
