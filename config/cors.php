<?php

return [
    'paths' => [
        'api/v1/shopify/*',
    ],

    'allowed_methods' => ['GET', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'https://extensions.shopifycdn.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
    ],

    'exposed_headers' => [
        'Content-Type',
    ],

    'max_age' => 86400,

    'supports_credentials' => false,
];
