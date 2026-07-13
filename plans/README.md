# Implementation Plans — PM One ↔ pmone-events integration, reliability, security, perf/SEO

Two waves of advisor work live here.

- **Wave 2 (plans 007–020)** — generated 2026-07-12 against pmone `24ea67e1` /
  pmone-events `72491aa`. The current focus: make the integration between the
  PM One backend and the 16 event websites smoother and more complete by moving
  hardcoded per-site config into dashboard-managed runtime data (with a strict
  perf/SEO guardrail so the sites never get slower), plus the verified
  reliability, security, and SEO/CWV work that supports it.
- **Wave 1 (plans 001–006)** — the ticketing/check-in/payment audit from
  2026-07-11. All DONE, awaiting merge (condensed status at the bottom).

Method for Wave 2: 5 parallel read-only auditors (frontend freshness, backend
cache-invalidation, public-API security, baked-vs-runtime inventory, deploy/DX)
plus a follow-up perf + SEO pass. The perf/SEO/scaffolding auditors were truncated
by a session limit; every finding turned into a plan below was **re-vetted by
reading the cited code directly**, and the perf/SEO plans (029/030) are structured
to measure before changing. No source code was modified — these are handoff plans.

Read each plan fully before starting. Honor its STOP conditions. Execute on a
branch — do not push or open a PR unless the operator asks. Update your status row
when done.

> **Key architectural finding that shapes Wave 2**: the event sites already
> **await one `/api/event/website-settings` call on every SSR render**
> (`projectSettings.ts` plugin), shared through a single keyed fetch. Adding
> nav/analytics/appearance/copy into that same payload therefore costs **zero new
> round-trips** — this is why the config-to-dashboard migration can be done without
> regressing Core Web Vitals. Plan 007 establishes this contract; 008–012 build on
> it. Also: event core data (dates/status/poster/edition) is **already 100%
> runtime** — the yearly rollover already needs no rebuild.

## Multi-brand wave (plan 031) — 2026-07-13

The owner is launching a second brand, **Monara (monara.id)**, from this same
codebase, plus future whitelabel client brands. Locked architecture decision:
NO multitenancy package; one repo deployed as one Forge site per brand on the
same VPS (own Postgres DB, own Horizon, identical workflows) + one Cloudflare
Pages project per brand for `frontend/`. Plan 031 makes the shared source
brand-agnostic (brand registry `frontend/brands/<id>/` selected via `BRAND`
build env, backend `config/brand.php` + literal sweep) and hardens deployment
(live CORS-on-exception `env()` bug fix, Horizon sizing via env, `env:audit`
deploy gate). Owner ops runbook for provisioning Monara is Appendix A of the
plan. Known follow-ups (separate future plans): pmone-events base-layer brand
audit; `frontend/content/docs/**` de-branding.

| Plan | Title | Priority | Effort | Depends on | Status |
|------|-------|----------|--------|------------|--------|
| 031 | Multi-brand brand layer (PM One + Monara + whitelabel) | P1 | L | - | DONE-code / BROWSER-VERIFIED / UNCOMMITTED (executed 2026-07-13 directly on branch `advisor/031-multibrand-brand-layer`; operator handles git) |

### Execution log — 2026-07-13 (plan 031)

Executed in-session (not handed off). All steps A1-A5 + B1-B7 complete:

- **Backend**: CORS-on-exception now reads `config('cors.allowed_origins')`
  (env-cache-safe; only remaining runtime `env()` outside config/ eliminated);
  `.env.example` completed as the deployment manifest + new `env:audit` command
  (deploy gate); Horizon production `maxProcesses` env-tunable
  (`HORIZON_{DEFAULT,ANALYTICS,PDF,TICKETS}_MAX_PROCESSES`, defaults unchanged);
  `config/brand.php` (`support_email`, `ics_domain`) + full literal sweep
  (MagicLink mail, exhibitor invite, 3 reservation blades, 4 PDF blades,
  EventIcs UIDs/PRODID, ChatAgent persona, PurchaseType label, OpenGraph UA).
  15 new Pest tests in `tests/Feature/Brand/` (Cors 2, EnvAudit 6, Config 2,
  Rendering 4, LiteralGuard 1) - all green; guard test caught + fixed one
  literal the plan inventory missed (`AttendeeETicketMail` comment).
