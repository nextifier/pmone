# Plan 038: External participant check-in + host-assigned seating — build plan

> Authored 2026-08-20 from a prospective-client evaluation (Genius Math League).
> Read-only investigation only; no source was modified. This plan is self-contained.

## Status

- **Priority**: P1 while the client engagement is live, P2 otherwise
- **Effort**: L — 13 developer-days for full scope; 7 days for the shippable core
- **Risk**: MED (new scan-resolution path on a hot, well-tested engine)
- **Depends on**: 022 (scalable check-in manifest — DONE/merged; the offline substrate
  this plan extends). Composes with, but does not touch, 033 and 034.
- **Planned at**: `main` @ `094d98b0`, 2026-08-20

## Context

A prospective client runs an inter-school mathematics competition with regional offline
rounds in **9 cities**, ~300 participants each (~2,700 total). Published timeline:
registration closed 7 Aug 2026, online qualification 9 Aug, **regional offline Aug–Sep
2026**, grand final 23 Oct, ceremony 24 Oct.

They already own registration. Every participant already holds a Participant Card PDF
carrying a QR whose payload is plain multi-line text:

```
Participant ID: 1202012626
Email: sigeh40652@copawoke.com
```

They need exactly two things from PM One: **on-site check-in against that existing card**
(no re-registration), and **seating arrangement**.

PM One can do neither today. The check-in engine is mature but hard-wired to its own badge
format, and there is no seating model at all. This plan adds a generic **External Check-in**
capability — QR parsing rules, participant import, and seating are per-event configuration,
not client-specific code — so the next external-registration client is a configuration job,
not a build.

Operator decisions taken up front:
- Build it **generic and reusable**, not one-off for this client.
- Data intake is **CSV/Excel import** first. API intake is optional and deferred.
- Seating is **host-assigned room + desk number**, not a visual seat map, not buyer-picked.
- Ship in stages — catch the remaining regional cities, then complete seating before the
  grand final.

Two prior plans stay untouched: **033** (concert/theatre buyer-picked seating, XL/HIGH,
still PLANNED) and **034** (RSVP, still PLANNED). Neither has a single line of
implementation. This plan borrows 033's *concepts* and explicitly discards most of its
schema.

---

## Verdict

Feasible, and smaller than it looks — **~13 developer-days** for the full scope, with the
first **7 days** already enough to run external check-in (online + offline) in all 9
cities. The one structural blocker is avoided rather than dismantled.

### The blocker, and why we go around it

`attendees.ticket_order_item_id` and `attendees.ticket_id` are both **NOT NULL**, and the
table has no direct `event_id`. Every downstream consumer scopes attendees by joining
through the order. Making those FKs nullable would touch **19 files / 106 references** in
`app/` plus 37 references across tests and factories, and would silently open a security
hole: `ScanService::checkIn()` reads `$attendee->ticket?->event`, so a null ticket means
the `wrong_event` guard never fires and a badge checks in at any event.

Instead, external participants ride the **existing complimentary-ticket path**: one hidden
free `Ticket` per event, one Confirmed zero-total `TicketOrder` per import batch, attendees
born under it exactly as `TicketPurchaseService::generateAttendeesForBatch()` already does
for bulk comps. Every gate — `checkIn()`, `manifestBaseQuery()`, `search()`,
`manifestChangesSince()`, `ensureAttendeeBelongsToEvent()`, `qrImage()`, `AttendeesExport`,
`AttendeeAnalyticsService`, void/refund — passes untouched, covered by the 58 existing test
files in `tests/Feature/Tickets/`.

Cost: a synthetic Rp0 order row per import batch. Cosmetic; the admin bulk-generate path
already creates identical rows with `source='admin'`. Before shipping, check the
`TicketDashboard` revenue queries and exclude `source='import'` if they would be polluted.

Decoupling attendees from orders remains the correct long-term model. It belongs in a
separate plan ("039: decouple attendee from order"), justified only if external check-in
becomes a repeat product line.

---

## Architecture

### 1. Scan Identity Profile — how a foreign QR is recognised

