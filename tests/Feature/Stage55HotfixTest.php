<?php

use Illuminate\Support\Facades\Route;

it('has a protected storage health diagnostic route for super admins', function () {
    $matching = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === 'api/platform/diagnostics/storage-health' && in_array('GET', $route->methods(), true));

    expect($matching)->not->toBeNull();
    expect($matching->gatherMiddleware())->toContain('auth:sanctum');
    expect($matching->gatherMiddleware())->toContain('role:super_admin');
});

it('allows principal billing routes but keeps school-active enforcement on normal school routes', function () {
    $billing = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === 'api/billing/status' && in_array('GET', $route->methods(), true));

    expect($billing)->not->toBeNull();
    expect($billing->gatherMiddleware())->toContain('role:proprietor|principal');
    expect($billing->gatherMiddleware())->not->toContain('school.active');

    $school = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === 'api/school' && in_array('GET', $route->methods(), true));

    expect($school)->not->toBeNull();
    expect($school->gatherMiddleware())->toContain('school.active');
});

it('does not leave branch switching outside school-active enforcement', function () {
    foreach (['api/branches', 'api/branches/switch'] as $uri) {
        $method = $uri === 'api/branches' ? 'GET' : 'POST';
        $matching = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route) => $route->uri() === $uri && in_array($method, $route->methods(), true));

        expect($matching)->not->toBeNull();
        expect($matching->gatherMiddleware())->toContain('school.active');
    }
});
