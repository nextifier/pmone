# Plan 034: RSVP / invitation flow — build plan (follow-up to spike 025)

> Follow-up implementation plan produced by **spike 025**. Reuses as much of the
> existing free-order + attendee + custom-field machinery as possible; adds only
> the response/invitation layer that's genuinely missing. **Read spike 025 first.**

## Status

- **Priority**: P2 (private/corporate events, galas, invite-only conferences)
- **Effort**: L — build in the slices below
- **Risk**: MED
- **Depends on**: reuses the free-order path + attendees + custom fields; composes
  with 021 (event capacity) + 020 (waitlist)
- **Planned at**: `advisor/022-manifest-scale` base, 2026-07-14

## Product decision (from the operator)

**Full RSVP.** Not just invite-code-gated free tickets — the real thing: an invite
list, a recipient who responds **yes / no / maybe**, optional **+N guests**, the
ability to **change or decline** later, a **capacity cap** on acceptances, and a
**headcount** the host tracks. Free (no payment).

## Recommendation: a dedicated invitation model that REUSES the free-order path

The spike's extend-vs-new question resolves to **new `event_invitations` model, but
reuse everything downstream of "accept"**. Rationale (verified read-only):

- **Access codes are the wrong home for responses.** `AccessCodeBatch`
  (`name, kind, assigned_to, brand_id, notes`) + `access_codes` +
  `access_code_redemptions` model **purchase unlocks / redemptions** — a code either
  gates a hidden ticket or applies a discount. There is no response state, decline,
  guest count, or "change my answer". Overloading it would tangle two concepts.
- **But the "accept" side already exists.** `TicketPurchaseService::resolvePayment`
  confirms a **zero-total order inline** (no gateway → `status=Confirmed`, `paid_at`,
  consume access code, dispatch confirmation emails, issue attendees + QR). That IS
  "RSVP yes → issue badge(s)". And `generateAttendeesForBatch` / the bulk-generate
  comp path already creates free confirmed attendees in bulk.
- **Attendees are the headcount + QR unit**, and already carry polymorphic
  **`CustomFieldValue`** registration responses (`Attendee::customFieldValues()`,
  `AttendeeService::saveRegistrationResponses` over `event->registrationFields()`).
  RSVP questions (dietary, +1 name) reuse this verbatim. An RSVP "yes (+2 guests)" =
  3 attendees.

So: **new invitation/response layer → on "attending", issue attendees through the
existing free-order path.** Minimal new surface, maximal reuse.

## Data model

- **`event_invitations`** — `id, ulid, event_id, name, email, phone,
  respond_token (unique, magic-link), status (invited|attending|declined|maybe|
  waitlisted|cancelled), guest_count (int, default 0 → party size = 1 + guest_count),
  ticket_id (which comp ticket to issue on accept), ticket_order_id (nullable, set
  when accepted → the free order created), responded_at, invited_by, sent_at,
  reminded_at, metadata jsonb, timestamps, softDeletes`.
  Indexes: `unique(event_id, email)`, `unique(respond_token)`, `(event_id, status)`.
- Named guests, if needed, live as attendees + their `CustomFieldValue`s (no extra
  table) — the party's attendees are the guest records. Add
  `event_invitation_guests` only if guests must be tracked *before* acceptance.
- Toggle: **`events.rsvp_enabled`** (mirror `tickets_enabled` / the hotel PROJECT-level
  toggle pattern) gates the whole flow. An RSVP event is a free event whose tickets
  are issued via responses, not sales.

## RSVP state machine

```
invited ─┬─▶ attending   (capacity OK → issue 1+guest_count attendees via free order)
         ├─▶ maybe       (no attendees yet; holds no capacity)
         ├─▶ declined    (no attendees)
         └─▶ waitlisted  (accept when at event capacity → 020 waitlist)

attending ─┬─▶ declined   (release: refund the comp order → frees 021 capacity → 020 offer)
           ├─▶ attending  (change guest_count: add/void attendees to match party size)
           └─▶ (host) cancelled

maybe ─▶ attending | declined
declined ─▶ attending    (re-accept iff capacity still fits, else waitlisted)
```

Capacity + waitlist wiring:
- **Accept** checks event capacity via `Event::reserveHeadcount(1 + guest_count)`
  (021). Fits → issue attendees; doesn't fit → `waitlisted` (020).
