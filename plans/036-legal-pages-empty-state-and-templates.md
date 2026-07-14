# Plan 036: Legal Pages - explain the empty tab, add default-template starting points

> Authored 2026-07-14 after an end-to-end trace (model, routes, seeders, admin
> UI, pmone-events consumers) plus a production DB check. Self-contained.

## The finding first: "no data on all projects" is NOT a bug

- Feature = `WebsitePage` model, table `website_pages`
  (`database/migrations/2026_07_12_133005_create_website_pages_table.php`):
  `project_id` FK, `key` (whitelist `WebsitePage::KEYS` = terms, privacy,
  event-policy, help-center, ticket-terms-and-conditions,
  ticket-refund-and-return-policy), `body` json translatable (en/id/ja/ko/zh),
  unique `(project_id, key)`. Shipped 2026-07-12 (plan 011, commit `08bc9426`).
- **There is no population path by design**: no seeder, no observer, no artisan
  command creates rows. `database/seeders/WebsiteConfigSeeder.php` (lines 28-35)
  documents the decision: the baked legal copy lives in pmone-events Vue
  templates (`layers/base/app/pages/terms.vue` etc.) with `{{ companyName }}`
  interpolation, is "not extractable as clean HTML", and stays **fail-open**
  (empty override = baked template renders) "until an operator writes a custom
  override in the dashboard".
- Production DB confirms (2026-07-14): `website_pages` has exactly **2 rows**,
  both project `megabuild` (`ticket-terms-and-conditions`, `terms`, created
  2026-07-13 - operator experiments). Every other project: zero rows.
- The public sites are unaffected: `layers/base/app/pages/terms.vue` /
  `privacy.vue` (+ 4 siblings) render `overrideBody` when present, else the full
  baked English boilerplate - docblock invariant: "a legal page must NEVER
  render empty."
