# Plan 037: pmone-events - maximize SEO, performance, accessibility, PageSpeed

> Authored 2026-07-14 from a read-only audit of `~/Frontend/pmone-events`
> (Nuxt 4 pnpm monorepo: `layers/base` + 16 apps, all `nitro.preset:
> "cloudflare-pages"`, SSR on). Successor to the merged plans 029 (CWV) / 030
> (SEO) - those landed canonical/hreflang verification, poster-over-logo Event
> schema, BreadcrumbList/BlogPosting, and the edge-cache stack. This plan covers
> what the new audit found still on the table. Self-contained.

## Status

- **Priority**: P2 overall; workstream 1 is P1 (real SEO content gap)
- **Effort**: L (many small independent items - execute workstream by workstream)
- **Risk**: MED - every `layers/base` change ships to all 16 sites on the next
  push; the repo has strict fail-open + CWV guardrails (see STOP conditions)
- **Before starting**: `git status` in pmone-events - there is known
  uncommitted local work (useSiteConfig collision fix, other backlog). Do not
  discard or mix it in; work on a branch off the current state and keep diffs
  scoped to this plan.

## Step 0 - measure first (always)

Baseline BEFORE any change, re-run AFTER each workstream:

- PageSpeed Insights (remote - allowed; NEVER local builds/typecheck on this
  laptop) for at least: megabuild, global-ai-expo, keramika, iicc - mobile +
  desktop, home + one content page (/news or /brands). Record
  LCP/CLS/TBT/scores in this file's Results section.
- `curl -s <prod-home> | grep -c` for the SSR-HTML checks in workstream 1.

## Workstream 1 (P1): put the four "legacy" home sections into the SSR HTML

**Finding (answers the operator's question "do the section toggles hurt
SEO?"):** the toggle system has two modes (`layers/base/app/composables/useHomeSection.ts`):

- `defaultVisible: true` sections (Hero, About Event, Partnerships, sliders,
  all per-app custom sections) read the `project-settings` payload that
  `layers/base/app/plugins/projectSettings.ts` awaits during SSR -> the `v-if`
  decision is made server-side. Toggled-off sections are simply absent from the
  HTML; toggled-on sections are fully crawlable. **No SEO problem.**
- `defaultVisible: false` sections - **Rundown, Brand Preview, Hotels,
  Credits/Partners** (`useRundownVisibility.ts`, `useBrandPreviewVisibility.ts`,
  `useHotelSectionVisibility.ts`, `useCreditsVisibility.ts`) - issue their own
  `useFetch(..., { server: false, lazy: true })`. During SSR the data is `null`
  so `v-if` is ALWAYS false: **these four sections never appear in the initial
  HTML even when enabled.** Non-JS crawlers never see them; real users get a
  post-hydration pop-in (layout shift) on every load.

**Operator context (2026-07-14)**: `server: false` was deliberate - the home
page USED to be prerendered, so a server-side fetch would have baked the data
at build time (never updating without a rebuild). Home is no longer prerendered
(`layers/base/modules/cf-cache.ts` STATIC_PAGES list does not include `/`;
home is runtime-SSR on CF Workers behind a 5-15 min edge cache), so the
original reason is gone and the operator has approved moving these to SSR.
Before changing anything, confirm no app still prerenders `/` (check every
`apps/*/nuxt.config.ts` for `routeRules`/`nitro.prerender` entries covering the
home route).

**Fix** (keep fail-closed semantics `stored === true`, keep `?show-*` force
params from `useForceShow.ts`, keep the content guards - rundown needs >=1 item
with content, brand preview needs >10 logo brands, `BRAND_PREVIEW_MIN`):

1. Toggle reads: switch the four wrapper composables to the SSR-resolved
   payload via `useNuxtData("project-settings")` (same pattern as the
   `defaultVisible: true` branch - zero new fetches; the payload is already
   awaited by the plugin). API outage -> payload null -> sections hidden ->
   identical to today's fail-closed behavior.
2. Content guards: to be SSR-visible the guard data must also resolve
   server-side. Do it WITHOUT extra round-trips by sharing the fetch key with
   the section component's own data fetch (e.g. the guard and `<LazyRundown>`
   both `useFetch("/api/event/rundown", { key: "..." })` -> Nuxt dedupes into
   one SSR fetch, already backed by a 300s SWR `defineCachedEventHandler`
   proxy). Audit each of the four for its data endpoint and existing keys
   before wiring.
