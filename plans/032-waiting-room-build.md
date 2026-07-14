# Plan 032: Virtual waiting room — platform-first build (follow-up to spike 023)

> Follow-up implementation plan produced by **spike 023** (spike file removed; its
> outcome is in the README "spikes 023/024/025" execution log), **revised 2026-07-14
> to platform-first**. PM One is a product sold to many event organizers for events
> of every type and scale, so a big-event capability must be **built into the platform
> and ready before any big on-sale** — not assembled reactively when a concert
> client shows up. This plan builds the waiting room as a **standing, tenant-wide
> platform feature**. This plan is self-contained.

## Status

- **Priority**: P1 for the platform's scale story (was P2 as a single-organizer nicety)
- **Effort**: L (built-in engine) + optional M (Cloudflare edge layer)
- **Risk**: MED — must fail-open (normal events are never queued) + must be
  load-tested before any real on-sale
- **Depends on**: 016 (atomic inventory) + 017 (async checkout) + 018 (queue/DB
  hardening) already deployed. **Prerequisite gate: the 016 k6 load test** (still
  owed) sizes the admission rate.
- **Planned at**: `advisor/022-manifest-scale` base, 2026-07-14 (revised)

## Recommendation (revised) — build the built-in engine; Cloudflare is optional edge

The spike's original "buy Cloudflare" call was framed for a single organizer who
rarely needs it. For a **platform reseller** the calculus flips:

- **Cloudflare Waiting Room is per-zone (per-domain), paid (Business plan tier).**
  With one domain per organizer/event and a constantly growing customer base,
  requiring every client domain to carry a paid CF plan is expensive to buy and
  awkward to provision. It cannot be the platform's *baseline* scale guarantee.
- **A built-in, application-level admission engine serves every tenant uniformly**,
  with no per-domain fee, full control, and admission that can be tied to the
  event's real inventory/drain rate. This is the right architectural home for a
  capability the product *promises to every customer*.

So: **the primary waiting room is built in (Redis-backed).** Cloudflare Waiting Room
stays as an **optional premium edge layer** for enterprise-scale on-sales (strictly
stronger — it queues before traffic reaches the origin — sold as a higher tier). The
**origin integration is identical either way** (one `waiting-room` middleware, a
driver switch), so adding the CF layer later is cheap.

## Architecture — the built-in engine

Everything below the "admitted" line reuses the already-hardened purchase path
(016/017/018). The waiting room only controls **who is let in, and how fast**.

### 1. Admission control (driver-agnostic)
- Middleware alias `waiting-room` (class `EnsureAdmitted`) on the purchase entry
  points: `POST /public/ticket-orders` (currently `throttle:10,1` + `tickets-enabled`),
  plus `…/tickets/…availability` + `…/preview-pricing`.
- **Fail-open**: an event with no armed on-sale window (`events.waiting_room_enabled
  = false` or outside `on_sale_starts_at`) passes untouched — the default expo path is
  byte-identical to today. This is the core safety property.
- When armed, require a valid **admission token**; else `403 WAITING_ROOM_NOT_ADMITTED`
  + queue info, so the frontend sends the user to the queue.
- Drivers behind one interface: `redis` (built-in, default), `cloudflare` (verify the
  CF WR JWT — premium edge), `null` (always-admit — disables the feature).

### 2. The Redis queue engine (the built-in driver)
- **Enqueue**: on arrival to an armed on-sale, issue a signed **queue token** and
  place it in a per-event **Redis sorted set** (score = arrival timestamp → FIFO
  fairness). Per-event key namespace isolates events from each other.
- **Admission worker** (scheduled/looped): promotes the head of the queue into an
  **admitted set** at a controlled **drain rate**, minting a short-TTL HMAC admission
  token. The middleware admits requests whose token is in that set and unexpired.
- **Status endpoint** `GET /public/events/{event}/queue/status` → `{state:
  queued|admitted, position, estimated_wait, token}`. **Redis-only reads, never
  Postgres** — this is what lets it absorb a flood without touching the order DB.
- **Fairness + abandonment**: idempotent re-entry (same session keeps its place on
  refresh — no line-jumping, no refresh-storm reward); token + queue TTL recycle
  abandoned slots.
- **Anti-bot**: gate queue entry with Turnstile (019, already integrated) so bots
  can't flood the queue itself.

### 3. Isolation & reliability (non-negotiable for a platform)
- The queue lives in a **separate Redis connection/instance** from Horizon's job
  queue (018), so a queue flood never starves job processing or the order path.
- That Redis needs **persistence + HA (replica/failover)** — if it hiccups mid
  on-sale, queue state (positions, admissions) must not vanish.
