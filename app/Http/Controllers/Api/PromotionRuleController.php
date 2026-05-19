<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromotionRule\StorePromotionRuleRequest;
use App\Http\Requests\PromotionRule\UpdatePromotionRuleRequest;
use App\Http\Resources\PromotionRuleIndexResource;
use App\Http\Resources\PromotionRuleResource;
use App\Models\AppliedAdjustment;
use App\Models\PromotionRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromotionRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('promotion_rules.read');

        $query = PromotionRule::query()
            ->withCount(['codes', 'appliedAdjustments'])
            ->with('event:id,title,slug', 'project:id,username,name');

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $items = $query->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => PromotionRuleIndexResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(PromotionRule $rule): JsonResponse
    {
        $this->authorizePermission('promotion_rules.read');

        $rule->load(['event', 'project', 'creator'])
            ->loadCount(['codes', 'appliedAdjustments']);

        return response()->json([
            'data' => (new PromotionRuleResource($rule))->resolve(),
        ]);
    }

    public function store(StorePromotionRuleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $rule = PromotionRule::query()->create($data);

        return response()->json([
            'message' => 'Promotion rule created successfully',
            'data' => (new PromotionRuleResource($rule->fresh()))->resolve(),
        ], 201);
    }

    public function update(UpdatePromotionRuleRequest $request, PromotionRule $rule): JsonResponse
    {
        $rule->update($request->validated());

        return response()->json([
            'message' => 'Promotion rule updated successfully',
            'data' => (new PromotionRuleResource($rule->fresh()))->resolve(),
        ]);
    }

    public function destroy(PromotionRule $rule): JsonResponse
    {
        $this->authorizePermission('promotion_rules.delete');

        $rule->delete();

        return response()->json([
            'message' => 'Promotion rule deleted',
        ]);
    }

    public function restore(string $ulid): JsonResponse
    {
        $this->authorizePermission('promotion_rules.restore');

        $rule = PromotionRule::onlyTrashed()->where('ulid', $ulid)->firstOrFail();
        $rule->restore();

        return response()->json([
            'message' => 'Promotion rule restored',
            'data' => (new PromotionRuleResource($rule->fresh()))->resolve(),
        ]);
    }

    public function trash(Request $request): JsonResponse
    {
        $this->authorizePermission('promotion_rules.delete');

        $items = PromotionRule::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => PromotionRuleIndexResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function report(PromotionRule $rule): JsonResponse
    {
        $this->authorizePermission('promotions.view_reports');

        $codeStats = DB::table('promo_codes')
            ->where('promotion_rule_id', $rule->id)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as issued, COUNT(*) FILTER (WHERE usage_count > 0) as used')
            ->first();

        $appliedStats = AppliedAdjustment::query()
            ->where('promotion_rule_id', $rule->id)
            ->whereNull('voided_at')
            ->selectRaw('COUNT(*) as total_uses, COALESCE(SUM(amount), 0) as total_amount')
            ->first();

        return response()->json([
            'data' => [
                'rule' => (new PromotionRuleResource($rule))->resolve(),
                'stats' => [
                    'codes_issued' => (int) ($codeStats->issued ?? 0),
                    'codes_used' => (int) ($codeStats->used ?? 0),
                    'total_uses' => (int) ($appliedStats->total_uses ?? 0),
                    'total_amount' => (float) ($appliedStats->total_amount ?? 0),
                ],
            ],
        ]);
    }

    private function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            abort(403);
        }
    }

    private function applyFilters($query, Request $request): void
    {
        if ($search = $request->input('filter_search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        if ($kind = $request->input('filter_kind')) {
            $query->where('kind', $kind);
        }

        if ($request->input('filter_is_active') !== null) {
            $query->where('is_active', filter_var($request->input('filter_is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($eventId = $request->input('filter_event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($triggerType = $request->input('filter_trigger_type')) {
            $query->where('trigger_type', $triggerType);
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sort = $request->input('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        $allowed = ['name', 'kind', 'priority', 'starts_at', 'ends_at', 'is_active', 'created_at'];

        if (! in_array($column, $allowed, true)) {
            $column = 'created_at';
        }

        $query->orderBy($column, $direction);
    }
}
