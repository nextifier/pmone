<?php

namespace App\Http\Controllers\Api;

use App\Exports\ExhibitorLeadsExport;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\ExhibitorLead;
use App\Services\Ticket\ExhibitorLeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExhibitorLeadController extends Controller
{
    public function __construct(protected ExhibitorLeadService $leads) {}

    public function scan(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrand($request, $brand);

        $validated = $request->validate([
            'qr_token' => ['required', 'string', 'max:255'],
            'event_id' => ['required', 'integer', 'exists:events,id'],
        ]);

        $event = Event::findOrFail($validated['event_id']);

        return response()->json([
            'data' => $this->leads->capture($brand, $event, $validated['qr_token'], $request->user()->id),
        ]);
    }

    /**
     * Resolve a brand_event (the page's URL key) to its numeric event context.
     * Gated by the same brand isolation as the rest of the leads endpoints, so
     * it works for both brand members and supporting staff (unlike the
     * member-only exhibitor dashboard events list).
     */
    public function context(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrand($request, $brand);

        $validated = $request->validate([
            'brand_event_id' => ['required', 'integer'],
        ]);

        $brandEvent = BrandEvent::query()
            ->where('id', $validated['brand_event_id'])
            ->where('brand_id', $brand->id)
            ->with('event:id,title,slug')
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $brandEvent->id,
                'brand' => ['id' => $brand->id, 'name' => $brand->name],
                'event' => [
                    'id' => $brandEvent->event?->id,
                    'title' => $brandEvent->event?->title,
                    'slug' => $brandEvent->event?->slug,
                ],
            ],
        ]);
    }

    public function index(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrand($request, $brand);

        $leads = ExhibitorLead::query()
            ->where('brand_id', $brand->id)
            ->with('event:id,title,slug')
            ->orderByDesc('scanned_at')
            ->paginate((int) $request->input('per_page', 30));

        return response()->json([
            'data' => $leads->getCollection()->map(fn (ExhibitorLead $lead) => [
                'id' => $lead->id,
                'name' => $lead->snapshot['name'] ?? null,
                'email' => $lead->snapshot['email'] ?? null,
                'phone' => $lead->snapshot['phone'] ?? null,
                'ticket_tier' => $lead->snapshot['ticket_tier'] ?? null,
                'event' => $lead->event?->title,
                'scanned_at' => $lead->scanned_at,
            ]),
            'meta' => [
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'total' => $leads->total(),
            ],
        ]);
    }

    public function analytics(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrand($request, $brand);

        return response()->json(['data' => $this->leads->analytics($brand)]);
    }

    public function export(Request $request, Brand $brand): BinaryFileResponse
    {
        $this->authorizeBrand($request, $brand);

        $filename = 'leads-'.$brand->slug.'-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new ExhibitorLeadsExport($brand), $filename);
    }

    /**
     * Data isolation: a user may only touch the leads of a brand they belong to
     * (or staff and above for support).
     */
    protected function authorizeBrand(Request $request, Brand $brand): void
    {
        $user = $request->user();
        $belongs = $user->brands()->whereKey($brand->id)->exists();

        abort_unless($belongs || $user->hasAnyRole(['staff', 'admin', 'master']), 403);
    }
}