This is the generic core. Per-event extraction rules map a raw scanned payload to attendee
lookup fields.

**Storage**: new columns `events.scan_identity` (jsonb) + `events.scan_identity_version`
(integer). Not a new table — exactly one profile per event, and a join on the scan hot path
is waste. Not `events.settings` — that column is a grab-bag with a history of
slice-clobbering (plan 035 wrote three Pest tests specifically about it); this profile must
be read and written atomically. The version integer lets an offline client detect a rule
change with one comparison.

```json
{
  "match_mode": "dual",
  "require_fields": ["external_id", "email"],
  "rules": [
    { "id": "participant_card", "type": "delimited_kv",
      "line_delimiter": "\n", "pair_delimiter": ":",
      "map": { "Participant ID": "external_id", "Email": "email" } },
    { "id": "json_payload", "type": "json",
      "map": { "external_id": "$.id", "email": "$.email" } },
    { "id": "bare_id", "type": "plain", "map": { "external_id": "$0" } }
  ],
  "self_test": [
    { "payload": "Participant ID: 1202012626\nEmail: a@b.com",
      "expect": { "external_id": "1202012626", "email": "a@b.com" } }
  ]
}
```

Rule types shipped: `delimited_kv`, `json`, `plain`. **`regex` is deliberately deferred** —
PCRE and JS RegExp diverge (lookbehind, backreferences, atomic groups), and admin-authored
patterns invite catastrophic backtracking. If it is ever needed, it must be server-validated
against `self_test` with a timeout and gated behind `scan_identity.manage`. This client does
not need it.

**Resolution order** in `ScanService::resolveAttendee()`:

1. `QrToken::normalize($raw)`; if the result is single-line and <= 64 chars, query
   `where('qr_token', ...)`. This catches every native PM One badge (26-char ULID) and the
   legacy `.../v/<token>` form — **byte-for-byte today's behaviour**.
2. On miss, and only if the event has a `scan_identity`, run the rules over `raw_payload`.
   First rule that yields every `require_fields` wins.
3. Look up `attendee_external_identities`. Miss → `ticket_not_found`.

An event with no profile executes zero new code and issues zero extra queries.

**External IDs live in a new table**, not on `attendees`:

```
attendee_external_identities
  id, attendee_id (FK cascade), event_id (FK),
  external_id (string), email_normalized (string, nullable),
  source (string), raw_payload_hash (char 64, nullable), timestamps
  unique (event_id, external_id)
  index (event_id, email_normalized)
```

`attendees` is hot, wide, and covered by 58 test files — leave it alone. A table also lets
one participant carry several identifiers (the card has two QRs), and the unique
`(event_id, external_id)` is simultaneously the import dedupe key and the answer to
"Participant IDs may not be unique across cities".

**Payload length**: add `raw_payload` (`nullable|string|max:2000`) to `CheckInRequest` and
to `logs.*` in `ScanController::sync()`. Leave `qr_token` at `max:255`. The client sends
both — `qr_token` as the truncated normalized value (fast path plus idempotency continuity),
`raw_payload` verbatim. Widening `qr_token` would put a 2 KB blob into a unique 26-char
column and index garbage.

**Audit**: `scan_logs.meta` (existing jsonb, currently only `{warning}`) gains
`{"identity": {"rule_id", "external_id", "matched_by"}}`. Never store the full raw payload
or a plaintext email there.

**Security**. A 10-digit Participant ID is guessable, so `match_mode: dual` is the default:
both ID and email must extract and match, turning "guess 10 digits" into "guess 10 digits
plus that person's email". `single` stays available for clients whose QR is a high-entropy
opaque token. A failed match returns `identity_mismatch` **without any attendee payload** —
never confirm that an ID exists but the email was wrong. Abuse guard counts *failures* only
(`ticket_not_found` + `identity_mismatch` + `identity_incomplete`) per staff user per
minute; over 20/min returns 429 and writes an `activity()` entry. Successful check-ins are
never throttled — a gate scanning 300 people in 30 minutes must not trip a limiter.