- Tests codify empty-by-default as correct: `tests/Feature/WebsitePageTest.php`
  ("index lists all six page keys even when none are configured"),
  `tests/Feature/PublicWebsitePageTest.php` ("an unconfigured project fails
  open with body null for every key").

So the real problem is **UX**: the admin tab
(`frontend/app/pages/projects/[username]/settings/legal-pages.vue`, 183 lines)
renders six blank TipTap editors with no explanation, no indication that the
live site is showing a built-in template, and no starting point to edit from.
An admin reads it as "data is missing".

## Target design

### 1. Make fail-open visible in the UI

- Page-level description under the heading: e.g. "Each page falls back to the
  website's built-in template. Write content here only to replace it - leave a
  page blank to keep the default." (English UI copy; follow STYLE_GUIDE.md.)
- Per-card **status badge** computed from the loaded translations:
  - all locales blank -> neutral badge "Built-in template"
  - any locale filled -> badge "Customized" + the filled locale codes
    (e.g. "Customized · EN, ID")
- Optional (cheap, do it): dot indicator on the locale switcher tabs for
  locales that have content, so admins see coverage at a glance.
- Per-card **"View live" link**: project website URL + the page path
  (terms -> `/terms`, privacy -> `/privacy`, event-policy -> `/event-policy`,
  help-center -> `/help-center`, ticket-terms-and-conditions ->
  `/ticket-terms-and-conditions`, ticket-refund-and-return-policy ->
  `/ticket-refund-and-return-policy`). Reuse the `websiteUrl` resolution
  already implemented in `settings/og-images.vue` (it derives the project's
  "Website" link and warns when absent) - extract to a composable if trivial,
  else duplicate the small lookup.

### 2. "Load default template" per card (the real value)

Give admins the baked copy as a starting point instead of a blank editor:

- **Extract the six baked bodies once** from pmone-events
  (`layers/base/app/pages/{terms,privacy,event-policy,help-center,ticket-terms-and-conditions,ticket-refund-and-return-policy}.vue` -
  the `v-else` baked branch) into pmone as HTML template files, e.g.
  `resources/website-page-templates/{key}.html`, replacing the Vue
  interpolations with literal placeholders: `{company_name}`,
  `{company_address}`, `{last_update}`. English only (that is all that exists
  baked).
- **New endpoint**: `GET /api/projects/{username}/website-pages/{key}/template`
  -> `{ data: { body: "<html...>" } }`. Controller method on
  `WebsitePageController`; authorize like `index` (project view/update policy);
  404 on unknown key; interpolate placeholders server-side from
  `projects.settings->website_settings->site_config.identity`
  (company_name/company_address) and `terms.last_update`, with sensible blanks
  when unset. Small support class (e.g. `App\Support\WebsitePageTemplates`)
  loading + interpolating the files.
- **UI**: a "Load default template" button per card that fetches the template
  and fills the **current locale's** editor. If the editor already has content,
  confirm before overwriting (use the project's existing confirm-dialog
  pattern - check sibling settings pages). Admin then edits/translates and
  saves normally (`PUT .../website-pages/{key}` upsert - unchanged).
- Add a short doc comment on BOTH sides (the template files here and the baked
  Vue pages in pmone-events) noting they are copy-paste siblings that must be
  updated together if legal copy ever changes.

### 3. Per-page "Last updated" (replaces the global Terms & Privacy date)

Operator decision 2026-07-14: the single global `terms.last_update` DatePicker
(currently a card in `website-settings.vue`; plan 035 deletes it) is replaced
by a last-update date **per legal page**, shown on that page on the live site.

- **Migration**: add nullable `date` column `last_updated_at` to
  `website_pages` (`php artisan make:migration`).
- **Admin**: a DatePicker on each of the six cards, saved through the existing
  `PUT /api/projects/{username}/website-pages/{key}` (extend
  `UpdateWebsitePageRequest` with `last_updated_at => nullable|date`;
  `WebsitePageController::update` uses `firstOrNew` + explicit sets, so add the
  explicit assignment - do not switch to a blind `update($validated)`, the
  project has a known strips-siblings gotcha there). Saving a date with a blank
  body is valid: the row exists with `body = null`, body stays fail-open.
- **Public payload**: `PublicProjectController::websitePages()` returns per key
  `{ body, last_update }`. Server-side fallback: when the row/column is null,
  fall back to the legacy
  `projects.settings->website_settings->terms.last_update` value - this keeps
  existing prod data and already-deployed event sites working with **no
  backfill and no coordinated deploy**. Response-cache tag `website-pages`
  already invalidates on save.
- **pmone-events** (cross-repo, zero new round-trips - the legal pages already
  fetch this payload): `layers/base/app/composables/useWebsitePage.ts` also
  exposes `lastUpdate`; the six legal pages render it in BOTH branches
  (override and baked - the baked branch currently interpolates
  `{{ lastUpdate }}` from `useProjectSettings().termsLastUpdate`). New
  precedence: per-page `last_update` from the website-pages payload, falling
  back to the legacy `termsLastUpdate`. Hide the "Last updated" line entirely
  when both are empty.
- **Cleanup (later, not now)**: once all projects have per-page dates, the
  legacy `terms.last_update` key and its validation can be retired. Leave a
  note; do not remove it in this plan.
- **Deploy note**: `php artisan migrate` on prod (the new column); pmone-events
  changes ride the next CF Pages rebuild.

## What NOT to do (explicit decisions)

- **No auto-backfill / seeding of `website_pages`** - the documented design is
  fail-open with dashboard-authored overrides only. Mass-inserting near-identical
  boilerplate per project would create 6 x N rows of drift-prone duplicated
  legal text that then must be maintained per project. The template button gives
  the same benefit on demand.
- No new keys in `WebsitePage::KEYS`, no per-event scoping. The public
  endpoint (`PublicProjectController::websitePages`) only gains the additive
  `last_update` field per key - the fail-open `null` semantics for `body` must
  stay byte-identical, and the shape change must be additive (deployed sites
  that ignore the new field keep working).

## Steps

1. Read `frontend/STYLE_GUIDE.md`. UI copy English.
2. Backend: migration (`last_updated_at`), template files +
   `WebsitePageTemplates` support class + controller method + route (place next
   to the existing website-pages routes, `routes/api.php` ~line 272), public
   payload `last_update` + legacy fallback. Pest tests in
   `tests/Feature/WebsitePageTest.php` + `tests/Feature/PublicWebsitePageTest.php`:
   template returns interpolated HTML (identity set), placeholders-blanked HTML
   (identity unset), 404 unknown key, 401/403 unauthorized; `last_updated_at`
   saves via PUT without disturbing `body` translations; public payload
   returns per-page date, falls back to legacy `terms.last_update`, and null
   when both unset. Run `php artisan test --compact --filter=WebsitePage`.
   `vendor/bin/pint --dirty`.
3. Frontend admin: status badges, locale-coverage dots, view-live links,
   load-template button + confirm, page description, per-card DatePicker.
4. pmone-events: `useWebsitePage` lastUpdate + render on the six pages (check
   `git status` first - known uncommitted local work; keep diffs scoped).
5. Browser-verify on `localhost:3000` (no builds/typecheck): load a project
   with zero rows -> badges say "Built-in template"; load template -> editor
   fills with interpolated copy; save -> badge flips to "Customized · EN";
   clear + save -> back to built-in; live links open the right paths;
   megabuild (has 2 real rows) shows "Customized" correctly; set a per-page
   date -> event-site dev shows it on that page; unset -> falls back to the
   legacy global date.

## STOP conditions

- Any temptation to seed/backfill `website_pages` in bulk: STOP (see above).
- If extracting the baked HTML from the Vue pages produces markup that TipTap
  cannot round-trip cleanly (editor mangles it on load), STOP and report with
  a sample instead of hand-massaging all six templates.
- No commits unless the operator asks.
