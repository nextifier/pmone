export const meta = {
  name: 'responsecache-audit',
  description: 'Audit public routes for spatie/laravel-responsecache coverage + correct cache invalidation',
  phases: [
    { title: 'Inventory', detail: 'authoritative route + invalidation maps' },
    { title: 'Audit', detail: 'per-tag invalidation correctness + coverage + recent changes' },
    { title: 'Verify', detail: 'adversarially verify each finding against the code' },
    { title: 'Report', detail: 'dedup, severity, concrete fixes' },
  ],
}

// ---------------------------------------------------------------------------
// Seed maps (from scouting routes/api.php, models, controllers). Agents must
// CONFIRM these against the code, not trust them blindly.
// ---------------------------------------------------------------------------
const SEED = `
PROJECT FACTS (confirm against code, do not trust blindly):
- spatie/laravel-responsecache ^8.3. Middleware applied PER-ROUTE as
  CacheResponse::for(ttlSeconds, 'tag') in routes/api.php. NOT global. Only
  successful GET requests are cacheable (CacheAllSuccessfulGetRequests profile).
- Invalidation mechanisms:
  (a) Trait App\\Traits\\ClearsResponseCache: on model saved/deleted/restored,
      calls ResponseCache::clear(static::responseCacheTags()). Fires ONLY on
      Eloquent model events. Bypassed by: query-builder update()/delete(),
      DB:: writes, ->upsert(), mass updates, pivot attach/sync/detach WITHOUT
      a model save, and Spatie MediaLibrary add/clear media (media changes do
      NOT fire the parent model's saved event).
  (b) Manual ResponseCache::clear(['tag']) in some controllers/jobs/commands.

CACHE TAG -> CACHED ROUTES (GET) -> KNOWN INVALIDATORS:
- short-links     | /resolve/{slug} (also resolves USER PROFILES) | models ShortLink, LinkPage, LinkPageItem; manual: LinkPageController, LinkPageItemController, UserController
- blog-posts      | public/blog/posts, /featured, /search, categories/{}/posts, tags/{}/posts, authors/{}/posts (NOTE: /posts/{slug} deliberately NOT cached - trackVisit) | model Post
- projects        | public/projects/{username} | model Project
- events          | {u}/events, /events/active, /events/{slug}, /editions | model Event; manual: ProjectController, EventController
- brands          | editions/{n}/brands, editions/{n}/brands/{slug}, {u}/brands, {u}/brands/{slug}, {u}/brands-with-conjunctions, {u}/events/{slug}/brands, {u}/events/{slug}/brands/{slug} | models Brand, BrandEvent, PromotionPost; manual: EventConjunctionController, EventController
- partners        | {u}/events/{slug}/partners, {u}/editions/{n}/partners | models Partner, PartnerCategory
- promotion-posts | {u}/events/{slug}/brands/{slug}/promotion-posts | models PromotionPost, BrandEvent; manual: EventController
- rundown         | {u}/events/{slug}/rundown | model RundownItem; manual: ProjectController
- website-settings| {u}/website-settings | NO model clears this tag - backed by Project.settings JSON; manual: ProjectController@updateWebsiteSettings ONLY
- guests          | {u}/events/{slug}/guests, {u}/events/{slug}/guests/{slug} | model Guest; manual: GuestController, BulkSoftDeleteGuests job
- exchange-rates  | exchange-rates, /currencies, /popular, /convert, /{currency} | model ExchangeRate (FetchExchangeRates job uses create() => events fire)
- hotels          | public/hotels, public/events/{slug}/hotels/{slug} | models Hotel, RoomType, HotelTransferOption, HotelEvent; manual: EventController. NOTE: availability + daily-availability + daily-availability-aggregate are intentionally NOT cached (volatile)

KNOWN-SUSPECT HYPOTHESES (investigate explicitly, confirm or refute):
1. website-settings: Project model clears only 'projects', so website-settings/
   rundown/events caches rely SOLELY on ProjectController manual clear. Any other
   write path to Project.settings (other controller, EventController, console,
   direct save) leaves those caches stale.
2. short-links /resolve resolves USER PROFILES too (ProfileController). User model
   changes likely do NOT clear 'short-links' => stale profile cache.
3. Pivot updates: BrandEvent.status, HotelEvent.is_active, order_column reorders.
   If updated via DB/sync/updateExistingPivot without a model save, trait won't fire.
4. MediaLibrary: brand logo, hotel photos, partner logo, guest photo, post cover.
   Adding/removing media does NOT fire the owning model's saved event => image
   changes won't bust the relevant tag. Confirm whether any save() follows.
5. Hotel 'show' route cached 3600s. Does its payload embed availability/pricing
   that changes more often than 3600s => stale availability risk?
6. Recent git changes: app/Services/Brand/*, PublicBrandDetailResource,
   PublicBrandIndexResource, BrandProfileScoreResource, PromotionPost, Link,
   BrandEventController. Do new public brand fields (profile score, marketing)
   depend on data not covered by the 'brands' tag invalidators?
7. Orphan tags: model Contact clears 'contacts' but NO route caches 'contacts'.
   Find any other tag mismatches (cached-but-never-invalidated, or
   invalidated-but-never-cached).
8. TTL sanity: any volatile data cached too long, or per-user/auth data
   accidentally cached (CacheResponse on an authenticated route)?
`

