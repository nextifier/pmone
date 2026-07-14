# Plan 032: Virtual waiting room — build plan (follow-up to spike 023)

> Follow-up implementation plan produced by **spike 023**. The spike's job was
> the build-vs-buy decision + the admission contract; this plan is the concrete
> build. **Read spike 023 first.** STOP conditions from 023 are carried down.

## Status

- **Priority**: P2 (concert-grade on-sales only)
- **Effort**: M (Option A) / L–XL (Option B fallback)
- **Risk**: MED (must fail-open so normal events are never queued)
- **Depends on**: 016 (atomic inventory) + 017 (async checkout) already deployed —
  the waiting room only throttles *arrivals*; the drain rate is 016/017's job.
- **Planned at**: `advisor/022-manifest-scale` base, 2026-07-14

## Spike 023 outcome — recommendation

**Build vs buy → BUY (Cloudflare Waiting Room), with a small origin-side guard.**
Grounded in the actual deploy topology (verified read-only):

- Every event website is its **own Cloudflare zone** (per-domain), deployed on CF
  Pages. Confirmed in `~/Frontend/pmone-events/docs/cloudflare-cache-rule.md`
  ("Rule identik untuk setiap zone / domain event", per-zone API loop) and the
  per-app `nuxt.config.ts` domain map.
- CF is already the platform for edge concerns here: **Turnstile** (bot protection,
  plan 019 — `docs/cloudflare-turnstile.md`), **Cache Rules** (Workers-CPU trim),
  **Access** (staging block). CF-native is the established pattern; a bespoke
  Redis queue would be the *only* non-CF piece of edge infrastructure.
- CF **Waiting Room** is a zone-level product that supports **scheduled Waiting
  Room Events** (per-event start/end, custom queueing, session renewal). That maps
  1:1 onto per-event on-sale timing — exactly the "can it be armed per-event on a
  schedule?" question the spike had to answer. **Yes, it can.**

So Option A wins the spike's own STOP condition ("if CF Waiting Room is available
and can be armed per-event, recommend buy — don't build B for fun"). Option B (a
Redis token queue) is fully designed below as the **fallback only if the plan tier
doesn't include Waiting Room or the cost is rejected**.

### The one blocking fact ops must confirm (STOP)

CF Waiting Room requires a **Business plan** (or an Enterprise plan / paid add-on)
**per zone**. With ~11–16 event zones, confirm:

1. Which plan tier each event zone is on today (dashboard → each zone → Overview).
2. Whether Waiting Room is included or an add-on, and the per-zone cost.
3. Whether only a *few* zones ever run concert-grade on-sales (arm WR only on those
   — you don't need it on every expo zone).

**Do not start the Option A build until 1–3 are answered.** If WR is unavailable or
rejected on cost, switch to Option B (below). The rest of Option A assumes it's
available.

## The integration problem the spike solved (why a naive arming fails)

The event page (e.g. `megabuild.id`, its own zone) and the order API
(`POST /public/ticket-orders` on the **PM One API host**) are **different origins**.
CF Waiting Room on the event zone queues users before they load the *page*, but the
browser then calls the API host directly — which the event-zone Waiting Room does
**not** cover. Two clean ways to make admission end-to-end:

- **A1 (recommended): same-zone API path.** Front the public order/availability
  endpoints under the event zone (a CF Pages Function / Worker route on
  `megabuild.id/api/*` that proxies to the PM One API), and arm the Waiting Room on
  the event zone covering **both** the on-sale page path and `…/api/public/
  ticket-orders`. Admitted users clear one queue; their WR session cookie is valid
  for every same-zone request. No cross-site cookie problem.
- **A2 (defense-in-depth): verify the WR JWT at the origin.** CF signs the Waiting
  Room cookie as a JWT. A Laravel middleware on `POST /public/ticket-orders` can
  verify it (CF public key / shared secret) and reject requests without a valid
  admission claim for that event. Pairs with A1 so a user who skips the page and
  hits the API directly is still rejected during an armed on-sale.

The origin integration point is identical for Option B — a `waiting-room` middleware
on the same route — so the backend work is the same shape either way.

## Admission-token contract (the reusable backend piece)

A middleware alias `waiting-room` (class `EnsureAdmitted`) on
`POST /public/ticket-orders` (and `POST /public/tickets/…availability` +
`preview-pricing`):

1. Resolve the Event from the request (same resolver as `tickets-enabled`).
2. **Fail-open** when the event has no armed on-sale window (`events.waiting_room_
   enabled = false` or no active window) — normal events are never gated. This is
   the safety property: the default path is untouched.
3. When armed, require proof of admission:
   - **Option A**: a valid CF Waiting Room JWT (cookie/header), claims match the
     event + not expired.
   - **Option B**: a Redis-issued admission token (opaque, HMAC-signed, event-scoped,
     TTL-bounded) presented as a header; verified against Redis.
4. On failure → `403` with `error_code: WAITING_ROOM_NOT_ADMITTED` and a hint the
   frontend uses to send the user (back) to the queue.

Config: `config/waiting_room.php` (`driver = cloudflare|redis|null`, CF JWT verify
key, Redis connection, default TTLs). `events.waiting_room_enabled` +
`events.on_sale_starts_at` gate arming. Fail-open when `driver = null`.

## Fairness + abandonment model (sizes the admission rate)

- **Admission batch size** ties to 016's *measured per-ticket clearing rate* (needs
  the 016 k6 load test — carried-forward gate). Admit N buyers per interval where N ≈
  the rate the purchase path drains without contention errors.
