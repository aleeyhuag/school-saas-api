<?php

use App\Models\School;
use App\Models\SchoolGroup;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('branch creation can use the injected subscription service', function () {
    $user = User::factory()->create([
        'school_id' => null,
    ]);

    $controller = app(\App\Http\Controllers\Api\School\BranchController::class);

    // The important regression is the closure capture in store():
    // `use ($validated, $user, $subscriptionService)`.
    $reflection = new ReflectionMethod($controller, 'store');
    $source = file_get_contents($reflection->getFileName());

    expect($source)
        ->toContain('use ($validated, $user, $subscriptionService)')
        ->and($source)->toContain('$subscriptionService->startTrial($branch)');
});

it('payment reference is stable and is not regenerated for renewal', function () {
    $school = School::factory()->create([
        'payment_reference_code' => 'SCH-STABLE1',
    ]);

    $service = app(SubscriptionService::class);

    $first = $service->ensurePaymentReferenceCode($school);
    $school->refresh();
    $second = $service->ensurePaymentReferenceCode($school);

    expect($first)->toBe('SCH-STABLE1')
        ->and($second)->toBe('SCH-STABLE1')
        ->and($school->payment_reference_code)->toBe('SCH-STABLE1');
});