const TAGS = [
  'short-links', 'blog-posts', 'projects', 'events', 'brands', 'partners',
  'promotion-posts', 'rundown', 'website-settings', 'guests',
  'exchange-rates', 'hotels',
]

const FINDINGS_SCHEMA = {
  type: 'object',
  required: ['tag', 'cachedRoutes', 'dataSources', 'findings'],
  properties: {
    tag: { type: 'string' },
    cachedRoutes: {
      type: 'array',
      items: {
        type: 'object',
        required: ['method', 'uri', 'ttl'],
        properties: {
          method: { type: 'string' },
          uri: { type: 'string' },
          ttl: { type: 'integer' },
          controller: { type: 'string' },
        },
      },
    },
    dataSources: {
      type: 'array',
      description: 'Models / resources / relationships / settings each cached route reads',
      items: { type: 'string' },
    },
    invalidatorsConfirmed: {
      type: 'array',
      description: 'Confirmed invalidation paths for this tag',
      items: {
        type: 'object',
        required: ['name', 'mechanism'],
        properties: {
          name: { type: 'string' },
          mechanism: { type: 'string', description: 'e.g. ClearsResponseCache trait, manual ResponseCache::clear' },
        },
      },
    },
    findings: {
      type: 'array',
      items: {
        type: 'object',
        required: ['id', 'severity', 'type', 'title', 'detail', 'file'],
        properties: {
          id: { type: 'string', description: 'short stable id e.g. brands-media-1' },
          severity: { type: 'string', enum: ['P0', 'P1', 'P2', 'P3'] },
          type: {
            type: 'string',
            enum: ['missing-invalidation', 'bypass-eloquent-events', 'stale-risk', 'orphan-tag', 'partial-coverage', 'wrong-ttl', 'cached-auth-route', 'other'],
          },
          title: { type: 'string' },
          detail: { type: 'string' },
          file: { type: 'string', description: 'path:line of the offending code' },
          evidence: { type: 'string', description: 'code snippet or grep result proving it' },
          suggestedFix: { type: 'string' },
        },
      },
    },
  },
}

const VERDICT_SCHEMA = {
  type: 'object',
  required: ['findingId', 'isReal', 'confidence', 'reasoning'],
  properties: {
    findingId: { type: 'string' },
    isReal: { type: 'boolean' },
    confidence: { type: 'string', enum: ['high', 'medium', 'low'] },
    reasoning: { type: 'string', description: 'What you checked in the code to confirm or refute' },
    correctedSeverity: { type: 'string', enum: ['P0', 'P1', 'P2', 'P3'] },
    suggestedFix: { type: 'string', description: 'Concrete, minimal fix referencing real file/method' },
  },
}

const COVERAGE_SCHEMA = {
  type: 'object',
  required: ['uncachedRoutes'],
  properties: {
    uncachedRoutes: {
      type: 'array',
      items: {
        type: 'object',
        required: ['method', 'uri', 'controller', 'shouldCache', 'reasoning'],
        properties: {
          method: { type: 'string' },
          uri: { type: 'string' },
          controller: { type: 'string' },
          shouldCache: { type: 'boolean' },
          reasoning: { type: 'string' },
          recommendedTag: { type: 'string' },
          recommendedTtl: { type: 'integer' },
          recommendedInvalidator: { type: 'string', description: 'which model/controller must clear the tag if cached' },
        },
      },
    },
  },
}

// ===========================================================================
// PHASE 1 — INVENTORY (confirm route + invalidation reality from code)
// ===========================================================================
phase('Inventory')

