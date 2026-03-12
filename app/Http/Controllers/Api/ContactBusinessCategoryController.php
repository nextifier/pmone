<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Tags\Tag;

class ContactBusinessCategoryController extends Controller
{
    private const TAG_TYPE = 'business_category';

    /**
     * List all contact business categories.
     */
    public function index(): JsonResponse
    {
        $categories = Tag::withType(self::TAG_TYPE)
            ->ordered()
            ->get()
            ->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'order_column' => $tag->order_column,
            ]);

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Create a new business category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $existing = Tag::withType(self::TAG_TYPE)
            ->where('name->en', $validated['name'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'A business category with this name already exists.',
            ], 422);
        }

        $tag = Tag::findOrCreate($validated['name'], self::TAG_TYPE);

        return response()->json([
            'message' => 'Business category created successfully.',
            'data' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'order_column' => $tag->order_column,
            ],
        ], 201);
    }

    /**
     * Update a business category name.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tag = Tag::withType(self::TAG_TYPE)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $existing = Tag::withType(self::TAG_TYPE)
            ->where('name->en', $validated['name'])
            ->where('id', '!=', $tag->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'A business category with this name already exists.',
            ], 422);
        }

        $tag->name = $validated['name'];
        $tag->save();

        return response()->json([
            'message' => 'Business category updated successfully.',
            'data' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'order_column' => $tag->order_column,
            ],
        ]);
    }

    /**
     * Delete a business category.
     */
    public function destroy(int $id): JsonResponse
    {
        $tag = Tag::withType(self::TAG_TYPE)->findOrFail($id);

        $tag->delete();

        return response()->json([
            'message' => 'Business category deleted successfully.',
        ]);
    }

    /**
     * Reorder business categories.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        foreach ($validated['orders'] as $orderData) {
            Tag::withType(self::TAG_TYPE)
                ->where('id', $orderData['id'])
                ->update(['order_column' => $orderData['order']]);
        }

        return response()->json([
            'message' => 'Business category order updated successfully.',
        ]);
    }
}