- **Frontend**: `frontend/brands/{index.ts,types.ts,pmone/*,monara/*}` registry,
  `#brand` alias selected via `BRAND` build env (default pmone, throws on
  unknown); `Logo`/`LogoMark` wrappers (call sites untouched); landing moved
  verbatim to `brands/pmone/Home.vue`, `pages/index.vue` is a brand shell;
  Monara placeholder Home/Logo/meta (`assetsReady: false` gates all icon and
  manifest asset references); app.config sourced from `#brand/meta` (+
  `app.brandId`, `organizationOptions`); nuxt.config head/site/PWA/sanctum/
  runtime URLs brand-driven with `NUXT_PUBLIC_SITE_URL`/`NUXT_PUBLIC_API_URL`/
  `NUXT_SANCTUM_BASE_URL` overrides; committed API-key fallback literal removed;
  ~18 shared-UI files swept (header/sidebars wordmark, both guides, form
  placeholders, `{{ appDomain }}` examples, BrowserMockup + reconciliation
  dialog - the latter was misclassified as a comment in the plan inventory,
  actually UI text, fixed); assets moved to `public/brands/pmone/`.
- **Browser-verified** on the dev server: default build = PM One byte-identical
  (dashboard chrome, /privacy, payment guide with runtime webhook URLs, zero
  console errors); `BRAND=monara pnpm dev` = "Dashboard · Monara" title, Monara
  wordmark + "M" placeholder mark, no icon links, zero console errors; both
  brands' Home components compile (vite @fs probe; note `/` redirects to
  /dashboard by design via `root-redirect.global.ts`, so brand Home pages are
  dormant until a marketing landing is re-enabled).
- **Test tally**: Tickets 416/0, Reservation+Hotel+Mail+Auth+Brand 383/0 (1
  pre-existing skip), remaining dirs 875/2, root files 1156/1 (8 skips). The 3
  failures are all pre-existing: 2 QaMatrix stale-date fixtures (documented
  above, reproduced on baseline) + `EventDocumentTest` booth-type filter which
  passes in isolation (cross-test pollution, same class as the documented
  TicketPurchaseTest flake). `vendor/bin/pint --dirty` clean.
- **Machine note**: the single-process full suite cannot finish on the
  operator's laptop - `DocumentService`/`TicketDocumentService` call
  `set_time_limit(120)` in their constructors, so any full run whose remaining
  wall time exceeds 120s dies with `FatalException: Maximum execution time of
  120 seconds exceeded`. Ran in 4 chunks instead. Worth a tiny follow-up
  (only bump the limit when not running in console/testing).
- **Owner follow-ups**: add the 7 new mandatory keys to local + both prod
  `.env`s when wiring `env:audit` into deploy scripts
  (`PAYMENT_TRUSTED_REDIRECT_HOSTS`, `BRAND_SUPPORT_EMAIL`, `BRAND_ICS_DOMAIN`,
  `HORIZON_*_MAX_PROCESSES` x4 - the command lists them); supply Monara assets
  + real company/contact values in `brands/monara/meta.ts`, then flip
  `assetsReady`; provisioning runbook = plan Appendix A. Out-of-scope
  follow-ups unchanged: pmone-events base-layer audit, docs content
  de-branding.

## Wave 2 — execution order & status

Recommended order below. The four P1 bug/security fixes (013–015 + 026) are
independent, tiny, high-confidence, and directly enable reliable propagation — do
them first. Then the foundation (007) unblocks the config-to-dashboard migrations.

> **Numbering note**: plans 016–025 are a **separate, concurrent ticketing-scale
> wave** authored by another advisor session (atomic counters, async payment,
> queue/DB scaling, anti-scalper, waitlist, capacity, seating, RSVP) — listed at
> the bottom under "Concurrent wave". This integration wave uses 007–015 and
> **026–030** to avoid number collisions with it.

