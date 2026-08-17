<?php

return [

    'paths' => [
        'api/*',
        'auth/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    // A single FRONTEND_URL isn't enough anymore — the same Netlify
    // site is reachable at multiple hostnames (skulag.com.ng,
    // admin.skulag.com.ng, and likely more subdomains later), and each
    // one is a genuinely different origin as far as the browser's CORS
    // check is concerned. FRONTEND_URL is kept as an explicit allow
    // (covers local/staging URLs that don't match the pattern below),
    // and allowed_origins_patterns covers every skulag.com.ng
    // subdomain without needing a code change each time a new one is
    // added.
    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL'),
        env('APP_ENV') === 'local' ? 'http://localhost:5173' : null,
    ])),

    'allowed_origins_patterns' => array_values(array_filter([
        // Matches https://skulag.com.ng and https://<anything>.skulag.com.ng
        // (admin., docs., any future subdomain). If the production
        // domain ever changes, update the literal domain here too.
        env('APP_ENV') !== 'local' ? '#^https://([a-z0-9-]+\.)*skulag\.com\.ng$#' : null,
    ])),

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Content-Disposition',
    ],

    'max_age' => 0,

    'supports_credentials' => false,

];