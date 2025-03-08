<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS'
    ],

    'allowed_origins' => [
        env('FRONTEND_URL', 'https:'),
        // Add your production URL when deploying
        // env('PRODUCTION_FRONTEND_URL'),
    ],

    'allowed_origins_patterns' => [
        // If you need to allow subdomains, you can add patterns here
        // 'http://*.yourdomain.com',
    ],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-XSRF-TOKEN',
        'X-CSRF-TOKEN',
    ],

    'exposed_headers' => [
        'Authorization',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    'max_age' => 60 * 60, // 1 hour in seconds

    'supports_credentials' => true,
];