| Plan | Title | Priority | Effort | Depends on | Status |
|------|-------|----------|--------|------------|--------|
| 026 | Stop public `settings`-blob leak + fix form-upload path traversal | P1 | S | — | DONE (executed+reviewed 2026-07-12, branch `advisor/026-...`; extended to close the same leak on the unauth `ProfileController` endpoint; backend-tested, APPROVED) |
| 013 | Close response-cache invalidation gaps (faqs/tickets/hotels/OG) | P1 | S | — | DONE (executed+reviewed 2026-07-12, branch `advisor/013-...`; all 6 gaps, 45 invalidation tests pass, APPROVED) |
| 014 | Stop caching empty payloads on PM One outage; tighten long TTLs | P1 | S-M | — | DONE-code / BROWSER-UNVERIFIED (executed+reviewed 2026-07-12, branch `advisor/014-...`; grep criteria pass, APPROVED — browser-verify outage+happy-path before merge) |
| 015 | Null-safe base pages (fix campx /book-space 500) + per-app data fixes | P1 | M | — | DONE-code / BROWSER-UNVERIFIED (executed+reviewed 2026-07-12, branch `advisor/015-...`; guards + contract + data fixes, APPROVED — browser-verify + 2 TODOs before merge) |
| 007 | Establish the `site-config` runtime contract (foundation) | P1 | M | — | TODO |
| 008 | Manage navigation (header/dialog/footer) from the dashboard | P2 | M-L | 007 | TODO |
| 009 | Manage analytics IDs (GA4 + TikTok pixel) from the dashboard | P2 | S-M | 007 | TODO |
| 027 | Hash API keys at rest, drop query-string keys, opt-in project scoping | P2 | M | — | TODO |
| 028 | Harden public abuse surfaces (attendee relay, tracking, pagination) | P2 | S-M | — | TODO |
| 030 | Maximize technical SEO (canonical/hreflang, richer schema, meta) | P2 | M | — | TODO |
| 029 | Protect + maximize Core Web Vitals; enforce migration guardrail | P2 | M | (coord 008/012) | TODO |
| 010 | Manage appearance/theme tokens from the dashboard | P3 | M | 007 | TODO |
| 011 | Manage legal/policy page content + company identity from the dashboard | P3 | M-L | 007 | TODO |
| 012 | (SPIKE) Move page/section copy + SEO meta to a translatable store | P3 | L | 007, 011 | TODO |

Status values: TODO | IN PROGRESS | DONE | BLOCKED (one-line reason) | REJECTED (one-line rationale).

## Execution log — 2026-07-12 (plans 026, 013, 014, 015)

Four plans executed via `improve execute` — each by a separate `sonnet` executor in
an isolated git worktree, then reviewed (scope + re-run criteria + read diff +
audit tests). **Nothing merged/pushed — merge is the user's decision.** Verdicts:
all four **APPROVED**.

- **026** — branch `advisor/026-public-settings-leak-and-form-traversal` (worktree
  `/tmp/advisor-wt-026`, 3 commits). New `PublicProjectResource`/`PublicEventResource`
  omit the raw `settings` blob; `PublicProjectController` show/event/activeEvent use
  them; form `revert` traversal guard added. Leak test 7 pass / traversal 13 pass
  (reviewer re-ran). **Reviewer-directed extension**: also swapped the
  **unauthenticated** `ProfileController::getProjectProfile` (`GET /api/projects/{username}`,
  no api.key — the more exposed door) to the public resource; `getUserProfile`
  confirmed clean (`UserResource`, no settings). Admin resources untouched.
- **013** — branch `advisor/013-cache-invalidation-gaps` (worktree `/tmp/advisor-wt-013`).
  All 6 gaps (A faqs, B ticket/session/phase reorder, C EventDay, D gateway tenant
  tags, E media→website-settings, F rundown poster race). Step 7 (Event `tickets`
  tag) correctly **deferred** (the generic `settings` write path is never exercised
  by the admin UI). Documented deviation: Gap C extended to `EventDayController::sync()`
  + `setActive()` (query-builder bulk paths the trait can't see) — serves the plan's
  intent, approved. 45 invalidation tests pass (spy-asserts exact tags).
- **014** — branch `advisor/014-outage-empty-cache-and-ttl` (worktree `/tmp/advisor-wt-014`).
  `orNullIf404` rethrows non-404s in all six section handlers (outage → 5xx, not a
  cached empty-200); 4 pages demoted 900s→300s. Grep criteria pass.
- **015** — branch `advisor/015-content-contract-and-campx-500` (worktree `/tmp/advisor-wt-015`).
  7 base components + 2 base pages null-safe (`v-if`/optional-chaining, cannot
  regress populated apps); dev-only `REQUIRED_CONTENT_KEYS` contract; campx
  `skipStaticPages` removed; renex copy/GA + global-ai-expo GA + keramika edition
  corrections.

**Required before merging 014 / 015** (frontend, executed with no browser — the
verifications are inherently browser-based): in the dev server confirm (014) `/faq
/partners /gallery /programs /rundown` render live, a dead-API-port request returns
5xx not an empty-200, and the no-event empty state still 200s; and confirm the
ofetch 404 shape matches `orNullIf404` (safe-fail if not); (015) campx `/`,
`/book-space`, `/partners`, `/brands` return 200, megabuild renders pixel-identical
(no regression), and the dev content-contract warning fires.

**Open TODOs left by the executors (need human input, correctly NOT guessed):**
- `apps/global-ai-expo/nuxt.config.ts` — real GA4 measurement id (gtag left disabled + TODO).
- `apps/keramika/i18n/locales/en.ts` — Keramika's real edition count ("21 editions" is
  Megabuild-inherited; left + TODO).
