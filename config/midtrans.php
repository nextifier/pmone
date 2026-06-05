<?php

return [
    // Per-project Midtrans credentials (Server Key, Client Key) are stored
    // encrypted in the project_payment_gateways table. No global env fallback —
    // each project MUST register its own gateway via Settings → Payment Gateways.

    // Snap transaction expiry, in seconds. Aligned with xendit.invoice_duration
    // so the hosted payment window behaves the same regardless of provider.
    'expiry_duration' => 86400, // 24 hours

    'currency' => 'IDR',
];
