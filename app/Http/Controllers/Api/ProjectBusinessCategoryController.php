<?php

namespace App\Http\Controllers\Api;

use App\Exports\BusinessCategoriesExport;
use App\Exports\BusinessCategoriesTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\BusinessCategoriesImport;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Tags\Tag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectBusinessCategoryController extends Controller
{
    /**
     * List all business categories for a project.
     */
    public function index(string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        $categories = Tag::withType("business_category:{$project->id}")
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
    public function store(Request $request, string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $type = "business_category:{$project->id}";

        // Check for duplicate name in same project type
        $existing = Tag::withType($type)
            ->where('name->en', $validated['name'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'A business category with this name already exists.',
            ], 422);
        }

        $tag = Tag::findOrCreate($validated['name'], $type);

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
    public function update(Request $request, string $username, int $id): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        $type = "business_category:{$project->id}";
        $tag = Tag::withType($type)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        // Check for duplicate name (excluding current)
        $existing = Tag::withType($type)
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
    public function destroy(string $username, int $id): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        $type = "business_category:{$project->id}";
        $tag = Tag::withType($type)->findOrFail($id);

        $tag->delete();

        return response()->json([
            'message' => 'Business category deleted successfully.',
        ]);
    }

    /**
     * Reorder business categories.
     */
    public function reorder(Request $request, string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $type = "business_category:{$project->id}";

        foreach ($validated['orders'] as $orderData) {
            Tag::withType($type)
                ->where('id', $orderData['id'])
                ->update(['order_column' => $orderData['order']]);
        }

        return response()->json([
            'message' => 'Business category order updated successfully.',
        ]);
    }

    /**
     * Export business categories.
     */
    public function export(Request $request, string $username): BinaryFileResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        $filters = [];
        if ($search = $request->input('filter_search')) {
            $filters['search'] = $search;
        }

        $sort = $request->input('sort', 'order_column');

        $export = new BusinessCategoriesExport($project->id, $filters, $sort);
        $filename = 'business_categories_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download($export, $filename);
    }

    /**
     * Download import template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new BusinessCategoriesTemplateExport, 'business_categories_import_template.xlsx');
    }

    /**
     * Import business categories.
     */
    public function import(Request $request, string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        $request->validate([
            'file' => ['required', 'string'],
        ]);

        $tempFolder = null;

        try {
            $tempFolder = $request->input('file');

            $metadataPath = "tmp/uploads/{$tempFolder}/metadata.json";

            if (! Storage::disk('local')->exists($metadataPath)) {
                return response()->json([
                    'message' => 'File not found',
                ], 404);
            }

            $metadata = json_decode(
                Storage::disk('local')->get($metadataPath),
                true
            );

            $filePath = "tmp/uploads/{$tempFolder}/{$metadata['original_name']}";

            if (! Storage::disk('local')->exists($filePath)) {
                return response()->json([
                    'message' => 'File not found',
                ], 404);
            }

            $import = new BusinessCategoriesImport($project->id);
            Excel::import($import, Storage::disk('local')->path($filePath));

            $failures = $import->getFailures();
            $importedCount = $import->getImportedCount();
            $skippedCount = $import->getSkippedCount();
            $errorMessages = [];

            foreach ($failures as $failure) {
                $errorMessages[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }

            if (count($errorMessages) > 0) {
                return response()->json([
                    'message' => 'Import completed with errors',
                    'errors' => $errorMessages,
                    'imported_count' => $importedCount,
                    'skipped_count' => $skippedCount,
                ], 422);
            }

            return response()->json([
                'message' => 'Business categories imported successfully',
                'imported_count' => $importedCount,
                'skipped_count' => $skippedCount,
            ]);
        } catch (\Exception $e) {
            logger()->error('Business categories import failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to import business categories',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            if ($tempFolder) {
                Storage::disk('local')->deleteDirectory("tmp/uploads/{$tempFolder}");
            }
        }
    }
}
