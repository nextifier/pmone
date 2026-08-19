<?php

return [

    /*
     * The day article views stopped being counted server-side and started being
     * counted in the browser via POST /api/track/visit.
     *
     * Before this date, a row was written every time the event websites rendered
     * /news/{slug} — so the figure counted server renders (bots, prefetchers and
     * link previews included), not readers. From 23 Jul 2026 those renders were
     * served from the Cloudflare edge cache instead, which is why the old series
     * collapses days before the counter was replaced. The two sides of this date
     * are not comparable and the dashboard says so.
     */
    'browser_counting_since' => env('VISIT_BROWSER_COUNTING_SINCE', '2026-07-28'),

    /*
     * The day the event websites started forwarding the real visitor IP
     * (X-Forwarded-For) on tracking beacons.
     *
     * Until then every beacon arrived with the Cloudflare Worker's egress
     * address, so `visits.ip_address` held exactly one distinct value and any
     * unique-visitor figure for an earlier date would read as 1. Leave this null
     * until the 15 event websites are rebuilt; the dashboard hides the unique
     * visitor metric while it is unset.
     */
    'visitor_ip_tracking_since' => env('VISIT_VISITOR_IP_TRACKING_SINCE'),

];
