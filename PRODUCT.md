# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

**Event organizer staff** (`master`, `admin`, `staff`) are the primary users. They run Indonesian B2B exhibitions end to end: exhibitor and brand records, ticket products and orders, promo codes and promotion rules, rundown and programs, hotel allotments and reservations, event website content, partners, tasks, and internal email. Their work is continuous operational work across many concurrent events, not occasional configuration.

**Exhibitor PICs** (`exhibitor`) log into the same application to manage their own booth data, promotion posts, documents, and captured leads. They are outside the organizer's company, use the product occasionally, and have no training.

**Visitors / ticket buyers** (`visitor`) hold accounts under `/account` to see their tickets, orders, and hotel reservations.

**Field crew** work on-site during an event and use a narrow slice of the product: badge scanning, exhibitor lead capture, and quick lookups.

All four groups sign in to the same Nuxt admin application, separated by role and permission rather than by separate apps. Mobile is a first-class context for all of them, not a fallback: exhibitors, visitors, and field crew reach the product from a phone as a matter of course, and staff do on-site.

## Product Purpose

PM One is the operating system for an exhibition business. A single Laravel API holds the durable record of every project, event, exhibitor, ticket, order, reservation, and piece of published content, and that record simultaneously powers the staff dashboard, the exhibitor and visitor portals, and the public event websites that consume the API.

Success means an organizer can run a full event cycle inside one system: publish the event site, sell tickets, onboard exhibitors, take hotel reservations, and operate the show floor, without stitching a CMS, a ticketing vendor, a spreadsheet, and a scanning app together.

## Positioning

The value is the combination, not any single module, and the combination is what a neighboring tool cannot copy quickly:

1. **One backend, many public sites.** One exhibitor/content/ticket database directly drives each event's own public website (blog posts, exhibitors, tickets, rundown, gallery, partners, contact form, short links, sitemap). No separate CMS per event.
2. **Complete cycle through on-site.** Ticket sale, e-ticket with QR, hotel reservation, badge scan, and exhibitor lead capture live in the same system rather than across vendors.
3. **Shaped by how Indonesian B2B exhibitions actually work.** Booth and exhibitor-PIC structure, promotion posts, local payment gateways, hotel allotments per event, and co-located event clusters are modeled as first-class product concepts, not adapted from a generic event tool.

## Operating Context

- A **project** is an organizing entity (a media division or an organizer company); an **event** belongs to a project; an event has editions across years. Config that used to sit on the event is moving to project level where it is genuinely shared (hotel reservations, payment gateway, PDF branding).
- Events cluster and co-locate. Several events can share one project, one venue, and one exhibitor pool, so cross-event views are normal work, not an edge case.
- The public event websites are a separate Nuxt monorepo (`~/Frontend/pmone-events/`) consuming this API. Changes to public-facing data models are cross-repo events with a deploy and cache-purge cost.
- Work happens both in the office over weeks of preparation and on the show floor over a few intense days. The on-site window has no patience for slow, ambiguous, or desktop-only screens.
- Third parties consume the API directly through registered API consumers.

## Capabilities and Constraints

**Confirmed capabilities:** projects and events; brands/exhibitors with per-event pivots; tickets, orders, promo codes, and promotion rules; payment gateways per project with multi-currency work in progress; hotel reservations (global hotel → per-event allotment and pricing, gated by a project-level toggle that requires an active payment gateway); attendees, e-tickets, QR badges, scanning, and exhibitor leads; a form builder with 25 field types; posts/news with multi-language content, galleries, partners, programs, FAQ, guests, media coverage; short links and link pages; tasks; internal email and inbox; activity logs, web analytics, and user activity analytics; roles and granular permissions; API consumers; banners and appearance/theming.

**Constraints that future work must respect:**
- Roles and permissions gate everything; a screen serves several roles with different visible surfaces.
- Auth is Sanctum cookie-based and requires a verified email.
- Public read endpoints are response-cached with tag-based invalidation; anything that changes public data must invalidate correctly.
- The staff dashboard uses literal English copy; the exhibitor dashboard, shared `components/ui`, and the public event sites are internationalized.
- `components/ui` is kept identical across three repositories (this frontend, pmone-events, levenium) and changes must propagate.

**Open decisions:** multi-organizer is the direction, not yet a shipped fact. The brand layer (`frontend/brands/`) already carries a second brand, Monara, whose company identity and assets are placeholders. The project record also lists non-Panorama organizations (CampX, ASKINDO, Global AI Expo) whose relationship to the multi-tenant plan is not settled here.

## Brand Commitments

- The product is **PM One** (`pmone.id`, API at `api.pmone.id`), built for **Panorama Media Group** (`panoramamedia.co.id`), Jakarta.
- Shared code must stay brand-neutral. Brand identity is resolved through a `BRAND` environment variable and the `#brand` alias; no shared component may name a brand directly. This is what keeps the multi-organizer direction open.
- Language: the staff dashboard stays English-only. Exhibitor-facing and public surfaces support Indonesian and English plus Japanese, Korean, and Chinese for foreign exhibitors and visitors.
- Copy in the interface is English, in every surface, regardless of the language used to discuss the work.

## Evidence on Hand

- `PROJECTS.md` — the full, factual portfolio: 3 Panorama divisions (`pm`, `pe`, `pl`), 5 legal entities, 11 events with their categories, venues, and clusters. This is real data, not marketing copy, and is the source for anything naming an event.
- `frontend/brands/` — brand metadata, logos, and company/contact facts for PM One (assets ready) and Monara (assets not ready).
- Production data for all 11 events lives in the live database; local and production are separate.
- `docs/`, `plans/`, and the root planning documents record decided work; `.impeccable.md` holds the previously captured visual system.
- No testimonials, customer counts, pricing, benchmarks, or licensing terms exist. Do not invent them.

## Product Principles

1. **The record is the product.** Every surface is a view onto one authoritative database that also feeds public websites. Nothing may present a convenient local truth that diverges from it.
2. **Operational speed beats expression.** These are people doing repetitive work under deadline. Predictability, scanability, and consistency across 244 screens are worth more than any individual screen being memorable.
3. **Four audiences, one application.** Every screen must be legible to an untrained exhibitor and fast for a staff member who uses it fifty times a day. Permission-driven surfaces, not separate products.
4. **The phone is not a downgrade.** Any surface an exhibitor, visitor, or field crew member can reach must be fully usable on a phone, including on a show floor with poor light and one free hand.
5. **Stay tenant-neutral.** Nothing shared may assume Panorama Media. Brand-specific truth belongs in the brand layer.

## Accessibility & Inclusion

No formal standard (WCAG level or equivalent) has been established for this product yet — an open decision, not a decision to skip it. Two user needs are confirmed and binding: full mobile parity for every non-staff-only surface, and multi-language support on exhibitor-facing and public surfaces for foreign exhibitors and visitors.