3. Update the `useHomeSection.ts` docblock (it documents the old tradeoff).
4. Measure: TTFB on the four heaviest apps before/after (the SSR now awaits
   the guard fetches). Nitro-cached proxies should keep this in single-digit
   ms after warm-up; if TTFB regresses beyond the contract gates, fall back to
   toggle-SSR-only for the offending section and report.
5. Verify: `curl` the prod/preview home HTML - enabled sections' headings must
   be present; disabled ones absent; no hydration-mismatch warnings in dev
   console.

Note for expectations: home HTML is edge-cached 5-15 min
(`layers/base/server/plugins/cacheControl.ts` + `shared/cf-cache-rules.ts`) and
the settings proxy is 60s SWR - dashboard toggle changes propagate within
minutes, not instantly. That is by design; do not "fix" it here.

## Workstream 2 (P1): SEO correctness holes

1. **Canonical**: `layers/base/app/composables/usePageMeta.js` sets `ogUrl` but
   no explicit `<link rel="canonical">`. Verify on rendered prod HTML whether
   `@nuxtjs/seo` (site-config) injects one on every route; if any route lacks
   it, add canonical emission to `usePageMeta` (from `appConfig.app.url +
   route.path`, strip query).
2. **iicc is a bespoke outlier**: `apps/iicc/app/pages/index.vue` hand-rolls
   its meta, skips `usePageMeta()` AND `useEventSchema()` -> no Event schema,
   no dashboard SEO-copy overrides, and its own FAQ renders a second `<h1>`.
   Align it with the other 12 event apps (adopt both composables, pass
   `tag="h2"` to FAQ, keep any iicc-specific copy as overrides).
3. **Duplicate `<h1>`**: `layers/base/app/components/FAQ.vue` defaults
   `tag: "h1"`; campx (`apps/campx/app/pages/index.vue`) and iicc don't pass
   `tag` and already have a Hero `<h1>`. Fix the two call sites AND flip the
   component default to `"h2"` - but first grep all `<FAQ`/`<LazyFAQ` usages
   (including any standalone `/faq` pages) and pass an explicit `tag="h1"`
   where the FAQ heading legitimately is the page heading.
4. **Event schema enrichment** (`layers/base/app/composables/useEventSchema.js`):
   add `offers.price`/`priceCurrency` from the lowest active ticket price. Only
   if it can ride an already-SSR-available/cached source (e.g. a cheap cached
   proxy or a field already in the settings/event payload) - the composable's
   docblock records that this was skipped for TTFB cost, so measure. If no
   cheap source exists, add the lowest price to the PM One public event/settings
   payload first (small pmone change) rather than a new SSR fetch.
   `eventStatus`/`eventAttendanceMode` stay hardcoded (model has no such
   fields) - leave the docblock note.
5. **Sitemap completeness**: `layers/base/server/api/sitemap-urls.ts` emits
   only `/news/[slug]` + `/brands/[slug]`. Audit other indexable dynamic routes
   (programs, guests, gallery, rundown - whatever has public detail pages) and
   add them with `lastmod` behind the same 1h `defineCachedFunction` pattern.
6. Non-event agency apps (campx, panorama-events, panorama-media) are out of
   scope for Event schema; do not force it there.

## Workstream 3 (P2): performance / PageSpeed

1. **Delayed hydration for below-fold sections** - the biggest untouched TBT/
   Workers-CPU lever. The home pages already use `Lazy*` async components
   (chunk-splitting only). Apply Nuxt 4 native lazy hydration
   (`hydrate-on-visible` / `hydrate-on-idle`) to below-fold home sections,
   section by section, starting with the static-ish ones (About, Partnerships,
   Facts & Figures, FAQ). Interactive sections (carousels, forms) get
   `hydrate-on-visible`; verify each still works when scrolled to. Skip
   anything above the fold. Measure TBT before/after on PSI.
