# Plan 033: Reserved / assigned seating — build plan (follow-up to spike 024)

> Follow-up implementation plan produced by **spike 024**. Reserved seating is
> the hardest concurrency problem in ticketing; this plan slices it into landable
> pieces on top of 016's proven atomic primitive. **Read spike 024 first.**

## Status

- **Priority**: P2 (concerts, theatres, seated galas/conferences)
- **Effort**: XL — build in the slices below, not at once
- **Risk**: HIGH (seat-level concurrency + a new inventory dimension)
- **Depends on**: 016 (atomic reservation — the seat-hold primitive), composes with
  020 (waitlist) + 021 (event capacity) + 032 (waiting room)
- **Planned at**: `advisor/022-manifest-scale` base, 2026-07-14

## Product decision (from the operator)

Current events (expos) are general-admission and need **no** seating. Build reserved
seating anyway, **flexible + best-practice**, so future concerts/conferences/theatres
can use it. So this is greenfield alongside the GA path — **the GA path is never
changed**; seated is an opt-in mode per ticket.

## What exists to build on (verified read-only)

- **No** seat/section/row/venue model anywhere (`app/Models` has none) — genuinely
  greenfield.
- **The atomic primitive already exists and is proven**: `Ticket::reserve($qty)` is a
  single conditional `UPDATE … WHERE (stock IS NULL OR sold_count + ? <= stock)`
  (first-wins, no app lock — `app/Models/Ticket.php`). Seat holds are the same
  pattern at row granularity.
- **`ticket_sessions`** already carry `capacity` + `booked_count` — i.e. "a section
  with capacity" is *already_ modeled. Full seat assignment is the new layer on top;
  the two compose (a session/time-slot can own a seat map).
- **Attendee** is the per-seat headcount + QR unit (`ticket_order_item_id`, `qr_token`,
  `cancelled_at`); a seated attendee gains a seat reference.
- **Event capacity** (021: `events.capacity` + `reserved_count`, `Event::reserveHeadcount`)
  and **waitlist** (020: `OfferWaitlistSeatsJob` on release) are the release/backfill hooks.

## Concurrency design — PROVEN in the spike

The seat hold is `Ticket::reserve`'s conditional UPDATE at seat granularity, extended
to reclaim an expired hold in the same statement (so a new buyer never waits for the
sweep):

```sql
UPDATE event_seats
   SET status = 'held', held_by = :token, held_until = now() + interval '10 min'
 WHERE id = :seat_id
   AND (status = 'available'
        OR (status = 'held' AND held_until < now()));
-- affected rows = 1 → you hold it; 0 → someone else holds it (report as taken)
```

**Spike prototype result (throwaway, SQLite in-memory, run then deleted):**
- Two buyers race the same seat → exactly one `UPDATE` affects 1 row, the other 0;
  the winner is recorded. ✔ first-wins, no double-hold.
- An expired hold is reclaimable by a new buyer in the same conditional. ✔
- Convert hold→sold is guarded `WHERE status='held' AND held_by=:token` — a
  non-holder's convert affects 0 rows. ✔ no cross-buyer theft at checkout.

Multi-seat orders = N independent conditional holds; if any returns 0, release the
ones you got and return "seats X, Y already taken". **No cross-seat lock needed** —
the same property that makes 016 safe.

## Data model (finalize in slice 1–2)

Static, reusable inventory (the physical map):
- **`venues`** — `id, ulid, project_id (nullable = global), name, address, timezone,
  metadata jsonb, timestamps, softDeletes`. Reusable across events. Mirror the
  Hotel-global pattern if a venue must be shared across projects.
- **`seat_maps`** — `id, ulid, venue_id, name, version, layout jsonb (SVG/coords for
  render), metadata`. A venue may have several configurations.
- **`seat_sections`** — `id, seat_map_id, name, color, order_column`.
- **`seat_rows`** — `id, seat_section_id, label, order_column`.
- **`seats`** — `id, ulid, seat_row_id, label, pos_x, pos_y, seat_type
  (standard|accessible|companion), attributes jsonb`. The static seat.

Per-event availability (the live inventory — where concurrency lives):
- **`event_seats`** — `id, event_id, seat_id, ticket_id (which ticket/price category
  this seat sells as), status (available|held|sold|blocked), held_by (order/session
  token), held_until, attendee_id (nullable, set on sold), price_override`.
  Indexes: `unique(event_id, seat_id)`, `(event_id, status)`, `(held_until)` for the
  sweep, `(ticket_id)`. This table is generated when an event arms a map.

