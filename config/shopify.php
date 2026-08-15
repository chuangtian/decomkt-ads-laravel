<?php

return [
    'client_id' => env('SHOPIFY_CLIENT_ID'),
    'client_secret' => env('SHOPIFY_CLIENT_SECRET'),
    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),
    'scopes' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'SHOPIFY_SCOPES',
        'read_discounts,write_discounts,read_products',
    ))))),
    'app_url' => rtrim((string) env('SHOPIFY_APP_URL', env('APP_URL')), '/'),
];
