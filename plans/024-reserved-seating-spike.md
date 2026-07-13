# Plan 024 (SPIKE): Reserved / assigned seating (seat maps)

> **This is a design/spike plan.** Reserved seating is a large product feature and
> the hardest concurrency problem in ticketing. Its output is a data-model +
> concurrency design + a follow-up implementation plan — not a built feature.

## Status

- **Priority**: P2 (concerts, theatres, galas with assigned seats)
- **Effort**: XL (spike itself: M–L)
- **Risk**: HIGH
- **Depends on**: 016 (atomic reservation is the primitive seat holds build on)
- **Category**: direction / feature-gap
- **Planned at**: `24ea67e1`, 2026-07-12

## Why this matters

pmone ticketing is **general-admission only** — quantity-based, no seat selection
(verified: no seat/section/row/seat_map model anywhere). Concerts, theatres, and
seated galas need to pick a specific seat (section → row → seat), see a live seat
map, and hold seats during checkout. This is a genuine architectural addition, and
seat-level concurrency (thousands trying to grab the same good seats) is harder
than GA quantity: each seat is a unit of inventory with a hold timer.

## The design this spike must produce

### Data model (sketch, to be finalized)
- `venues` / `seat_maps` (reusable across events), `sections`, `rows`, `seats`
  (with attributes: type, price category, accessibility).
- `event_seats` (per-event availability of each seat) with a status
  (`available | held | sold`), `held_until`, `held_by` (session/order).
- How ticket types map to seat categories/pricing.

### Concurrency model (the hard part)
- **Seat hold on selection**: when a buyer clicks a seat, atomically flip it
  `available → held` with a short `held_until` (e.g. 5–10 min), tied to their
  session. Use the same atomic-conditional-update primitive as 016
  (`UPDATE event_seats SET status='held', held_until=…, held_by=… WHERE id=… AND status='available'`).
- **Hold expiry sweep** returns abandoned holds to `available` (and feeds the
  waitlist, 020).
- **Checkout converts holds → sold** atomically; partial failures release.
- **The seat map view** must show near-live availability without hammering the DB
  (cache + a lightweight availability feed / websocket vs polling — evaluate).

### Integration
- How this coexists with the existing GA `TicketOrderItem`/`Attendee` model (a
  seated order item references an `event_seat`; the attendee's QR encodes the seat).
- Whether `TicketSession` (the existing capacity primitive) is reused or replaced.

## Spike deliverables (READ-ONLY + throwaway prototype)
1. A concrete data-model proposal (tables + relationships + how it hangs off
   `Event`/`Ticket`/`Attendee`).
2. A concurrency design for seat holds using 016's atomic primitive, with the
   hold-timer + sweep + waitlist interplay.
3. A seat-map availability-delivery decision (cached snapshot + delta vs realtime).
4. A throwaway prototype (spike branch) of the atomic seat-hold + hold-expiry, to
   prove no double-hold under contention.
5. A follow-up implementation plan (`plans/027-…`) broken into landable slices
   (model → holds → checkout integration → seat-map UI).

## Explicitly NOT in this spike
- A finished seat-map UI or a production seat-inventory system.
- Changing the GA path.

## Done criteria (spike)
- [ ] Written data-model + concurrency design doc (in the plan or a linked doc)
- [ ] Prototype proving atomic seat-hold has no double-hold under contention
- [ ] A sliced follow-up implementation plan file
- [ ] `plans/README.md` row updated

## STOP conditions
- If the product only ever needs "sections with capacity" (not specific seats),
  that's far cheaper (a section is a GA pool) — confirm the real requirement
  (assigned seats vs seated sections) BEFORE designing full seat-level inventory.
- Build on 016's atomic primitive; if 016 isn't landed, the hold concurrency has
  no safe foundation — note the dependency.

## Maintenance notes
- Seat holds + waitlist (020) + waiting room (023) together are the concert stack;
  design them to compose (a released seat → waitlist offer; admission via the
  waiting room).
