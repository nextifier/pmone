# Plan 023 (SPIKE): Virtual waiting room / on-sale queue

> **This is a design/spike plan, not a build-everything plan.** Its output is a
> decision + a thin prototype + a follow-up implementation plan — not a finished
> feature. Do not have a cheap executor "just build a waiting room" from this.

## Status

- **Priority**: P2 (for concert-grade on-sales)
- **Effort**: L–XL (spike itself: M)
- **Risk**: HIGH if built wrong
- **Depends on**: conceptually pairs with 016/017 (throughput) — a waiting room
  throttles arrivals so the purchase path (raised by 016/017) drains a controlled
  rate
- **Category**: direction / architecture
- **Planned at**: `24ea67e1`, 2026-07-12

## Why this matters

Even with 016 (atomic inventory) and 017 (async checkout), a true concert on-sale
(tens of thousands arriving in the same second for a few thousand seats) needs a
**waiting room**: admit users into the purchase flow in controlled batches, show a
fair position/queue, and shed the rest gracefully instead of letting them all
hammer the API and self-DoS. This is the single biggest gap for concert-scale.
Building it wrong (e.g. a naive DB-backed queue on the same Postgres) makes things
worse.

## The decision this spike must make (build vs buy)

| Option | What it is | Trade-off to evaluate |
|--------|------------|-----------------------|
| **A. Cloudflare Waiting Room (buy)** | pmone-events already sits behind Cloudflare (CF Pages, Access, cache per project notes). CF Waiting Room is a config-level product that queues at the edge, before requests reach the origin. | Least code, edge-native, protects the origin entirely. Cost + per-event configuration + does it integrate with per-ticket on-sale timing? Evaluate whether it can be armed per-event/per-on-sale. |
| **B. Build a token/queue service (build)** | A Redis-backed queue issuing time-boxed admission tokens; the order endpoint requires a valid token; a small SPA shows position. | Full control + integrates with ticket data; but it's a real distributed-systems build (fairness, token TTL, abandonment, Redis HA) and must NOT run on the purchase Postgres. |
| **C. Rate-limit + async only (no room)** | Rely on 016/017 + `throttle` + a friendly "try again" page. | Cheapest; acceptable for medium on-sales, inadequate for a true stampede (no fairness, users refresh-storm). |

## Spike deliverables (this plan's actual work — READ-ONLY + a throwaway prototype)

1. **Confirm the CF setup**: does pmone-events run behind Cloudflare with a plan
   tier that includes Waiting Room? (Check the project's CF usage notes / ask ops.)
   Can it be armed per-event on a schedule? This likely decides A vs B.
2. **Define the admission contract**: if built (B) or if CF passes a token, how
   does the order endpoint verify admission? Sketch the token shape + where it's
   checked (a middleware on `POST /public/ticket-orders`).
3. **Fairness + abandonment model**: position assignment, admission batch size
   (tie to the per-ticket clearing rate from 016), token TTL, what happens on
   abandonment/refresh.
4. **A thin prototype** (in a throwaway branch, clearly marked spike) of the
   admission-token middleware + a fake queue, to prove the integration point —
   NOT a production queue.
5. **Output a follow-up implementation plan** (`plans/026-…`) for the chosen
   option, with the concrete build steps.

## Explicitly NOT in this spike
- Building a production queue service.
- Any change to the real purchase path beyond a spike-flagged middleware stub.

## Done criteria (spike)
- [ ] A written build-vs-buy recommendation grounded in the actual CF setup
- [ ] A defined admission-token contract + the integration point identified
- [ ] A throwaway prototype proving the integration (marked spike, not merged)
- [ ] A follow-up implementation plan file written for the chosen option
- [ ] `plans/README.md` row updated with the recommendation

## STOP conditions
- If CF Waiting Room is available and can be armed per-event, STOP the build
  investigation and recommend A (buy) — do not build B just because it's more fun.
- Do not touch the production order path except a clearly-flagged spike stub.

## Maintenance notes
- A waiting room is only as good as the rate it feeds; size the admission batch to
  016's measured per-ticket clearing rate (needs the 016 load test first).
