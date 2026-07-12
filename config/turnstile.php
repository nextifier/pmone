<?php

return [
    // Server-side secret for the Cloudflare Turnstile siteverify endpoint.
    // Never set directly here - the event website only ever needs the public
    // site key (its own runtime config), not this secret.
    'secret' => env('TURNSTILE_SECRET_KEY'),
];
