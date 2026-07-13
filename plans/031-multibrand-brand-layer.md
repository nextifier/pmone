# Plan 031: Multi-brand brand layer (PM One + Monara + future whitelabel)

> **Executor instructions**: You are starting with ZERO prior context. Read this
> plan fully before touching anything. Follow steps in order; verify each; STOP
> on any STOP condition. Execute on a branch:
> `git checkout -b advisor/031-multibrand-brand-layer main`. Do not push or open
> a PR unless the operator asks. Update `plans/README.md` status row when done.
>
> **Conventions that bind you** (repo CLAUDE.md + owner rules, restated so a
> fresh session cannot miss them):
> - Backend tests: `php artisan test --compact` (SQLite in-memory). NEVER touch
>   the local/production PostgreSQL. NEVER run `migrate:fresh/reset/rollback`,
>   `db:wipe`, DROP/TRUNCATE.
> - Frontend package manager is **pnpm** (NOT npm), run from `frontend/`.
> - NEVER run `nuxi typecheck`, `npm run build`, `nuxi build`, or any heavy
>   build from the terminal (slow laptop). Verify frontend in the browser on
>   the dev server instead.
> - `vendor/bin/pint --dirty` after touching any PHP file.
> - All UI copy in English. Read `frontend/STYLE_GUIDE.md` before UI work.
>   Typography rules: always `tracking-tight` (`tracking-tighter` for text-xl+),
>   max `font-semibold`, small text is `text-xs sm:text-sm`.
> - Never use em-dashes in copy or docs; use a plain dash or comma.
> - Page meta: `usePageMeta(null, { title: "Page Title" })`, divider `·`.
> - Do not create documentation files beyond what this plan specifies.

## Status

- **Priority**: P1 (blocks Monara launch)
- **Effort**: L (backend M + frontend M-L)
- **Risk**: MED (touches CORS error path, transactional email copy, nuxt.config)
- **Depends on**: nothing (fully standalone)
- **Category**: architecture / multi-brand
- **Planned at**: main @ `247fa3ad`, 2026-07-13

## Why this matters

The owner is launching a second brand, **Monara (monara.id)**, from this same
codebase, plus future **whitelabel client brands**. Architecture decision
(already made, do not revisit): NO multitenancy package. Instead: one GitHub
repo deployed as one Laravel Forge site per brand on the same VPS (api.pmone.id,
api.monara.id, ...), one PostgreSQL database per brand, one Cloudflare Pages
project per brand for the Nuxt admin in `frontend/`. Both brands must run
**identical workflows** (both use Horizon, same daemons, same cron; only env
values differ).

Because each brand is a separate deployment with its own `.env` and its own
database, everything DB-driven or env-driven is already per-brand for free
(per-project branding JSON, payment gateways, AppSetting global branding +
scanner sounds, mail-from, CDN URL, CORS lists...). What is NOT per-brand yet
is everything hardcoded in the source: "PM One" strings in emails/PDFs/UI, the
inline-SVG logo, the landing page, the PWA manifest, and prod URLs baked into
`nuxt.config.ts` via `NODE_ENV` ternaries. This plan removes all of that and
adds deployment hardening (a live CORS bug fix, Horizon sizing via env, an
`env:audit` deploy gate) so that adding a brand = adding a `brands/<id>/`
folder + env values, with zero code forks.

## Glossary

- **Brand**: a deployment identity (pmone, monara, future whitelabel ids).
  Lowercase alphanumeric id. Selected at build time for the frontend via the
  `BRAND` env var, and per Forge site for the backend via ordinary env vars.
- **Brand-owned file**: anything under `frontend/brands/<id>/` or
  `frontend/public/brands/<id>/`. These MAY hardcode their own brand freely.
- **Shared code**: everything else. Shared code MUST be brand-agnostic.

## Current state (verified 2026-07-13; re-verify in Step 0)

### Backend

- **LIVE BUG**: `bootstrap/app.php:130-144` - the exception-response CORS
  handler calls `env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://pmone.test')`
  at request time. Under `config:cache`, `env()` returns null for .env-sourced
  vars, so API error responses fall back to localhost origins and the browser
  sees opaque CORS failures on every 4xx/5xx.
- `config/horizon.php:200-253` defines 4 supervisors (`supervisor-1` default /
  `supervisor-analytics` / `supervisor-pdf` / `supervisor-tickets`);
  `environments.production` (`:255-277`) hardcodes `maxProcesses` 10/3/2/10.
- Hardcoded brand literals:
  - `app/Mail/MagicLinkMail.php:23` subject `'Your PM One Login Link'`;
    view `resources/views/emails/magic-link.blade.php` ("PM One" in heading/body).
  - `resources/views/emails/exhibitor-invite.blade.php` ("...on PM One").
  - `resources/views/emails/reservation/booking-received.blade.php`,
    `hotel-voucher.blade.php`, `cancellation.blade.php` - fallbacks
    `'PM One Team'` / `'support@pmone.id'` when the project has no name/email.
  - `resources/views/pdf/reservation/receipt.blade.php`, `.../invoice.blade.php`,
    `resources/views/pdf/ticket/invoice.blade.php`, `.../receipt.blade.php` -
    `$branding['company_name'] ?? 'PM One'`.
  - `app/Support/EventIcs.php` - UIDs `...@pmone.id` (lines ~27, 54, 67) and
    `PRODID:-//PM One//Tickets//EN` (~line 130).
  - `app/Agents/ChatAgent.php:39` - persona "assistant for PM One...".
  - `app/Enums/Ticketing/PurchaseType.php:18` - label `'First-party (PM One)'`.
  - `app/Services/OpenGraph/OpenGraphExtractor.php:52` - UA
    `PMOne/1.0; +https://pmone.id`.
