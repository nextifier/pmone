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
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Project;
use App\Models\WebsitePage;
use App\Services\Rundown\RundownGrouper;
use App\Support\HomeSectionCatalog;
use App\Support\OgPages;
use App\Support\PaginationClamp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        // that does, so a freshly-created event still shows brands. Gated by the
        // project's per-section data-fallback toggle.
        if ($request->boolean('fallback')
            && $event->project->shouldFallbackToPreviousEventData('brands')
            && ! $event->brandEvents()->where('status', 'active')->exists()) {
            $event = $this->fallbackEventWithItems(
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
     * Get single brand detail from the active event for a project.
     * Falls back to conjunction events if brand is not found in the primary event.
     */
    public function activeBrand(string $username, string $brandSlug): JsonResponse
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
        // when the project disables previous-edition brand fallback.
        if (! $brandEvent && $event->project->shouldFallbackToPreviousEventData('brands')) {
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
                    'show_search_bar' => (bool) ($rundownSettings['show_search_bar'] ?? true),
                    'show_location_filter' => (bool) ($rundownSettings['show_location_filter'] ?? true),
                    'show_all_rundown_details' => (bool) ($rundownSettings['show_all_rundown_details'] ?? false),
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
                    'show_search_bar' => (bool) ($rundownSettings['show_search_bar'] ?? true),
                    'show_location_filter' => (bool) ($rundownSettings['show_location_filter'] ?? true),
                    'show_all_rundown_details' => (bool) ($rundownSettings['show_all_rundown_details'] ?? false),
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
            : ($this->fallbackEventWithItems($event, 'programs', null, 'programs') ?? $event);

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
            : ($this->fallbackEventWithItems($event, 'mediaCoverages', null, 'media_coverages') ?? $event);

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
            : ($this->fallbackEventWithItems($event, 'faqs', null, 'faqs') ?? $event);

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
    private function fallbackEventWithItems(Event $event, string $relation, ?\Closure $constraint, string $settingKey): ?Event
    {
        if (! $event->project->shouldFallbackToPreviousEventData($settingKey)) {
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
     * Return the project's public website settings (visibility toggles for
     * sections rendered on the public event website). Project-level, so no
     * event slug is required.
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
        $dataFallback = data_get($settings, 'data_fallback', []);
        $ogPages = $this->ogPagesPayload($project);
        $siteConfig = data_get($settings, 'site_config', []);

        // Generic home-page section visibility map. The four legacy nested keys
        // below are derived from this so already-deployed event sites (which read
        // the nested shape) and newer sites (which read `home_sections`) always
        // agree. See config/home_sections.php.
        $homeSections = HomeSectionCatalog::resolveAll($settings);

        return response()->json([
            'data' => [
                'settings' => [
                    'rundown' => [
                        'show_search_bar' => (bool) ($rundown['show_search_bar'] ?? true),
                        'show_location_filter' => (bool) ($rundown['show_location_filter'] ?? true),
                        'show_all_rundown_details' => (bool) ($rundown['show_all_rundown_details'] ?? false),
                        'show_rundown_on_home_page' => $homeSections['rundown'],
                    ],
                    'brands' => [
                        'show_brand_preview_on_home_page' => $homeSections['brand_preview'],
                    ],
                    'partners' => [
                        'show_partners_on_home_page' => $homeSections['partners'],
                    ],
                    'hotels' => [
                        'show_hotel_section_on_home_page' => $homeSections['hotels'],
                    ],
                    // Full { sectionKey => bool } map for newer event sites.
                    'home_sections' => $homeSections,
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
                    // Defaults true: previous-edition data fallback has always
                    // been on, so an unconfigured project keeps borrowing data.
                    'data_fallback' => [
                        'brands' => (bool) ($dataFallback['brands'] ?? true),
                        'guests' => (bool) ($dataFallback['guests'] ?? true),
                        'partners' => (bool) ($dataFallback['partners'] ?? true),
                        'programs' => (bool) ($dataFallback['programs'] ?? true),
                        'faqs' => (bool) ($dataFallback['faqs'] ?? true),
                        'gallery' => (bool) ($dataFallback['gallery'] ?? true),
                        'media_coverages' => (bool) ($dataFallback['media_coverages'] ?? true),
                    ],
                    'og_pages' => $ogPages,
                    // Dashboard-managed site config. Empty by default: every event site keeps
                    // its baked app.config values via the frontend fail-open getters until a
                    // project opts in per key. Sub-keys (nav, analytics, appearance, identity,
                    // copy) are populated by plans 008-012.
                    'site_config' => [
                        'version' => 1,
                        'nav' => data_get($siteConfig, 'nav'),               // null until plan 008
                        'analytics' => data_get($siteConfig, 'analytics'),   // null until plan 009
                        'appearance' => data_get($siteConfig, 'appearance'), // null until plan 010
                        'identity' => data_get($siteConfig, 'identity'),     // null until plan 011
                    ],
                ],
            ],
        ]);
    }

    /**
     * Serve dashboard-managed legal/policy page body overrides for the
     * requested locale, keyed by page key. Kept out of the small
     * `website-settings` payload (per the site-config contract's
     * zero-round-trip note) since bodies are potentially large and only the
     * six legal pages need them - see plan 011.
     *
     * Fail-open: a project with no row for a key, or a row with no saved
     * translation for the requested locale, returns `body: null` so the
     * event website falls back to its baked `<p>` copy - never an empty
     * legal page.
     *
     * Response shape: `{ data: { [key]: { body: string|null } } }`.
     */
    public function websitePages(Request $request, string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        $locale = $request->input('locale', config('app.locale', 'en'));

        $pages = WebsitePage::query()
            ->where('project_id', $project->id)
            ->get()
            ->keyBy('key');

        $payload = [];
        foreach (WebsitePage::KEYS as $key) {
            $page = $pages->get($key);
            $body = $page?->getTranslation('body', $locale, false);

            $payload[$key] = [
                'body' => filled($body) ? $body : null,
            ];
        }

        return response()->json(['data' => $payload]);
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
            : ($this->fallbackEventWithItems($event, 'guests', $hasPublicGuests, 'guests') ?? $event);

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
    public function partners(string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $hasActivePartners = fn ($q) => $q->whereHas('partners', fn ($p) => $p->active());

        $source = $event->partnerCategories()->whereHas('partners', fn ($p) => $p->active())->exists()
            ? $event
            : ($this->fallbackEventWithItems($event, 'partnerCategories', $hasActivePartners, 'partners') ?? $event);

        $categories = $source->partnerCategories()
            ->with(['partners' => fn ($q) => $q->active()->with('media')])
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
            ->with(['partners' => fn ($q) => $q->active()->with('media')])
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
     */
    private function findEvent(string $username, string $eventSlug): Event
    {
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
