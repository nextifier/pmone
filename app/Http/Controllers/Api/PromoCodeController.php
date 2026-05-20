<?php

namespace App\Http\Controllers\Api;

use App\Exports\PromoCodesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromoCode\BulkGeneratePromoCodeRequest;
use App\Http\Requests\PromoCode\StorePromoCodeRequest;
use App\Http\Requests\PromoCode\UpdatePromoCodeRequest;
use App\Http\Resources\PromoCodeIndexResource;
use App\Http\Resources\PromoCodeResource;
use App\Http\Resources\PromoCodeUsageResource;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Services\Promotion\PromoCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PromoCodeController extends Controller
{
    public function __construct(
        protected PromoCodeService $promoCodes,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('promo_codes.read');

        $query = PromoCode::query()
            ->with('promotionRule:id,ulid,name,kind,value_type,value', 'event:id,title,slug')
            ->withCount('usages');

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $items = $query->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => PromoCodeIndexResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(PromoCode $code): JsonResponse
    {
        $this->authorizePermission('promo_codes.read');

        $code->load(['promotionRule', 'event', 'creator'])->loadCount('usages');

        return response()->json([
            'data' => (new PromoCodeResource($code))->resolve(),
        ]);
    }

    public function store(StorePromoCodeRequest $request, PromotionRule $rule): JsonResponse
    {
        $data = $request->validated();
        $data['promotion_rule_id'] = $rule->id;

        $promo = PromoCode::query()->create($data);

        return response()->json([
            'message' => 'Promo code created successfully',
            'data' => (new PromoCodeResource($promo->fresh(['promotionRule'])))->resolve(),
        ], 201);
    }

    public function bulkStore(BulkGeneratePromoCodeRequest $request, PromotionRule $rule): JsonResponse
    {
        $data = $request->validated();

        $codes = $this->promoCodes->bulkGenerate($rule, (int) $data['quantity'], [
            'prefix' => $data['prefix'] ?? '',
            'length' => $data['length'] ?? 8,
            'usage_limit' => $data['usage_limit'] ?? 1,
            'usage_limit_per_email' => $data['usage_limit_per_email'] ?? 1,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'metadata' => $data['metadata'] ?? null,
        ]);

        return response()->json([
            'message' => "Generated {$codes->count()} promo codes",
            'data' => PromoCodeIndexResource::collection($codes)->resolve(),
        ], 201);
    }

    public function update(UpdatePromoCodeRequest $request, PromoCode $code): JsonResponse
    {
        $code->update($request->validated());

        return response()->json([
            'message' => 'Promo code updated successfully',
            'data' => (new PromoCodeResource($code->fresh(['promotionRule'])))->resolve(),
        ]);
    }

    public function destroy(PromoCode $code): JsonResponse
    {
        $this->authorizePermission('promo_codes.delete');

        $code->delete();

        return response()->json([
            'message' => 'Promo code deleted',
        ]);
    }

    public function usages(Request $request, PromoCode $code): JsonResponse
    {
        $this->authorizePermission('promotions.view_reports');

        $items = $code->usages()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 25));

        return response()->json([
            'data' => PromoCodeUsageResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorizePermission('promo_codes.read');

        $filters = [];

        if ($search = $request->input('filter_search')) {
            $filters['search'] = $search;
        }

        if ($ruleId = $request->input('filter_rule_id')) {
            $filters['rule_id'] = $ruleId;
        }

        if ($eventId = $request->input('filter_event_id')) {
            $filters['event_id'] = $eventId;
        }

        if ($request->input('filter_is_active') !== null) {
            $filters['is_active'] = $request->input('filter_is_active');
        }

        if ($request->boolean('filter_exhausted')) {
            $filters['exhausted'] = true;
        }

        $sort = $request->input('sort', '-created_at');
        $filename = 'promo_codes_'.now()->format('Y-m-d_His').'.xlsx';

        activity()
            ->causedBy($request->user())
            ->event('exported')
            ->withProperties(['model_type' => 'PromoCode', 'filename' => $filename])
            ->log('Exported promo codes');

        return Excel::download(new PromoCodesExport($filters, $sort), $filename);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $this->authorizePermission('promo_codes.delete');

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:promo_codes,id'],
        ]);

        $deletedCount = 0;

        PromoCode::query()
            ->whereIn('id', $validated['ids'])
            ->get()
            ->each(function (PromoCode $code) use (&$deletedCount) {
                $code->delete();
                $deletedCount++;
            });

        if ($deletedCount > 0) {
            activity()
                ->causedBy($request->user())
                ->event('bulk_deleted')
                ->withProperties(['deleted_count' => $deletedCount, 'model_type' => 'PromoCode'])
                ->log("Bulk deleted {$deletedCount} promo code(s)");
        }

        return response()->json([
            'message' => "{$deletedCount} promo code(s) deleted",
            'deleted_count' => $deletedCount,
            'errors' => [],
        ]);
    }

    public function trash(Request $request): JsonResponse
    {
        $this->authorizePermission('promo_codes.delete');

        $items = PromoCode::onlyTrashed()
            ->with('promotionRule:id,ulid,name,kind,value_type,value')
            ->orderByDesc('deleted_at')
            ->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => PromoCodeIndexResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function restore(string $ulid): JsonResponse
    {
        $this->authorizePermission('promo_codes.restore');

        $code = PromoCode::onlyTrashed()->where('ulid', $ulid)->firstOrFail();
        $code->restore();

        return response()->json([
            'message' => 'Promo code restored',
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
                $q->where('code', 'ilike', "%{$search}%")
                    ->orWhere('issued_to_email', 'ilike', "%{$search}%");
            });
        }

        if ($ruleId = $request->input('filter_rule_id')) {
            $query->where('promotion_rule_id', $ruleId);
        }

        if ($eventId = $request->input('filter_event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($request->input('filter_is_active') !== null) {
            $query->where('is_active', filter_var($request->input('filter_is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('filter_exhausted')) {
            $query->whereColumn('usage_count', '>=', 'usage_limit')->whereNotNull('usage_limit');
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sort = $request->input('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        $allowed = ['code', 'usage_count', 'usage_limit', 'valid_until', 'is_active', 'created_at'];

        if (! in_array($column, $allowed, true)) {
            $column = 'created_at';
        }

        $query->orderBy($column, $direction);
    }
}
