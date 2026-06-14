<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandEventIndexResource;
use App\Http\Resources\BrandEventResource;
use App\Http\Resources\EventIndexResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\FaqPublicResource;
use App\Http\Resources\GalleryPublicResource;
use App\Http\Resources\GuestPublicResource;
use App\Http\Resources\MediaCoveragePublicResource;
use App\Http\Resources\ProgramPublicResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\PromotionPostResource;
use App\Http\Resources\PublicBrandDetailResource;
use App\Http\Resources\PublicBrandIndexResource;
use App\Http\Resources\RundownItemPublicResource;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Project;
use App\Services\Rundown\RundownGrouper;
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
            ->with(['media', 'links', 'members.media'])
            ->where('username', $username)
            ->active()
            ->firstOrFail();

        return response()->json([
            'data' => new ProjectResource($project),
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

        $events = $query->paginate($request->input('per_page', 15));

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
            'data' => new EventResource($event),
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

        $brandEvents = $query->paginate($request->input('per_page', 30));

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
            ->paginate($request->input('per_page', 30));

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

        $brandEvents = $query->paginate($request->input('per_page', 200));

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

        $brandEvents = $query->paginate($request->input('per_page', 200));

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
            : ($this->fallbackEventWithItems($event, 'programs') ?? $event);

        $programs = $source->programs()
            ->with('media')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'data' => ProgramPublicResource::collection($programs),
        ]);
    }

    public function mediaCoverages(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $source = $event->mediaCoverages()->where('is_active', true)->exists()
            ? $event
            : ($this->fallbackEventWithItems($event, 'mediaCoverages') ?? $event);

        $items = $source->mediaCoverages()
            ->where('is_active', true)
            ->get();

        return response()->json([
            'data' => MediaCoveragePublicResource::collection($items),
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
            : ($this->fallbackEventWithItems($event, 'faqs') ?? $event);

        $faqs = $source->faqs()
            ->where('is_active', true)
            ->get()
            ->each(fn ($faq) => $faq->setRelation('event', $event));

        return response()->json([
            'data' => FaqPublicResource::collection($faqs),
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
                fn ($q) => $q->where('collection_name', 'gallery')
            ) ?? $event);

        return response()->json([
            'data' => GalleryPublicResource::collection($source->getMedia('gallery')),
            'meta' => [
                'aspect_ratio' => data_get($event->settings, 'gallery_aspect_ratio', '1:1'),
            ],
        ]);
    }

    /**
     * Most recent other event in the same project that has items for the given
     * relation. Used as a content fallback for programs/faqs (default: active
     * items) and gallery (constraint: media in the `gallery` collection).
     */
    private function fallbackEventWithItems(Event $event, string $relation, ?\Closure $constraint = null): ?Event
    {
        return $event->project
            ->events()
            ->where('id', '!=', $event->id)
            ->whereHas($relation, $constraint ?? fn ($q) => $q->where('is_active', true))
            ->orderByDesc('start_date')
            ->first();
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
        $brands = data_get($settings, 'brands', []);
        $hotels = data_get($settings, 'hotels', []);
        $blog = data_get($settings, 'blog', []);
        $ticketTabs = data_get($settings, 'ticket_tabs', []);
        $bookSpaceForm = data_get($settings, 'book_space_form', []);
        $terms = data_get($settings, 'terms', []);

        return response()->json([
            'data' => [
                'settings' => [
                    'rundown' => [
                        'show_search_bar' => (bool) ($rundown['show_search_bar'] ?? true),
                        'show_location_filter' => (bool) ($rundown['show_location_filter'] ?? true),
                        'show_all_rundown_details' => (bool) ($rundown['show_all_rundown_details'] ?? false),
                        'show_rundown_on_home_page' => (bool) ($rundown['show_rundown_on_home_page'] ?? false),
                    ],
                    'brands' => [
                        'show_brand_preview_on_home_page' => (bool) ($brands['show_brand_preview_on_home_page'] ?? false),
                    ],
                    'hotels' => [
                        'show_hotel_section_on_home_page' => (bool) ($hotels['show_hotel_section_on_home_page'] ?? false),
                    ],
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
                ],
            ],
        ]);
    }

    /**
     * List public guests/speakers for an event.
     */
    public function guests(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $locale = $request->input('locale', config('app.locale', 'en'));
        App::setLocale($locale);

        $query = $event->guests()
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
            ->firstOrFail();

        return response()->json([
            'data' => new GuestPublicResource($guest),
        ]);
    }

    /**
     * List partner categories with partners for an event.
     */
    public function partners(string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

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
            'data' => new EventResource($event),
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
