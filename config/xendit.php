<?php

return [
    // Per-project Xendit credentials are stored encrypted in
    // project_payment_gateways table. No global env fallback — each project
    // MUST register its own gateway via Settings → Payment Gateways.
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
