<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\HotelTransferOption;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Project;
use App\Models\PromotionPost;
use App\Models\RoomType;
use App\Support\ImageOptimizer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Exceptions\InvalidCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ResponseCache\Facades\ResponseCache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * Supported file types and their configurations
     */
    protected const FILE_TYPE_CONFIGS = [
        'images' => [
            'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'],
            'max_size' => 20480, // 20MB
            'supports_conversions' => true,
        ],
        'documents' => [
            'mime_types' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv',
                'text/plain',
            ],
            'max_size' => 20480, // 20MB
            'supports_conversions' => false,
        ],
        'videos' => [
            'mime_types' => ['video/mp4', 'video/avi', 'video/quicktime', 'video/x-msvideo'],
            'max_size' => 51200, // 50MB
            'supports_conversions' => false, // Could be true if you implement video thumbnails
        ],
        'audio' => [
            'mime_types' => ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4'],
            'max_size' => 20480, // 20MB
            'supports_conversions' => false,
        ],
    ];

    public function upload(Request $request): JsonResponse
    {
        // Initial validation
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file'],
            'collection' => ['required', 'string', 'max:255'],
            'model_type' => ['required', 'string'],
            'model_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $modelType = $request->input('model_type');
            $modelId = $request->input('model_id');
            $collection = $request->input('collection');

            // Find the model
            if (! class_exists($modelType)) {
                return response()->json([
                    'message' => 'Invalid model type',
                ], 422);
            }

            // Handle model_id = 0 for uploads before model exists (e.g., content images in new posts)
            if ($modelId == 0) {
                // Store in temporary storage and return temp reference
                return $this->handleTemporaryMediaUpload($file, $collection);
            }

            $model = app($modelType)->findOrFail($modelId);

            // Validate file against collection requirements
            $validationResult = $this->validateFileForCollection($file, $model, $collection);
            if ($validationResult !== true) {
                return $validationResult;
            }

            // Check authorization
            if (! $this->authorizeUpload($model, $modelType, $modelId)) {
                return response()->json([
                    'message' => 'Unauthorized to upload media for this resource',
                ], 403);
            }

            // Clear existing media if collection is single file
            if ($this->isSingleFileCollection($model, $collection)) {
                $model->clearMediaCollection($collection);
            }

            // Cap + compress the original in place before it becomes media.
            ImageOptimizer::compressInPlace($file->getPathname());

            // Upload the file with proper naming
            $mediaAdder = $model->addMediaFromRequest('file');

            // Human-readable display name; the global UniqueFileNamer appends a
            // random token to the stored disk file name, so two uploads sharing an
            // original name never overwrite each other in the shared collection folder.
            $sanitizedName = $this->sanitizeFileName($file->getClientOriginalName());
            $mediaAdder->usingName($sanitizedName);
            $mediaAdder->usingFileName($sanitizedName);

            // Add metadata including dimensions
            $customProperties = [
                'original_name' => $file->getClientOriginalName(),
                'uploaded_by' => auth()->id(),
                'file_type' => $this->detectFileType($file),
                'uploaded_at' => now()->toISOString(),
            ];

            // Add dimensions for images and videos
            $dimensions = $this->extractFileDimensions($file);
            if ($dimensions) {
                $customProperties = array_merge($customProperties, $dimensions);
            }

            $mediaAdder->withCustomProperties($customProperties);

            // Serialize order_column assignment per model+collection so
            // concurrent uploads can't read the same "highest order" and collide.
            $media = $this->withOrderLock($modelType, $modelId, $collection, fn () => $mediaAdder->toMediaCollection($collection));

            $this->clearOwnerResponseCache($media);

            // Get media URLs including conversions
            $mediaUrls = $this->getMediaResponse($media, $model, $collection);

            return response()->json([
                'message' => 'File uploaded successfully',
                'media' => $mediaUrls,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Resource not found',
            ], 404);

        } catch (InvalidCollection $e) {
            return response()->json([
                'message' => 'Invalid media collection',
                'error' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            logger()->error('File upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'model_type' => $request->input('model_type'),
                'collection' => $request->input('collection'),
                'file_name' => $request->file('file')?->getClientOriginalName(),
                'file_size' => $request->file('file')?->getSize(),
            ]);

            return response()->json([
                'message' => 'File upload failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Upload multiple files at once
     */
    public function bulkUpload(Request $request): JsonResponse
    {
        // Initial validation
        $validator = Validator::make($request->all(), [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file'],
            'collection' => ['required', 'string', 'max:255'],
            'model_type' => ['required', 'string'],
            'model_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $files = $request->file('files');
            $modelType = $request->input('model_type');
            $modelId = $request->input('model_id');
            $collection = $request->input('collection');

            // Find the model
            if (! class_exists($modelType)) {
                return response()->json([
                    'message' => 'Invalid model type',
                ], 422);
            }

            $model = app($modelType)->findOrFail($modelId);

            // Check authorization
            if (! $this->authorizeUpload($model, $modelType, $modelId)) {
                return response()->json([
                    'message' => 'Unauthorized to upload media for this resource',
                ], 403);
            }

            // Check if collection supports multiple files
            if ($this->isSingleFileCollection($model, $collection) && count($files) > 1) {
                return response()->json([
                    'message' => 'This collection only accepts single file uploads',
                    'collection' => $collection,
                ], 422);
            }

            $uploadedMedia = [];
            $failedUploads = [];
            $totalSize = 0;

            // Pre-validate all files
            foreach ($files as $index => $file) {
                $totalSize += $file->getSize();

                $validationResult = $this->validateFileForCollection($file, $model, $collection);
                if ($validationResult !== true) {
                    $failedUploads[] = [
                        'index' => $index,
                        'filename' => $file->getClientOriginalName(),
                        'error' => $validationResult->getData()->message ?? 'Validation failed',
                    ];
                }
            }

            // Check total upload size (max 100MB for bulk upload)
            if ($totalSize > (100 * 1024 * 1024)) {
                return response()->json([
                    'message' => 'Total file size exceeds bulk upload limit',
                    'limit' => '100MB',
                    'total_size' => $this->formatFileSize($totalSize),
                ], 422);
            }

            // If any files failed validation, return errors
            if (! empty($failedUploads)) {
                return response()->json([
                    'message' => 'Some files failed validation',
                    'failed_uploads' => $failedUploads,
                    'total_files' => count($files),
                    'failed_count' => count($failedUploads),
                ], 422);
            }

            // Clear existing media if collection is single file (though we already checked above)
            if ($this->isSingleFileCollection($model, $collection)) {
                $model->clearMediaCollection($collection);
            }

            // Process each file
            foreach ($files as $index => $file) {
                try {
                    // Cap + compress the original in place before it becomes media.
                    ImageOptimizer::compressInPlace($file->getPathname());

                    // Upload the file with proper naming
                    $mediaAdder = $model->addMediaFromRequest('files', $index);

                    // Human-readable display name; the global UniqueFileNamer appends
                    // a random token to the stored disk file name, so duplicate original
                    // names never clobber within the shared collection folder.
                    $sanitizedName = $this->sanitizeFileName($file->getClientOriginalName());
                    $mediaAdder->usingName($sanitizedName);
                    $mediaAdder->usingFileName($sanitizedName);

                    // Add metadata including dimensions
                    $customProperties = [
                        'original_name' => $file->getClientOriginalName(),
                        'uploaded_by' => auth()->id(),
                        'file_type' => $this->detectFileType($file),
                        'uploaded_at' => now()->toISOString(),
                        'bulk_upload_batch' => uniqid('bulk_', true),
                        'bulk_upload_index' => $index,
                    ];

                    // Add dimensions for images and videos
                    $dimensions = $this->extractFileDimensions($file);
                    if ($dimensions) {
                        $customProperties = array_merge($customProperties, $dimensions);
                    }

                    $mediaAdder->withCustomProperties($customProperties);

                    // Serialize order_column assignment to avoid concurrent collisions.
                    $media = $this->withOrderLock($modelType, $modelId, $collection, fn () => $mediaAdder->toMediaCollection($collection));

                    $lastUploaded = $media;

                    // Get media URLs including conversions
                    $uploadedMedia[] = $this->getMediaResponse($media, $model, $collection);

                } catch (\Exception $e) {
                    $failedUploads[] = [
                        'index' => $index,
                        'filename' => $file->getClientOriginalName(),
                        'error' => $e->getMessage(),
                    ];

                    logger()->error('Bulk upload file failed', [
                        'error' => $e->getMessage(),
                        'file' => $file->getClientOriginalName(),
                        'index' => $index,
                        'user_id' => auth()->id(),
                        'model_type' => $modelType,
                        'collection' => $collection,
                    ]);
                }
            }

            // One clear per batch is enough - every file targets the same owner.
            if (isset($lastUploaded)) {
                $this->clearOwnerResponseCache($lastUploaded);
            }

            $response = [
                'message' => 'Bulk upload completed',
                'total_files' => count($files),
                'successful_uploads' => count($uploadedMedia),
                'failed_uploads' => count($failedUploads),
                'uploaded_media' => $uploadedMedia,
            ];

            if (! empty($failedUploads)) {
                $response['failed_files'] = $failedUploads;
            }

            return response()->json($response, count($failedUploads) > 0 ? 207 : 200); // 207 Multi-Status if some failed

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Resource not found',
            ], 404);

        } catch (\Exception $e) {
            logger()->error('Bulk upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'model_type' => $request->input('model_type'),
                'collection' => $request->input('collection'),
                'file_count' => count($request->file('files', [])),
            ]);

            return response()->json([
                'message' => 'Bulk upload failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete multiple media files at once
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $mediaIds = $request->input('media_ids');
            $deletedMedia = [];
            $failedDeletes = [];

            // Get all media files
            $mediaFiles = Media::whereIn('id', $mediaIds)->get();

            if ($mediaFiles->count() !== count($mediaIds)) {
                $foundIds = $mediaFiles->pluck('id')->toArray();
                $notFoundIds = array_diff($mediaIds, $foundIds);

                foreach ($notFoundIds as $id) {
                    $failedDeletes[] = [
                        'id' => $id,
                        'error' => 'Media not found',
                    ];
                }
            }

            // Process each media file
            foreach ($mediaFiles as $media) {
                try {
                    // Check authorization
                    if (! $this->authorizeDelete($media)) {
                        $failedDeletes[] = [
                            'id' => $media->id,
                            'filename' => $media->file_name,
                            'error' => 'Unauthorized to delete this media',
                        ];

                        continue;
                    }

                    // Store info before deletion
                    $mediaInfo = [
                        'id' => $media->id,
                        'filename' => $media->file_name,
                        'collection' => $media->collection_name,
                    ];

                    $media->delete();

                    // Media ops bypass the owner's Eloquent events; bust its cache.
                    $this->clearOwnerResponseCache($media);

                    $deletedMedia[] = $mediaInfo;

                } catch (\Exception $e) {
                    $failedDeletes[] = [
                        'id' => $media->id,
                        'filename' => $media->file_name ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];

                    logger()->error('Bulk delete file failed', [
                        'error' => $e->getMessage(),
                        'media_id' => $media->id,
                        'user_id' => auth()->id(),
                    ]);
                }
            }

            $response = [
                'message' => 'Bulk delete completed',
                'total_requested' => count($mediaIds),
                'successful_deletes' => count($deletedMedia),
                'failed_deletes' => count($failedDeletes),
                'deleted_media' => $deletedMedia,
            ];

            if (! empty($failedDeletes)) {
                $response['failed_deletes'] = $failedDeletes;
            }

            return response()->json($response, count($failedDeletes) > 0 ? 207 : 200); // 207 Multi-Status if some failed

        } catch (\Exception $e) {
            logger()->error('Bulk delete failed', [
                'error' => $e->getMessage(),
                'media_ids' => $request->input('media_ids'),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Bulk delete failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Reorder media within a single owner + collection.
     *
     * Generic, context-agnostic sibling to bulkDelete: works for any model's
     * media (e.g. global Hotel gallery, event-scoped Room gallery) without a
     * per-context route. All-or-nothing - the full coherent set is required so
     * Spatie's setNewOrder does not interleave unrelated sequences.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mediaIds = $request->input('media_ids');
        $media = Media::whereIn('id', $mediaIds)->get();

        if ($media->count() !== count($mediaIds)) {
            return response()->json([
                'message' => 'One or more media not found',
            ], 422);
        }

        $first = $media->first();

        $isCoherent = $media->every(fn (Media $item): bool => $item->model_type === $first->model_type
            && (int) $item->model_id === (int) $first->model_id
            && $item->collection_name === $first->collection_name);

        if (! $isCoherent) {
            return response()->json([
                'message' => 'All media must belong to the same model and collection',
            ], 422);
        }

        foreach ($media as $item) {
            if (! $this->authorizeDelete($item)) {
                return response()->json([
                    'message' => 'Unauthorized to reorder this media',
                ], 403);
            }
        }

        Media::setNewOrder($mediaIds);

        $this->clearOwnerResponseCache($first);

        return response()->json([
            'message' => 'Order updated',
        ]);
    }

    /**
     * Update editable metadata for a single media item. Currently the caption
     * (also used as the image alt text on public pages). Generic across any
     * owner that uses GalleryManager.
     */
    public function update(Request $request, int $mediaId): JsonResponse
    {
        try {
            $media = Media::findOrFail($mediaId);

            if (! $this->authorizeDelete($media)) {
                return response()->json([
                    'message' => 'Unauthorized to update this media',
                ], 403);
            }

            $validated = $request->validate([
                'caption' => ['nullable', 'string', 'max:1000'],
            ]);

            $caption = isset($validated['caption']) ? trim((string) $validated['caption']) : '';
            $media->setCustomProperty('caption', $caption !== '' ? $caption : null);
            $media->save();

            $this->clearOwnerResponseCache($media);

            return response()->json([
                'message' => 'Media updated',
                'data' => [
                    'id' => $media->id,
                    'caption' => $media->getCustomProperty('caption'),
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Media not found',
            ], 404);
        }
    }

    public function download(int $mediaId): StreamedResponse|BinaryFileResponse
    {
        $media = Media::findOrFail($mediaId);

        $disk = Storage::disk($media->disk);

        $downloadName = $media->name.($media->extension ? '.'.$media->extension : '');

        return $disk->download($media->getPathRelativeToRoot(), $downloadName);
    }

    /**
     * Bust the public response cache for the model that owns the given media.
     *
     * Spatie MediaLibrary delete only removes the Media row; it does not fire
     * the owning model's Eloquent events, so the ClearsResponseCache trait
     * never runs. Mirror that invalidation here for owners whose media is
     * rendered in cached public responses (mirrors Link::booted()).
     */
    private function clearOwnerResponseCache(Media $media): void
    {
        $tags = match ($media->model_type) {
            Hotel::class,
            RoomType::class,
            HotelTransferOption::class => ['hotels'],
            Brand::class => ['brands'],
            PromotionPost::class => ['brands', 'promotion-posts'],
            Partner::class => ['partners'],
            Guest::class => ['guests'],
            // Event media spans the gallery collection AND the poster_image /
            // visitor_eguide embedded in cached event payloads.
            Event::class => ['gallery', 'events'],
            // Project profile_image is embedded in every cached event payload
            // (EventResource) besides the project profile itself. OG media is
            // embedded in the cached website-settings og_pages payload
            // (PublicProjectController::ogPagesPayload).
            Project::class => ['projects', 'events', 'website-settings'],
            Post::class => ['blog-posts'],
            default => [],
        };

        if ($tags !== []) {
            ResponseCache::clear($tags);
        }
    }

    public function delete(int $mediaId): JsonResponse
    {
        try {
            $media = Media::findOrFail($mediaId);

            // Check authorization
            if (! $this->authorizeDelete($media)) {
                return response()->json([
                    'message' => 'Unauthorized to delete this media',
                ], 403);
            }

            $mediaInfo = [
                'media_id' => $media->id,
                'model_type' => $media->model_type,
                'model_id' => $media->model_id,
                'collection_name' => $media->collection_name,
                'file_name' => $media->file_name,
            ];

            $owner = ($media->model_type && $media->model_id && class_exists($media->model_type))
                ? ($media->model_type)::find($media->model_id)
                : null;

            $media->delete();

            // Media ops bypass the owner's Eloquent events; bust its public cache.
            $this->clearOwnerResponseCache($media);

            $properties = $mediaInfo;
            if ($owner !== null) {
                if ($projectId = $owner->project_id ?? $owner->event?->project_id) {
                    $properties['project_id'] = $projectId;
                }
            }

            $activity = activity()
                ->causedBy(auth()->user())
                ->event('media_deleted')
                ->withProperties($properties);

            if ($owner !== null) {
                $activity->performedOn($owner);
            }

            $activity->log("Media deleted: {$mediaInfo['file_name']}");

            return response()->json([
                'message' => 'Media deleted successfully',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Media not found',
            ], 404);

        } catch (\Exception $e) {
            logger()->error('Media deletion failed', [
                'error' => $e->getMessage(),
                'media_id' => $mediaId,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Failed to delete media',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Validate file against collection requirements
     */
    protected function validateFileForCollection(UploadedFile $file, $model, string $collection)
    {
        // Get collection configuration from model
        $collectionConfig = $this->getCollectionConfig($model, $collection);
        if (! $collectionConfig) {
            return response()->json([
                'message' => 'Invalid collection for this model',
            ], 422);
        }

        // Detect file type
        $fileType = $this->detectFileType($file);
        $fileConfig = self::FILE_TYPE_CONFIGS[$fileType] ?? null;

        if (! $fileConfig) {
            return response()->json([
                'message' => 'Unsupported file type',
                'supported_types' => array_keys(self::FILE_TYPE_CONFIGS),
            ], 422);
        }

        // Check if file type is allowed for this collection
        if (isset($collectionConfig['mime_types']) &&
            ! in_array($file->getMimeType(), $collectionConfig['mime_types'])) {
            return response()->json([
                'message' => 'File type not allowed for this collection',
                'allowed_types' => $collectionConfig['mime_types'],
                'provided_type' => $file->getMimeType(),
            ], 422);
        }

        // Check file size against collection or file type limits
        $maxSize = $collectionConfig['max_size'] ?? $fileConfig['max_size'];
        if ($file->getSize() > ($maxSize * 1024)) { // Convert KB to bytes
            return response()->json([
                'message' => 'File size exceeds limit',
                'max_size' => $maxSize.'KB',
                'file_size' => round($file->getSize() / 1024, 2).'KB',
            ], 422);
        }

        return true;
    }

    /**
     * Get collection configuration from model
     */
    protected function getCollectionConfig($model, string $collection): ?array
    {
        if (method_exists($model, 'getMediaCollections')) {
            $collections = $model->getMediaCollections();

            return $collections[$collection] ?? null;
        }

        // Fallback for known collections
        $defaults = [
            'profile_image' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
                'max_size' => 20480,
            ],
            'cover_image' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
                'max_size' => 20480,
            ],
        ];

        return $defaults[$collection] ?? null;
    }

    /**
     * Detect file type category
     */
    protected function detectFileType(UploadedFile $file): string
    {
        $mimeType = $file->getMimeType();

        foreach (self::FILE_TYPE_CONFIGS as $type => $config) {
            if (in_array($mimeType, $config['mime_types'])) {
                return $type;
            }
        }

        return 'unknown';
    }

    /**
     * Sanitize file name to prevent issues
     */
    protected function sanitizeFileName(string $fileName): string
    {
        $pathInfo = pathinfo($fileName);
        $name = Str::slug($pathInfo['filename']);
        $extension = $pathInfo['extension'] ?? '';

        return $name.($extension ? '.'.$extension : '');
    }

    /**
     * Serialize order_column assignment for a model+collection. Spatie reads the
     * current highest order then writes +1 without a DB lock, so two concurrent
     * uploads to the same collection can land on the same order_column. An atomic
     * cache lock around the insert keeps the read-then-write race-free.
     *
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    protected function withOrderLock(string $modelType, int $modelId, string $collection, callable $callback)
    {
        $key = 'media-order:'.md5($modelType.'|'.$modelId.'|'.$collection);

        return Cache::lock($key, 15)->block(10, $callback);
    }

    /**
     * Check authorization for upload
     */
    protected function authorizeUpload($model, string $modelType, int $modelId): bool
    {
        // For User model, users can only upload to their own model
        if ($modelType === 'App\Models\User' && $modelId !== auth()->id()) {
            return auth()->user()->can('admin.media');
        }

        // Add authorization logic for other models here
        // For example, check if user owns the model or has specific permissions
        if (method_exists($model, 'user_id') && property_exists($model, 'user_id')) {
            if ($model->user_id !== auth()->id()) {
                return auth()->user()->can('manage.'.class_basename($model));
            }
        }

        return true;
    }

    /**
     * Check authorization for delete
     */
    protected function authorizeDelete(Media $media): bool
    {
        // Check if user uploaded this media
        if ($media->hasCustomProperty('uploaded_by') &&
            $media->getCustomProperty('uploaded_by') === auth()->id()) {
            return true;
        }

        // For User model media
        if ($media->model_type === 'App\Models\User' && $media->model_id === auth()->id()) {
            return true;
        }

        // Users who can manage (update) the owning model may manage its media.
        $manageAbility = $this->mediaOwnerManageAbility($media);
        if ($manageAbility !== null && auth()->user()->can($manageAbility)) {
            return true;
        }

        // Global media admin can manage any media
        return auth()->user()->can('admin.media');
    }

    /**
     * The permission that lets a user manage (reorder/delete) the media owned
     * by a given model. Mirrors the per-resource update gate used by the
     * resource's own routes (e.g. hotels.update guards hotel media endpoints).
     */
    protected function mediaOwnerManageAbility(Media $media): ?string
    {
        return match ($media->model_type) {
            Hotel::class, HotelTransferOption::class => 'hotels.update',
            RoomType::class => 'room_types.update',
            Brand::class, PromotionPost::class => 'brands.update',
            Partner::class => 'partners.update',
            Guest::class => 'guests.update',
            Event::class => 'events.update',
            Post::class => 'posts.update',
            default => null,
        };
    }

    /**
     * Check if collection accepts single file only
     */
    protected function isSingleFileCollection($model, string $collection): bool
    {
        $config = $this->getCollectionConfig($model, $collection);

        return $config['single_file'] ?? true;
    }

    /**
     * Get media response with smart conversion handling
     */
    protected function getMediaResponse(Media $media, $model, string $collection): array
    {
        $fileType = $media->getCustomProperty('file_type', 'unknown');
        $fileTypeConfig = self::FILE_TYPE_CONFIGS[$fileType] ?? null;

        $response = [
            'id' => $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'size_human' => $this->formatFileSize($media->size),
            'url' => $media->getUrl(),
            'collection_name' => $media->collection_name,
            'file_type' => $fileType,
            'supports_conversions' => $fileTypeConfig['supports_conversions'] ?? false,
            'custom_properties' => $media->custom_properties,
        ];

        // Add conversion URLs only if file supports conversions (images)
        if ($fileTypeConfig && $fileTypeConfig['supports_conversions']) {
            $conversions = [];

            // Get original file URL
            $conversions['original'] = $media->getUrl();

            // Get conversion URLs based on collection type
            if ($collection === 'profile_image' || $collection === 'cover_image') {
                $conversions['lqip'] = $media->getUrl('lqip');
                $conversions['sm'] = $media->getUrl('sm');
                $conversions['md'] = $media->getUrl('md');
                $conversions['lg'] = $media->getUrl('lg');
                $conversions['xl'] = $media->getUrl('xl');
            } elseif ($collection === 'featured_image') {
                $conversions['lqip'] = $media->getUrl('lqip');
                $conversions['sm'] = $media->getUrl('sm');
                $conversions['md'] = $media->getUrl('md');
                $conversions['lg'] = $media->getUrl('lg');
                $conversions['xl'] = $media->getUrl('xl');
            } elseif ($collection === 'content_images') {
                $conversions['sm'] = $media->getUrl('sm');
                $conversions['md'] = $media->getUrl('md');
                $conversions['lg'] = $media->getUrl('lg');
                $conversions['xl'] = $media->getUrl('xl');
            }

            $response['conversions'] = $conversions;
        }

        return $response;
    }

    /**
     * Format file size in human readable format
     */
    protected function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }

    /**
     * Extract file dimensions for images and videos
     */
    protected function extractFileDimensions(UploadedFile $file): ?array
    {
        $mimeType = $file->getMimeType();
        $fileType = $this->detectFileType($file);

        try {
            if ($fileType === 'images') {
                return $this->getImageDimensions($file);
            } elseif ($fileType === 'videos') {
                return $this->getVideoDimensions($file);
            }
        } catch (\Exception $e) {
            logger()->warning('Failed to extract file dimensions', [
                'file' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Get image dimensions using getimagesize
     */
    protected function getImageDimensions(UploadedFile $file): ?array
    {
        $imageInfo = @getimagesize($file->getPathname());

        if ($imageInfo === false) {
            return null;
        }

        $dimensions = [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
        ];

        // Add additional image info if available
        if (isset($imageInfo['mime'])) {
            $dimensions['detected_mime'] = $imageInfo['mime'];
        }

        if (isset($imageInfo['bits'])) {
            $dimensions['bits_per_channel'] = $imageInfo['bits'];
        }

        if (isset($imageInfo['channels'])) {
            $dimensions['channels'] = $imageInfo['channels'];
        }

        // Calculate aspect ratio
        if ($dimensions['height'] > 0) {
            $dimensions['aspect_ratio'] = round($dimensions['width'] / $dimensions['height'], 2);
        }

        // Determine orientation
        if ($dimensions['width'] > $dimensions['height']) {
            $dimensions['orientation'] = 'landscape';
        } elseif ($dimensions['width'] < $dimensions['height']) {
            $dimensions['orientation'] = 'portrait';
        } else {
            $dimensions['orientation'] = 'square';
        }

        return $dimensions;
    }

    /**
     * Get video dimensions using ffprobe if available
     */
    protected function getVideoDimensions(UploadedFile $file): ?array
    {
        // Check if ffprobe is available
        if (! $this->isFFProbeAvailable()) {
            return $this->getVideoBasicInfo($file);
        }

        $filePath = $file->getPathname();

        // Use ffprobe to get video information
        $command = sprintf(
            'ffprobe -v quiet -print_format json -show_streams "%s"',
            escapeshellarg($filePath)
        );

        $output = shell_exec($command);

        if (! $output) {
            return $this->getVideoBasicInfo($file);
        }

        $data = json_decode($output, true);

        if (! isset($data['streams'])) {
            return $this->getVideoBasicInfo($file);
        }

        // Find video stream
        foreach ($data['streams'] as $stream) {
            if ($stream['codec_type'] === 'video' && isset($stream['width']) && isset($stream['height'])) {
                $dimensions = [
                    'width' => (int) $stream['width'],
                    'height' => (int) $stream['height'],
                ];

                // Add additional video info
                if (isset($stream['duration'])) {
                    $dimensions['duration'] = (float) $stream['duration'];
                    $dimensions['duration_formatted'] = $this->formatDuration($dimensions['duration']);
                }

                if (isset($stream['bit_rate'])) {
                    $dimensions['bitrate'] = (int) $stream['bit_rate'];
                }

                if (isset($stream['codec_name'])) {
                    $dimensions['codec'] = $stream['codec_name'];
                }

                if (isset($stream['r_frame_rate'])) {
                    $dimensions['frame_rate'] = $stream['r_frame_rate'];
                }

                // Calculate aspect ratio
                if ($dimensions['height'] > 0) {
                    $dimensions['aspect_ratio'] = round($dimensions['width'] / $dimensions['height'], 2);
                }

                return $dimensions;
            }
        }

        return $this->getVideoBasicInfo($file);
    }

    /**
     * Get basic video info without ffprobe
     */
    protected function getVideoBasicInfo(UploadedFile $file): array
    {
        return [
            'width' => null,
            'height' => null,
            'file_size' => $file->getSize(),
            'note' => 'Video dimensions not available - ffprobe not installed',
        ];
    }

    /**
     * Check if ffprobe is available
     */
    protected function isFFProbeAvailable(): bool
    {
        $output = shell_exec('which ffprobe 2>/dev/null');

        return ! empty($output);
    }

    /**
     * Format duration in human readable format
     */
    protected function formatDuration(float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            return sprintf('%02d:%02d', $minutes, $seconds);
        }
    }

    /**
     * Handle temporary media upload for models that don't exist yet
     */
    protected function handleTemporaryMediaUpload($file, string $collection): JsonResponse
    {
        $folder = uniqid('tmp-media-', true);
        $filename = $file->getClientOriginalName();

        // Store file in temporary storage
        $path = Storage::disk('local')->putFileAs(
            "tmp/uploads/{$folder}",
            $file,
            $filename
        );

        // Cap + compress the original before it is later moved into a collection.
        $absolutePath = Storage::disk('local')->path($path);
        ImageOptimizer::compressInPlace($absolutePath);

        // Store metadata
        Storage::disk('local')->put(
            "tmp/uploads/{$folder}/metadata.json",
            json_encode([
                'original_name' => $filename,
                'mime_type' => $file->getMimeType(),
                'size' => @filesize($absolutePath) ?: $file->getSize(),
                'collection' => $collection,
                'uploaded_at' => now()->toISOString(),
            ])
        );

        // Generate a temporary URL for the uploaded file
        $tempUrl = url("/api/tmp-media/{$folder}");

        return response()->json([
            'message' => 'File uploaded to temporary storage',
            'media' => [
                'id' => null,
                'name' => pathinfo($filename, PATHINFO_FILENAME),
                'file_name' => $filename,
                'url' => $tempUrl,
                'temp_folder' => $folder,
                'is_temporary' => true,
            ],
        ]);
    }

    /**
     * Delete temporary media file
     */
    public function deleteTempMedia(string $folder): JsonResponse
    {
        if (! Str::startsWith($folder, 'tmp-media-')) {
            return response()->json(['error' => 'Invalid folder'], 400);
        }

        $folderPath = "tmp/uploads/{$folder}";

        if (! Storage::disk('local')->exists($folderPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        try {
            Storage::disk('local')->deleteDirectory($folderPath);

            return response()->json(['message' => 'Temporary media deleted successfully']);
        } catch (\Exception $e) {
            logger()->warning('Failed to delete temporary media', [
                'folder' => $folder,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to delete temporary media'], 500);
        }
    }

    /**
     * Serve temporary media file
     */
    public function serveTempMedia(string $folder)
    {
        if (! Str::startsWith($folder, 'tmp-media-')) {
            return response()->json(['error' => 'Invalid folder'], 400);
        }

        $metadataPath = "tmp/uploads/{$folder}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        $filePath = "tmp/uploads/{$folder}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return response()->file(
            Storage::disk('local')->path($filePath),
            [
                'Content-Type' => $metadata['mime_type'],
                'Content-Disposition' => 'inline; filename="'.$metadata['original_name'].'"',
            ]
        );
    }
}
