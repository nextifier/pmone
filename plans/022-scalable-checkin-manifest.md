# Plan 022: Scalable check-in manifest (paginated + delta sync for very large events)

> **Executor instructions**: Follow step by step; verify each; STOP on any STOP
> condition. Update `plans/README.md` when done.
>
> **BASE**: Modifies `ScanService::manifest` + `ScanController` + the offline
> scanner composable (Plan 005 already reworked the outbox/sync). Branch off the
> ticketing stack tip: `git checkout -b advisor/022-manifest-scale advisor/021-event-capacity` (or latest stack tip present). Skip the literal drift check.

## Status

- **Priority**: P3 (only large events; the offline design itself is already good)
- **Effort**: M
- **Risk**: MED (gate-scanner critical path)
- **Depends on**: ticketing stack (esp. 005 offline sync, 001 cancelled exclusion)
- **Category**: performance
- **Planned at**: `24ea67e1`, 2026-07-12

## Why this matters

The offline check-in design is sound, but the **initial manifest** does not scale
to very large events. `ScanService::manifest()` streams rows with `->lazy()` (good
for DB memory) but then `->map(...)->all()` materializes the FULL array and
returns every confirmed attendee in one response. For a 20k–50k-person conference,
that is a tens-of-MB JSON payload delivered to every scanner device on load, over
what may be a congested venue network — slow first load, high memory on cheap
scanner phones, and a full re-fetch whenever the manifest is refreshed.

### Trigger scenario

A 40k-attendee conference. 30 gate devices each pull the full manifest on startup
= 30 × ~30MB over the venue Wi-Fi at doors-open, plus a full re-pull each refresh.
First-scan readiness is delayed and low-end devices struggle to hold 40k entries.

### Mitigation options & trade-offs

| Option | What it does | Trade-off |
|--------|--------------|-----------|
| **A. Paginated manifest + delta sync (recommended)** | Manifest served in pages (cursor by attendee id) with an ETag/version; the device pulls pages once, then pulls only DELTAS (attendees added/cancelled/reissued since a cursor) on refresh, reusing the existing sync cursor mechanism (005 already pulls check-ins since a cursor). | The offline manifest becomes incrementally built; needs an `updated_at`/version cursor on attendees and a "changed since" query. More client bookkeeping. |
| **B. Compress + cache only** | Gzip the full manifest + cache per device. | Reduces bytes but still O(all attendees) memory + a full re-pull on any change. Half-measure. |
| **C. Server-side scan only (no offline)** | Drop offline for huge events. | Defeats the whole offline-first value at exactly the events (huge venues) where connectivity is worst. Rejected. |

This plan implements **A** (pages + deltas), keeping the offline-first contract.

## Current state

- `app/Services/Ticket/ScanService.php` `manifest(Event $scanEvent)` — builds
  `scannableEventIds`, queries confirmed attendees `->whereNull('cancelled_at')`
  (once 001 lands) `->with([...])->lazy()->map(fn ($a) => [...])->all()` — returns
  the full mapped array.
- `checkInsSince(Event, ?cursor)` already returns check-ins after a `scanned_at`
  cursor (the delta primitive for check-in state).
- `frontend/app/composables/useScanSession.ts` (Plan 005) — caches the manifest in
  IndexedDB, has an outbox + cursor-based `flushOutbox`/pull.

## Commands you will need

| Purpose | Command | Expected |
|---------|---------|----------|
| Targeted | `php artisan test --compact --filter=ManifestScale` | pass |
| Scan suite | `php artisan test --compact tests/Feature/Tickets/TicketScanTest.php` | 0 failures |
| Format | `vendor/bin/pint --dirty` | clean |

## Scope

**In scope:**
- `app/Services/Ticket/ScanService.php` — `manifestPage(Event, ?cursorId, int $limit)`
  (returns a page + next cursor + a manifest version) and
  `manifestChangesSince(Event, string $sinceIso)` (attendees added / cancelled /
  qr-rotated since a timestamp — needs an indexed `attendees.updated_at`).
- `app/Http/Controllers/Api/ScanController.php` — `GET …/scan/manifest` accepts
  `cursor`/`limit` and returns one page; a new `GET …/scan/manifest/changes?since=…`
  for deltas. Keep the old full-manifest response behind a small-event threshold
  or a query flag for backward compatibility.
- Migration: ensure `attendees.updated_at` is indexed (add index if missing) so
  the delta query is fast on 50k rows.
- Frontend (static-review-only): `useScanSession.ts` — page through the manifest on
  first load (store the cursor + version), then pull `/changes?since=` on refresh
  and merge into the cached manifest instead of re-fetching everything.
- New Pest `tests/Feature/Tickets/ManifestScaleTest.php`.

**Out of scope:**
- Changing the offline outbox/check-in logic (005 owns it).
- Real device/network load testing (note for ops).

## Steps

### Step 1: Paginated manifest
- `manifestPage` with keyset pagination (`where id > cursor order by id limit N`),
  returns rows + `next_cursor` + `version` (e.g. max updated_at). Controller wires
  `cursor`/`limit`.
- **Verify**: `--filter=ManifestScaleTest` — "paging through returns every confirmed attendee exactly once and terminates" passes.

### Step 2: Delta endpoint
- `manifestChangesSince` returns attendees with `updated_at > since` including
  newly-confirmed, newly-cancelled (001), and qr-rotated (reissue/transfer) — each
  tagged so the client can add/remove/update its cache. Ensure `attendees.updated_at`
  is indexed.
- **Verify**: `--filter=ManifestScaleTest` — "a newly-confirmed attendee, a cancelled attendee, and a reissued token all appear in the delta since a cursor" passes.

### Step 3: Frontend paging + delta merge (static-review-only)
- First load pages the manifest; refresh pulls `/changes?since=version` and merges
  (add new, drop cancelled, update rotated tokens) into the IndexedDB manifest.
- **Verify**: static review; record that browser + large-dataset verification is
  required before a big event.

## Test plan
- `ManifestScaleTest.php`: full paging coverage + terminates; delta captures
  add/cancel/rotate. Pattern: `tests/Feature/Tickets/TicketScanTest.php`.

## Done criteria
- [ ] `php artisan test --compact --filter=ManifestScale` — pass
- [ ] `php artisan test --compact tests/Feature/Tickets/TicketScanTest.php` — 0 failures
- [ ] `manifestPage` + `manifestChangesSince` exist and the routes accept cursor/since
- [ ] `attendees.updated_at` indexed (`php artisan migrate --pretend`)
- [ ] `vendor/bin/pint --dirty` clean; `git status` in-scope; frontend static-review-only noted
- [ ] `plans/README.md` row updated

## STOP conditions
- The delta approach can miss a change if `updated_at` isn't bumped on every
  relevant mutation (cancel, reissue, transfer) — audit those write paths (001,
  005, scan reissue) to confirm `updated_at` changes; if any uses `saveQuietly`
  or `forceFill(...)->save()` without touching timestamps, fix that or the delta
  is unreliable — STOP and report which path.
- Keep the full-manifest response working for small events (don't break existing
  scanners) — gate the new behavior.

## Maintenance notes
- The delta's correctness hinges on `updated_at` being bumped on every attendee
  mutation — document this invariant loudly; a missed bump = a scanner that
  admits a cancelled badge offline.
- Pairs with 005 (offline outbox) — same composable; land 005 first.
