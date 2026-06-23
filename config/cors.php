<?php

return [

    /*
     * CORS for the SPA (Next.js). In production the SPA is served from the
     * same parent domain, so CORS mostly matters for local development
     * (next dev on localhost:3000 → API on localhost:8000).
     * Override origins via CORS_ALLOWED_ORIGINS (comma-separated).
     */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:3000,http://127.0.0.1:3000'
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Required for cookie-based (Sanctum stateful) auth.
    'supports_credentials' => true,

];
