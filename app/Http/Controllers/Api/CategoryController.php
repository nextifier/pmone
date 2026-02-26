<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $query = Category::query()
            ->with(['parent', 'children', 'posts']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $categories = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => CategoryResource::collection($categories->items()),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        // Search filter
        if ($searchTerm = $request->input('filter_search')) {
            $query->where(function ($q) use ($searchTerm) {
                $searchTerm = strtolower($searchTerm);
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        // Visibility filter
        if ($visibility = $request->input('filter_visibility')) {
            $query->where('visibility', $visibility);
        }

        // Root categories only (no parent)
        if ($request->boolean('filter_root')) {
            $query->whereNull('parent_id');
        }

        // Parent filter
        if ($parentId = $request->input('filter_parent')) {
            $query->where('parent_id', $parentId);
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortField = $request->input('sort', '-created_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        if (in_array($field, ['name', 'slug', 'visibility', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        $category->load(['parent', 'children', 'posts', 'creator', 'updater']);

        return response()->json([
            'data' => new CategoryResource($category),
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        try {
            $data = $request->validated();

            $category = Category::create($data);
            $category->load(['parent', 'children']);

            return response()->json([
                'message' => 'Category created successfully',
                'data' => new CategoryResource($category),
            ], 201);
        } catch (\Exception $e) {
            logger()->error('Category creation failed', [
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);

            return response()->json([
                'message' => 'Failed to create category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        try {
            $data = $request->validated();

            $category->update($data);
            $category->load(['parent', 'children']);

            return response()->json([
                'message' => 'Category updated successfully',
                'data' => new CategoryResource($category),
            ]);
        } catch (\Exception $e) {
            logger()->error('Category update failed', [
                'error' => $e->getMessage(),
                'category_id' => $category->id,
            ]);

            return response()->json([
                'message' => 'Failed to update category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        try {
            $category->delete();

            return response()->json([
                'message' => 'Category deleted successfully',
            ]);
        } catch (\Exception $e) {
            logger()->error('Category deletion failed', [
                'error' => $e->getMessage(),
                'category_id' => $category->id,
            ]);

            return response()->json([
                'message' => 'Failed to delete category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get trashed categories
     */
    public function trash(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $query = Category::onlyTrashed()
            ->with(['parent', 'deleter']);

        // Search filter
        if ($searchTerm = $request->input('filter_search')) {
            $query->where(function ($q) use ($searchTerm) {
                $searchTerm = strtolower($searchTerm);
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        $categories = $query->orderBy('deleted_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => CategoryResource::collection($categories->items()),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    /**
     * Restore a trashed category
     */
    public function restore(int $id): JsonResponse
    {
        $category = Category::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $category);

        $category->restore();

        return response()->json([
            'message' => 'Category restored successfully',
            'data' => new CategoryResource($category->fresh()),
        ]);
    }

    /**
     * Permanently delete a category
     */
    public function forceDestroy(int $id): JsonResponse
    {
        $category = Category::onlyTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $category);

        $category->forceDelete();

        return response()->json([
            'message' => 'Category permanently deleted',
        ]);
    }

    /**
     * Bulk delete categories
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $this->authorize('deleteAny', Category::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:categories,id',
        ]);

        $categories = Category::whereIn('id', $request->ids)->get();

        foreach ($categories as $category) {
            $this->authorize('delete', $category);
        }

        Category::whereIn('id', $request->ids)->delete();

        return response()->json([
            'message' => count($request->ids).' categories deleted successfully',
        ]);
    }

    /**
     * Bulk restore categories
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $this->authorize('restoreAny', Category::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:categories,id',
        ]);

        Category::onlyTrashed()->whereIn('id', $request->ids)->restore();

        return response()->json([
            'message' => count($request->ids).' categories restored successfully',
        ]);
    }

    /**
     * Bulk force delete categories
     */
    public function bulkForceDestroy(Request $request): JsonResponse
    {
        $this->authorize('forceDeleteAny', Category::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:categories,id',
        ]);

        Category::onlyTrashed()->whereIn('id', $request->ids)->forceDelete();

        return response()->json([
            'message' => count($request->ids).' categories permanently deleted',
        ]);
    }
}