- Business question flagged: campx `/book-space` now renders a generic contact form
  (a campground has no exhibitor booths) — restored per plan preference; decide if
  that page should exist for campx.

**Pre-existing (NOT introduced by this work):** the full backend suite shows 3
failures — 2 stale-hardcoded-date QaMatrix fixtures + 1 full-suite-only
`TicketPurchaseTest` cross-test pollution (the known flaky documented in Wave 1
follow-ups). Reproduced on the `24ea67e1` baseline via `git stash`. Worth its own
small plan (reset the polluting fixture); unrelated to 013.

## Execution log 2 — 2026-07-12 (plans 007-011, 027-030 + 012 spike)

The remaining Wave 2 plans were executed (each a `sonnet` executor in an isolated
worktree, stacked in dependency order for the config chain), reviewed, and the
whole set integration-tested. **Nothing merged/pushed.**

- **007-011 (config → dashboard chain)** — APPROVED. Stacked branches
  `advisor/007…` → `advisor/011…` (both repos). 007 site_config foundation +
  `useSiteConfig` + contract doc; 008 nav editor + fail-open sourcing; 009
  analytics (nuxt-gtag manual-init + dashboard-override dataLayer fix); 010
  appearance picker + `dependsOn` plugin ordering; 011 `WebsitePage` translatable
  model + legal-page fail-open + company identity. Backend fully tested; frontend
  runtime browser-verified on the prod stack (megabuild renders, /terms baked legal
  body renders, nav fail-open, zero SSR/console errors).
- **027 API key hardening** — APPROVED. Hash-in-place (backfill test proves NO key
  rotation needed), header-only, opt-in project scoping. Adds 2 migrations.
- **028 abuse hardening** — APPROVED. e-ticket + account caps, tracking `exists`
  validation + referer bound, `per_page` clamp (ceiling 1000 for sitemap endpoints).
- **029 Core Web Vitals** — APPROVED (measure-first; no prod Lighthouse). Statically
  -safe LCP fixes + perf regression gates in the contract doc; heavier items recommended.
- **030 SEO** — APPROVED. Audit confirmed canonical+hreflang ALREADY emitted by
  @nuxtjs/i18n (no-op); Event schema poster-over-logo; BreadcrumbList + BlogPosting.
- **012 copy/SEO-meta SPIKE — deliverables APPROVED, prototype HELD from production.**
  Design docs + `website_copy` backend are sound, but the prototype's locale-aware
  `useProjectSettingsData` calls `useI18n()` inside the awaited `projectSettings`
  plugin — **browser-verified to 500 every page** (`"Must be called at the top of a
  setup function"`, vue-i18n). Fix: resolve locale via `nuxtApp.$i18n`/route before
  shipping. `advisor/012-…` is EXCLUDED from the integration branches.

**Integration verified (both repos merge clean onto current main; nothing pushed):**
- `advisor/integration-pmone` — main + 026 + 013 + 027 + 011-chain(007-011) + 028.
  Full merged backend suite: **571 passed / 7210 assertions / 0 failed** (3 new
  migrations apply cleanly).
- `advisor/integration-events` — main + 014 + 015 + 011-chain(007-011) + 029 + 030.
  Browser-verified rendering + fail-open on megabuild (full prod stack).