- **Monitoring**: live queue length, admission rate, admitted count, error rate —
  a platform-admin view (optionally surfaced to the organizer).

### 4. Config model
- `config/waiting_room.php`: `default_driver` (redis|cloudflare|null), queue Redis
  connection name, default drain rate + token/queue TTLs.
- Per-event: `events.waiting_room_enabled`, `events.on_sale_starts_at`, a per-event
  **drain-rate override**, and an optional per-event **driver** (an enterprise event
  can use `cloudflare` while everyone else uses the built-in `redis`).

### 5. Drain rate = measured, not guessed
Admit N buyers per interval where N ≈ the purchase path's **load-tested** clearing
rate (the 016 k6 gate). A waiting room that feeds faster than the purchase path
drains just moves the stampede inside the gate — so the load test is a hard
prerequisite, not a nicety.

## Cloudflare — optional premium edge layer (later, enterprise tier)
- Same `waiting-room` middleware, driver `cloudflare`: verify the CF Waiting Room
  JWT so a user who skips the queue page and hits the API directly is still rejected.
- Ops: arm a scheduled CF Waiting Room on the enterprise event's zone (CF API), with
  same-zone API routing so admission is enforced end-to-end (each event site is
  already its own CF zone — verified). Sold as a higher plan.

## Build sequence (the work order)

> **Phase 0 is a prerequisite gate for sizing; Phases 1–3 are the built-in feature;
> Phase 4 is the optional edge layer; Phase 5 is the GA hardening gate.**

- **Phase 0 — Measure (prerequisite).** Run the owed 016 k6 load test against a
  production-like stack → the purchase path's real throughput (tickets/sec drained
  without errors). This number sizes the admission rate. *Also surfaces any
  pgbouncer/DB ceiling (018 ops TODO) before it bites during a real on-sale.*
- **Phase 1 — Admission plumbing (backend, driver-agnostic).** `config/waiting_room.php`
  + `events.waiting_room_enabled` / `on_sale_starts_at` migration + the
  `waiting-room` middleware (fail-open) + the driver interface with a `null` driver.
  Tests: non-armed event byte-identical to today; armed + no token → 403; armed +
  valid token → pass.
- **Phase 2 — Redis queue engine (the built-in driver).** Enqueue (sorted set) +
  admission worker (drain rate) + admitted-set TTL tokens + status endpoint
  (Redis-only) + idempotent re-entry + Turnstile at entry + separate Redis
  connection. Tests: FIFO order, re-entry keeps place, drain rate respected, expired
  tokens rejected, per-event isolation.
- **Phase 3 — Frontend + organizer controls.** Thin queue page (position + wait +
  auto-advance) on the checkout entry; handle `WAITING_ROOM_NOT_ADMITTED` → queue
  page; admin per-event toggle + on-sale time + drain-rate override + live queue
  monitor.
- **Phase 4 — Cloudflare edge layer (optional, enterprise).** `cloudflare` driver
  (verify WR JWT) + per-zone arming runbook (CF API) + same-zone API routing.
- **Phase 5 — Hardening + GA gate.** Separate Redis HA + persistence + monitoring/
  alerting + an **end-to-end k6 load test of queue + purchase together** (sized by
  Phase 0) + an on-sale operations runbook.

## Done criteria
- [ ] Phase 0: 016 k6 load test run; measured drain rate recorded
- [ ] Phase 1: `waiting-room` middleware + config + toggle; fail-open verified (non-armed = identical to today)
- [ ] Phase 2: Redis queue engine (FIFO, drain rate, TTLs, isolation) on a separate Redis
- [ ] Phase 3: queue page + organizer controls + live monitor
- [ ] Phase 4 (optional): `cloudflare` driver + per-zone arming runbook
- [ ] Phase 5: Redis HA + monitoring + end-to-end load test + on-sale runbook
- [ ] `plans/README.md` row updated per phase

## STOP conditions
- The middleware **MUST fail-open** for non-armed events — a bug here queues every
  expo. Gate behind an explicit per-event flag + active on-sale window; test it first.
- **Load-test before any real on-sale** (Phase 0 sizes it, Phase 5 proves the whole
  path). Correctness of a queue only shows under real concurrency — never ship it on
  "it compiled".
- The queue's Redis MUST be **separate** from the purchase DB/Horizon Redis, or a
  queue flood drags down the very path it protects.

## Maintenance notes
- Waiting room (this) + waitlist (020) + seat holds (024/033) are the concert stack:
  admission throttles arrivals, waitlist backfills releases, seat holds assign
  inventory. Design them to compose.
- The built-in engine is the platform baseline every tenant gets; Cloudflare is an
  upsell for the rare giant on-sale — one middleware, one driver switch, so the
  premium tier is cheap to add once the baseline exists.
