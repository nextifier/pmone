<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController extends Controller
{
    /**
     * Supported file types and their configurations
     */
    protected const FILE_TYPE_CONFIGS = [
        'images' => [
            'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'],
            'max_size' => 10240,
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
            'max_size' => 10240, // 10MB
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

            // Upload the file with proper naming
            $mediaAdder = $model->addMediaFromRequest('file');

            // Set custom file name to avoid collisions
            $sanitizedName = $this->sanitizeFileName($file->getClientOriginalName());
            $mediaAdder->usingName($sanitizedName);

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

            $media = $mediaAdder->toMediaCollection($collection);

            // Get media URLs including conversions
            $mediaUrls = $this->getMediaResponse($media, $model, $collection);

            return response()->json([
                'message' => 'File uploaded successfully',
                'media' => $mediaUrls,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Resource not found',
            ], 404);

        } catch (\Spatie\MediaLibrary\MediaCollections\Exceptions\InvalidCollection $e) {
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
                    // Upload the file with proper naming
                    $mediaAdder = $model->addMediaFromRequest('files', $index);

                    // Set custom file name to avoid collisions
                    $sanitizedName = $this->sanitizeFileName($file->getClientOriginalName());
                    $mediaAdder->usingName($sanitizedName);

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

                    $media = $mediaAdder->toMediaCollection($collection);

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

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
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

            $media->delete();

            return response()->json([
                'message' => 'Media deleted successfully',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
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
                'max_size' => $maxSize . 'KB',
                'file_size' => round($file->getSize() / 1024, 2) . 'KB',
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
                'max_size' => 10240,
            ],
            'cover_image' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
                'max_size' => 10240,
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

        return $name . ($extension ? '.' . $extension : '');
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
                return auth()->user()->can('manage.' . class_basename($model));
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

        // Admin can delete any media
        return auth()->user()->can('admin.media');
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
            if ($collection === 'profile_image') {
                $conversions['sm'] = $media->getUrl('sm');
                $conversions['md'] = $media->getUrl('md');
                $conversions['lg'] = $media->getUrl('lg');
                $conversions['xl'] = $media->getUrl('xl');
            } elseif ($collection === 'cover_image') {
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
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
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
}
