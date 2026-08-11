<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_merge(
        ['http://localhost:5173'],
        // FRONTEND_URL is unset locally (falls back to the dev
        // server above) and set to your real Netlify/custom domain
        // in production — see render.yaml. Kept as '*' as a fallback
        // only for the pilot's first deploy; tighten this to just
        // FRONTEND_URL once you've confirmed the real domain works,
        // since '*' means literally any website can call this API.
        [env('FRONTEND_URL')],
        [env('APP_ENV') === 'production' ? null : '*']
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // 'Content-Disposition' exposed so the frontend's file-download
    // helper (src/utils/download.js) can read the server's suggested
    // filename — without this, the browser can't see that header on
    // a cross-origin response at all, and every download falls back
    // to a filename with no extension.
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,

];
