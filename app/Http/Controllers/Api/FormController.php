<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFormRequest;
use App\Http\Requests\UpdateFormRequest;
use App\Http\Resources\FormIndexResource;
use App\Http\Resources\FormResource;
use App\Models\Form;
use App\Models\ShortLink;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FormController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Form::class);

        $user = $request->user();

        $query = Form::query()
            ->with(['creator', 'project.media', 'media', 'tags'])
            ->withCount('responses');

        if (! $user->hasRole(['master', 'admin'])) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('created_by', $user->id);
            });
        }

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $forms = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => FormIndexResource::collection($forms->items()),
            'meta' => [
                'current_page' => $forms->currentPage(),
                'last_page' => $forms->lastPage(),
                'per_page' => $forms->perPage(),
                'total' => $forms->total(),
            ],
        ]);
    }

    public function store(StoreFormRequest $request): JsonResponse
    {
        $this->authorize('create', Form::class);

        $data = $request->validated();
        unset($data['tmp_cover_image'], $data['delete_cover_image'], $data['tags']);

        $form = Form::create([
            ...$data,
            'user_id' => $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        $this->handleTemporaryUpload($request, $form);
        $form->syncTagsWithType($request->input('tags', []), 'form');

        if ($form->status === Form::STATUS_PUBLISHED) {
            $this->createOrUpdateShortLink($form);
        }

        return response()->json([
            'message' => 'Form created successfully',
            'data' => new FormResource($form->load(['creator', 'project', 'media', 'tags'])),
        ], 201);
    }

    public function show(Form $form): JsonResponse
    {
        $this->authorize('view', $form);

        $form->load(['fields', 'creator', 'updater', 'project.media', 'media', 'tags']);
        $form->loadCount('responses');

        return response()->json([
            'data' => new FormResource($form),
        ]);
    }

    public function update(UpdateFormRequest $request, Form $form): JsonResponse
    {
        $this->authorize('update', $form);

        $oldStatus = $form->status;

        $data = $request->validated();
        unset($data['tmp_cover_image'], $data['delete_cover_image'], $data['tags']);

        $form->update($data);

        $this->handleTemporaryUpload($request, $form);
        $form->syncTagsWithType($request->input('tags', []), 'form');

        if ($form->status === Form::STATUS_PUBLISHED) {
            $oldSlug = $form->getOriginal('slug');

            if ($oldStatus !== Form::STATUS_PUBLISHED) {
                $this->createOrUpdateShortLink($form);
            } elseif ($form->wasChanged(['slug', 'title', 'description'])) {
                $this->updateExistingShortLink($form, $oldSlug);
            }
        }

        return response()->json([
            'message' => 'Form updated successfully',
            'data' => new FormResource($form->load(['fields', 'creator', 'updater', 'project', 'media', 'tags'])),
        ]);
    }

    public function destroy(Form $form): JsonResponse
    {
        $this->authorize('delete', $form);

        $form->delete();

        return response()->json([
            'message' => 'Form deleted successfully',
        ]);
    }

    public function trash(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Form::class);

        $query = Form::onlyTrashed()
            ->with(['creator', 'project.media', 'media', 'tags'])
            ->withCount('responses');

        $user = $request->user();
        if (! $user->hasRole(['master', 'admin'])) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('created_by', $user->id);
            });
        }

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $forms = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => FormIndexResource::collection($forms->items()),
            'meta' => [
                'current_page' => $forms->currentPage(),
                'last_page' => $forms->lastPage(),
                'per_page' => $forms->perPage(),
                'total' => $forms->total(),
            ],
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $form = Form::onlyTrashed()->findOrFail($id);

        $this->authorize('restore', $form);

        $form->restore();

        return response()->json([
            'message' => 'Form restored successfully',
            'data' => new FormResource($form->load(['creator', 'project'])),
        ]);
    }

    public function forceDestroy(int $id): JsonResponse
    {
        $form = Form::onlyTrashed()->findOrFail($id);

        $this->authorize('forceDelete', $form);

        $form->forceDelete();

        return response()->json([
            'message' => 'Form permanently deleted',
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($search = $request->input('filter_search')) {
            $query->search($search);
        }

        if ($status = $request->input('filter_status')) {
            $query->byStatus(is_array($status) ? $status : [$status]);
        }

        if ($project = $request->input('filter_project')) {
            $query->where('project_id', $project);
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSortFields = [
            'title',
            'status',
            'created_at',
            'updated_at',
            'responses_count',
        ];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }
    }

    private function handleTemporaryUpload(Request $request, Form $form): void
    {
        if ($request->input('delete_cover_image') === true) {
            $form->clearMediaCollection('cover_image');

            return;
        }

        $value = $request->input('tmp_cover_image');

        if (! $value || ! Str::startsWith($value, 'tmp-')) {
            return;
        }

        $form->clearMediaCollection('cover_image');

        $metadataPath = "tmp/uploads/{$value}/metadata.json";
        if (! Storage::disk('local')->exists($metadataPath)) {
            throw new \Exception("File `{$value}` does not exist");
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        $filename = $metadata['original_name'];
        $tempFilePath = "tmp/uploads/{$value}/{$filename}";

        $form->addMediaFromDisk($tempFilePath, 'local')
            ->usingName(pathinfo($filename, PATHINFO_FILENAME))
            ->toMediaCollection('cover_image');

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");
    }

    private function createOrUpdateShortLink(Form $form): void
    {
        $frontendUrl = config('app.frontend_url', 'https://pmone.id');
        $destinationUrl = rtrim($frontendUrl, '/').'/f/'.$form->slug;

        $existing = ShortLink::where('destination_url', 'like', '%/f/'.$form->slug)->first();

        if ($existing) {
            $existing->update([
                'destination_url' => $destinationUrl,
                'og_title' => $form->title,
                'og_description' => $form->description ? Str::limit(strip_tags($form->description), 160) : null,
            ]);

            return;
        }

        $slug = $form->slug;
        $suffix = 0;
        while (ShortLink::withTrashed()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $form->slug.'-'.$suffix;
        }

        ShortLink::create([
            'user_id' => $form->user_id,
            'slug' => $slug,
            'destination_url' => $destinationUrl,
            'is_active' => true,
            'og_title' => $form->title,
            'og_description' => $form->description ? Str::limit(strip_tags($form->description), 160) : null,
        ]);
    }

    private function updateExistingShortLink(Form $form, ?string $oldSlug): void
    {
        $frontendUrl = config('app.frontend_url', 'https://pmone.id');
        $searchSlug = $oldSlug ?: $form->slug;

        $existing = ShortLink::where('destination_url', 'like', '%/f/'.$searchSlug)->first();

        if (! $existing) {
            $this->createOrUpdateShortLink($form);

            return;
        }

        $destinationUrl = rtrim($frontendUrl, '/').'/f/'.$form->slug;

        $updateData = [
            'destination_url' => $destinationUrl,
            'og_title' => $form->title,
            'og_description' => $form->description ? Str::limit(strip_tags($form->description), 160) : null,
        ];

        if ($form->wasChanged('slug')) {
            $newSlug = $form->slug;
            $suffix = 0;
            while (ShortLink::withTrashed()->where('slug', $newSlug)->where('id', '!=', $existing->id)->exists()) {
                $suffix++;
                $newSlug = $form->slug.'-'.$suffix;
            }
            $updateData['slug'] = $newSlug;
        }

        $existing->update($updateData);
    }
}