**Deploy (post-merge):** `php artisan migrate` (3 new: `api_key_hash`,
`api_consumer_project`, `website_pages`) — **no key rotation, no `permissions:sync`**.
pmone-events push triggers the CF Pages rebuild of all 16 sites.

**Admin UI = browser-UNVERIFIED (auth-gated):** the new settings tabs (Navigation,
Analytics, Appearance, Legal Pages, SEO Meta) were code-completed + syntax-checked
but not clicked through (admin login needs credentials the advisor can't enter).
Confirm save round-trips after deploy.

**Follow-ups:** (1) 012 useI18n-in-plugin fix; (2) plan 015's dev-only
`contentContract` check false-positives on the 12 i18n-setup-store apps (reads
`store.$state`) — dev noise only, DCE'd in prod, guards are correct; (3) retire
morefood's legacy `tiktok-pixel.client.js` for the shared analytics plugin (009);
(4) campx has no `schemaOrg.enabled` so new schema won't render there (030);
(5) outingexpo has an orphaned 16MB `hero-video.mp4` (029).

## Dependency notes (Wave 2)

- **007 blocks 008, 009, 010, 011, 012.** It adds the `site_config` container to
  the website-settings payload, the reusable `useSiteConfig()` consumer, and the
  binding `docs/site-config-contract.md` guardrail. Do it before any migration plan.
- **012 also depends on 011** — it reuses 011's translatable-content (`WebsitePage`)
  pattern. Land 011's model first.
- **029 coordinates with 008 and 012** — it defines the perf regression gates
  (SSR-rendered config, no new fetch, CLS≈0, non-empty meta on outage) that the
  nav and copy migrations must pass. Run 029 Step 1 (baseline) early so the
  migrations have a number to beat.
- **013/014 are independent** but both serve "dashboard edits propagate fast",
  which is the point of the whole migration — do them early.
- **026/027/028 are independent security** and can run in parallel with anything.
- The migration plans all keep the baked `app.config`/`content.js` values as the
  **fail-open fallback** — none of them delete baked config, so each is
  individually safe and reversible.

## Findings map (Wave 2 — which plan covers which audited finding)

| Audit finding(s) | Severity | Plan |
|---|---|---|
| SEC-01 (settings-blob leak: internal emails/config), SEC-02 (form revert path traversal) | HIGH / MED | 026 |
| CACHE-01 (link→faqs), CACHE-03/04 (ticket/EventDay reorder→tickets), CACHE-05 (gateway→hotels/website-settings), CACHE-02 (media→website-settings), CORR-01 (rundown re-cache race) | MED | 013 |
| FRESH-CORR-01 (empty-200 cached on outage), FRESH-02 (900s TTL → 16-min staleness) | HIGH / MED | 014 |
| DEBT-01 (campx /book-space 500 + missing content-key contract), DEBT-03/04 (renex GA id, global-ai-expo no analytics, stale Megabuild copy) | HIGH / LOW | 015 |
| Inventory: settings-payload already SSR-awaited; site-config contract + guardrail | — | 007 |
| DIR (nav baked, ~60% of app.config; Hotels runtime precedent) | HIGH | 008 |
| DEBT-03 + DIR (analytics IDs baked) | MED | 009 |
| SEC-03 (API key plaintext + query-param + no tenant scope) | HIGH / MED | 027 |
| SEC-04 (attendee e-ticket relay), SEC-05/CORR-02 (tracking no-exists + unbounded per_page) | MED | 028 |
| SEO (missing canonical/hreflang across 5 locales; Event schema uses logo not poster; no price/breadcrumb/Article) | MED | 030 |
| Perf guardrail (baked→runtime migration must not regress CWV) + heavy-app CWV | MED | 029 |
| DIR (appearance tokens baked, opt-in engine exists) | LOW | 010 |
| DIR (legal page bodies baked in shared .vue; company identity baked) | MED | 011 |
| DIR (2.4–4k copy strings × 5 locale × 12 apps baked; highest-churn files) | HIGH (value) | 012 |

## Deploy notes (Wave 2, per plan)

Plans touching the backend DB/permissions require the usual post-merge steps:
- **007/008/009/010/011** add `website_settings.site_config.*` — no migration
  (JSON blob), but 011 adds a `WebsitePage` table → `php artisan migrate`.
- **027** adds `api_key_hash` → `php artisan migrate` (hash-in-place, **no key
  rotation needed**; rotation is an optional follow-up).
- Any plan that adds a permission → `php artisan permissions:sync`.
- Frontend changes deploy via the normal Cloudflare Pages build. Because config now
  comes from the API, most *content* changes after this work need **no rebuild** —
  which was the goal.

## Findings considered and rejected / deferred (so nobody re-audits them)

- **Deploy/ops features (per-project Deploy button via CF Pages deploy hooks, edge
  -cache purge button, build watch paths to stop 16× rebuilds, CI gate)** — real
  and valuable (the deploy/DX audit found the backend already has every building
  block: per-project settings JSON, domain map via `Project::websiteUrl()`, the
  `response-cache/clear` ops-endpoint pattern, CF env plumbing). **Deferred by
  operator decision**: the focus is data integration (config→dashboard), not
  deployment. Revisit if rebuild frequency/cost becomes the pain point. An
  edge-cache **purge** button is the highest-leverage of these for "edited but
  still shows old" and pairs naturally with plan 013.
- **Persistent KV cache for the Nitro handler layer** (currently per-isolate
  memory) — deferred to a measured investigation inside plan 029; may not beat the
  edge layer.
- **Company identity as its own plan** — folded into 011 (low churn; rides with the
  legal pages that already interpolate `companyName`).
- **`getKey` cache-key collision (non-`\w` stripping), 504-vs-500 labeling in
  `pmOneFetch`, rundown reorder N-clear storm, sitemap partial-failure caching,
  Event `tickets` tag (CACHE-06), root `/` 302-on-locale crawl** — real but LOW;
  noted in the relevant plan's Maintenance/Deferred sections, not planned here.
- **components/ui 3-way sync check, new-event scaffolder, nuxt.config factory,
  base content-store dialect unification, pnpm-override documentation** — DX
  tech-debt from the deploy/DX audit; worth a future hygiene pass, out of scope for
  the integration focus.
- **App-wide global-permission authorization model; multi-tenancy** — by-design /
  reverted decision; not findings (consistent with Wave 1's rejections).

## Not audited / out of coverage (Wave 2)

- The perf + SEO code-level auditors were truncated by a session limit; plans 029
  (perf) and 030 (SEO) therefore **start with a measurement/audit step** and inline
  only the facts re-vetted directly (image/font/OG baseline, meta/schema structure).
  Their first step establishes ground truth before any change.
- The admin-UI scaffolding map (how to add a settings tab end-to-end) was not fully
  captured; the migration plans (008–011) point the executor at the existing
  website-settings + hotel-reservations admin pages to mirror, with a STOP
  condition if that pattern can't be located.
- Wave 1's out-of-coverage list (hotel domain internals, PDF rendering, SDK-level
  checkout) still applies.

---

## Ticketing scale & all-event-types wave (plans 016–025) — execution order

> **STATUS 2026-07-13**: Phase 1 (016/017/018) + Phase 2 (021/019/020) are all
> **MERGED to `main` and PUSHED to prod** (merge commit `10227c19`; Phase 2
> reconciled onto main via cherry-pick, 4 additive conflicts in
> createOrder/Event resolved, full Tickets+Webhook **450 passed / 0 failed** gate
> before push). Plan files 016–021 removed (done). Remaining: **022** (P3
> manifest) + spikes **023/024/025**. Open follow-ups carried forward: ⚠ 016
> Postgres load-test gate (pre-on-sale), 018 pgbouncer/ops, 019 Turnstile FE
> (needs `bot_protection_enabled` on public Event resource), 020 FE + session/phase
> waitlist + event-capacity↔waitlist wiring, 021 bulkGenerate-comp headcount
> decision. 017 FE (preparing/pay states) + origin-redirect + EmptyState already
> shipped in pmone-events.

Authored 2026-07-12 to make the ticketing system serve **all event types**
(concerts/flash-sale, RSVP, private, conference), not just expos. **Base = `main`**:
the Wave-1 ticketing program (001–006) that these built on is now merged to `main`
and deployed — so 016–025 branch off `main`, not the old `advisor/*` stack.

**Sequencing constraints:**
- **Serial (file overlap):** 016, 017, 019, 021, 020 all edit
  `app/Services/Ticket/TicketPurchaseService.php` → must stack in order (parallel
  branches would conflict).
- **Parallel-safe (different files):** 018 (config/Horizon), 022 (scan service).
- **Dependency keystone:** 016's atomic-reservation primitive is what 021 (event
  counter), 020 (waitlist claim holds), and 024 (seat holds) build on — so 016 lands first.
