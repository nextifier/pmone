<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandEventIndexResource;
use App\Http\Resources\BrandEventResource;
use App\Http\Resources\EventIndexResource;
use App\Http\Resources\EventResource;
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
            ->with(['brand.media', 'brand.tags'])
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
                    'show_all_rundown_details' => (bool) ($rundownSettings['show_all_rundown_details'] ?? false),
                ],
            ],
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
            ->with(['media'])
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
