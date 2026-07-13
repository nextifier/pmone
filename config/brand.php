<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brand Identity
    |--------------------------------------------------------------------------
    |
    | Each deployment of this codebase serves one brand (pmone, monara, or a
    | whitelabel client). The brand display name is config('app.name') and
    | URLs come from config('app.url') / config('app.frontend_url'); the keys
    | below cover brand values that have no other config home. Defaults keep
    | the historical PM One output so existing deployments are unaffected.
    |
    */

    'support_email' => env('BRAND_SUPPORT_EMAIL', 'support@pmone.id'),

    'ics_domain' => env('BRAND_ICS_DOMAIN', 'pmone.id'),

];