- **Spikes (023/024/025):** design + prototype + write a follow-up build plan
  (031+); run in parallel, low code risk. 024's prototype wants 016 done first.

### Recommended order

**Phase 1 — Throughput foundation (do first; this is the "survive thousands concurrent" core)**
1. **016** Atomic inventory counters — KEYSTONE. HIGH-risk money-path rewrite;
   execute carefully + **load-test it** before anything stacks on it.
2. **017** Async payment-link creation — stack on 016.
   - *In parallel:* **018** Queue/DB scale hardening + ops runbook (independent
     files; ops needs the pgbouncer/Redis/Horizon runbook before any big on-sale anyway).

**Phase 2 — Fair on-sale + capacity (concert/conference readiness)**
3. **021** Event-total-capacity cap — stack; small quick win, needs 016's atomic counter.
4. **019** Anti-scalper (per-buyer cap + server-side Turnstile) — stack.
5. **020** Waitlist / notify-when-available — stack; needs 016, benefits from 021's event-release.

**Phase 3 — Large-event + design spikes (parallel, lower urgency)**
6. **022** Scalable check-in manifest — P3; parallel-safe; only matters for 20k+ events.
7. **023 / 024 / 025** SPIKES (waiting room / seating / RSVP) — design + prototype,
   run in parallel; each outputs a follow-up build plan (031+) to schedule after.