- **Decline after accepting** → `refundOrder`/`refundAttendee` on the comp order
  (already releases `reserved_count` + fires `OfferWaitlistSeatsJob`, 020) and flips
  the invitation to `declined`. The release plumbing already exists — the state
  machine just triggers it.
- **Change guest_count** → add attendees (reserve delta) or void attendees (release
  delta) to match the new party size, guarded by capacity.

## Respond flow (public)

- **`GET /public/invitations/{respond_token}`** → invite details + current status +
  the event's RSVP questions (`registrationFields`). Reuse the existing magic-link
  pattern (`/public/ticket-orders/magic/{token}` already exists).
- **`POST /public/invitations/{respond_token}/respond`** → `{response:
  attending|declined|maybe, guest_count, registration:{…}}` → runs the state
  transition; on `attending` issues the comp order + attendees (free-order path) and
  saves registration answers; on `declined` releases. Throttled + validated (mirror
  `validate-access-code`'s anti-brute-force throttle).
- Confirmation: reuse `dispatchConfirmationEmails` (the accepted party gets its QR
  e-tickets exactly like a free purchase).

## Host tools (admin)

- **Import invite list** (CSV → `event_invitations`), mirroring access-code batch
  generation (`AccessCodeService::generateBatch` is the template).
- **Send / resend invites** (queued mail jobs; `sent_at`/`reminded_at` audit),
  reusing the mail-job pattern.
- **RSVP breakdown** — status counts (invited/attending/declined/maybe/waitlisted) +
  headcount; reuse `AttendeeAnalyticsService` for the attending side.
- Admin CRUD on invitations, gated by `events.rsvp_enabled` + a `rsvp.manage`
  permission (`permissions:sync` after adding it to `config/permissions.php`).

## Build slices (land independently, in order)

1. **Model + toggle + host list management.** `event_invitations` migration + model +
   factory + `events.rsvp_enabled` + admin invite-list CRUD/CSV import. No public flow
   yet — the host can build a list.
2. **Respond flow + state machine.** Public `GET`/`POST respond` wired to the
   free-order/attendee path; state machine + capacity (021) + waitlist (020) hooks;
   RSVP questions via `registrationFields`/`CustomFieldValue`. This is the core.
3. **Host tooling.** Send/resend invites (mail) + RSVP breakdown analytics + reminders.

## Prototype (spike deliverable — throwaway, NOT merged)

Prove the reuse: a respond endpoint that, on `attending`, calls the existing
free-order path to issue `1 + guest_count` confirmed attendees, and on `declined`
voids them (releasing capacity). The proof to run in the spike:

- Accept with `guest_count = 2` → a zero-total order confirms inline and **3
  attendees** exist with QR tokens (reusing `resolvePayment`'s free branch /
  `generateAttendeesForBatch`). ✔ reuse works, no payment.
- Decline after accepting → the comp order is voided, `reserved_count` drops back,
  `OfferWaitlistSeatsJob` fires. ✔ release + waitlist reuse works.

This proves the invitation layer only needs the response state + a thin adapter onto
machinery that already exists — no new payment/attendee/QR code.

## Done criteria (per slice)
- [ ] Slice 1: `event_invitations` + `events.rsvp_enabled` + admin list CRUD/import + tests
- [ ] Slice 2: public respond flow + state machine + capacity/waitlist hooks + registration reuse
- [ ] Slice 3: send/resend invites + RSVP breakdown + reminders
- [ ] `php artisan permissions:sync` (new `rsvp.manage`); `php artisan migrate` (new table + toggle)
- [ ] `plans/README.md` row updated per slice

## STOP conditions
- If the real requirement turns out to be only "free tickets gated by an invite code"
  (no true responses/declines/guest counts), the existing free-order + access-code
  flow already suffices with host tooling only — **but the operator confirmed full
  RSVP**, so build the response model.
- Reuse the free-order/attendee/void machinery — do NOT fork a parallel attendee or
  payment path. The invitation layer owns *response state*; everything downstream of
  "accept" is the existing pipeline.

## Maintenance notes
- Declines must release capacity to the event cap (021) and offer to the waitlist
  (020) — the state machine emits those exactly as `refundAttendee` already does.
- A paid-RSVP hybrid (invite that then requires payment) is explicitly out of scope;
  if needed later, the `attending` transition would open a checkout instead of a
  free order — note it, don't build it now.
