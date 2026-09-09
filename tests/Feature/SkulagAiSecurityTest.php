<?php

use Illuminate\Support\Facades\Route;

it('keeps the Skulag AI endpoint authenticated and rate-limited', function () {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === 'api/ai/ask' && in_array('POST', $route->methods(), true));

    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain('auth:sanctum');
    expect($route->gatherMiddleware())->toContain('throttle:10,1');
});