- **Position** — Option A: CF assigns + renders it (no code). Option B: Redis sorted
  set by arrival timestamp; a lightweight SPA polls position.
- **Token/session TTL** — a short admission window (e.g. 10–15 min, matching the
  order `payment_expires_at`); on expiry the buyer re-queues.
- **Abandonment / refresh** — Option A: CF handles refresh-storms at the edge (the
  whole point). Option B: idempotent position (re-entering with the same session id
  keeps your place); a refresh doesn't jump the queue.

## Build slices

### Option A (CF Waiting Room) — recommended
1. **Ops/config** (no app code): arm a scheduled Waiting Room on the target
   event zone(s), covering the on-sale page path (+ the same-zone API path from A1).
   Document per-zone in an ops runbook (mirror `docs/cloudflare-cache-rule.md`'s
   per-zone API-token approach).
2. **A1 same-zone API routing**: a CF Pages Function/Worker on the event zone that
   proxies `…/api/public/{ticket-orders,tickets/*availability,…}` to the PM One API,
   preserving CORS/CSRF/Sanctum contracts. (Investigate: pmone-events may already
   proxy some `/api` calls — reuse that path if so.)
3. **A2 origin guard** (`waiting-room` middleware, fail-open): verify the CF WR JWT
   on the order POST. `config/waiting_room.php` driver `cloudflare`.
4. **Frontend**: an armed event's checkout entry routes through the WR page; a
   `WAITING_ROOM_NOT_ADMITTED` 403 sends the user to the queue. Mostly config +
   a small guard on the checkout route.

### Option B (Redis token queue) — fallback only
1. Redis-backed queue service (sorted-set positions, HMAC admission tokens, TTL,
   idempotent re-entry) on a **separate Redis** from the purchase path (never the
   order Postgres/Redis under load).
2. `waiting-room` middleware, driver `redis`, verifying the token.
3. A thin queue SPA (position + auto-advance on admission).
4. Admission worker that promotes the head of the queue at the 016-measured rate.

## Prototype (spike deliverable — throwaway, NOT merged)

The integration point is a fail-open middleware stub. A spike-flagged proof:

```php
// SPIKE ONLY — proves the integration point; do NOT merge.
Route::post('/public/ticket-orders', /* … */)
    ->middleware(['throttle:10,1', 'tickets-enabled', 'waiting-room']);

class EnsureAdmitted // spike stub
{
    public function handle(Request $r, Closure $next) {
        $event = $this->resolveEvent($r);
        if (! $event?->waiting_room_enabled) return $next($r);       // fail-open
        abort_unless($this->admitted($r, $event), 403,
            'WAITING_ROOM_NOT_ADMITTED');                             // armed → gate
        return $next($r);
    }
}
```

The proof to run in the spike: (a) with `waiting_room_enabled = false`, a normal
order POST passes untouched (fail-open); (b) with it armed and no token, the POST
403s; (c) with a valid token, it passes. This is a pure middleware test — no queue,
no CF — and confirms the ONLY origin change is a fail-open guard.

## Done criteria (build)
- [ ] Ops confirmed CF plan tier + Waiting Room availability per target zone (STOP gate)
- [ ] `waiting-room` middleware + `config/waiting_room.php` + `events.waiting_room_enabled`
- [ ] Fail-open verified: a non-armed event's order POST is byte-identical to today
- [ ] Armed-event admission enforced end-to-end (page + API) for the chosen option
- [ ] Admission batch size documented against 016's measured clearing rate
- [ ] `plans/README.md` row updated

## STOP conditions
- **Do not build until the CF plan tier is confirmed** (Option A) — if WR is
  unavailable/rejected, build Option B instead; don't half-arm A.
- The middleware MUST fail-open for non-armed events — a bug here queues every expo.
  Gate it behind an explicit per-event flag + an active on-sale window.
- Size the admission rate to 016's **load-tested** clearing rate — that load test is
  still owed (carried-forward gate from 016). A waiting room feeding faster than the
  purchase path drains just moves the stampede inside the gate.
```

## Maintenance notes
- Waiting room + waitlist (020) + seat holds (024/033) are the concert stack —
  admission (this) throttles arrivals, waitlist backfills releases, seat holds
  assign inventory. Design them to compose.