const inventory = await agent(
  `You are auditing a Laravel 12 app at the repo root for spatie/laravel-responsecache correctness.

${SEED}

TASK: Produce an AUTHORITATIVE inventory by reading the actual code. Do NOT trust the seed.
1. Run \`php artisan route:list --json\` (or grep routes/api.php + routes/web.php) and list EVERY public / unauthenticated route (no auth:sanctum). For each: method, uri, controller@method, and whether it has CacheResponse::for(...) with its tag+ttl.
2. Grep all CacheResponse::for( usages -> exact tag + ttl per route.
3. Grep all responseCacheTags() in app/Models -> model -> tags.
4. Grep all ResponseCache::clear( in app/ -> file:line -> tags.
5. Note any route guarded by auth:sanctum/verified that ALSO has CacheResponse (would be a bug).

Return a concise structured text inventory: (A) cached public GET routes by tag, (B) uncached public GET routes, (C) tag->invalidators map, (D) any tag that is cached-but-never-invalidated or invalidated-but-never-cached. Be exact with file:line.`,
  { label: 'inventory', phase: 'Inventory' }
)

// ===========================================================================
// PHASE 2+3 — per-tag audit -> adversarial verify (pipelined, no barrier)
// PLUS parallel coverage + recent-changes dimensions
// ===========================================================================

const perTagPrompt = (tag) => `You are auditing cache invalidation for ONE spatie/laravel-responsecache tag: "${tag}".

${SEED}

CONFIRMED INVENTORY FROM PHASE 1 (authoritative):
${inventory}

TASK for tag "${tag}":
1. Identify EVERY cached public GET route using this tag (CacheResponse::for(ttl, '${tag}')). Read each serving controller method.
2. For each route, enumerate EVERY data source the JSON response depends on: Eloquent models, relationships, nested API Resources, computed attributes, Spatie media (logos/photos), Spatie tags, JSON settings columns, pivot fields. Read the actual Resource classes.
3. For EACH data source, prove whether a create/update/delete/reorder/media-change to it clears the "${tag}" cache:
   - Does the model use ClearsResponseCache AND does responseCacheTags() include "${tag}"?
   - OR does every write path call ResponseCache::clear([...'${tag}'...])?
   - Check write paths that BYPASS Eloquent events: query-builder update()/delete(), DB::, upsert(), pivot attach/sync/detach/updateExistingPivot, mass operations, MediaLibrary add/clearMediaCollection, ->touch() vs save().
4. Output findings ONLY for real gaps with file:line evidence. Severity guide:
   - P0: public data can change but cache NEVER busts on the normal admin/user write path (users see stale data indefinitely up to TTL ${tag === 'website-settings' ? '86400s' : ''}).
   - P1: busts on some paths but a common path (media change, pivot update, reorder, bulk op) leaves it stale.
   - P2: stale only on rare/admin-only path, or TTL too long for volatility.
   - P3: orphan tag / cosmetic / cleanliness.
Read real code (Read/Grep/Bash). Do not speculate without evidence.`

const verifyPrompt = (f, tag) => `Adversarially VERIFY this cache-invalidation finding for tag "${tag}". Default to isReal=false unless the code proves it.

FINDING:
- id: ${f.id}
- severity: ${f.severity}
- type: ${f.type}
- title: ${f.title}
- detail: ${f.detail}
- file: ${f.file}
- evidence: ${f.evidence || '(none given)'}
- suggestedFix: ${f.suggestedFix || '(none given)'}

Open the cited file(s) and the relevant model/controller/Resource. Confirm:
1. Is the data source actually exposed by a route cached under "${tag}"? (if not -> not real)
2. Does a NORMAL write path truly leave the cache stale? Check trait fires, manual clears, model observers, and whether the write path uses Eloquent events. Look for a save()/touch() that WOULD fire the trait even after the suspicious operation.
3. Is the severity correct given the route's TTL and how the admin UI actually mutates this data?
Return verdict with the concrete minimal fix referencing real symbols.`

// Pipeline: each tag's findings get verified the moment that tag's analysis lands.
const perTagResults = await pipeline(
  TAGS,
  (tag) => agent(perTagPrompt(tag), { label: `audit:${tag}`, phase: 'Audit', schema: FINDINGS_SCHEMA }),
  (analysis, tag) => {
    const findings = (analysis && analysis.findings) || []
    if (findings.length === 0) {
      return { tag, analysis, verified: [] }
    }
    return parallel(
      findings.map((f) => () =>
        agent(verifyPrompt(f, tag), { label: `verify:${f.id}`, phase: 'Verify', schema: VERDICT_SCHEMA })
          .then((v) => ({ finding: f, verdict: v }))
      )
    ).then((verified) => ({ tag, analysis, verified: verified.filter(Boolean) }))
  }
)