- `.env.example` is INCOMPLETE vs production: it lacks many production keys
  referenced in config (`CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`,
  `PAYMENT_TRUSTED_REDIRECT_HOSTS`, `MEDIA_DISK`, `AWS_URL`, `AWS_ENDPOINT`,
  `RESPONSE_CACHE_DRIVER`, `HORIZON_PREFIX`, `REDIS_PREFIX`, `REDIS_DB`,
  `REDIS_CACHE_DB`, `BACKUP_NOTIFICATION_EMAIL`, ...).
- Already brand-safe, DO NOT TOUCH: `config/payment.php` trusted hosts (env
  override exists; the pmone default list is fine because each deployment sets
  the env), per-project `Project.branding` + `branding_logo` media +
  `app/Services/Pdf/ResolvesPdfBranding.php` merge logic, `AppSetting`
  `branding`/scan sounds (per-DB = per-brand automatically),
  `Event::publicBaseUrl()` / project "Website" link, `QrToken` (parser is
  host-agnostic; the pmone.id URL there is only a comment), Midtrans
  idempotency prefixes `pmone-rfd-`/`pmone-ping-` (internal keys, renaming
  would churn production idempotency), `pmone:create-super-admin` command
  signature (ops muscle memory), seeders/factories (pmone demo data).

### Frontend (`frontend/`, Nuxt 4, srcDir `app/`)

- `app/app.config.ts` is the de-facto brand config (name "PM One", shortName,
  url via `NODE_ENV` ternary, company PT Panorama Media + address, contact
  hello@panoramamedia.co.id / whatsapp). Already consumed via `useAppConfig()`
  by `privacy.vue:398-404`, `terms.vue:253-259`, `usePageMeta.js:17,36-38`,
  `DialogShare.vue:36`, `[username]/index.vue:112`, `news/[slug].vue:220`,
  `OgImage/Page.takumi.vue:83-87`. Strategy: keep this shape, source it per
  brand.
- `nuxt.config.ts` brand couplings: `:16` `pmOneApiKey` has a REAL API key
  literal as fallback (`pk_apm8...`); `:20-22` `siteUrl`/`apiUrl` NODE_ENV
  ternaries to pmone.id/api.pmone.id; `:53` head.title "PM One"; `:67`
  apple-touch icon `/icons/apple-touch-icon.png`; `:190` sanctum.baseUrl
  ternary to api.pmone.id; `:313-314` site.name/site.url; `:340-379` PWA
  manifest (name/short_name/description "PM One", icons `/icons/icon-*.png`,
  screenshots `/screenshots/*.png` labeled "…of PM One").
- Logo: `app/components/Logo.vue` (PM One wordmark) and `LogoMark.vue` (mark),
  both template-only inline SVG, no props, `fill="currentColor"`. Usages:
  LogoMark in `Header.vue:36`, `AppSidebar.vue:12`, `DocsSidebar.vue:12`; Logo
  in `pages/index.vue:137` (footer) + commented-out on 5 auth pages.
- Wordmark text literals next to LogoMark: `Header.vue:39`,
  `AppSidebar.vue:16`, `DocsSidebar.vue:16` - `<span>PM One</span>`.
- `app/pages/index.vue` = the whole PM One landing page (hero, 14 feature
  sections with `mockupUrl: "pmone.id/..."` strings, CTA with
  mailto:hello@panoramamedia.co.id, inline footer with `<Logo>` and
  "© ... PM One"). `usePageMeta(null, { title: "PM One", withoutTitleTemplate: true })`
  at `:198`. `app/stores/content.js:5` also has `home: { title: "PM One" }`.
- Shared-UI brand literals (user-visible, must become brand-agnostic):
  - `app/components/branding/BrandingForm.vue:23` placeholder "PT PM One",
    `:48` placeholder "info@pmone.id"
  - `app/pages/projects/[username]/settings/website-settings.vue:461`
    placeholder "e.g. PT Panorama Media"
  - `app/components/FormShortLink.vue:82,86,90,94` and
    `app/components/link-page/FormLinkPage.vue:76,80,84,88` - example URLs
    `pmone.id/<slug>`
  - `app/pages/tools/print-test.vue:36` placeholder `https://pmone.id/v/abc123`
    (and demo default at `:291`)
  - `app/pages/tools/whatsapp-tester.vue:116` placeholder
    `https://pmone.id/hotels/reservation/...`
  - `app/pages/hotels/reservation/[token].vue:229,233,236` - "Contact PM One
    support:" + `support@pmone.id` fallback
  - `app/pages/payment-gateways/guide.vue` - "PM One" x15 (lines 11-323) +
    hardcoded `https://api.pmone.id/api/webhooks/{midtrans,xendit}` (:237,:242)
    and `https://api.pmone.id/payment/redirect` (:247)
  - `app/pages/promotion-rules/guide.vue:11` - "...di PM One..."
  - `app/components/BrowserMockup.vue:8` - title prop default "pmone.id"
  - `app/components/appearance/AppearanceCustomizer.vue:50` label
    "Default (MinusOne)"; `app/lib/appearance/styles.ts:13` description
    "PM One's original look. Clean and neutral."
  - `app/components/FormProject.vue:486` `ORGANIZATION_OPTIONS` =
    ["Panorama Media", "CampX", "ASKINDO", "Global AI Expo"]
