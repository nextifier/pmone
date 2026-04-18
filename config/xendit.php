<?php

return [
    'secret_key' => env('XENDIT_SECRET_KEY'),
    'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
    'is_production' => env('XENDIT_IS_PRODUCTION', false),
    'invoice_duration' => 86400, // 24 hours in seconds
    'currency' => 'IDR',

    // Per-invoice URL is overridden by each module (hotel, ticket, etc.).
    // Default fallback derives from FRONTEND_URL so env stays DRY.
    'success_redirect_url' => env(
        'XENDIT_SUCCESS_REDIRECT_URL',
        rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/').'/payment/success'
    ),
    'failure_redirect_url' => env(
        'XENDIT_FAILURE_REDIRECT_URL',
        rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/').'/payment/failure'
    ),
];
