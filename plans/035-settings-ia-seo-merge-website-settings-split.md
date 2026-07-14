# Plan 035: Project settings IA - merge "SEO Meta" + "OG Images" into one "SEO" tab, split "Website Settings"

> Authored 2026-07-14 from a full read-only inventory of the settings stack
> (admin pages, tab nav, backend routes/storage). Self-contained: everything the
> executor needs is cited here. **Frontend-only reorganization** - zero public API
> or storage-shape changes. Backend work is limited to tests proving partial
> PATCH safety.

## Status

- **Priority**: P2 (UX debt; no correctness bug)
- **Effort**: M
- **Risk**: LOW-MED - pure admin UI reshuffle, but the autosave semantics and
  the partial-PATCH merge behavior must be preserved exactly
- **Depends on**: nothing. Coordinates with plan 036 (legal pages) - see "Handoffs".

## Why

1. `/settings/seo-meta` (17 page cards x 5 locales) and `/settings/og-images`
   (10 page cards) are two views of the same mental model ("how does page X look
   in search/share previews") split across two crowded tabs.
2. `frontend/app/pages/projects/[username]/settings/website-settings.vue` is
   **856 lines and 11 unrelated cards**: section toggles, rundown options, ticket
   tabs, blog, book-space form, terms date, data fallback, navigation editors,
   analytics IDs, appearance pickers, company identity. Too much on one page.

## Current state (verified 2026-07-14)

### Tab nav
`frontend/app/pages/projects/[username]/settings.vue` lines 27-86: hardcoded
`settingsTabs` array. 11 tabs; `Branding` gated by `events.update_branding`,
`Payment Gateways` by `payment_gateways.read`, rest visible under the page-level
`projects.update` middleware. Rendered by
`frontend/app/components/ui/tab-nav/TabNav.vue`.

### The four relevant tabs

| Tab | File | Lines | Save model | Storage |
|---|---|---|---|---|
| Website Settings | `settings/website-settings.vue` | 856 | autosave (deep `watch` line ~851, snapshot-diff + re-entrancy guard) | `projects.settings->website_settings` JSON via `PATCH /api/projects/{username}/website-settings` (`ProjectController::updateWebsiteSettings`, `app/Http/Controllers/Api/ProjectController.php:351-517`, merge = `array_replace_recursive` + wholesale-replace for `hotels.notification_email`, `site_config.nav`, `site_config.analytics`) |
| SEO Meta | `settings/seo-meta.vue` | 281 | per-card Save (2 parallel PUTs: title + description) | `website_copy` table (`WebsiteCopy` model, Spatie translatable `value`, 17 `PAGE_KEYS` x `title/description`), `GET /api/projects/{username}/website-copy`, `PUT .../website-copy/{page}/{field}` |
| OG Images | `settings/og-images.vue` + `components/og/OgPageCard.vue` | 160 + 213 | per-card Save (dirty-tracked) | text in `projects.settings->website_settings->og_pages.{key}` (NOT translatable) + Spatie media collection `og_image_{key}` (10 keys, `app/Support/OgPages.php`); `GET/PUT /api/projects/{project}/og-images`, `POST .../og-images/capture-all`, `POST .../og-images/{pageKey}/capture` (Browsershot jobs polled via `frontend/app/composables/useJobProgress.ts`) |
| Legal Pages | `settings/legal-pages.vue` | 183 | per-card Save | `website_pages` table (plan 036 territory) |

### Website Settings page - the 11 cards

1. **Home Page** - dynamic Switch list from `home_sections_catalog`
   (`config/home_sections.php` via `App\Support\HomeSectionCatalog`; 4 keys have
   `legacy_path` dual-write - do not touch resolution logic)
2. **Rundown** - 3 switches
3. **Ticket Page Tabs** - 6 switches
4. **Blog** - 2 switches
5. **Book Space Form** - 3 switches
6. **Terms & Privacy** - single DatePicker (`terms.last_update`)
7. **Data Fallback** - 7 switches
8. **Navigation** - 3x `@/components/project/NavigationListEditor.vue`
9. **Analytics** - 4x `@/components/AnalyticsIdListInput.vue` (GA4/TikTok/Meta/GTM)
10. **Appearance** - enable Switch + 4x `@/components/appearance/AppearancePicker.vue`
11. **Company Identity** - Input (company name) + Textarea (address)

### Key naming mismatch you MUST map explicitly (SEO merge)

`WebsiteCopy::PAGE_KEYS` (camelCase, 17): home, brands, rundown, programs,
contact, bookSpace, ticket, gallery, faq, links, news, ticketPolicy,
eventPolicy, partners, terms, privacy, winner.

`OgPages::KEYS` (kebab-case, 10): home, brands, rundown, programs, contact,
book-space, tickets, gallery, partners, guests.

Mapping table for the unified catalog (build it as a single const in the new page):

| Unified page | copy key | og key |
|---|---|---|
| Home | home | home |
| Brands | brands | brands |
| Rundown | rundown | rundown |
| Programs | programs | programs |
| Contact | contact | contact |
| Book Space | bookSpace | book-space |
| Tickets | ticket | tickets |
| Gallery | gallery | gallery |
| Partners | partners | partners |
| Guests | - | guests |
| FAQ / Links / News / Ticket Policy / Event Policy / Terms / Privacy / Winner | (each own key) | - |

18 unified entries: 9 with both, 1 OG-only, 8 copy-only.

## Target design

### A. New merged tab: **SEO** (`/settings/seo`)

One page, page-centric editing (the admin thinks "the Tickets page", not
"the meta-description field type"):

- **Layout**: master-detail. Desktop: left rail listing the 18 pages (small
  nav list, `text-xs sm:text-sm`, active state), right panel edits the selected
  page. Mobile: `Select` (or the shared segmented `Tabs` if it fits) above a
  single panel. This kills the 27-cards-in-two-tabs sprawl.
- **Per-page panel** contains, in order:
  1. **Search & meta** block (only if the page has a copy key): locale switcher
     (`Tabs variant="segmented"`, locales `en/id/ja/ko/zh` - extract the
     duplicated switcher from `seo-meta.vue`/`legal-pages.vue` into a shared
     component, e.g. `components/project/SettingsLocaleTabs.vue`, and reuse it
     in `legal-pages.vue` too), Title input (max 300), Meta description
     textarea (max 300). Blank locale -> `null` (fail-open; consolidate the
     thrice-duplicated blank-to-null helper into one util, e.g.
     `app/utils/blankToNull.ts` - check `frontend/app/utils/` conventions first).
  2. **Share preview (OG)** block (only if the page has an og key): reuse the
     guts of `components/og/OgPageCard.vue` (image upload with 1200x630
     auto-crop note, OG title max 255, OG description max 500, per-page
     "Capture from website" button). OG fields are single-locale - render them
     OUTSIDE the locale switcher scope and label them so this is obvious.
  3. One **Save** button per panel, dirty-tracked, orchestrating only the dirty
     calls: up to 2 `PUT .../website-copy/{page}/{field}` + 1
     `PUT .../og-images` (with `pages`, `tmp_images`, `delete_images` slices for
     just this key). Endpoints unchanged.
- **Page header actions**: keep "Capture all pages" (Browsershot batch +
  `useJobProgress` polling) and the "no Website link" warning banner from
  `og-images.vue`.
- **Routing**: new `settings/seo.vue` with
  `definePageMeta({ middleware: ["permission"], permissions: ["projects.update"] })`.
  Replace `seo-meta.vue` and `og-images.vue` with thin redirect stubs
  (`navigateTo(.../settings/seo, { redirectCode: 301, replace: true })` in setup
  or route middleware) so old bookmarks/deep links keep working.
- **Tab nav**: replace the two entries with one `{ label: "SEO", icon: "hugeicons:seo", to: .../seo }`.

### B. Split Website Settings

- **`website-settings.vue` keeps only display toggles** (cards 1-5 + 7:
  Home Page, Rundown, Ticket Page Tabs, Blog, Book Space Form, Data Fallback).
  All-Switch page -> the existing deep-watch autosave is safe as-is. Fix the
  misleading header copy: these settings are **project-scoped** (storage +
  route are per-project), so replace "The list is specific to this event"
  (line ~24) with project-level wording, e.g. "Applies to this project's
  website across all editions."
- **New tab "Site Config"** (`/settings/site-config`): Navigation, Analytics,
  Appearance, Company Identity (cards 8-11). Same `PATCH .../website-settings`
  endpoint, sending only its slice.
  - **Autosave caution**: this page mixes free-text (identity, nav labels/URLs,
    analytics IDs). Do NOT blanket deep-watch like today (fires a PATCH per
    keystroke burst). Follow the narrowed-watch pattern documented in
    `settings/hotel-reservations.vue` (watch booleans immediately; text saves
    on blur/change) or debounce the deep watch ~800ms. Keep the
    snapshot-diff + `saving`/`savePending` serialization guards from the
    current implementation (lines ~810-846).
  - Keep `FieldError` wiring - backend validation errors key off dot-paths like
    `site_config.analytics.ga4`.
- **Terms & Privacy card (card 6, the `terms.last_update` DatePicker) is
  REMOVED entirely** (operator decision 2026-07-14): it is replaced by a
  per-page "Last updated" field on each legal page, built in plan 036. Delete
  the card from the UI only - do NOT strip `terms.last_update` from the backend
  validation or the public payload yet (existing prod data + deployed event
  sites still read it; plan 036 adds the server-side fallback and owns the
  eventual cleanup).
- **Tab nav result** (order): General, Members, Contact Form, Brand Fields,
  Website Settings, Site Config, Legal Pages, SEO, [Branding], Hotel
  Reservations, [Payment Gateways]. Net tab count unchanged (11), every page
  materially smaller. Pick the Site Config icon from the hugeicons set already
  in use (e.g. `hugeicons:configuration-01`).

### Backend: prove partial-PATCH safety (tests only)

`updateWebsiteSettings` merges with `array_replace_recursive($current, $validated)`
so a payload containing only one slice should not clobber the others - but the
UI has never exercised split payloads, and the project has a known FormRequest
gotcha where nested rules strip sibling keys. Add Pest feature tests
(extend the existing website-settings test file if one exists; else create
`tests/Feature/ProjectWebsiteSettingsPartialUpdateTest.php`):

1. Seed a project with populated `home_sections` + `site_config` (nav/analytics/
   appearance/identity) + `og_pages`.
2. PATCH only a `site_config` slice -> assert `home_sections`, `og_pages`,
   `terms.last_update` untouched.
3. PATCH only `home_sections` -> assert `site_config.nav`/`analytics` untouched
   (wholesale-replace overrides must not fire when the key is absent).
4. PATCH `terms` slice only -> others untouched.

If any test fails, fix by guarding the wholesale-replace overrides with
`array_key_exists` on the validated payload - do NOT restructure the merge.

## Steps

1. Read `frontend/STYLE_GUIDE.md` (mandatory before any UI work). UI copy in
   English. Styling rules: `text-xs sm:text-sm`, `tracking-tight`, max
   `font-semibold`, reuse `.frame`/`.frame-panel` card shells
   (`frontend/app/assets/css/main.css:1509-1531`).
2. Backend partial-PATCH tests (section above). Run:
   `php artisan test --compact --filter=WebsiteSettings`.
3. Extract shared pieces: `SettingsLocaleTabs`, blank-to-null util.
4. Build `settings/seo.vue` (+ refactor `OgPageCard` internals into the panel,
   or keep the component and embed it - prefer whichever yields less
   duplication). Wire redirect stubs. Update tab nav.
5. Build `settings/site-config.vue`; slim `website-settings.vue`; delete the
   Terms & Privacy card (frontend only); fix scoping copy.
6. `usePageMeta(null, { title: "..." })` on every new/renamed page (project
   convention, divider `·`).
7. `vendor/bin/pint --dirty` if PHP touched.
8. Verify in the browser on `localhost:3000` (pnpm, Claude-managed dev server -
   do NOT run builds/typecheck): every merged/split page loads, saves round-trip
   (check Network tab payloads), old `/settings/seo-meta` + `/settings/og-images`
   URLs redirect, capture buttons still poll job progress, switches persist
   after reload, no console errors.

## STOP conditions

- Do NOT change any public API response shape, storage key, or endpoint path -
  pmone-events reads `site_config`, `og_pages`, `home_sections` verbatim from
  the public website-settings payload.
- Do NOT touch `HomeSectionCatalog` resolution (`stored -> legacy_path -> default`).
- Do NOT rename `WebsiteCopy::PAGE_KEYS` / `OgPages::KEYS` values.
- If partial-PATCH tests reveal the merge clobbers siblings and the
  `array_key_exists` guard does not fix it cleanly, STOP and report - do not
  invent a new merge strategy.
- No commits unless the operator asks.

## Handoffs

- The Terms & Privacy card deletion pairs with plan 036's per-page
  "Last updated" field. Either order works: 036's server-side fallback to the
  legacy `terms.last_update` value means deleting the card here never breaks
  the live sites. Whichever plan runs second checks the card is gone/replaced.