State this plainly to the client: **this is convenience check-in, not anti-fraud.** A
screenshot of someone else's card will pass unless a human checks physical ID.

**Ambiguity**: the `external_id` path cannot be ambiguous (unique constraint). The
email-only path can — one parent registering two children with the same address is very
plausible for a school competition. Return `ambiguous_identity` with a candidate count,
never auto-pick, and route the operator to the existing Find panel + `manual-check-in`.
This is a second reason `dual` is the default.

### 2. Offline parity without a divergent rule engine

Verified: `useScanSession.ts` persists only `manifestKey`, `outboxKey`, and `soundsKey` to
IndexedDB (lines 46–48). `/scan/context` is **not** persisted, so the profile must travel
with the manifest.

- The server compiles the profile to a JS-safe shape and ships it in **both**
  `GET /scan/context` and the manifest envelope. `persistManifest()` (line 869) already
  writes `{data, version, generated_at}` — add `identity_profile` and
  `identity_profile_version` to that same object.
- `deltaRefresh()` carries the profile version; a bump triggers a profile re-fetch.
- The client evaluator is a ~40-line pure function mirroring
  `App\Support\ScanIdentity::extract()`. The part that varies per client — the rules — lives
  only in the database.
- **Self-test guard**: the admin "Test payload" tool stores its server result as a fixture
  in `profile.self_test[]`. On load the client evaluates those fixtures locally. One
  mismatch disables offline identity matching for that event and shows a hard banner
  ("Stay online — offline matching unavailable"). Safe degradation, never a silent
  mis-match.
- `manifestRow()` gains `ext_id` (plaintext — already printed on the card in the operator's
  hand) and `email_hash` (first 16 hex of sha256 of the lowercased address). **Plaintext
  emails stay off the device**: 2,700 addresses sitting in IndexedDB on a day-hire
  freelancer's phone is a privacy liability. The current `manifestRow()` already ships no
  email at all, so this preserves the existing posture. The client hashes with
  `crypto.subtle.digest` — the scanner is already HTTPS-only for camera and Web Bluetooth.
- New client indexes: `manifestByExtId`, `manifestByPair` (`ext_id + ':' + emailHash`).

### 3. Participant import

CSV/Excel first. It needs nothing from the client's engineering team — they export a
spreadsheet, which they can already do today — and it does not depend on their uptime on
event day. API intake is slice 6, only if asked. Webhook push is refused as a primary path:
same work as API intake with worse failure modes (retry storms, ordering, no backfill).

**Upsert is the gap.** All 13 existing importers use plain `Model::create()`. The new
importer must not follow that pattern. `app/Imports/EventParticipantsImport.php` implements
`ToCollection, WithHeadingRow, WithChunkReading, SkipsEmptyRows, SkipsOnFailure, WithEvents`
plus `TracksImportProgress` and `ImportsFirstSheetOnly`, and upserts per chunk of 200
against `attendee_external_identities.(event_id, external_id)`.

- New row → attendee created under this batch's order item.
- Existing row → update `name`/`email`/`phone` and registration answers. **Never rotate
  `qr_token`, never reset `checked_in_at`.**
  **Trap**: `AttendeeService::applyStaffEdit()` (`app/Services/Ticket/AttendeeService.php:44-52`)
  rotates `qr_token` automatically when name or email changes on a not-yet-checked-in
  attendee. Routing the importer through it would invalidate every participant's badge on
  re-import. The importer writes to the model directly.
- In DB but absent from the file → **never delete**. Explicit `absent_rows` option:
  `ignore` (default) or `mark_cancelled`, which sets `cancelled_at` with reason
  `removed_by_import`. The existing `manifestChangesSince()` then tags them `remove` and
  propagates to devices for free.
- Row marked cancelled in the file → set `cancelled_at`; back to active → clear it.

