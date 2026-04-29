<?php

namespace App\Http\Controllers\Api;

use App\Exports\RundownItemsExport;
use App\Exports\RundownItemsTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRundownItemRequest;
use App\Http\Requests\UpdateRundownItemRequest;
use App\Http\Resources\RundownItemResource;
use App\Imports\RundownItemsImport;
use App\Jobs\ProcessExcelImport;
use App\Models\Event;
use App\Models\Project;
use App\Models\RundownItem;
use App\Services\Rundown\RundownGrouper;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RundownItemController extends Controller
{
    use AuthorizesRequests;

    private function resolveProject(string $username): Project
    {
        return Project::where('username', $username)->firstOrFail();
    }

    private function resolveEvent(Project $project, string $eventSlug): Event
    {
        return $project->events()->where('slug', $eventSlug)->firstOrFail();
    }

    public function index(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $query = $event->rundownItems()->with(['media', 'tags']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhere('location', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('date')) {
            $query->where('date', $request->input('date'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $items = $query->get();

        return response()->json([
            'data' => [
                'days' => RundownGrouper::group(
                    $items,
                    fn ($item) => (new RundownItemResource($item))->resolve(),
                    event: $event,
                    unscheduledLabel: 'Unscheduled',
                ),
            ],
        ]);
    }

    public function store(StoreRundownItemRequest $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $validated = $request->validated();
        $categories = $validated['categories'] ?? null;
        $tmpPoster = $validated['tmp_poster'] ?? null;
        $posterDelete = $validated['poster_delete'] ?? false;
        unset($validated['categories'], $validated['tmp_poster'], $validated['poster_delete']);

        $item = $event->rundownItems()->create($validated);

        if ($categories !== null) {
            $item->syncTagsWithType($categories, 'rundown_category');
        }

        $this->handleTemporaryUpload($tmpPoster, $posterDelete, $item, 'poster');
        $item->load(['media', 'tags']);

        return response()->json([
            'message' => 'Rundown item created successfully',
            'data' => new RundownItemResource($item),
        ], 201);
    }

    public function show(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $item = $event->rundownItems()->with(['media', 'tags'])->findOrFail($id);

        return response()->json([
            'data' => new RundownItemResource($item),
        ]);
    }

    public function update(UpdateRundownItemRequest $request, string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $item = $event->rundownItems()->findOrFail($id);

        $validated = $request->validated();
        $categories = $validated['categories'] ?? null;
        $tmpPoster = $validated['tmp_poster'] ?? null;
        $posterDelete = $validated['poster_delete'] ?? false;
        $hasCategories = array_key_exists('categories', $validated);
        unset($validated['categories'], $validated['tmp_poster'], $validated['poster_delete']);

        $item->update($validated);

        if ($hasCategories) {
            $item->syncTagsWithType($categories ?? [], 'rundown_category');
        }

        $this->handleTemporaryUpload($tmpPoster, $posterDelete, $item, 'poster');
        $item->load(['media', 'tags']);

        return response()->json([
            'message' => 'Rundown item updated successfully',
            'data' => new RundownItemResource($item),
        ]);
    }

    public function destroy(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $item = $event->rundownItems()->findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Rundown item deleted successfully',
        ]);
    }

    public function trash(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $query = RundownItem::onlyTrashed()
            ->with(['media', 'tags', 'deleter'])
            ->where('event_id', $event->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        $query->orderByDesc('deleted_at');

        $items = $query->paginate($request->input('per_page', 30));

        return response()->json([
            'data' => RundownItemResource::collection($items->items()),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function restore(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $item = RundownItem::onlyTrashed()
            ->where('event_id', $event->id)
            ->findOrFail($id);

        $item->restore();

        return response()->json([
            'message' => 'Rundown item restored successfully',
        ]);
    }

    public function forceDestroy(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $item = RundownItem::onlyTrashed()
            ->where('event_id', $event->id)
            ->findOrFail($id);

        $item->forceDelete();

        return response()->json([
            'message' => 'Rundown item permanently deleted',
        ]);
    }

    public function reorder(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'distinct', 'exists:rundown_items,id'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $ids = collect($validated['orders'])->pluck('id')->all();

        $belongCount = RundownItem::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->count();

        if ($belongCount !== count($ids)) {
            return response()->json([
                'message' => 'One or more items do not belong to this event.',
            ], 422);
        }

        DB::transaction(function () use ($validated, $event) {
            foreach ($validated['orders'] as $orderData) {
                $item = RundownItem::where('event_id', $event->id)
                    ->where('id', $orderData['id'])
                    ->first();

                if ($item) {
                    $item->order_column = $orderData['order'];
                    $item->save();
                }
            }
        });

        return response()->json([
            'message' => 'Rundown order updated successfully',
        ]);
    }

    public function export(Request $request, string $username, string $eventSlug): BinaryFileResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $filters = [];
        if ($date = $request->input('filter_date')) {
            $filters['date'] = $date;
        }

        $export = new RundownItemsExport($event->id, $filters ?: null);

        $filename = sprintf(
            'rundown_%s_%s.xlsx',
            Str::slug($event->slug ?: 'event'),
            now()->format('Y-m-d_His'),
        );

        return Excel::download($export, $filename);
    }

    public function exportJson(Request $request, string $username, string $eventSlug): StreamedResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $items = $event->rundownItems()
            ->with(['tags'])
            ->orderBy('date')
            ->orderBy('order_column')
            ->get();

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'event' => [
                'title' => $event->title,
                'slug' => $event->slug,
            ],
            'items' => $items->map(fn (RundownItem $item) => $this->serializeItemForJson($item))->all(),
        ];

        $filename = sprintf(
            'rundown_%s_%s.json',
            Str::slug($event->slug ?: 'event'),
            now()->format('Y-m-d_His'),
        );

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function downloadTemplate(string $username, string $eventSlug): BinaryFileResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        return Excel::download(new RundownItemsTemplateExport, 'rundown_import_template.xlsx');
    }

    public function import(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tempFolder = $request->input('file');
        $metadataPath = "tmp/uploads/{$tempFolder}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        $filePath = "tmp/uploads/{$tempFolder}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        if ($this->isJsonUpload($metadata)) {
            return $this->processJsonUpload($event, $tempFolder, $filePath);
        }

        $importId = Str::uuid()->toString();

        Cache::put("import:{$importId}", [
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'imported_count' => 0,
            'percentage' => 0,
            'errors' => [],
            'error_message' => null,
        ], now()->addMinutes(30));

        ProcessExcelImport::dispatch(
            $importId,
            Storage::disk('local')->path($filePath),
            RundownItemsImport::class,
            $tempFolder,
            [$event->id],
        );

        return response()->json(['import_id' => $importId]);
    }

    private function isJsonUpload(array $metadata): bool
    {
        $name = strtolower((string) ($metadata['original_name'] ?? ''));
        $mime = strtolower((string) ($metadata['mime_type'] ?? ''));

        return Str::endsWith($name, '.json') || $mime === 'application/json';
    }

    private function processJsonUpload(Event $event, string $tempFolder, string $filePath): JsonResponse
    {
        $contents = Storage::disk('local')->get($filePath);
        $payload = json_decode($contents, true);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$tempFolder}");

        if (! is_array($payload) || ! isset($payload['items']) || ! is_array($payload['items'])) {
            return response()->json([
                'message' => 'Invalid JSON payload. Expected an object with an "items" array.',
            ], 422);
        }

        $importedCount = 0;
        $errors = [];

        DB::transaction(function () use ($payload, $event, &$importedCount, &$errors) {
            foreach ($payload['items'] as $index => $rawItem) {
                if (! is_array($rawItem)) {
                    $errors[] = ['row' => $index + 1, 'errors' => ['items' => ['Item must be an object.']]];

                    continue;
                }

                $normalized = $this->normalizeJsonItem($rawItem);

                $itemValidator = Validator::make($normalized, $this->jsonItemRules());

                if ($itemValidator->fails()) {
                    $errors[] = [
                        'row' => $index + 1,
                        'errors' => $itemValidator->errors()->toArray(),
                    ];

                    continue;
                }

                $validated = $itemValidator->validated();
                $categories = $validated['categories'] ?? [];
                unset($validated['categories']);

                $item = $event->rundownItems()->create($validated);

                if (! empty($categories)) {
                    $item->syncTagsWithType($categories, 'rundown_category');
                }

                $importedCount++;
            }
        });

        $importId = Str::uuid()->toString();

        Cache::put("import:{$importId}", [
            'status' => 'completed',
            'total_rows' => count($payload['items']),
            'processed_rows' => count($payload['items']),
            'imported_count' => $importedCount,
            'skipped_count' => 0,
            'percentage' => 100,
            'errors' => $errors,
            'error_message' => null,
        ], now()->addMinutes(30));

        return response()->json(['import_id' => $importId]);
    }

    public function importJson(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $payload = $this->resolveJsonImportPayload($request);

        if (! is_array($payload) || ! isset($payload['items']) || ! is_array($payload['items'])) {
            return response()->json([
                'message' => 'Invalid JSON payload. Expected an object with an "items" array.',
            ], 422);
        }

        $importedCount = 0;
        $errors = [];

        DB::transaction(function () use ($payload, $event, &$importedCount, &$errors) {
            foreach ($payload['items'] as $index => $rawItem) {
                if (! is_array($rawItem)) {
                    $errors[] = ['row' => $index + 1, 'errors' => ['items' => ['Item must be an object.']]];

                    continue;
                }

                $normalized = $this->normalizeJsonItem($rawItem);

                $itemValidator = Validator::make($normalized, $this->jsonItemRules());

                if ($itemValidator->fails()) {
                    $errors[] = [
                        'row' => $index + 1,
                        'errors' => $itemValidator->errors()->toArray(),
                    ];

                    continue;
                }

                $validated = $itemValidator->validated();
                $categories = $validated['categories'] ?? [];
                unset($validated['categories']);

                $item = $event->rundownItems()->create($validated);

                if (! empty($categories)) {
                    $item->syncTagsWithType($categories, 'rundown_category');
                }

                $importedCount++;
            }
        });

        return response()->json([
            'message' => 'Rundown items imported successfully',
            'imported_count' => $importedCount,
            'errors' => $errors,
        ]);
    }

    private function serializeItemForJson(RundownItem $item): array
    {
        $translations = [];
        foreach (['title', 'subtitle', 'description', 'theme', 'location', 'presented_by', 'moderator'] as $field) {
            $translations[$field] = $item->getTranslations($field);
        }

        return array_merge([
            'date' => $item->date?->format('Y-m-d'),
            'start_time' => $item->start_time,
            'end_time' => $item->end_time,
        ], $translations, [
            'panelists' => $this->flattenLocalizedList($item->panelists),
            'speakers' => $this->flattenLocalizedList($item->speakers),
            'categories' => $item->tags
                ->filter(fn ($tag) => $tag->type === 'rundown_category')
                ->pluck('name')
                ->unique()
                ->values()
                ->all(),
            'is_active' => (bool) $item->is_active,
        ]);
    }

    private function flattenLocalizedList(mixed $value): array
    {
        if (! is_array($value) || empty($value)) {
            return [];
        }

        if (array_is_list($value)) {
            return $value;
        }

        foreach (['en', 'id'] as $locale) {
            if (isset($value[$locale]) && is_array($value[$locale]) && ! empty($value[$locale])) {
                return array_values($value[$locale]);
            }
        }

        foreach ($value as $candidate) {
            if (is_array($candidate) && ! empty($candidate)) {
                return array_values($candidate);
            }
        }

        return [];
    }

    private function resolveJsonImportPayload(Request $request): mixed
    {
        if ($request->hasFile('file')) {
            $contents = file_get_contents($request->file('file')->getRealPath());

            return json_decode($contents, true);
        }

        $fileInput = $request->input('file');

        if (is_string($fileInput) && Str::startsWith($fileInput, 'tmp-')) {
            $metadataPath = "tmp/uploads/{$fileInput}/metadata.json";

            if (! Storage::disk('local')->exists($metadataPath)) {
                return null;
            }

            $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
            $filePath = "tmp/uploads/{$fileInput}/{$metadata['original_name']}";

            if (! Storage::disk('local')->exists($filePath)) {
                return null;
            }

            $contents = Storage::disk('local')->get($filePath);
            $payload = json_decode($contents, true);

            Storage::disk('local')->deleteDirectory("tmp/uploads/{$fileInput}");

            return $payload;
        }

        if ($request->isJson()) {
            return $request->json()->all();
        }

        if ($request->has('data')) {
            $value = $request->input('data');

            return is_string($value) ? json_decode($value, true) : $value;
        }

        return null;
    }

    private function normalizeJsonItem(array $item): array
    {
        $panelists = $this->flattenLocalizedList($item['panelists'] ?? null);
        $speakers = $this->flattenLocalizedList($item['speakers'] ?? null);

        $normalized = [
            'date' => $this->normalizeDate($item['date'] ?? null),
            'start_time' => $this->normalizeTime($item['start_time'] ?? null),
            'end_time' => $this->normalizeTime($item['end_time'] ?? null),
            'panelists' => $panelists ?: null,
            'speakers' => $speakers ?: null,
            'categories' => $this->normalizeCategories($item['categories'] ?? []),
            'is_active' => array_key_exists('is_active', $item) ? (bool) $item['is_active'] : true,
        ];

        foreach (['title', 'subtitle', 'description', 'theme', 'location', 'presented_by', 'moderator'] as $field) {
            $normalized[$field] = $this->normalizeTranslatable($item[$field] ?? null);
        }

        return $normalized;
    }

    private function normalizeTranslatable(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v) => trim($v))
            ->all();
    }

    private function normalizeCategories(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($v) => is_string($v) ? trim($v) : null,
            $value,
        )));
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeTime(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('H:i');
        } catch (\Throwable $e) {
            return is_string($value) ? $value : null;
        }
    }

    private function jsonItemRules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],

            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:500'],
            'title.id' => ['nullable', 'string', 'max:500'],

            'subtitle' => ['nullable', 'array'],
            'subtitle.en' => ['nullable', 'string', 'max:500'],
            'subtitle.id' => ['nullable', 'string', 'max:500'],

            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.id' => ['nullable', 'string'],

            'theme' => ['nullable', 'array'],
            'theme.en' => ['nullable', 'string', 'max:500'],
            'theme.id' => ['nullable', 'string', 'max:500'],

            'location' => ['nullable', 'array'],
            'location.en' => ['nullable', 'string', 'max:500'],
            'location.id' => ['nullable', 'string', 'max:500'],

            'presented_by' => ['nullable', 'array'],
            'presented_by.en' => ['nullable', 'string', 'max:255'],
            'presented_by.id' => ['nullable', 'string', 'max:255'],

            'moderator' => ['nullable', 'array'],
            'moderator.en' => ['nullable', 'string', 'max:255'],
            'moderator.id' => ['nullable', 'string', 'max:255'],

            'panelists' => ['nullable', 'array'],
            'panelists.*.name' => ['required_with:panelists', 'string', 'max:255'],
            'panelists.*.title' => ['nullable', 'string', 'max:255'],

            'speakers' => ['nullable', 'array'],
            'speakers.*.name' => ['required_with:speakers', 'string', 'max:255'],
            'speakers.*.title' => ['nullable', 'string', 'max:255'],
            'speakers.*.organization' => ['nullable', 'string', 'max:255'],

            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'max:100'],

            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function handleTemporaryUpload(?string $tmpValue, bool $shouldDelete, RundownItem $item, string $collection): void
    {
        if ($shouldDelete) {
            $item->clearMediaCollection($collection);

            return;
        }

        if (! $tmpValue || ! Str::startsWith($tmpValue, 'tmp-')) {
            return;
        }

        $metadataPath = "tmp/uploads/{$tmpValue}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return;
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        $filePath = "tmp/uploads/{$tmpValue}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return;
        }

        $item->clearMediaCollection($collection);
        $item->addMedia(Storage::disk('local')->path($filePath))->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$tmpValue}");
    }
}