// Coverage + recent-changes dimensions (independent of the per-tag pipeline)
phase('Audit')
const [coverage, recentChanges] = await parallel([
  () => agent(
    `Audit CACHE COVERAGE of public routes (Laravel 12, spatie/laravel-responsecache).

${SEED}

CONFIRMED INVENTORY:
${inventory}

TASK: For EVERY public / unauthenticated GET route that does NOT have CacheResponse::for(...), decide whether it SHOULD be cached.
- shouldCache=true for stable read-mostly public content (profiles, listings, detail pages) that lacks a side effect.
- shouldCache=false for: routes with side effects (trackVisit, counters), per-user/token/magic-link data, volatile availability/pricing, search with high-cardinality query params, anything already correctly excluded.
For each shouldCache=true route, recommend a tag (reuse an existing tag if the data overlaps), a TTL, and which model/controller must invalidate that tag.
Pay special attention to: /{user:username} (getUserProfile), /projects/{username} (getProjectProfile), /s/{slug} (resolveShortLink) vs /resolve/{slug} (cached) inconsistency, posts/{post:slug} public show, public/forms/{slug}.`,
    { label: 'coverage', phase: 'Audit', schema: COVERAGE_SCHEMA }
  ),
  () => agent(
    `Audit whether RECENT UNCOMMITTED changes introduced cache gaps.

${SEED}

TASK:
1. \`git status\` + \`git diff\` the modified/new files: BrandEventController, PublicProjectController, BrandEventIndexResource, PublicBrandDetailResource, PublicBrandIndexResource, Link, PromotionPost, app/Services/Brand/*, BrandProfileScoreResource test, tests/Unit/Brand/*.
2. Determine if any NEW public-facing field (e.g. brand profile score, marketing data) is now served by a route cached under 'brands' or 'promotion-posts', and whether the data backing that field invalidates the tag when it changes.
3. Specifically: app/Models/Link.php uses ResponseCache::clear([$tag]) - confirm the dynamic $tag is correct and fires on the right events.
Return findings as structured text with file:line and severity P0-P3.`,
    { label: 'recent-changes', phase: 'Audit' }
  ),
])

// ===========================================================================
// PHASE 4 — SYNTHESIS
// ===========================================================================
phase('Report')

const confirmedFindings = perTagResults
  .filter(Boolean)
  .flatMap((r) =>
    (r.verified || [])
      .filter((v) => v.verdict && v.verdict.isReal)
      .map((v) => ({
        tag: r.tag,
        id: v.finding.id,
        severity: v.verdict.correctedSeverity || v.finding.severity,
        type: v.finding.type,
        title: v.finding.title,
        detail: v.finding.detail,
        file: v.finding.file,
        fix: v.verdict.suggestedFix || v.finding.suggestedFix,
        confidence: v.verdict.confidence,
      }))
  )

const rejected = perTagResults
  .filter(Boolean)
  .flatMap((r) => (r.verified || []).filter((v) => v.verdict && !v.verdict.isReal).length)
  .reduce((a, b) => a + b, 0)

const report = await agent(
  `Synthesize the final spatie/laravel-responsecache audit report for the PM One Laravel backend.

CONFIRMED FINDINGS (already adversarially verified as real):
${JSON.stringify(confirmedFindings, null, 2)}

COVERAGE ANALYSIS (uncached public routes):
${JSON.stringify(coverage, null, 2)}

RECENT-CHANGES ANALYSIS:
${recentChanges}

Produce a clear, prioritized report:
1. Executive summary: is cache coverage + invalidation correct overall? (1-3 sentences)
2. Findings grouped by severity (P0 first). For each: the problem, the affected tag/routes, file:line, and the concrete minimal fix (real symbols).
3. Coverage gaps: routes that should be cached but are not (with recommended tag/ttl/invalidator), and any route cached that should not be.
4. A short "invalidation matrix" table: tag -> all write paths that must bust it -> which are covered / NOT covered.
5. Concrete next-step checklist of edits, ordered by priority.
Be precise and actionable. Output GitHub-flavored markdown. Do not invent findings beyond the inputs.`,
  { label: 'synthesis', phase: 'Report' }
)

return {
  totalConfirmed: confirmedFindings.length,
  totalRejected: rejected,
  bySeverity: confirmedFindings.reduce((acc, f) => {
    acc[f.severity] = (acc[f.severity] || 0) + 1
    return acc
  }, {}),
  confirmedFindings,
  report,
}