**Column mapping**: client-side per import, not a persisted mapping UI and not a rigid
template. The dialog reads the uploaded file's header row and renders one select per target
field — `external_id, name, email, phone, status` plus every registration custom field on
the event (`Event::registrationFields()`). The mapping rides to the job through
`ProcessExcelImport::$constructorArgs`, which **already exists**
(`app/Jobs/ProcessExcelImport.php:31`, spread as `new $this->importClass(...$this->constructorArgs)`).
Zero new tables, zero new framework, ~150 lines of Vue. A downloadable template stays
available via `app/Exports/BaseTemplateExport.php` for clients who prefer the straight path.

Extra client columns (category "Siswa - SMP Sederajat", school, date of birth, city) land in
`custom_fields` with context `ticket_registration` via
`CustomFieldValues::store($attendee, $fields, $data, 'ulid')`. **This is the generic escape
hatch**: arbitrary client data without a per-client migration, already exposed as
`registration_answers` on `AttendeeResource`/`AttendeeIndexResource`, and it doubles as the
grouping key for seating.

### 4. Seating — host-assigned

A separate plan from 033, which stays PLANNED for the concert/theatre buyer-picked case.
From 033 we reuse the concepts and discard most of the schema: `venues` (the event's
existing `location` is the venue), `seat_maps`, `seats` as reusable static inventory, all
holds (`held_by`/`held_until`/conditional-UPDATE/sweep job/k6 gate), the buyer picker, the
waitlist hook, `price_override`, and `tickets.seating_mode` — seating is a property of the
event, not the ticket. `seat_sections` becomes `event_rooms`; `seat_rows` becomes an
optional `row_label` string. Risk drops from XL/HIGH to M/LOW for one reason: assignment is
a single admin action, not thousands of buyers racing for the good seats.

```
event_rooms
  id, ulid, event_id (FK), name, code, capacity (int),
  order_column, notes, timestamps

event_seat_assignments
  id, event_id (FK), event_room_id (FK), seat_number (int),
  row_label (string, nullable), label (string, e.g. "A-12"),
  attendee_id (FK, nullable), status (open|assigned|blocked),
  assigned_at, assigned_by, timestamps
  unique (event_room_id, seat_number)
  unique (event_id, attendee_id) WHERE attendee_id IS NOT NULL
  index (event_id, status)
```

The entire concurrency story is `Cache::lock("seating:{$event->id}", 60)` around auto-assign.

`app/Services/Seating/SeatAssignmentService.php` provides `generateSeats(EventRoom)`,
`autoAssign(Event, array $options)`, `reassign()` (an explicit swap when the target is
occupied, never a silent overwrite), `release()`, and `block()`. Strategies:

- `sequential` — ordered by name or `external_id`, filling room by room.
- `spread_by` — grouping key from a registration custom field (school, home city). Bucket by
  key, sort buckets descending by size, deal round-robin across the flattened seat list,
  then verify no two adjacent seats (same room, `seat_number ± 1`) share a key and run one
  local-swap repair pass. Deterministic and easy to test.
- `group_by` — the inverse: one category per room (SMP in Room A, SMA in Room B), respecting
  per-room capacity.

Options: `only_unassigned` (**default true**, so a re-run after a late import does not
reshuffle already-announced seats) and `dry_run` (preview counts, conflicts and shortfall
before committing).

No-shows are never released automatically. Provide a report of assignments whose attendee
has a null `checked_in_at`, plus a bulk "Release no-shows" action for after the cutoff, so
standbys can be seated.

Operational output: a per-room manifest endpoint and `app/Exports/SeatingExport.php`, a
print-CSS desk-label page (A4 grid: label, name, `external_id` — no PDF dependency), and —
the feature the committee will actually feel — **room and desk shown on the scanner the
instant the card is scanned**. `ScanService::present()` and `manifestRow()` gain
`seat: {room, label}`; `ScanPanel.vue` renders it as the dominant element under the name
(name is currently at lines 151–154, meta badges at 172–173; seat goes between them, at
display size). Because it rides `manifestRow()`, it works fully offline with no request.

### 5. Nine cities

**Nine Events in one Project.** There is no `venues`/`cities` table, `event_days` has no
location column, and every location attribute (`location`, `hall`, `timezone`, `capacity`)
lives on the Event. Anything else means a new migration for no benefit.

