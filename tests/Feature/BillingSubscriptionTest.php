<?php

use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('runs the billing schema in the test database', function () {
    expect(Schema::hasTable('schools'))->toBeTrue()
        ->and(Schema::hasTable('subscriptions'))->toBeTrue()
        ->and(Schema::hasTable('payments'))->toBeTrue();
});

it('repairs a school that has no subscription and no payment reference before payment', function () {
    $school = School::create([
        'name' => 'Billing Test School',
        'slug' => 'billing-test-school',
        'is_active' => true,
        'payment_reference_code' => null,
    ]);

    $plan = Plan::create([
        'name' => 'Monthly',
        'amount_kobo' => 100000,
        'duration_months' => 1,
        'billing_interval' => 'monthly',
        'is_active' => true,
    ]);

    $service = app(SubscriptionService::class);

    expect($school->subscription)->toBeNull();

    $payment = $service->submitBankTransferPayment(
        $school,
        $plan,
        'payment-proofs/test.jpg',
    );

    $school->refresh();

    expect($payment->school_id)->toBe($school->id)
        ->and($payment->subscription_id)->not->toBeNull()
        ->and($payment->reference_code)->not->toBeEmpty()
        ->and($payment->status)->toBe('pending_review')
        ->and($school->payment_reference_code)->toBe($payment->reference_code)
        ->and(Subscription::where('school_id', $school->id)->count())->toBe(1);
});

it('reuses an existing subscription and payment reference', function () {
    $school = School::create([
        'name' => 'Existing Billing School',
        'slug' => 'existing-billing-school',
        'is_active' => true,
        'payment_reference_code' => 'SCH-EXIST01',
    ]);

    $subscription = Subscription::create([
        'school_id' => $school->id,
        'status' => 'trialing',
        'trial_ends_at' => now()->addDays(7),
    ]);

    $plan = Plan::create([
        'name' => 'Monthly',
        'amount_kobo' => 100000,
        'duration_months' => 1,
        'billing_interval' => 'monthly',
        'is_active' => true,
    ]);

    $payment = app(SubscriptionService::class)->submitBankTransferPayment(
        $school,
        $plan,
        'payment-proofs/test-existing.jpg',
    );

    expect($payment->subscription_id)->toBe($subscription->id)
        ->and($payment->reference_code)->toBe('SCH-EXIST01');
});
