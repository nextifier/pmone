<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted redirect hosts
    |--------------------------------------------------------------------------
    |
    | After a payment, the guest is redirected back to the ORIGINATING site
    | (the event website they booked on, or the admin). The booking proxy sends
    | its own `origin` (siteUrl) with the reservation; the backend only honours
    | it when its host is in this allowlist, otherwise it falls back to
    | config('app.frontend_url'). This prevents open-redirect abuse.
    |
    | Hosts only (no scheme/port). Keep in sync with the event-website siteUrls
    | in the pmone-events apps' nuxt configs. Override per-environment via
    | PAYMENT_TRUSTED_REDIRECT_HOSTS (comma-separated).
    |
    */

    'trusted_redirect_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PAYMENT_TRUSTED_REDIRECT_HOSTS', implode(',', [
            'pmone.id',
            'cafebrasserieexpo.com',
            'campx.id',
            'cokelatexpo.id',
            'franchise-expo.co.id',
            'global-ai-expo.pages.dev',
            'iicc.askindo.id',
            'indocoffeefestival.com',
            'indonesiaanimecon.com',
            'indonesiacomiccon.com',
            'indooutingexpo.co.id',
            'keramika.co.id',
            'megabuild.co.id',
            'morefoodexpo.com',
            'panoramaevents.id',
            'panoramamedia.co.id',
            'renex.megabuild.co.id',
            // Local development
            'localhost',
            'pmone.test',
        ]))),
    ))),

];
