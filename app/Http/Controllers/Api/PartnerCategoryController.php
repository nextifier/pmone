<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartnerCategoryResource;
use App\Models\Event;
use App\Models\Partner;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerCategoryController extends Controller
{
    /**
     * Resolve project by username.
     */
    private function resolveProject(string $username): Project
    {
        return Project::where('username', $username)->firstOrFail();
    }

    /**
     * Resolve event by slug within project.
     */
    private function resolveEvent(Project $project, string $eventSlug): Event
    {
        return $project->events()->where('slug', $eventSlug)->firstOrFail();
    }

    /**
     * List partner categories for an event with their partners.
     */
    public function index(string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $categories = $event->partnerCategories()
            ->with(['partners.media'])
            ->withCount('partners')
            ->ordered()
            ->get();

        return response()->json([
            'data' => PartnerCategoryResource::collection($categories),
        ]);
    }

    /**
     * Create a new partner category for an event.
     */
    public function store(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'no_container' => ['sometimes', 'boolean'],
        ]);

        $category = $event->partnerCategories()->create($validated);

        $category->load(['partners.media']);
        $category->loadCount('partners');

        return response()->json([
            'data' => new PartnerCategoryResource($category),
            'message' => 'Category created successfully',
        ], 201);
    }

    /**
     * Update a partner category.
     */
    public function update(Request $request, string $username, string $eventSlug, string $categorySlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $category = $event->partnerCategories()->where('slug', $categorySlug)->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'no_container' => ['sometimes', 'boolean'],
        ]);

        $category->update($validated);

        $category->load(['partners.media']);
        $category->loadCount('partners');

        return response()->json([
            'data' => new PartnerCategoryResource($category),
            'message' => 'Category updated successfully',
        ]);
    }

    /**
     * Delete a partner category.
     */
    public function destroy(string $username, string $eventSlug, string $categorySlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $category = $event->partnerCategories()->where('slug', $categorySlug)->firstOrFail();
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    /**
     * Reorder partner categories.
     */
    public function updateOrder(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer'],
        ]);

        foreach ($validated['order'] as $position => $categoryId) {
            $event->partnerCategories()
                ->where('id', $categoryId)
                ->update(['order_column' => $position + 1]);
        }

        return response()->json(['message' => 'Category order updated']);
    }

    /**
     * Add a partner to a category.
     */
    public function addPartner(Request $request, string $username, string $eventSlug, string $categorySlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $category = $event->partnerCategories()->where('slug', $categorySlug)->firstOrFail();

        $validated = $request->validate([
            'partner_id' => ['required_without:partner_name', 'integer', 'exists:partners,id'],
            'partner_name' => ['required_without:partner_id', 'string', 'max:255'],
            'website_url' => ['nullable', 'string', 'url', 'max:500'],
        ]);

        if (! empty($validated['partner_id'])) {
            $partner = Partner::findOrFail($validated['partner_id']);
        } else {
            // Create new partner
            $partner = Partner::create([
                'name' => $validated['partner_name'],
                'website_url' => $validated['website_url'] ?? null,
            ]);
        }

        // Get the next order_column
        $maxOrder = $category->partners()->max('partner_category_partner.order_column') ?? 0;

        $category->partners()->attach($partner->id, [
            'order_column' => $maxOrder + 1,
        ]);

        $partner->load('media');

        return response()->json([
            'data' => [
                'id' => $partner->id,
                'name' => $partner->name,
                'slug' => $partner->slug,
                'website_url' => $partner->website_url,
                'partner_logo' => $partner->partner_logo,
            ],
            'message' => 'Partner added to category',
        ], 201);
    }

    /**
     * Remove a partner from a category.
     */
    public function removePartner(string $username, string $eventSlug, string $categorySlug, int $pivotId): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $category = $event->partnerCategories()->where('slug', $categorySlug)->firstOrFail();

        DB::table('partner_category_partner')
            ->where('id', $pivotId)
            ->where('partner_category_id', $category->id)
            ->delete();

        return response()->json(['message' => 'Partner removed from category']);
    }

    /**
     * Reorder partners within a category.
     */
    public function updatePartnerOrder(Request $request, string $username, string $eventSlug, string $categorySlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $category = $event->partnerCategories()->where('slug', $categorySlug)->firstOrFail();

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer'],
        ]);

        foreach ($validated['order'] as $position => $pivotId) {
            DB::table('partner_category_partner')
                ->where('id', $pivotId)
                ->where('partner_category_id', $category->id)
                ->update(['order_column' => $position + 1]);
        }

        return response()->json(['message' => 'Partner order updated']);
    }

    /**
     * Copy partner categories and partners from another event.
     */
    public function copyFromEvent(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $validated = $request->validate([
            'source_event_id' => ['required', 'integer', 'exists:events,id'],
        ]);

        $sourceEvent = Event::findOrFail($validated['source_event_id']);

        $sourceCategories = $sourceEvent->partnerCategories()
            ->with('partners')
            ->ordered()
            ->get();

        if ($sourceCategories->isEmpty()) {
            return response()->json(['message' => 'Source event has no partner categories'], 422);
        }

        $copiedCategories = 0;
        $copiedPartners = 0;

        foreach ($sourceCategories as $sourceCategory) {
            $newCategory = $event->partnerCategories()->create([
                'name' => $sourceCategory->name,
                'no_container' => $sourceCategory->no_container,
            ]);

            foreach ($sourceCategory->partners as $partner) {
                $newCategory->partners()->attach($partner->id, [
                    'order_column' => $partner->pivot->order_column,
                ]);
                $copiedPartners++;
            }

            $copiedCategories++;
        }

        return response()->json([
            'message' => "Copied {$copiedCategories} categories with {$copiedPartners} partners",
            'copied_categories' => $copiedCategories,
            'copied_partners' => $copiedPartners,
        ]);
    }

    /**
     * List events that have partner categories (for copy-from-event dialog).
     */
    public function eventsWithPartners(Request $request): JsonResponse
    {
        $events = Event::query()
            ->whereHas('partnerCategories')
            ->withCount('partnerCategories')
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'partner_categories_count' => $event->partner_categories_count,
            ]);

        return response()->json(['data' => $events]);
    }
}