2. **AVIF**: `layers/base/nuxt.config.ts` `image: { format: ["webp"] }` ->
   `["avif", "webp"]` after confirming the production `cloudflare` provider
   (CF Image Resizing) has AVIF enabled on the current plan. Spot-check bytes
   on a heavy gallery page.
3. **Root `/` edge-cache investigation** (`resolveCacheControl()` in
   `layers/base/shared/cf-cache-rules.ts` deliberately never caches `/`
   because the i18n redirect varies by cookie). Highest-traffic route pays
   full Worker SSR every hit. Investigate options and DECIDE with evidence,
   do not just implement: (a) serve default-locale content at `/` without
   redirect (i18n strategy change - big, risky), (b) edge-cache `/` only for
   requests without the locale cookie, (c) leave as-is if Worker CPU numbers
   say it is cheap. Record the decision here.
4. **LCP audit per app**: hero media. Ensure the hero image/poster gets
   `fetchpriority="high"` + preload where it is the LCP element; the hero
   headline font is already preloaded (`MinusOne-VF.woff2`, display swap).
   Remove/compress the orphaned 16MB `apps/outingexpo/.../hero-video.mp4`
   (known leftover flagged by plan 029 follow-ups) - confirm it is truly
   unreferenced first.
5. Do NOT touch: the analytics deferral (`layers/base/app/plugins/
   analytics.client.ts` already defers all four vendors to `app:mounted`),
   `nuxt-vitalizer`'s `disablePrefetchLinks`, the prerender list in
   `layers/base/modules/cf-cache.ts`, or the PII-excluded cache rules for
   /tickets and /hotels sub-flows.

## Workstream 4 (P2): accessibility

1. **Skip-to-content link**: none exists repo-wide. Add to
   `layers/base/app/layouts/default.vue`: visually-hidden-until-focused link
   targeting `<main id="main">` (all 16 apps inherit). Style per the existing
   focus-visible ring system.
2. **Alt text fixes**: `layers/base/app/components/DialogRundown.vue` hardcodes
   `alt=""` on `activity.poster_image` (real content) -> use the activity
   title. `Gallery.vue` fallback `alt="Gallery"` -> use item title/event name
   when available; keep `alt=""` ONLY for genuinely decorative images.
3. Quick pass: heading order on one representative home page after workstream
   2's h1 fixes; confirm reka-ui dialog/accordion/carousel ARIA still intact
   (they were verified good - don't churn them).

## Verification & deploy notes

- Dev verification in the browser at `localhost:3000` per app (nvm-managed
  node; Claude manages its own dev server; NEVER `npm run build`/
  `nuxi typecheck` locally - CRITICAL laptop rule; CF builds only via push).
- A push to pmone-events main rebuilds all 16 CF Pages sites - pushing is the
  operator's call, never automatic. Known CF build gotchas: og-image fan-out
  hang (never build from terminal), global-ai-expo needs NODE_OPTIONS heap 6GB.
- After deploy: re-run PSI on the baseline set; fill the Results section.

## STOP conditions

- Honor `docs/site-config-contract.md` guardrails (SSR-rendered config, no new
  uncached SSR fetch on the critical path, CLS ~ 0, non-empty meta on outage).
  Any change that violates a gate: revert that item and report.
- Fail-open invariants are sacred: legal pages never render empty; analytics
  never breaks the app; sections fail closed on missing settings exactly as
  today.
- If a workstream item needs a PM One backend change beyond adding one field to
  a public payload (workstream 2.4), STOP and split it out.
- No commits/pushes unless the operator asks.

## Results (fill during execution)

| App | Metric | Before | After |
|---|---|---|---|
| megabuild (mobile) | Perf score / LCP / TBT / CLS | | |
| global-ai-expo (mobile) | Perf score / LCP / TBT / CLS | | |
| keramika (mobile) | Perf score / LCP / TBT / CLS | | |
| iicc (mobile) | Perf score / LCP / TBT / CLS | | |