| Plan | Title | Phase | Priority | Type | Status |
|------|-------|-------|----------|------|--------|
| 016 | Atomic inventory counters (drop lock+SUM) | 1 | P1 | build | **DEPLOYED to prod** 2026-07-12 — my merge `9e41ccdb` is an ancestor of pushed `origin/main` `fe64876d` (integration-wave session pushed it; batch-50 migrations applied on prod confirm the deploy ran). 016 preserved intact (reserve/release present, lockForUpdate count identical to my merge). ⚠ **GATE NOT YET RUN**: Postgres load test (k6, 500 vus) still owed before the next big on-sale — code is semantically tested (410 green), not load-tested under real concurrency. |
| 017 | Async payment-link creation | 1 | P1 | build | **DEPLOYED to prod** 2026-07-12 (in `fe64876d`). Backend live + Pest-verified. **FRONTEND-FOLLOWUP still TODO** (pmone-events): "preparing payment" state + 30s hint + remove dead payment_url redirect (existing FE degrades gracefully meanwhile). |
| 018 | Queue + DB connection scale hardening | 1 (parallel) | P1 | build+ops | **DEPLOYED to prod** 2026-07-12 (in `fe64876d`) — `tickets` queue/supervisor + 3 buyer-facing jobs routed + `docs/scale-runbook.md`. **OPS still TODO**: pgbouncer + prod env steps in the runbook before the next big on-sale. |
| 021 | Event-level total-capacity cap | 2 | P2 | build | DONE-code / REVIEWED / UNCOMMITTED (executed+reviewed 2026-07-12, worktree branch `worktree-agent-abe3f6c3bf1d7b3a5` off `main`; `events.capacity`+`reserved_count`, atomic `Event::reserveHeadcount` w/ correct parenthesization + regression guard, wired createOrder/expire/refund. Reviewer-directed extension: closed the `reconfirmAfterExpiry` under-count gap the executor flagged (pre-check event capacity → needs_reconciliation + restore reserved_count) + 2 tests. `bulkGenerate` comps intentionally NOT counted (documented decision). EventCapacity 14/14, full Tickets+Webhook **429 passed / 0 failed**, pint clean. NOT committed/merged — main advanced past the worktree base, rebase before merge.) |
| 019 | Anti-scalper: per-buyer cap + Turnstile | 2 | P2 | build | DONE-code / REVIEWED / UNCOMMITTED (executed+reviewed 2026-07-12, worktree branch `worktree-agent-a964744a0602f6d31` off `main`; per-buyer cap `tickets.max_per_buyer`+`events.max_tickets_per_buyer` in createOrder w/ held-qty query (Confirmed+non-expired-pending, email+user_id match, `(event_id,buyer_email)` index), server-side Turnstile (`config/turnstile.php`, `Turnstile::verify` fail-closed, `events.bot_protection_enabled`, fail-open no-secret/expo). **Reviewer SECURITY fix**: removed the client-spoofable `source=admin` Turnstile bypass — proxy forwards the browser body verbatim, so a `source=admin` field was attacker-controllable; controller uses `validated()` so dropping the rule also stops `source`-column spoofing. Test rewritten as a security guard. AntiScalper 12/12, Tickets **393 passed / 0 failed**, pint clean. **FE follow-up**: `checkout.vue` Turnstile widget needs `bot_protection_enabled` exposed on the public Event resource first (not done). Overlaps 021 in `createOrder` (both uncommitted) — reconcile at merge. NOT committed/merged; rebase (main advanced). |
| 020 | Waitlist / notify-when-available | 2 | P2 | build | DONE-code / REVIEWED / UNCOMMITTED (executed+reviewed 2026-07-12, worktree branch `worktree-agent-aee07a1010fb67d10` off `main`; `TicketWaitlistEntry` model + `WaitlistService` (join/offer/claim/sweep) + `OfferWaitlistSeatsJob` + 3 mail jobs + scheduled `ExpireStaleWaitlistOffersJob`. Oversell-safe: reserve-on-offer via 016 `Ticket::reserve()`, release-on-expiry, claim consumes the held seat WITHOUT re-reserving (nested-tx rollback returns a failed claim to Offered, no leaked hold); claim↔sweep race mutually-exclusive via `lockForUpdate`. **`createOrder` untouched (0 lines)** — claim uses a sibling `createOrderFromWaitlistClaim()`; expire/refund hooks are 1 line each (isolated from 021). Public routes throttled + validated. Waitlist 9/9, Tickets **390 passed / 0 failed**, pint clean. Reviewer verified oversell math + race + throttle; no fixes needed. **Follow-ups**: session/phase-level waitlist, event-capacity(021)↔waitlist wiring, notify_only re-notify de-dup, FE (`TicketList.vue` sold-out affordance + claim page), admin waitlist UI. NOT committed/merged; rebase (main advanced). |
| 022 | Scalable check-in manifest | 3 (parallel) | P3 | build | TODO |
| 023 | Virtual waiting room | 3 | P2 | **SPIKE** | TODO |
| 024 | Reserved / assigned seating | 3 | P2 | **SPIKE** | TODO |
| 025 | RSVP / invitation flow | 3 | P2 | **SPIKE** | TODO |

## Wave 1 — Tickets / Check-in / Business Matching / Payment Gateway (DONE, awaiting merge)

Generated 2026-07-11 against `24ea67e1`. All six executed the same day in isolated
worktrees; **awaiting user merge**. Full detail (branch topology, per-plan
follow-ups, considered-and-rejected) is preserved in git history of this file; the
condensed status:

| Plan | Title | Status |
|------|-------|--------|
| 006 | Scope webhook settlement to authenticated project + validate amount | DONE — branch `advisor/006-webhook-project-scoping` (independent, merge on its own) |
| 003 | Purchase-time inventory integrity | DONE — base of the stacked chain |
| 002 | Payment settlement vs expiry | DONE — stacked on 003 (2 follow-ups noted in plan) |
| 004 | PricePhase integrity | DONE — stacked on 002 |
| 005 | Offline outbox sync hardening | DONE-backend / FRONTEND-UNVERIFIED — browser-verify before merge |
| 001 | Ticket-order refund & cancellation model | DONE — TIP of the stacked chain (contains 003+002+004+005+001) |

**Merge**: two branches — `advisor/006-…` (independent) and
`advisor/001-ticket-refund-cancel` (the stacked tip containing all five). Before
merging 001/005, browser-verify the `useScanSession.ts` offline-sync frontend
(implemented without a browser). **Post-merge deploy**: `php artisan migrate` (new
columns on `ticket_orders`/`ticket_order_items`/`attendees`) +
`php artisan permissions:sync` (`attendees.refund`).

Wave 1 follow-ups still open (do not re-audit): the flaky Webhook→Tickets
test-isolation bug; `settleTicketSession` paid-after-expiry gap; access-code hold
not restored on reconfirm. See each plan's Maintenance section.