- Brand assets in `public/`: `favicon.ico`, `icons/apple-touch-icon.png`,
  `icons/icon-192x192.png`, `icons/icon-512x512.png`,
  `screenshots/{desktop-1,mobile-1}.png`, `img/hero-img-{1,2,3}.png`. Generic
  (leave): flags/, img/payment-methods/, sfx/, fonts/ (the MinusOne font is
  the PLATFORM font for all brands, not a brand asset - keep name and usage).
- Brand-clean already: `app/error.vue`, `app/app.vue`, all 4 layouts,
  `i18n/locales/*.json` (zero brand strings).
- Internal storage keys `pmone-*` / `pmone:*` (colorMode cookie, appearance
  cookie, scan IndexedDB, icon-picker, booking store, lucky-draw) - INTENTIONALLY
  KEPT, they are per-domain namespaces invisible to users. Same for the
  `pmOneApiKey` runtimeConfig KEY NAME and `NUXT_PM_ONE_API_KEY` env name
  (renaming would touch 15 server routes for zero user value) - keep the name,
  but remove the committed key literal (Step B1).

## Target architecture

### Backend: env is the brand layer

No tenant objects, no new tables, NO migrations. Two tiny additions:

1. `config/brand.php` - only for values that have no existing config home:
   ```php
   return [
       'support_email' => env('BRAND_SUPPORT_EMAIL', 'support@pmone.id'),
       'ics_domain' => env('BRAND_ICS_DOMAIN', 'pmone.id'),
   ];
   ```
   Everything else reuses what exists: display name = `config('app.name')`
   (APP_NAME is already per-deployment and already drives redis/cache/horizon
   prefixes + session cookie name), URLs = `config('app.url')` /
   `config('app.frontend_url')`, sender = `config('mail.from.*')`.
2. Literal sweep: every hardcoded "PM One"/"pmone.id" in runtime strings is
   replaced by those configs (Step A4). Defaults keep current pmone values so
   the pmone production deployment behaves byte-identically with NO .env change.

### Frontend: `BRAND` build arg + `#brand` alias

