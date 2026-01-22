<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
        'http://localhost:5174',
    ],

    'allowed_origins_patterns' => [
        // Allow Docker internal network IPs
        '#^http://172\.\d+\.\d+\.\d+:\d+$#',
        // Allow all verifystaff subdomains in production
        // '#^https?://.*\.verifystaff\.com$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
