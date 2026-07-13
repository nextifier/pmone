# Plan 025 (SPIKE): RSVP / invitation-only event flow

> **This is a design/spike plan.** RSVP is a distinct flow from the purchase
> pipeline. Its output is a model design that reuses as much of the existing
> attendee/access-code machinery as possible + a follow-up implementation plan.

## Status

- **Priority**: P2 (private/corporate events, galas, invite-only conferences)
- **Effort**: L (spike itself: M)
- **Risk**: MED
- **Depends on**: reuses the free-order path + access codes + attendees
- **Category**: direction / feature-gap
- **Planned at**: `24ea67e1`, 2026-07-12

## Why this matters

pmone models **purchasing** tickets, not **RSVP-ing** to an invitation. There is
no invitation/response model (verified: "invitation" appears only in an
`AccessCodeBatch` comment; no respond/decline/guest-list state). A true RSVP event
needs: an invite list, a recipient who responds **yes / no / maybe**, optionally
**+N guests**, the ability to **change or decline** later, a **capacity cap** on
acceptances, and a **headcount** the host can track — usually **free** (no
payment). The current free-ticket + access-code mechanism gets partway (a free
order confirms immediately; access codes gate who can claim) but does not model a
response state, declines, guest counts, or an invite list the host manages.

## What exists to reuse (assess in the spike)
- **Free-order path**: `TicketPurchaseService::resolvePayment` confirms a
  zero-total order inline (no gateway) — this is the "accept" side of an RSVP.
- **Access codes**: per-recipient invite-like tokens (`AccessCodeBatch` = "Gold
  Sponsor — 20 invitations") — close to an invite list but modeled as purchase
  unlocks, not responses.
- **Attendees**: the unit of headcount + QR; an RSVP "yes (+2 guests)" is 3
  attendees.
- **Custom fields / registration**: for RSVP questions (dietary, +1 name).

## What's missing (the design this spike must produce)
- An **invitation** concept: an invite list (recipients), each with a response
  state (`invited | attending | declined | maybe | waitlisted`), guest count, and
  a respond token/link. Decide: a new `event_invitations` table, or an extension
  of access codes + a response-state field on the attendee/order.
- A **respond flow** (public): open the invite link → respond yes/no/maybe → set
  guest count → (for "yes") issue attendee(s)/QR → confirmation; allow changing
  the response (decline after accepting releases capacity + feeds waitlist 020).
- **Capacity** on acceptances (ties to event capacity, 021).
- **Host tools**: import an invite list, send/resend invites, see the RSVP
  breakdown (reuse attendee analytics).

## Spike deliverables (READ-ONLY + a thin prototype)
1. A recommendation: **extend access codes + attendee response-state** vs a
   **dedicated invitation model** — with the reuse map (what the free-order +
   access-code + attendee machinery already gives you).
2. The RSVP state machine (invited → attending/declined/maybe → changed) and how
   "attending (+guests)" maps to attendees/QRs and event capacity.
3. A throwaway prototype of the respond endpoint (accept/decline) wired to the
   free-order/attendee path, to prove the reuse works.
4. A follow-up implementation plan (`plans/028-…`) in landable slices (model +
   respond flow → host invite management → analytics).

## Explicitly NOT in this spike
- A finished RSVP product or host-side invite UI.
- Payment (RSVP is free; if a paid RSVP hybrid is needed, note it separately).

## Done criteria (spike)
- [ ] A written extend-vs-new recommendation with the reuse map
- [ ] The RSVP state machine defined + its mapping to attendees/capacity
- [ ] A prototype proving accept/decline via the free-order/attendee path
- [ ] A sliced follow-up implementation plan file
- [ ] `plans/README.md` row updated

## STOP conditions
- If the requirement is really just "free tickets gated by an invite code" (not
  true responses/declines/guest-counts), the existing free-order + access-code
  flow may already suffice with only host-side tooling — confirm the real RSVP
  requirement before designing a new model.

## Maintenance notes
- Declines should release capacity to the event cap (021) and offer to the
  waitlist (020) — design the response state machine to emit those events.