Each brand admin is its own Cloudflare Pages project building this same repo
with `BRAND=<id>` in the build environment. Brand selection is build-time
(logos, PWA icons, manifest are build artifacts anyway; per-brand builds also
tree-shake other brands' pages).

```
frontend/
  brands/
    index.ts            # registry: import each brand's meta; export brands map (NO .vue imports here)
    pmone/
      meta.ts           # plain object: id, name, shortName, siteUrl, apiUrl, company{}, contact{},
                        # manifestDescription, assetsReady: true, organizationOptions: [...]
      Logo.vue          # current PM One wordmark SVG (moved from app/components/Logo.vue)
      LogoMark.vue      # current mark SVG (moved)
      Home.vue          # current landing page (moved from app/pages/index.vue, template+data verbatim)
    monara/
      meta.ts           # Monara values; assetsReady: false until real assets arrive
      Logo.vue          # placeholder wordmark (simple SVG <text>MONARA</text>, TODO real logo)
      LogoMark.vue      # placeholder mark (simple geometric SVG, TODO real logo)
      Home.vue          # minimal placeholder landing (hero + Get Started -> /login), English copy,
                        # follows STYLE_GUIDE typography rules
  public/brands/pmone/  # favicon.ico, icons/, screenshots/, img/hero-img-{1,2,3}.png (moved)
  public/brands/monara/ # created empty with .gitkeep (assets pending from owner)
```

- `nuxt.config.ts`: `const brandId = process.env.BRAND || "pmone"`; import the
  registry, hard-fail the config load if `brandId` is unknown; alias
  `#brand` -> `./brands/<brandId>`; use meta for head.title, site.name/url,
  PWA manifest (name/short_name/description; icon + screenshot entries emitted
  ONLY when `assetsReady`), apple-touch + favicon links pointing at
  `/brands/<brandId>/...`, and prod defaults for `siteUrl`/`apiUrl`/
  `sanctum.baseUrl` (still overridable via `NUXT_PUBLIC_SITE_URL`,
  `NUXT_PUBLIC_API_URL`, `NUXT_SANCTUM_BASE_URL`).
- `app/app.config.ts`: `import meta from "#brand/meta"` and build the existing
  `app`/`contact` shape from it (all current `useAppConfig()` consumers keep
  working untouched).
- `app/components/Logo.vue` / `LogoMark.vue` become 3-line wrappers:
  `<template><BrandLogo /></template>` + `import BrandLogo from "#brand/Logo.vue"`.
  Every existing usage (classes like `text-foreground h-6`) keeps working via
  attribute fallthrough. No call sites change.
- `app/pages/index.vue` becomes a shell: renders `<BrandHome />` imported from
  `#brand/Home.vue`, sets `usePageMeta(null, { title: appConfig.app.name,
  withoutTitleTemplate: true })`.
- Rule going forward, stated in `brands/index.ts` header comment: shared code
  never mentions a brand; brand-owned files may hardcode their own brand.

## Commands you will need

| Purpose | Command | Expected |
|---------|---------|----------|
| Backend targeted | `php artisan test --compact --filter=Brand` | pass |
| CORS fix test | `php artisan test --compact --filter=CorsException` | pass |
| env:audit test | `php artisan test --compact --filter=EnvAudit` | pass |
| Full backend suite (once, at the end) | `php artisan test --compact` | 0 failures |
| Format PHP | `vendor/bin/pint --dirty` | clean |
| Frontend dev server | `cd frontend && pnpm dev` | serves on :3000 |
| Frontend dev as Monara | `cd frontend && BRAND=monara pnpm dev` | Monara branding on :3000 |
| Literal regression grep | `grep -rn --include="*.vue" --include="*.ts" --include="*.js" -i "pm one\|pmone" frontend/app frontend/nuxt.config.ts` | only allowlisted hits (comments, internal keys, wrappers) |

## Scope

**In scope**: everything under Steps A0-A5 and B1-B7 below. Backend `app/`,
`config/`, `bootstrap/app.php`, `resources/views/`, `.env.example`, tests.
Frontend `frontend/brands/` (new), `frontend/app/`, `frontend/nuxt.config.ts`,
`frontend/public/brands/` (new).

**Out of scope** (do NOT do):
- Any DB migration, any change to models/routes/API contracts.
- The pmone-events monorepo (`~/Frontend/pmone-events/`) - its base-layer
  brand audit is a separate follow-up plan.
- `frontend/content/docs/**` markdown ("PM One" x40+ in in-app docs) - known
  limitation, separate follow-up; note it in the README row.
- Real Monara visual identity (logo, icons, colors, landing design) - owner
  will supply; placeholders only, gated by `assetsReady: false`.
- Renaming `pmOneApiKey` runtimeConfig key / `NUXT_PM_ONE_API_KEY` env,
  `pmone-*` storage keys, Midtrans idempotency prefixes, artisan command
  signature `pmone:create-super-admin`, seeders/factories.
- Ops work (Forge site, DNS, SES, CF Pages, R2) - owner-manual, Appendix A.

## Steps

### Step 0: Drift check (10 min)
The line numbers in "Current state" were verified at `247fa3ad`. Re-grep before
editing: `grep -rn "CORS_ALLOWED_ORIGINS" bootstrap/app.php`,
`grep -rn "PM One\|pmone" app/ resources/views/ config/ | grep -v test`,
and the frontend grep from the commands table. If a cited file has moved or a
cited literal is already gone, adapt; if the whole shape differs (e.g. the CORS
handler was already fixed), STOP and report before proceeding.

### Step A1: Fix the CORS-on-exception env() bug
In `bootstrap/app.php` exception handler, replace the `env('CORS_ALLOWED_ORIGINS', ...)`
call with `config('cors.allowed_origins')` (already an array; drop the
`explode`). Audit the rest of `bootstrap/app.php` and `app/` for other runtime
`env()` calls outside `config/` (`grep -rn "env(" app/ bootstrap/`) - if any
exist, replace with config reads (add config keys if needed).
- New test `tests/Feature/Brand/CorsExceptionResponseTest.php`: override
  `config(['cors.allowed_origins' => ['https://brand-a.test']])`, request a
  guaranteed-404 API route with `Origin: https://brand-a.test`, assert
  `Access-Control-Allow-Origin` echoes the origin + credentials header; assert
  a non-allowlisted origin gets no ACAO header.
- **Verify**: `php artisan test --compact --filter=CorsException` passes.

### Step A2: Complete `.env.example` + `env:audit` deploy gate
1. Collect every `env('KEY'...)` across `config/*.php`
   (`grep -rhoE "env\('([A-Z0-9_]+)'" config/ | sort -u`). Add all missing keys
   to `.env.example`: production-mandatory keys UNCOMMENTED with a safe local
   value or empty; optional/feature keys as commented lines with a short
   comment. Mandatory-uncommented set at minimum: `APP_NAME`, `APP_URL`,
   `FRONTEND_URL`, `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`,
   `CORS_ALLOWED_ORIGINS`, `DB_*`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`,
   `MEDIA_DISK`, `AWS_BUCKET`, `AWS_URL`, `PAYMENT_TRUSTED_REDIRECT_HOSTS`,
   `BRAND_SUPPORT_EMAIL`, `BRAND_ICS_DOMAIN`, `HORIZON_DEFAULT_MAX_PROCESSES`,
   `HORIZON_ANALYTICS_MAX_PROCESSES`, `HORIZON_PDF_MAX_PROCESSES`,
   `HORIZON_TICKETS_MAX_PROCESSES`.
2. New command `app/Console/Commands/EnvAudit.php`, signature
   `env:audit {--env-file=.env} {--example=.env.example}`. Parses UNCOMMENTED
   `KEY=` lines from both files. Missing-in-env keys -> list + exit 1.
   Extra-in-env keys -> warning list, exit 0. PHPDoc explains the contract:
   ".env.example uncommented keys are the per-deployment manifest; every brand
   site must define them all."
- New test `tests/Feature/Brand/EnvAuditCommandTest.php` using temp files
  (write fixtures to `storage/framework/testing/`), covering pass, missing-key
  failure, extra-key warning, commented keys ignored.
- **Verify**: `--filter=EnvAudit` passes; `php artisan env:audit` runs against
  the real local `.env` without erroring (fix `.env.example` if it flags local
  noise; do NOT edit `.env` yourself - if the local `.env` is missing mandatory
  keys, report them in your summary instead).

### Step A3: Horizon sizing via env
In `config/horizon.php` `environments.production`, replace hardcoded
`maxProcesses` with `env('HORIZON_DEFAULT_MAX_PROCESSES', 10)`,
`env('HORIZON_ANALYTICS_MAX_PROCESSES', 3)`, `env('HORIZON_PDF_MAX_PROCESSES', 2)`,
`env('HORIZON_TICKETS_MAX_PROCESSES', 10)`. Defaults = current values, so pmone
production is unchanged without any .env edit. Leave `defaults` and `local`
blocks untouched.
- Test (in `tests/Feature/Brand/BrandConfigTest.php`): config values resolve to
  the defaults when env unset.
- **Verify**: `--filter=BrandConfig` passes.

### Step A4: `config/brand.php` + backend literal sweep
Create `config/brand.php` as specified in Target architecture. Then sweep, one
sub-edit per bullet (all defaults preserve current pmone output exactly):
1. `MagicLinkMail` subject -> `'Your '.config('app.name').' Login Link'`;
   `resources/views/emails/magic-link.blade.php` "PM One" -> `{{ config('app.name') }}`.
2. `resources/views/emails/exhibitor-invite.blade.php` "on PM One" ->
   `on {{ config('app.name') }}`.
3. Reservation email blades (`booking-received`, `hotel-voucher`,
   `cancellation`): fallback `'PM One Team'` -> `config('app.name').' Team'`;
   fallback `'support@pmone.id'` -> `config('brand.support_email')`.
4. PDF blades (`pdf/reservation/receipt`, `pdf/reservation/invoice`,
   `pdf/ticket/invoice`, `pdf/ticket/receipt`): `?? 'PM One'` ->
   `?? config('app.name')`.
5. `app/Support/EventIcs.php`: `'@pmone.id'` (3 sites) ->
   `'@'.config('brand.ics_domain')`; `PRODID:-//PM One//Tickets//EN` ->
   `'PRODID:-//'.config('app.name').'//Tickets//EN'`. (UIDs stay stable for
   pmone because the default is pmone.id.)
6. `app/Agents/ChatAgent.php:39`: "PM One" -> `config('app.name')`
   interpolation.
7. `app/Enums/Ticketing/PurchaseType.php` label:
   `'First-party ('.config('app.name').')'`.
8. `app/Services/OpenGraph/OpenGraphExtractor.php:52` UA: build from
   `Str::slug(config('app.name'), '')` + `config('app.frontend_url')`.
- New test `tests/Feature/Brand/BrandRenderingTest.php`: set
  `config(['app.name' => 'BrandX', 'brand.support_email' => 'help@brandx.test',
  'brand.ics_domain' => 'brandx.test'])`, then (a) render `MagicLinkMail` and
  assert subject + html contain "BrandX" and NOT "PM One"; (b) render the
  reservation blades via `view(...)->render()` with minimal fake data and
  assert no "PM One"/"support@pmone.id"; (c) `EventIcs::forEvent()` output
  contains `@brandx.test` + `PRODID:-//BrandX`; (d) `PurchaseType::FirstParty
  ->label()` contains "BrandX". Use existing factories; check sibling tests in
  `tests/Feature/` for mail/ics patterns first.
- **Verify**: `--filter=BrandRendering` passes.

### Step A5: Backend brand-literal guard
Add to `tests/Feature/Brand/BrandLiteralGuardTest.php`: recursively scan
`resources/views/` + `app/Mail/` + `app/Notifications/` file CONTENTS for the
case-insensitive literals `pm one` / `pmone` and assert zero hits outside an
explicit allowlist array (expected allowlist after the sweep: none; keep the
mechanism so future regressions fail CI-less test runs). Keep the scan narrow
(user-visible output dirs only) so doc-comments elsewhere stay legal.
- **Verify**: `--filter=BrandLiteralGuard` passes. Run `vendor/bin/pint --dirty`.

### Step B1: Frontend brand registry + nuxt.config
1. Create `frontend/brands/pmone/meta.ts` (values copied from today's
   `app.config.ts` + nuxt.config: name/shortName "PM One", siteUrl
   `https://pmone.id`, apiUrl `https://api.pmone.id`, company PT Panorama
   Media + address, contact email/whatsapp, manifestDescription = current
   manifest description, `assetsReady: true`,
   `organizationOptions: ["Panorama Media", "CampX", "ASKINDO", "Global AI Expo"]`),
   `frontend/brands/monara/meta.ts` (name/shortName "Monara", siteUrl
   `https://monara.id`, apiUrl `https://api.monara.id`, company/contact
   placeholder values clearly marked TODO, `assetsReady: false`,
   `organizationOptions: ["Monara"]`), and `frontend/brands/index.ts`
   exporting `const brands = { pmone, monara }` + the shared `BrandMeta` type.
   Header comment states the brand-layer rule. NO .vue imports in these files.
2. `nuxt.config.ts`: resolve `brandId`/`brand` from `process.env.BRAND`
   (default `"pmone"`, throw on unknown id); add `alias: { "#brand": ... }`
   pointing at `./brands/<brandId>`; replace `:53` head.title, `:313-314`
   site.name/site.url, PWA manifest name/short_name/description with brand
   values; emit manifest `icons`/`screenshots` and the apple-touch/favicon
   head links ONLY when `brand.assetsReady`, with paths under
   `/brands/<brandId>/...`; replace the `:20-22` and `:190` NODE_ENV ternaries
   with `process.env.NUXT_PUBLIC_SITE_URL || (isProd ? brand.siteUrl : "http://localhost:3000")`
   (same pattern for apiUrl and sanctum.baseUrl); REMOVE the hardcoded
   `pk_apm8...` fallback at `:16` (empty-string default; dev value comes from
   `frontend/.env`).
- **Verify**: `pnpm dev` boots clean (no unresolved `#brand`); document title
  is "PM One"; login works against localhost:8000 (proves sanctum/apiUrl
  unchanged in dev).

### Step B2: Brand components (Logo, LogoMark)
Move SVG contents of `app/components/Logo.vue` -> `brands/pmone/Logo.vue`,
`LogoMark.vue` -> `brands/pmone/LogoMark.vue`. Rewrite the two originals as
wrappers importing from `#brand/...` (single root element so class fallthrough
keeps working). Create `brands/monara/Logo.vue` (simple `<svg>` with
`<text>MONARA</text>`, `fill="currentColor"`, sensible viewBox, `<!-- TODO:
replace with real Monara wordmark -->`) and `brands/monara/LogoMark.vue`
(simple geometric mark, e.g. rounded square with "M").
- **Verify** (browser, dev server): header chip, app sidebar, docs sidebar
  render the mark; `/` footer renders the wordmark; no console errors.

### Step B3: app.config.ts from brand meta
Rewrite `app/app.config.ts` to `import meta from "#brand/meta"` and populate
the existing shape (`app.name/shortName/url`, `app.company`, `contact`) from
it. Keep `settings`, `routes`, `buildDate` as-is. Keep the
`isProduction ? meta.siteUrl : "http://localhost:3000"` behavior for `app.url`.
- **Verify** (browser): `/privacy` and `/terms` still render company name,
  address, contact email correctly (they are the heaviest app.config
  consumers).

### Step B4: Home page per brand
1. Move the ENTIRE current `app/pages/index.vue` content into
   `brands/pmone/Home.vue` verbatim (template + script data arrays). Inside it,
   update only the asset paths after Step B6 (`/img/hero-img-*.png` ->
   `/brands/pmone/img/hero-img-*.png`). PM One literals inside this file are
   now FINE (brand-owned).
2. New `app/pages/index.vue` shell: `<BrandHome />` from `#brand/Home.vue`,
   `definePageMeta({})` preserved, `usePageMeta(null, { title:
   appConfig.app.name, withoutTitleTemplate: true })`.
3. `app/stores/content.js:5`: home title "PM One" -> remove the literal
   (the index.vue override is the only consumer path for home; verify by
   grepping `getMetaByKey` usage for "home" before deleting; if consumed
   elsewhere, source it from `useAppConfig().app.name` at access time instead).
4. Create `brands/monara/Home.vue`: minimal clean landing (h1 "Manage events,
   exhibitors, and content from one dashboard." or similar generic copy, sub
   copy, Get Started -> /login button, small footer "© {year} Monara. All
   rights reserved."), English, STYLE_GUIDE-compliant, no fake features, no
   screenshots. Comment: owner will redesign later.
- **Verify** (browser): `/` unchanged visually for pmone (compare against
  main); restart dev with `BRAND=monara pnpm dev` and confirm `/` shows the
  Monara placeholder, tab title "Monara", header/sidebar wordmark "Monara",
  placeholder logos.

### Step B5: Shared-UI literal sweep
Using `useAppConfig()` (`app.name`, `contact.email`) and
`useRuntimeConfig().public` (`siteUrl`, `apiUrl`), fix every item listed in
"Current state - shared-UI brand literals". Specifics:
- Header/AppSidebar/DocsSidebar `<span>PM One</span>` ->
  `{{ appConfig.app.shortName }}`.
- FormShortLink + FormLinkPage examples: compute
  `const siteHost = new URL(config.public.siteUrl).host` once, render
  `` `${siteHost}/<slug>` ``.
- BrowserMockup default title -> siteHost (computed the same way).
- print-test placeholder + demo default and whatsapp-tester placeholder ->
  template from `config.public.siteUrl`.
- hotels/reservation/[token].vue -> "Contact {{ appConfig.app.name }} support:"
  and fallback email from... NOTE: this is the magic-link receipt page served
  by the ADMIN frontend; the correct fallback is `appConfig.contact.email`.
- payment-gateways/guide.vue: "PM One" -> `{{ appConfig.app.name }}`
  interpolations; webhook/redirect URLs -> template literals from
  `config.public.apiUrl`.
- promotion-rules/guide.vue same treatment.
- BrandingForm placeholders -> neutral examples ("Your Company Ltd",
  "billing@yourcompany.com"); website-settings.vue:461 -> "e.g. Your Company
  Ltd".
- AppearanceCustomizer label "Default (MinusOne)" -> "Default";
  styles.ts description -> "The original look. Clean and neutral."
- FormProject `ORGANIZATION_OPTIONS` -> `appConfig` value sourced from brand
  meta (`meta.organizationOptions`).
- **Verify** (browser): spot-check each touched page renders (at minimum:
  `/links` create form, `/payment-gateways/guide`, a project settings page,
  `/tools/print-test`); then run the literal regression grep from the commands
  table - remaining hits must be only: comments (category E), internal storage
  keys (category D), `pmOneApiKey` occurrences, and `brands/pmone/**`.

### Step B6: Brand assets restructure
`git mv public/favicon.ico public/brands/pmone/favicon.ico`; `git mv
public/icons public/brands/pmone/icons`; `git mv public/screenshots
public/brands/pmone/screenshots`; `git mv public/img/hero-img-*.png
public/brands/pmone/img/`. Update refs: nuxt.config icon/screenshot/appleTouch
paths (already brand-templated in B1), `brands/pmone/Home.vue` hero images,
and grep for any other `/icons/`, `/screenshots/`, `favicon.ico`,
`hero-img` references (`grep -rn "hero-img\|/icons/\|/screenshots/\|favicon"
frontend/app frontend/nuxt.config.ts`). Create `public/brands/monara/.gitkeep`.
- **Verify** (browser): favicon loads on pmone dev; no 404s in the network
  panel for icons on `/`; `BRAND=monara pnpm dev` has no icon/screenshot
  references in its manifest (assetsReady=false) and no 404 spam.

### Step B7: Final verification pass
1. Backend: `php artisan test --compact` (full suite) - 0 failures;
   `vendor/bin/pint --dirty` clean.
2. Frontend pmone: with the dev server + browser, walk: `/` landing, `/login`
   + authenticate, dashboard, one project settings page, `/privacy`, `/docs`
   entry, `/payment-gateways/guide`. Everything must look byte-identical to
   main except the swept placeholder texts.
3. Frontend monara: `BRAND=monara pnpm dev`, walk `/`, `/login`, tab titles,
   wordmarks, logos. Confirm zero "PM One" visible anywhere in the chrome
   (docs content is a known exception, out of scope).
4. `git status` - every change in scope; nothing stray.

## Test plan

New Pest files (all under `tests/Feature/Brand/`): `CorsExceptionResponseTest`,
`EnvAuditCommandTest`, `BrandConfigTest` (horizon env sizing + brand config
defaults), `BrandRenderingTest` (mail/blade/ics/enum against a fake brand),
`BrandLiteralGuardTest` (views/mail literal scan). Follow existing Pest
conventions (check `tests/Feature/` siblings for style, factories, and
`RefreshDatabase` usage). Frontend has no test harness; its verification is
the browser walkthroughs in B-steps (record what you saw in the final report).

## Done criteria

- [ ] `php artisan test --compact` fully green; `vendor/bin/pint --dirty` clean
- [ ] `bootstrap/app.php` exception CORS reads config, test proves env-cache safety
- [ ] `env:audit` command exists + tested; `.env.example` is the complete manifest
- [ ] Horizon production `maxProcesses` env-tunable with unchanged defaults
- [ ] Zero "PM One"/"pmone" literals in `resources/views`, `app/Mail`,
      `app/Notifications` (guard test enforces)
- [ ] `frontend/brands/{index.ts,pmone/*,monara/*}` exist; `#brand` alias wired
- [ ] `BRAND=monara pnpm dev` shows Monara name/logo/Home; default dev shows
      pmone unchanged
- [ ] Literal regression grep returns only allowlisted categories
- [ ] Assets moved under `public/brands/pmone/`; no 404s
- [ ] `plans/README.md` status row updated with outcome + browser-verification notes

## STOP conditions

- `config('cors.allowed_origins')` turns out to be shaped differently than the
  env string (e.g. supports patterns) - STOP and report before changing
  behavior.
- The `#brand` alias fails inside `app.config.ts` or `pages/index.vue` on the
  dev server (bundler edge case): fall back to a tiny Nuxt module that writes
  `app/brand-active.ts` re-exporting the selected brand at `prepare` time, and
  note the deviation. If that also fails, STOP.
- Any existing test breaks in a way unrelated to your change - STOP, report,
  do not "fix" unrelated tests.
- Rendering tests reveal a template that embeds "PM One" through data you
  cannot reach via config (e.g. seeded DB content) - leave it, list it in the
  final report.
- You are tempted to touch `.env` (local or otherwise) - don't; report needed
  keys instead.

## Maintenance notes

- **Adding a whitelabel brand `acme`** (code side, ~30 min): create
  `frontend/brands/acme/{meta.ts,Logo.vue,LogoMark.vue,Home.vue}`, register in
  `brands/index.ts`, drop assets in `public/brands/acme/`, set
  `assetsReady: true`. Backend needs NOTHING (env-only). Ops side: Appendix A.
- The brand-layer rule: shared code never names a brand; `brands/<id>/**` may.
  The guard test + regression grep keep this honest.
- Known follow-ups (separate plans): pmone-events base-layer brand audit;
  `frontend/content/docs/**` de-branding; optional nitro exclusion of other
  brands' `public/brands/*` dirs from each build.

## Appendix A: Ops runbook (OWNER-MANUAL - the executor must NOT do these)

Provisioning Monara, after this plan is merged and deployed to pmone
(pmone needs the new BRAND_*/HORIZON_* keys added to its Forge .env only if
`env:audit` is wired into its deploy script; defaults keep behavior identical):

1. **DB**: on the VPS Postgres: `CREATE DATABASE monara;` (owner forge). Check
   `max_connections` (target 200).
2. **Forge site** `api.monara.id` on server MinusOne, same repo + branch
   `main`, PHP 8.4, zero-downtime deploy like api.pmone.id; enable quick
   deploy. Deploy script mirrors pmone's (composer install, `php artisan
   env:audit`, migrate --force, permissions:sync, config:cache?, horizon:terminate)
   - keep the two scripts textually identical except nothing (env carries all
   differences).
3. **.env for monara** = copy pmone's, then change: `APP_NAME=Monara`,
   `APP_URL=https://api.monara.id`, `FRONTEND_URL=https://monara.id`,
   `SESSION_DOMAIN=.monara.id`, `SANCTUM_STATEFUL_DOMAINS=monara.id` (+ CF
   preview domain when needed), `CORS_ALLOWED_ORIGINS=https://monara.id,...`,
   `DB_DATABASE=monara`, `REDIS_DB=2`, `REDIS_CACHE_DB=3`, explicit
   `REDIS_PREFIX=monara-database-`, `CACHE_PREFIX=monara-cache-`,
   `HORIZON_PREFIX=monara_horizon:`, `MAIL_FROM_ADDRESS=noreply@monara.id`,
   `MAIL_FROM_NAME="${APP_NAME}"`, `SES_CONFIGURATION_SET=monara-transactional`
   + new SNS topic -> api.monara.id webhook, `AWS_BUCKET=monara`,
   `AWS_URL=https://cdn.monara.id`, `PAYMENT_TRUSTED_REDIRECT_HOSTS=monara.id`
   (+ client event domains later), `BRAND_SUPPORT_EMAIL=support@monara.id`,
   `BRAND_ICS_DOMAIN=monara.id`, `BACKUP_NOTIFICATION_EMAIL=...`,
   `HORIZON_DEFAULT_MAX_PROCESSES=1`, `HORIZON_ANALYTICS_MAX_PROCESSES=1`,
   `HORIZON_PDF_MAX_PROCESSES=1`, `HORIZON_TICKETS_MAX_PROCESSES=1` (4 GB RAM;
   raise after upgrading to 8 GB), fresh `APP_KEY` (`php artisan key:generate --show`).
4. **Daemons/cron**: Horizon daemon for `/home/forge/api.monara.id/current`
   (identical command to pmone's) + scheduler cron every minute. Identical
   workflow on both sites, per the locked decision.
5. **R2**: bucket `monara` + custom domain cdn.monara.id.
6. **Bootstrap data**: run `php artisan pmone:create-super-admin` +
   `php artisan permissions:sync` on the monara site; set AppSetting branding
   + scanner sounds via the dashboard; create an ApiConsumer (allowed_origins =
   monara admin + future event sites) and put its key in the CF Pages env.
7. **CF Pages**: new project from the same repo, root `frontend/`, build env:
   `BRAND=monara`, `NUXT_PUBLIC_SITE_URL=https://monara.id`,
   `NUXT_PUBLIC_API_URL=https://api.monara.id`,
   `NUXT_SANCTUM_BASE_URL=https://api.monara.id`, `NUXT_PM_ONE_API_KEY=<monara
   ApiConsumer key>`; custom domain monara.id. Add the `*.pages.dev` preview
   host to SANCTUM_STATEFUL_DOMAINS + CORS when preview logins are needed.
8. **Email**: SES identity for monara.id (DKIM/SPF/DMARC), configuration set
   `monara-transactional`, SNS topic subscribed to
   `https://api.monara.id/api/webhooks/ses`. Until SES production access,
   `MAIL_MAILER=resend` with a monara.id sender, mirroring pmone's setup.
9. **Guardrails**: Forge monitor alert at memory 75%; deployment-failed
   notifications on BOTH sites; check swap exists (`free -m`, add 2 GB
   swapfile if absent); stagger monara's backup/analytics-heavy hours vs
   pmone if IO contention shows up.
10. **Smoke checklist per brand launch**: login on monara.id; password reset
    email (link -> monara.id, sender noreply@monara.id); magic-link email copy
    says Monara; a PDF receipt shows Monara branding (AppSetting); media upload
    lands on cdn.monara.id; Horizon dashboard reachable at
    api.monara.id/horizon; `php artisan env:audit` green on both sites.
