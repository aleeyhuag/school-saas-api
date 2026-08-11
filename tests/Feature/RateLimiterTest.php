<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

it('registers the api rate limiter', function () {
    $limiter = RateLimiter::limiter('api');

    expect($limiter)->not->toBeNull();

    $limits = $limiter(
        request()
    );

    expect($limits)->toBeInstanceOf(Limit::class);
});
