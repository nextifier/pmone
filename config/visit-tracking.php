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
     * unique-visitor figure for an earlier date would read as 1.
     *
     * Set to 21 Aug 2026: the event websites were rebuilt during the early hours
     * of the 20th, and the distinct IP count for post visits went from 1 to 34
     * within the hour. The 20th is a partial day, so the first day that is
     * trustworthy end to end is the 21st.
     *
     * A default rather than an env-only value on purpose. This is a fact about
     * the production data's history, identical in every environment, so leaving
     * it in .env meant it could vanish with a server rebuild and quietly take the
     * unique visitor metric with it. The env var stays as an override.
     */
    'visitor_ip_tracking_since' => env('VISIT_VISITOR_IP_TRACKING_SINCE', '2026-08-21'),

];