Ticket linkage:
- Add `tickets.seating_mode` (`general|reserved`, default `general`) — or a
  `settings` flag to avoid a migration on the hot table; a reserved ticket's `stock`
  is *derived* from `count(event_seats where ticket_id = this)`, and `sold_count`
  still tracks sold seats (so 021 event-capacity + analytics keep working unchanged).
- A seated `TicketOrderItem` maps to its seat(s): `attendees.event_seat_id`
  (nullable) is the cleanest — one attendee = one seat, QR encodes the seat.

## Seat-map availability delivery (don't hammer the DB)

The picker must show near-live availability at scale without a query per render:
- **Cached snapshot + delta** — serve a cached `{seat_id: status}` snapshot (short
  TTL) plus a "changed since a version" delta feed. **Reuse the plan-022 pattern**
  (keyset/version cursor + tagged deltas) — it's the same shape (status changes since
  a cursor), and 022 is already built + tested.
- Upgrade path: SSE/websocket push only if polling proves insufficient (measure
  first — realtime is a heavier, separate slice).

## Build slices (land independently, in order)

1. **Static inventory + admin map builder.** venues/seat_maps/sections/rows/seats
   models + factories + an admin seat-map editor (or a CSV/JSON importer for a
   venue's seat chart). Zero concurrency — pure CRUD. Ship-able alone.
2. **Per-event seat inventory.** `event_seats` generation from a map + a ticket→section
   price mapping + admin "arm this map for this event". `tickets.seating_mode`. Still
   no live holds — availability is computed, not raced.
3. **Holds (the concurrency-critical slice — LOAD-TEST like 016).** The atomic
   hold/reclaim primitive above + a `held_until` sweep job (returns abandoned holds to
   `available` and fires `OfferWaitlistSeatsJob`, 020). k6 contention test mirroring
   016's (the spike proved correctness; the load test proves it at scale).
4. **Checkout integration.** A seated `createOrder` path: hold → confirm → `UPDATE …
   SET status='sold', attendee_id=… WHERE status='held' AND held_by=:token`; partial
   failure releases the rest and 422s with the taken seats. Wire `attendees.event_seat_id`,
   QR encodes the seat, and `refundAttendee`/void releases the seat (status→available +
   waitlist offer). Reuse the existing order/attendee/void machinery — **GA path
   untouched**.
5. **Seat-map UI.** Public seat picker (snapshot + 022-style delta), hold timer,
   admin overrides/blocks (`status='blocked'` for house seats/kills).

## Prototype (spike deliverable — DONE, throwaway, not merged)

Written as a self-contained Pest test on a scratch `proto_event_seats` table
(SQLite in-memory), **run green (2/2, 8 assertions), then deleted** — it touched no
production schema. It proved the three concurrency properties above (first-wins,
expired-hold reclaim, holder-only convert). Re-create it as the real slice-3 test
against `event_seats` when that table lands.

## Done criteria (per slice; the feature is the sum)
- [ ] Slice 1: static seat models + admin map builder/importer + factories/tests
- [ ] Slice 2: `event_seats` generation + `seating_mode` + arm-map admin flow
- [ ] Slice 3: atomic hold/reclaim + sweep + **k6 contention load test** + waitlist hook
- [ ] Slice 4: seated checkout (hold→sold), attendee↔seat, QR, void-releases-seat
- [ ] Slice 5: public seat picker (snapshot + 022-delta) + admin blocks
- [ ] `plans/README.md` row updated per slice

## STOP conditions
- **Do NOT change the GA path.** Seated is opt-in via `seating_mode='reserved'`; every
  existing expo keeps its quantity-based flow byte-for-byte.
- Build slice 3 on 016's atomic primitive (proven) — never an app-side
  lock-then-check; that reintroduces the race 016 removed.
- **Load-test slice 3 before any real seated on-sale** — the spike proved *correctness*
  under a 2-buyer race; production concurrency (thousands for the good seats) needs the
  k6 gate, same as 016's still-owed load test.
- If a future need is really only "priced sections without seat numbers", that's the
  existing `ticket_sessions` capacity pool — don't build full seat-level inventory for it.

## Maintenance notes
- Seat holds + waitlist (020) + waiting room (032) are the concert stack: a released
  seat → waitlist offer; admission via the waiting room; seat assignment here. The
  hold-release event should emit to the waitlist exactly like `refundAttendee` does.
- `event_seats.held_until` sweep + the in-statement expired-hold reclaim are belt-and
  -suspenders: the reclaim keeps buyers moving without waiting for the sweep; the
  sweep guarantees eventual waitlist offers + clean state.
