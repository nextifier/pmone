<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandEventIndexResource;
use App\Http\Resources\BrandEventResource;
use App\Http\Resources\EventIndexResource;
use App\Http\Resources\FaqPublicResource;
use App\Http\Resources\GalleryPublicResource;
use App\Http\Resources\GuestPublicResource;
use App\Http\Resources\MediaCoveragePublicResource;
use App\Http\Resources\ProgramPublicResource;
use App\Http\Resources\PromotionPostResource;
use App\Http\Resources\PublicBrandDetailResource;
use App\Http\Resources\PublicBrandIndexResource;
use App\Http\Resources\PublicEventResource;
use App\Http\Resources\PublicProjectResource;
use App\Http\Resources\RundownItemPublicResource;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Project;
use App\Services\Rundown\RundownGrouper;
use App\Support\OgPages;
use App\Support\PaginationClamp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class PublicProjectController extends Controller
{
    /**
     * Get project detail by username.
     */
    public function show(string $username): JsonResponse
    {
        $project = Project::query()
            ->with(['media', 'links'])
            ->where('username', $username)
            ->active()
            ->firstOrFail();

        return response()->json([
            'data' => new PublicProjectResource($project),
        ]);
    }

    /**
     * List published events for a project.
     */
    public function events(Request $request, string $username): JsonResponse
    {
        $project = $this->findProject($username);

        $query = Event::query()
            ->with(['media'])
            ->where('project_id', $project->id)
            ->published();

        $this->applyEventFilters($query, $request);
        $this->applyEventSorting($query, $request);

        $events = $query->paginate(PaginationClamp::perPage($request, 15));

        return response()->json([
            'data' => EventIndexResource::collection($events->items()),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * Get single event detail.
     */
    public function event(string $username, string $eventSlug): JsonResponse
    {
        $project = $this->findProject($username);

        $event = Event::query()
            ->with(['media'])
            ->where('project_id', $project->id)
            ->where('slug', $eventSlug)
            ->published()
            ->firstOrFail();

        return response()->json([
            'data' => new PublicEventResource($event),
        ]);
    }

    /**
     * List active brands for an event.
     */
    public function brands(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $query = BrandEvent::query()
            ->with(['brand.media', 'brand.tags', 'brand.links'])
            ->withCount([
                'promotionPosts',
                'promotionPosts as posts_with_caption_count' => fn ($q) => $q->whereNotNull('caption')->where('caption', '!=', ''),
                'promotionPosts as posts_with_image_count' => fn ($q) => $q->whereHas('media', fn ($m) => $m->where('collection_name', 'post_image')),
            ])
            ->whereHas('brand')
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->ordered();

        if ($search = $request->input('search')) {
            $query->whereHas('brand', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('company_name', 'ilike', "%{$search}%");
            });
        }

        $brandEvents = $query->paginate(PaginationClamp::perPage($request, 30));

        return response()->json([
            'data' => BrandEventIndexResource::collection($brandEvents->items()),
            'meta' => [
                'current_page' => $brandEvents->currentPage(),
                'last_page' => $brandEvents->lastPage(),
                'per_page' => $brandEvents->perPage(),
                'total' => $brandEvents->total(),
            ],
        ]);
    }

    /**
     * Get single brand detail.
     */
    public function brand(string $username, string $eventSlug, string $brandSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $brandEvent = BrandEvent::query()
            ->with(['brand.media', 'brand.tags', 'brand.links'])
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->whereHas('brand', fn ($q) => $q->where('slug', $brandSlug))
            ->firstOrFail();

        return response()->json([
            'data' => new BrandEventResource($brandEvent),
        ]);
    }

    /**
     * List promotion posts for a brand.
     */
    public function promotionPosts(Request $request, string $username, string $eventSlug, string $brandSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $brandEvent = BrandEvent::query()
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->whereHas('brand', fn ($q) => $q->where('slug', $brandSlug))
            ->firstOrFail();

        $posts = $brandEvent->promotionPosts()
            ->with(['media'])
            ->paginate(PaginationClamp::perPage($request, 30));

        return response()->json([
            'data' => PromotionPostResource::collection($posts->items()),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * List brands from the active event for a project.
     */
    public function activeBrands(Request $request, string $username): JsonResponse
    {
        $event = $this->findActiveEvent($username);

        if (! $event) {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 200, 'total' => 0],
            ]);
        }

        $activeEvent = $event;

        // Opt-in fallback (used by the home-page BrandPreview teaser): when the
        // active event has no brands yet, borrow the most recent previous edition
        // that does, so a freshly-created event still shows brands.
        if ($request->boolean('fallback')
            && ! $event->brandEvents()->where('status', 'active')->exists()) {
            $event = $this->fallbackEventWithItems(
                $request,
                $event,
                'brandEvents',
                fn ($q) => $q->where('status', 'active'),
                'brands',
            ) ?? $event;
        }

        $query = BrandEvent::query()
            ->with(['brand.media', 'brand.tags', 'brand.links', 'promotionPosts.media', 'event'])
            ->whereHas('brand')
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->ordered();

        if ($search = $request->input('search')) {
            $query->whereHas('brand', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('company_name', 'ilike', "%{$search}%");
            });
        }

        // Higher ceiling than the other listings: pmone-events' sitemap
        // generator fetches this endpoint with per_page=1000 to enumerate
        // every brand for /brands/[slug] URLs, so a lower cap would silently
        // drop brands from the sitemap.
        $brandEvents = $query->paginate(PaginationClamp::perPage($request, 200, 1000));

        return response()->json([
            'data' => PublicBrandIndexResource::collection($brandEvents->items()),
            'meta' => [
                'current_page' => $brandEvents->currentPage(),
                'last_page' => $brandEvents->lastPage(),
                'per_page' => $brandEvents->perPage(),
                'total' => $brandEvents->total(),
                'fallback' => $this->fallbackMeta($activeEvent, $event),
            ],
        ]);
    }

    /**
     * List brands from the active event and its conjunction events for a project.
     */
    public function activeBrandsWithConjunctions(Request $request, string $username): JsonResponse
    {
        $event = $this->findActiveEvent($username);

        if (! $event) {
            return response()->json([
                'data' => ['groups' => []],
            ]);
        }

        $event->load('conjunctionEvents.project');

        $groups = [];

        // Primary event brands
        $groups[] = $this->buildBrandGroup($event, isPrimary: true);

        // Conjunction event brands
        foreach ($event->conjunctionEvents as $conjunctionEvent) {
            $groups[] = $this->buildBrandGroup($conjunctionEvent, isPrimary: false);
        }

        return response()->json([
            'data' => ['groups' => $groups],
        ]);
    }

    /**
     * Build a brand group array for an event.
     */
    private function buildBrandGroup(Event $event, bool $isPrimary): array
    {
        $brandEvents = BrandEvent::query()
            ->with(['brand.media', 'brand.tags', 'brand.links', 'promotionPosts.media'])
            ->whereHas('brand')
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->ordered()
            ->get();

        return [
            'event_title' => $event->title,
            'project_username' => $event->project?->username,
            'project_name' => $event->project?->name,
            'is_primary' => $isPrimary,
            'brands_count' => $brandEvents->count(),
            'brands' => PublicBrandIndexResource::collection($brandEvents)->resolve(),
        ];
    }

    /**
     * Every brand slug that `activeBrand()` below can resolve, for the event
     * website's sitemap.
     *
     * WHY IT IS NOT ONE OF THE LISTINGS: the sitemap used to enumerate brands
     * through `activeBrands()`, which only ever looks at the active event. Brand
     * DETAIL resolves three sources in order — active event, conjunction events,
     * then previous editions — so every brand reachable through the last two was
     * served 200 with no path for Google to discover it. Measured 8 Aug 2026 that
     * was roughly 1,700 URLs across eight sites, all 359 of franchise-expo.co.id's
     * among them, and its /brands listing renders client-side so the sitemap was
     * the only discovery path there was. Mirror the resolver here; a sitemap built
     * from a listing drifts from it again the moment the resolver gains a step.
     *
     * Slug and timestamp only. The listings carry media, tags, links and promotion
     * posts — 1.35 MB and 5.7s on the wire for morefoodexpo — none of which a
     * sitemap has any use for.
     *
     * `meta.sources` counts what each source contributed after dedup, so a site
     * that stops publishing brands says which of the three dried up without
     * anyone having to open the database.
     */
    public function activeBrandSitemapSlugs(Request $request, string $username): JsonResponse
    {
        $event = $this->findActiveEvent($username);
        $counts = ['active' => 0, 'conjunction' => 0, 'previous_edition' => 0];

        if (! $event) {
            return response()->json(['data' => [], 'meta' => ['sources' => $counts]]);
        }

        $conjunctionEventIds = $event->conjunctionEvents()->pluck('events.id');

        $sources = [
            'active' => fn ($q) => $q->where('event_id', $event->id),
            'conjunction' => fn ($q) => $q->whereIn('event_id', $conjunctionEventIds),
        ];

        // The same third step activeBrand() takes: ANY published edition of this
        // project, not the single most recent one activeBrands() borrows from.
        // Narrowing it here would drop brands whose detail page still answers 200.
        if ($this->fallbackAllowed($request)) {
            $sources['previous_edition'] = fn ($q) => $q->whereHas(
                'event',
                fn ($e) => $e->where('project_id', $event->project_id)->published(),
            );
        }

        $brands = collect();

        foreach ($sources as $source => $constraint) {
            $fresh = $this->brandSlugsForSitemap($constraint)->diffKeys($brands);

            $counts[$source] = $fresh->count();
            $brands = $brands->union($fresh);
        }

        return response()->json([
            'data' => $brands->values()->map(fn (Brand $brand) => [
                'slug' => $brand->slug,
                'updated_at' => $brand->updated_at?->toIso8601String(),
            ]),
            'meta' => ['sources' => $counts],
        ]);
    }

    /**
     * Brands with an active `brand_event` row matching $constraint, keyed by slug.
     *
     * @param  \Closure(Builder): Builder  $constraint
     * @return Collection<string, Brand>
     */
    private function brandSlugsForSitemap(\Closure $constraint): Collection
    {
        return Brand::query()
            ->whereHas(
                'brandEvents',
                fn ($q) => $constraint($q->reorder()->where('status', 'active')),
            )
            ->select('slug', 'updated_at')
            ->get()
            ->keyBy('slug');
    }

    /**
     * Get single brand detail from the active event for a project.
     * Falls back to conjunction events if brand is not found in the primary event.
     */
    public function activeBrand(Request $request, string $username, string $brandSlug): JsonResponse
    {
        $event = $this->findActiveEvent($username);

        if (! $event) {
            abort(404);
        }

        $brandEvent = BrandEvent::query()
            ->with(['brand.media', 'brand.tags', 'brand.links', 'promotionPosts.media', 'event'])
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->whereHas('brand', fn ($q) => $q->where('slug', $brandSlug))
            ->first();

        // Fallback: search in conjunction events
        if (! $brandEvent) {
            $conjunctionEventIds = $event->conjunctionEvents()->pluck('events.id');

            if ($conjunctionEventIds->isNotEmpty()) {
                $brandEvent = BrandEvent::query()
                    ->with(['brand.media', 'brand.tags', 'brand.links', 'promotionPosts.media', 'event'])
                    ->whereIn('event_id', $conjunctionEventIds)
                    ->where('status', 'active')
                    ->whereHas('brand', fn ($q) => $q->where('slug', $brandSlug))
                    ->first();
            }
        }

        // Fallback: search previous editions of the same project (most recent
        // first) so links from the home-page BrandPreview teaser still resolve
        // when the active event borrows a previous edition's brands. Skipped
        // when the caller sends `?fallback=0` or the project disables
        // previous-edition brand fallback.
        if (! $brandEvent && $this->fallbackAllowed($request)) {
            $brandEvent = BrandEvent::query()
                ->with(['brand.media', 'brand.tags', 'brand.links', 'promotionPosts.media', 'event'])
                ->where('status', 'active')
                ->whereHas('brand', fn ($q) => $q->where('slug', $brandSlug))
                ->whereHas('event', fn ($q) => $q->where('project_id', $event->project_id)->published())
                ->orderByDesc(
                    Event::select('start_date')->whereColumn('events.id', 'brand_event.event_id'),
                )
                ->first();
        }

        if (! $brandEvent) {
            abort(404);
        }

        return response()->json([
            'data' => new PublicBrandDetailResource($brandEvent),
        ]);
    }

    /**
     * List published editions for a project.
     */
    public function publishedEditions(string $username): JsonResponse
    {
        $project = $this->findProject($username);

        $events = Event::query()
            ->where('project_id', $project->id)
            ->published()
            ->orderByDesc('edition_number')
            ->get();

        return response()->json([
            'data' => $events->map(fn (Event $e) => [
                'slug' => $e->slug,
                'edition_number' => $e->edition_number,
                'edition_label' => $e->edition_number_with_ordinal,
                'title' => $e->title,
                'is_active' => $e->is_active,
                'date_label' => $this->formatEditionDate($e),
            ])->values(),
        ]);
    }

    /**
     * List brands for a specific edition of a project.
     */
    public function brandsByEdition(Request $request, string $username, int $editionNumber): JsonResponse
    {
        $event = $this->findEventByEdition($username, $editionNumber);

        $query = BrandEvent::query()
            ->with(['brand.media', 'brand.tags', 'brand.links', 'promotionPosts.media', 'event'])
            ->whereHas('brand')
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->ordered();

        if ($search = $request->input('search')) {
            $query->whereHas('brand', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('company_name', 'ilike', "%{$search}%");
            });
        }

        // Same brand-listing family as activeBrands() above - kept at the same
        // higher ceiling for consistency (per-edition exhibitor listings are
        // rendered unpaginated by pmone-events).
        $brandEvents = $query->paginate(PaginationClamp::perPage($request, 200, 1000));

        return response()->json([
            'data' => PublicBrandIndexResource::collection($brandEvents->items()),
            'meta' => [
                'current_page' => $brandEvents->currentPage(),
                'last_page' => $brandEvents->lastPage(),
                'per_page' => $brandEvents->perPage(),
                'total' => $brandEvents->total(),
            ],
        ]);
    }

    /**
     * Get single brand detail for a specific edition.
     */
    public function brandByEdition(string $username, int $editionNumber, string $brandSlug): JsonResponse
    {
        $event = $this->findEventByEdition($username, $editionNumber);

        $brandEvent = BrandEvent::query()
            ->with(['brand.media', 'brand.tags', 'brand.links', 'promotionPosts.media', 'event'])
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->whereHas('brand', fn ($q) => $q->where('slug', $brandSlug))
            ->firstOrFail();

        return response()->json([
            'data' => new PublicBrandDetailResource($brandEvent),
        ]);
    }

    /**
     * List rundown items grouped by date for an event.
     * Returns translated content based on `?locale=` query param (default: en).
     */
    public function rundown(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);
        $event->loadMissing('project');

        $locale = $request->input('locale', config('app.locale', 'en'));
        App::setLocale($locale);

        $items = $event->rundownItems()
            ->with(['media', 'tags'])
            ->where('is_active', true)
            ->get();

        $rundownSettings = data_get($event->project?->settings, 'website_settings.rundown', []);

        return response()->json([
            'data' => [
                'days' => RundownGrouper::group(
                    $items,
                    fn ($item) => (new RundownItemPublicResource($item))->resolve(),
                    event: $event,
                    unscheduledLabel: null,
                ),
                'settings' => [
                    'show_rundown_on_home_page' => (bool) ($rundownSettings['show_rundown_on_home_page'] ?? false),
                ],
            ],
        ]);
    }

    /**
     * List rundown items grouped by date for a specific edition of a project.
     * Returns translated content based on `?locale=` query param (default: en).
     */
    public function rundownByEdition(Request $request, string $username, int $editionNumber): JsonResponse
    {
        $event = $this->findEventByEdition($username, $editionNumber);
        $event->loadMissing('project');

        $locale = $request->input('locale', config('app.locale', 'en'));
        App::setLocale($locale);

        $items = $event->rundownItems()
            ->with(['media', 'tags'])
            ->where('is_active', true)
            ->get();

        $rundownSettings = data_get($event->project?->settings, 'website_settings.rundown', []);

        return response()->json([
            'data' => [
                'days' => RundownGrouper::group(
                    $items,
                    fn ($item) => (new RundownItemPublicResource($item))->resolve(),
                    event: $event,
                    unscheduledLabel: null,
                ),
                'settings' => [
                    'show_rundown_on_home_page' => (bool) ($rundownSettings['show_rundown_on_home_page'] ?? false),
                ],
            ],
        ]);
    }

    /**
     * List active main programs for an event, ordered.
     * Returns translated content based on `?locale=` query param (default: en).
     *
     * Fallback: if the (active) event has no programs yet, use the most recent
     * other event in the same project that does, so a freshly-created event
     * still shows programs until its own are added.
     */
    public function programs(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $locale = $request->input('locale', config('app.locale', 'en'));
        App::setLocale($locale);

        $source = $event->programs()->where('is_active', true)->exists()
            ? $event
            : ($this->fallbackEventWithItems($request, $event, 'programs', null, 'programs') ?? $event);

        $programs = $source->programs()
            ->with('media')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'data' => ProgramPublicResource::collection($programs),
            'meta' => ['fallback' => $this->fallbackMeta($event, $source)],
        ]);
    }

    public function mediaCoverages(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $source = $event->mediaCoverages()->where('is_active', true)->exists()
            ? $event
            : ($this->fallbackEventWithItems($request, $event, 'mediaCoverages', null, 'media_coverages') ?? $event);

        $items = $source->mediaCoverages()
            ->where('is_active', true)
            ->get();

        return response()->json([
            'data' => MediaCoveragePublicResource::collection($items),
            'meta' => ['fallback' => $this->fallbackMeta($event, $source)],
        ]);
    }

    /**
     * List active FAQ items for an event, ordered. Returns translated content
     * (`?locale=`) with {{tokens}} resolved against the event/project context.
     *
     * Fallback: if the (active) event has no FAQ yet, use the most recent other
     * event in the same project that does. Tokens still resolve against the
     * CURRENT event so a borrowed template shows the current event's details.
     */
    public function faqs(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);
        $event->loadMissing('project.links');

        $locale = $request->input('locale', config('app.locale', 'en'));
        App::setLocale($locale);

        $source = $event->faqs()->where('is_active', true)->exists()
            ? $event
            : ($this->fallbackEventWithItems($request, $event, 'faqs', null, 'faqs') ?? $event);

        $faqs = $source->faqs()
            ->where('is_active', true)
            ->get()
            ->each(fn ($faq) => $faq->setRelation('event', $event));

        return response()->json([
            'data' => FaqPublicResource::collection($faqs),
            'meta' => ['fallback' => $this->fallbackMeta($event, $source)],
        ]);
    }

    /**
     * List active gallery photos for an event (Spatie media collection).
     *
     * Fallback: if the (active) event has no gallery photos yet, use the most
     * recent other event in the same project that does — so a freshly-created
     * event still shows a gallery (e.g. last edition's photos) until its own
     * are uploaded.
     */
    public function gallery(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $source = $event->hasMedia('gallery')
            ? $event
            : ($this->fallbackEventWithItems(
                $request,
                $event,
                'media',
                fn ($q) => $q->where('collection_name', 'gallery'),
                'gallery',
            ) ?? $event);

        return response()->json([
            'data' => GalleryPublicResource::collection($source->getMedia('gallery')),
            'meta' => [
                'aspect_ratio' => data_get($event->settings, 'gallery_aspect_ratio', '1:1'),
                'fallback' => $this->fallbackMeta($event, $source),
            ],
        ]);
    }

    /**
     * Most recent other event in the same project that has items for the given
     * relation. Used as a content fallback for programs/faqs (default: active
     * items), gallery (constraint: media in the `gallery` collection), and
     * partners (constraint: a category with active partners).
     *
     * Returns null when the project disables previous-edition data fallback for
     * the given section ($settingKey), so callers keep the active event's own
     * (possibly empty) data.
     */
    private function fallbackEventWithItems(Request $request, Event $event, string $relation, ?\Closure $constraint, string $settingKey): ?Event
    {
        if (! $this->fallbackAllowed($request)) {
            return null;
        }

        return $event->project
            ->events()
            ->where('id', '!=', $event->id)
            ->whereHas($relation, $constraint ?? fn ($q) => $q->where('is_active', true))
            ->reorder()
            ->orderByDesc('start_date')
            ->first();
    }

    /**
     * Whether this request may borrow a previous edition's data for a section.
     *
     * The caller decides. Event websites send `?fallback=0|1` per endpoint from
     * their own `app.config.ts`, so the rule lives in the site's code, gets
     * reviewed with it, and is visible to whoever is looking at the page.
     *
     * A second, project-level toggle used to be ANDed in here. Its dashboard
     * editor was removed in Aug 2026 with the rest of the website-settings
     * screens, which left a switch that changed behaviour and that nobody could
     * see or reach — the worst kind. One source of truth now.
     */
    private function fallbackAllowed(Request $request): bool
    {
        return $request->boolean('fallback', true);
    }

    /**
     * Build the `meta.fallback` block for a section response so the event
     * website can show a "data from a previous edition" notice. Flags whether
     * the rendered data was borrowed from another event and, if so, which one.
     *
     * @return array{is_fallback: bool, source_event: array{title: string, edition_number: int|null, edition_label: string|null, slug: string}|null}
     */
    private function fallbackMeta(Event $activeEvent, Event $source): array
    {
        if ($source->id === $activeEvent->id) {
            return ['is_fallback' => false, 'source_event' => null];
        }

        return [
            'is_fallback' => true,
            'source_event' => [
                'title' => $source->title,
                'edition_number' => $source->edition_number,
                'edition_label' => $source->edition_number_with_ordinal,
                'slug' => $source->slug,
            ],
        ];
    }

    /**
     * Per-page Open Graph overrides for the project's event website.
     *
     * The name is historical and now oversized: this used to carry every
     * dashboard-managed website setting — nav, analytics, appearance, identity,
     * copy, section visibility. All of it moved back into the pmone-events repo
     * in Aug 2026 so the sites could be prerendered, and the only survivor is
     * the OG image map, which the frontend narrows to before use
     * (layers/base/server/api/event/og-pages.get.ts).
     *
     * Not locale-parameterized on purpose: the payload is fetched from a Nuxt
     * plugin whose setup() runs before any page's, and a locale-aware fetch
     * there would need useI18n() from a plugin, which throws. Every saved
     * locale ships in one response and the frontend picks its own.
     */
    public function websiteSettings(string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        $settings = data_get($project->settings, 'website_settings', []);
        $rundown = data_get($settings, 'rundown', []);
        $blog = data_get($settings, 'blog', []);
        $ticketTabs = data_get($settings, 'ticket_tabs', []);
        $bookSpaceForm = data_get($settings, 'book_space_form', []);
        $terms = data_get($settings, 'terms', []);
        $ogPages = $this->ogPagesPayload($project);

        return response()->json([
            'data' => [
                'settings' => [
                    // Defaults mirror the base app.config.ts in pmone-events so a
                    // project that has never saved these still renders identically.
                    'blog' => [
                        'show_post_card_author' => (bool) ($blog['show_post_card_author'] ?? false),
                        'show_post_card_excerpt' => (bool) ($blog['show_post_card_excerpt'] ?? false),
                    ],
                    'ticket_tabs' => [
                        'show_tickets' => (bool) ($ticketTabs['show_tickets'] ?? true),
                        'show_guests' => (bool) ($ticketTabs['show_guests'] ?? false),
                        'show_brands' => (bool) ($ticketTabs['show_brands'] ?? true),
                        'show_rundown' => (bool) ($ticketTabs['show_rundown'] ?? true),
                        'show_about' => (bool) ($ticketTabs['show_about'] ?? true),
                        'show_photos' => (bool) ($ticketTabs['show_photos'] ?? true),
                    ],
                    'book_space_form' => [
                        'show_job_title' => (bool) ($bookSpaceForm['show_job_title'] ?? false),
                        'show_brand_name' => (bool) ($bookSpaceForm['show_brand_name'] ?? true),
                        'show_products' => (bool) ($bookSpaceForm['show_products'] ?? false),
                    ],
                    'terms' => [
                        'last_update' => $terms['last_update'] ?? null,
                    ],
                    // The ONLY thing still read from this endpoint. Nav, analytics,
                    // appearance, identity and copy used to ride along under a
                    // `site_config` key; all of them moved back into the
                    // pmone-events repo in Aug 2026 and the frontend narrows this
                    // response to `settings.og_pages` before anything else sees it.
                    'og_pages' => $ogPages,
                ],
            ],
        ]);
    }

    /**
     * Whether an HTML body has any visible text once tags and whitespace-only
     * entities are stripped. Mirrors the admin editor's blank check so an
     * "empty" rich-text value (e.g. "<p></p>", "<p>&nbsp;</p>") fails open.
     */
    private function hasVisibleText(?string $html): bool
    {
        if (! filled($html)) {
            return false;
        }

        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        return preg_replace('/[\s\x{00A0}]+/u', '', $plain) !== '';
    }

    /**
     * Per-page OG overrides for the event website. Only keys with an image,
     * title, or description are included; the website falls back to its
     * generated OG card for anything absent.
     *
     * @return array<string, array{title: ?string, description: ?string, image: ?array{url: string, width: ?int, height: ?int}}>
     */
    protected function ogPagesPayload(Project $project): array
    {
        $ogPages = data_get($project->settings, 'website_settings.og_pages', []);

        $payload = [];

        foreach (OgPages::KEYS as $key) {
            $media = $project->getFirstMedia(OgPages::collectionFor($key));
            $title = data_get($ogPages, "{$key}.title");
            $description = data_get($ogPages, "{$key}.description");

            if (! $media && ! $title && ! $description) {
                continue;
            }

            $payload[$key] = [
                'title' => $title,
                'description' => $description,
                'image' => $media ? [
                    'url' => $media->getUrl(),
                    'width' => $media->getCustomProperty('width'),
                    'height' => $media->getCustomProperty('height'),
                ] : null,
            ];
        }

        return $payload;
    }

    /**
     * List public guests/speakers for an event.
     */
    public function guests(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $locale = $request->input('locale', config('app.locale', 'en'));
        App::setLocale($locale);

        $hasPublicGuests = fn ($q) => $q->active()->where('visibility', 'public');

        $source = $event->guests()->active()->where('visibility', 'public')->exists()
            ? $event
            : ($this->fallbackEventWithItems($request, $event, 'guests', $hasPublicGuests, 'guests') ?? $event);

        $query = $source->guests()
            ->active()
            ->where('visibility', 'public')
            ->with(['media', 'tags', 'links']);

        if ($request->boolean('featured_only')) {
            $query->where('is_featured', true);
        }

        $items = $query->orderByDesc('is_featured')
            ->orderBy('order_column', 'asc')
            ->get();

        return response()->json([
            'data' => GuestPublicResource::collection($items),
            'meta' => [
                'count' => $items->count(),
                'featured_count' => $items->where('is_featured', true)->count(),
                'fallback' => $this->fallbackMeta($event, $source),
            ],
        ]);
    }

    /**
     * Show a single public guest by slug.
     */
    public function guest(Request $request, string $username, string $eventSlug, string $slug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $locale = $request->input('locale', config('app.locale', 'en'));
        App::setLocale($locale);

        $guest = $event->guests()
            ->active()
            ->where('visibility', 'public')
            ->where('slug', $slug)
            ->with(['media', 'tags', 'links'])
            ->first();

        // Fallback: resolve a guest borrowed from a previous edition (most recent
        // first) so links from a fallback-rendered guest list still resolve.
        if (! $guest) {
            $sourceEvent = $this->fallbackEventWithItems(
                $request,
                $event,
                'guests',
                fn ($q) => $q->active()->where('visibility', 'public')->where('slug', $slug),
                'guests',
            );

            $guest = $sourceEvent?->guests()
                ->active()
                ->where('visibility', 'public')
                ->where('slug', $slug)
                ->with(['media', 'tags', 'links'])
                ->first();
        }

        if (! $guest) {
            abort(404);
        }

        return response()->json([
            'data' => new GuestPublicResource($guest),
        ]);
    }

    /**
     * List partner categories with partners for an event.
     *
     * Fallback: if the (active) event has no partners yet, borrow the most
     * recent other event in the same project that does — mirrors programs/faqs
     * so a freshly-created event still shows the Credits section.
     */
    public function partners(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $hasActivePartners = fn ($q) => $q->whereHas('partners', fn ($p) => $p->active()->publiclyVisible());

        $source = $event->partnerCategories()->whereHas('partners', fn ($p) => $p->active()->publiclyVisible())->exists()
            ? $event
            : ($this->fallbackEventWithItems($request, $event, 'partnerCategories', $hasActivePartners, 'partners') ?? $event);

        $categories = $source->partnerCategories()
            ->with(['partners' => fn ($q) => $q->active()->publiclyVisible()->with('media')])
            ->ordered()
            ->get();

        $data = $categories
            ->filter(fn ($cat) => $cat->partners->isNotEmpty())
            ->values()
            ->map(fn ($cat) => [
                'category' => $cat->name,
                'no_container' => $cat->no_container,
                'partners' => $cat->partners->map(fn ($partner) => [
                    'name' => $partner->name,
                    'logo' => $partner->partner_logo,
                    'link' => $partner->website_url,
                ]),
            ]);

        return response()->json([
            'data' => $data,
            'meta' => ['fallback' => $this->fallbackMeta($event, $source)],
        ]);
    }

    /**
     * List partner categories with partners for a specific edition.
     */
    public function partnersByEdition(string $username, int $editionNumber): JsonResponse
    {
        $event = $this->findEventByEdition($username, $editionNumber);

        $categories = $event->partnerCategories()
            ->with(['partners' => fn ($q) => $q->active()->publiclyVisible()->with('media')])
            ->ordered()
            ->get();

        $data = $categories
            ->filter(fn ($cat) => $cat->partners->isNotEmpty())
            ->values()
            ->map(fn ($cat) => [
                'category' => $cat->name,
                'no_container' => $cat->no_container,
                'partners' => $cat->partners->map(fn ($partner) => [
                    'name' => $partner->name,
                    'logo' => $partner->partner_logo,
                    'link' => $partner->website_url,
                ]),
            ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Find a published event by edition number within a project.
     */
    private function findEventByEdition(string $username, int $editionNumber): Event
    {
        $project = $this->findProject($username);

        return Event::query()
            ->where('project_id', $project->id)
            ->where('edition_number', $editionNumber)
            ->published()
            ->firstOrFail();
    }

    /**
     * Format edition date without day names.
     */
    private function formatEditionDate(Event $event): ?string
    {
        if (! $event->start_date) {
            return null;
        }

        $start = $event->start_date;

        if (! $event->end_date || $start->isSameDay($event->end_date)) {
            return $start->format('M j, Y');
        }

        $end = $event->end_date;

        if ($start->year !== $end->year) {
            return $start->format('M j, Y').' - '.$end->format('M j, Y');
        }

        if ($start->month !== $end->month) {
            return $start->format('M j').' - '.$end->format('M j, Y');
        }

        return $start->format('M j').'-'.$end->format('j, Y');
    }

    /**
     * Find the active event for a project, fallback to latest published event.
     */
    private function findActiveEvent(string $username): ?Event
    {
        $project = $this->findProject($username);

        return Event::query()
            ->where('project_id', $project->id)
            ->where('is_active', true)
            ->published()
            ->first()
            ?? Event::query()
                ->where('project_id', $project->id)
                ->published()
                ->orderByDesc('edition_number')
                ->first();
    }

    /**
     * Find an active project by username.
     */
    private function findProject(string $username): Project
    {
        return Project::query()
            ->where('username', $username)
            ->active()
            ->firstOrFail();
    }

    /**
     * Find a published event within a project.
     *
     * The literal `active` resolves to whatever findActiveEvent() resolves,
     * which makes every section endpoint reachable at a stable, edge-cacheable
     * URL: `/events/active/faqs`, `/events/active/rundown`, and so on. Callers
     * that only know the project — the event websites, which fetch these from
     * the visitor's browser — no longer have to resolve the slug first and then
     * fetch, so a page costs one round trip per section instead of two.
     *
     * `active` was already unusable as a real slug: `/events/active` is
     * registered ahead of `/events/{eventSlug}` in routes/api.php, so an event
     * actually named that has never been reachable there.
     */
    private function findEvent(string $username, string $eventSlug): Event
    {
        if ($eventSlug === 'active') {
            return $this->findActiveEvent($username)
                ?? throw (new ModelNotFoundException)->setModel(Event::class);
        }

        $project = $this->findProject($username);

        return Event::query()
            ->where('project_id', $project->id)
            ->where('slug', $eventSlug)
            ->published()
            ->firstOrFail();
    }

    /**
     * Get the active event for a project.
     */
    public function activeEvent(string $username): JsonResponse
    {
        $project = $this->findProject($username);

        $event = Event::query()
            ->with(['media', 'project.media', 'project.links', 'conjunctionEvents.project.media', 'conjunctionEvents.project.links'])
            ->where('project_id', $project->id)
            ->where('is_active', true)
            ->published()
            ->firstOrFail();

        return response()->json([
            'data' => new PublicEventResource($event),
        ]);
    }

    /**
     * Apply filters to event query.
     */
    private function applyEventFilters($query, Request $request): void
    {
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('location', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->byStatus($status);
        }
    }

    /**
     * Apply sorting to event query.
     */
    private function applyEventSorting($query, Request $request): void
    {
        $sortField = $request->input('sort', '-start_date');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        $allowedFields = ['title', 'start_date', 'end_date', 'created_at', 'order_column'];

        if (in_array($field, $allowedFields)) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('start_date', 'desc');
        }
    }
}