Operational consequences and their mitigations: a row filter in the import dialog ("only
rows where City = Bandung") lets one master file serve all nine; "Copy scan identity profile
from event..." and "Copy rooms from event..." actions mean the profile and the room list are
authored once; the scanner scope and the `scanner` role already work per event.

Cross-city reporting **does need new work** — `event_conjunctions` does not aggregate
anything, it only widens `scannableEventIds()`. Add
`GET /projects/{username}/attendance-summary?event_ids[]=` returning registered / checked-in
/ percentage / last scan per event, plus one dashboard card.

**Do not enable `event_conjunctions.allow_cross_scan` between the nine cities.** It would
let a Jakarta card check in at Surabaya. Here the `wrong_event` guard is a feature. Write
this into the configuration notes.

### 6. Toggles and permissions

Event-level, mirroring `events.tickets_enabled`, since nine cities are nine events and the
middleware already resolves the event from route or payload.

- `events.external_checkin_enabled` (bool, default false) +
  `app/Http/Middleware/EnsureExternalCheckInEnabled.php`, alias `external-checkin-enabled`
  in `bootstrap/app.php` beside `tickets-enabled`. Returns **404** with
  `error_code: EXTERNAL_CHECKIN_DISABLED`, following `EnsureTicketsEnabled` (404 not 403, so
  it does not leak which events exist).
- Applied to the `participants/*` and `scan-identity/*` route groups only. `/scan/*` stays
  under `tickets-enabled`, because external check-in still rides the ticket engine. The
  toggle endpoint returns 422 if `tickets_enabled` is off.
- `events.seating_enabled` (bool, default false) + alias `seating-enabled`. Independent — a
  GA expo may want seating without external check-in.

New permissions in the `ticket_ops` group (`config/permissions.php:435`), then
`php artisan permissions:sync`:

```
participants.import   => 'Import an external participant list'
participants.export   => 'Export the external participant list'
scan_identity.manage  => 'Configure the event QR identity profile'
seating.read          => 'View seating rooms and assignments'
seating.manage        => 'Create rooms and generate seats'
seating.assign        => 'Auto-assign, reassign and release seats'
seating.export        => 'Export seating manifests and desk labels'
```

`master`, `admin` and `project-coordinator` get all of them. The `scanner` role gets
**none** — seat information reaches the scanner through the already-gated `/scan/check-in`
response and manifest. `scan_identity.manage` in particular must be withheld: a day-hire
freelancer must not be able to change matching rules.

---

## Slices

Ordered so slice 1 delivers external check-in end-to-end for one city.

### Slice 0 — Discovery and data contract (0.5 day, no code)

Written confirmation of four things: the decoded contents of **both** QRs on the card
(decode the real PDF, do not assume), one real participant export for one city, the room
list with capacities for one city, and whether Participant IDs are unique across cities.

**DoD**: approved column map plus confirmed QR payloads for at least two cities.
**Slice 1 does not start without this.**

### Slice 1 — Import to scannable attendee, one city, online (4 days)

Migrations: `create_attendee_external_identities_table`,
`add_external_checkin_to_events_table` (`external_checkin_enabled`, `scan_identity`,
`scan_identity_version`).

Backend: `app/Models/AttendeeExternalIdentity.php`; `app/Support/ScanIdentity.php`
(`extract()` + `compileForClient()`); `app/Services/Ticket/ParticipantImportService.php`
(resolve/create the hidden Participant ticket, order + item per batch, upsert by
`(event_id, external_id)`, registration answers via `CustomFieldValues::store()`,
`absent_rows` handling); `app/Imports/EventParticipantsImport.php`;
`app/Http/Controllers/Api/EventParticipantController.php` (`import`, `template`,
`identityProfileShow/Update/Test`); `EnsureExternalCheckInEnabled` + alias;
`ScanService::resolveAttendee()` fast path + profile path with new reasons
`identity_incomplete`, `identity_mismatch`, `ambiguous_identity`, `unrecognized_payload`;
`ScanService::search()` gains `orWhereHas('externalIdentities', ...)` so staff can find
someone by Participant ID; `checkIn()` writes `meta.identity` and the failure counter;
`CheckInRequest` gains `raw_payload`; `ScanController` passes it through;
`AttendeeIndexResource` exposes `external_id`; routes for `events/{event}/participants` and
`events/{event}/scan-identity`; permission entries.

Frontend: `frontend/app/components/participant/ParticipantImportDialog.vue` (header-aware
parse + mapping selects + `useImportProgress.ts`, modelled on `ContactImportDialog.vue`);
`frontend/app/pages/projects/[username]/events/[eventSlug]/participants/index.vue` using
`TableData` with backend `whereLike` search; a "Scan Identity Profile" card with a "Test
payload" button on the ticket settings page.

Pest (`tests/Feature/Tickets/ExternalCheckInTest.php`, `ParticipantImportTest.php`):
multi-line kv payload checks in; **a native ULID badge still resolves** (mandatory
regression guard); right ID + wrong email returns `identity_mismatch` with no attendee data
in the response; unknown ID returns `ticket_not_found`; a repeated idempotency key yields one
`ScanLog`; imported attendees appear in `/manifest` and `/manifest/changes`; 200 rows produce
200 attendees under one Confirmed order; **re-importing the same file creates zero new
attendees, updates the name, and leaves `qr_token` and `checked_in_at` untouched**;
`status=cancelled` sets `cancelled_at` and surfaces as `remove` in the delta; a header alias
("Nama Peserta" → `name`) maps correctly; a disabled event returns 404
`EXTERNAL_CHECKIN_DISABLED`; the `scanner` role cannot PATCH the identity profile.

**DoD**: on staging, upload the real client export, scan the real Participant Card PDF with a
phone camera at `/scan/{eventId}`, get a green check-in. `vendor/bin/pint --dirty` clean.

### Slice 2 — Offline parity and hardening (2.5 days)

Backend: `ext_id` + `email_hash` on `manifestRow()`; `identity_profile` +
`identity_profile_version` in the manifest envelope and `/scan/context`; `sync` accepts
`raw_payload`; failure throttle + `activity()` log.

Frontend (`useScanSession.ts`): `extractIdentity()`; `manifestByExtId` / `manifestByPair`;
`hashEmail()` via `crypto.subtle`; `onScanValue()` forwards the raw payload (lines 699–704);
`submitCheckIn()` sends `raw_payload` (660–688); `offlineResult()` resolves via identity
(477–518); `enqueueOffline()` stores `raw_payload`; `persistManifest()` stores the profile
(869–875); self-test guard and degradation banner.

Pest: an outbox entry carrying only `raw_payload` checks in correctly on sync; the same key
twice yields one `ScanLog`; a manifest row carries `ext_id` and `email_hash` and **no
plaintext email**.

**DoD**: airplane mode, scan 20 cards, reconnect, exactly 20 check-ins land.

### Slice 3 — Multi-city ops (1.5 days)

Copy identity profile between events; copy rooms between events; import row filter ("only
rows where City = X"); `GET /projects/{username}/attendance-summary` plus a nine-city
dashboard card.

**DoD**: nine events configured in under an hour; one screen shows check-in progress across
all nine.

### Slice 4 — Seating core and scanner display (3 days)

Migrations `create_event_rooms_table`, `create_event_seat_assignments_table`,
`add_seating_enabled_to_events_table` + `seating-enabled` middleware.
`SeatAssignmentService` with three strategies, `dry_run`, `only_unassigned` and the cache
lock. `seat` added to `ScanService::present()` and `manifestRow()`. `ScanPanel.vue` renders
room and desk as the dominant element. Admin page
`.../events/[eventSlug]/seating/index.vue` (TableData: room CRUD, assignment table,
auto-assign dialog with preview).

Pest (`tests/Feature/Seating/SeatAssignmentTest.php`): `sequential` fills rooms in order;
`spread_by=school` leaves no two adjacent seats with the same school; `group_by=category`
keeps SMP and SMA in separate rooms; `only_unassigned=true` moves nothing already assigned;
insufficient capacity returns 422 with the shortfall; parallel auto-assign is serialised by
the lock with no double assignment; `seat` appears in the `/scan/check-in` response **and**
in `manifestRow()`.

**DoD**: 300 attendees across 10 rooms; scanning a card in airplane mode shows
"Room C — Desk 14".

### Slice 5 — Seating operational output (1.5 days)

`SeatingExport` per room; desk-label print page; per-room attendance report (assigned vs
checked in); bulk "Release no-shows".

**DoD**: the committee prints labels and room lists without a developer.

### Slice 6 — API intake (2.5 days, only on request)

Per-event API-key scope (`api_consumer_event` or an event-scoped token);
`POST /public/events/{event}/participants/sync` (batches <= 500) reusing
`ParticipantImportService`; idempotent by `(event_id, external_id)` plus payload hash; an
audit table modelled on `payment_webhook_events`.

---

## Effort

| Slice | Days | Cumulative |
|---|---|---|
| 0 Discovery | 0.5 | 0.5 |
| 1 Import + external check-in (online) | 4.0 | 4.5 |
| 2 Offline parity + hardening | 2.5 | 7.0 |
| 3 Multi-city ops | 1.5 | 8.5 |
| 4 Seating core + scanner display | 3.0 | 11.5 |
| 5 Seating output | 1.5 | 13.0 |
| 6 API intake (optional) | 2.5 | 15.5 |

Minimum path to serve the remaining regional cities: **slices 0+1+2 = 7 working days**,
giving online and offline external check-in for all nine cities without seating. Slice 3
(1.5 days) is strongly recommended before the third city, since without it every city is a
manual setup from scratch.

Timeline reality at authoring time (20 Aug 2026): seven working days plus a slice 0 that
depends on the client's responsiveness means the first city we can realistically serve falls
**on or after 5 September**. Say so up front; do not promise next week's city.

Seating (slices 4+5, 4.5 days) can run in parallel with a second developer once slice 1 is
done. There is no schema dependency between seating and identity — only two shared touch
points, `ScanService::present()`/`manifestRow()` and `ScanPanel.vue`.

---

## Risks and STOP conditions

Manageable risks: QR formats differing per city (the ordered `rules[]` handles even a city
that mixes two formats); the two QRs on the card carrying different content (a `plain` or
prefix-strip rule covers it, and `unrecognized_payload` tells the operator "wrong QR" rather
than "not registered"); Participant IDs colliding across cities (the unique key is
`(event_id, external_id)`, and `dual` matching plus keeping cross-scan disabled makes a
practical collision impossible); the roster changing on the day (import is an upsert and the
delta manifest pushes changes on the next refresh — a device offline all day falls back to
the online Find panel); a venue with no internet (solved by plan 022, **but slice 2 is
mandatory** for external QRs — never ship slice 1 alone to an offline venue); rule-engine
drift between server and client (the self-test guard degrades to online-only rather than
mis-matching).

**Stop and decline if:**

- The QR carries no stable machine-readable field — an opaque token only their server can
  resolve, or a payload that varies per print run. Then only slice 6 works and an
  Aug–Sep timeline is dead.
- The client insists on single-field matching **and** Participant IDs are not unique across
  cities. Ask for a per-city prefix, or refuse. Never ship matching that can check in the
  wrong person.
- Sample data does not arrive by the agreed date. Do not start slice 1 blind.
- The client expects anti-fraud. Correct this before the contract.
- The requirement turns into "participants pick their own seats" — that is plan 033
  (XL/HIGH, k6 load test required). Re-scope; do not stretch slice 4's model.
- The client wants real-time consistency across all offline devices. Physically impossible;
  explain eventual consistency and first-wins.
- The same project also sells paid tickets and the synthetic Rp0 orders pollute revenue
  reporting beyond a `source='import'` filter. That is additional scope, not a freebie.

---

## Questions for the client before committing

**Data.** Can you export the participant list to CSV/Excel — send one real file (redacted is
fine) now, not a description of the columns. Which columns exist, specifically Participant
ID, name, email, phone, category/level, **school**, city, status? Are Participant IDs unique
across all nine cities or reset per city? Can one participant appear in more than one city?
Can two participants share an email address (siblings, a parent's address)? Since
registration closed on 7 August, does the list still change — additions, withdrawals — and
who sends updates, how often? How is a withdrawn participant marked in your export? For
cities that have already run, do you need their check-in data migrated, or do we start from
the next city?

**QR and Participant Card.** Send two or three real Participant Card PDFs from different
cities. **Do the small and large QR contain the same thing?** If not, what exactly is in
each? Is the payload format identical across all nine cities, or did it change partway? Have
all participants received their card, and what percentage? What happens when someone arrives
without a card? Is check-in on ID plus email acceptable, or must it be a single scan with no
extra data?

**Seating.** Are seats assigned by the committee or chosen by participants? (If chosen, the
whole design changes.) How many rooms per city and what capacity — send the list for one
city. Are desks numbered 1..N per room or formatted (A-01, B-12), and are there rows? Are
there placement rules — same-school participants not adjacent, levels split across rooms,
categories separated? Are seats decided before the day (printed, announced) or at check-in?
Any accessibility seating? Which outputs do you need: room lists, printed desk labels, seat
cards, or is the scanner display enough?

**Event day.** Is there stable internet at the venue, WiFi or tethering? How many check-in
staff per city, on what devices — personal Android/iOS phones, tablets, USB scanner guns? How
long is the check-in window (300 people in 30 minutes versus 2 hours changes the number of
gates)? Do you need on-site badge printing (Bluetooth TSPL printers are supported — which
model)? Do you need check-out or per-session attendance? (There is no check-out today and
only one `checked_in_at`; that would be extra scope.) Who receives scanner accounts, and may
they see other participants' data?

**Commercial.** Which city do you want served first, and on exactly what date — this is the
test of whether the timeline is possible at all. Do you understand this is convenience
check-in, not anti-fraud? Who is your technical point of contact for data questions?

---

## Verification

- `php artisan test --compact --filter=ExternalCheckIn` and `--filter=ParticipantImport`
  after slice 1; `--filter=SeatAssignment` after slice 4.
- Run the existing scan suite unchanged as the regression gate:
  `php artisan test --compact tests/Feature/Tickets/TicketScanTest.php tests/Feature/Tickets/ScanSyncTest.php tests/Feature/Tickets/ManifestScaleTest.php tests/Feature/Tickets/BadgeQrNormalizationTest.php`.
  A native ULID badge resolving unchanged is the single most important assertion in this plan.
- `vendor/bin/pint --dirty` before finalising any PHP change.
- Browser verification at `http://localhost:3000` (never `nuxi typecheck` or `npm run build`):
  import a real export, open `/scan/{eventId}`, scan the real Participant Card PDF from a
  phone, confirm a green check-in and — after slice 4 — the room and desk display. Repeat in
  airplane mode after slice 2.
- Deploy notes: `php artisan migrate` and `php artisan permissions:sync` on production; the
  pmone Cloudflare frontend rebuild for the admin pages.

## Critical files

- `app/Services/Ticket/ScanService.php` — `resolveAttendee()` (L308-314), `manifestRow()`
  (L237-252), `present()` (L390-417), `search()` (L108-127): every identity + seat touch point.
- `app/Services/Ticket/TicketPurchaseService.php` — `bulkGenerate()` + `generateAttendeesForBatch()`
  (L1503) are the blueprint for `ParticipantImportService`.
- `frontend/app/composables/useScanSession.ts` — `offlineResult()` (L477), `submitCheckIn()`
  (L660), `normalizeQrToken()`/`onScanValue()` (L690-704), `persistManifest()`/`deltaRefresh()`
  (L869-925).
- `app/Imports/ContactsImport.php` + `app/Jobs/ProcessExcelImport.php` — importer pattern and
  the `$constructorArgs` channel that carries the column mapping.
- `plans/033-reserved-seating-build.md` — the sub-schema reused vs discarded for host-assigned
  seating.
