<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class UploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:10240'], // 10MB max
            'collection' => ['nullable', 'string', 'max:255'],
            'model_type' => ['nullable', 'string'],
            'model_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $collection = $request->input('collection', 'default');
            $modelType = $request->input('model_type');
            $modelId = $request->input('model_id');

            // If model is specified, attach to model
            if ($modelType && $modelId) {
                $model = app($modelType)->findOrFail($modelId);

                $media = $model->addMediaFromRequest('file')
                    ->toMediaCollection($collection);
            } else {
                // Upload as temporary file
                $path = $file->store('temp', 'public');

                return response()->json([
                    'message' => 'File uploaded successfully',
                    'path' => $path,
                    'url' => Storage::disk('public')->url($path),
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }

            return response()->json([
                'message' => 'File uploaded successfully',
                'media' => [
                    'id' => $media->id,
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'url' => $media->getUrl(),
                    'collection_name' => $media->collection_name,
                ],
            ]);
        } catch (\Exception $e) {
            logger()->error('File upload failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'File upload failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadProfileImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'profile_image' => ['required', 'image', 'max:5120'], // 5MB max for images
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();

            // Remove existing profile image
            $user->clearMediaCollection('profile_image');

            // Add new profile image
            $media = $user->addMediaFromRequest('profile_image')
                ->toMediaCollection('profile_image');

            return response()->json([
                'message' => 'Profile image uploaded successfully',
                'profile_image' => [
                    'id' => $media->id,
                    'url' => $media->getUrl(),
                    'thumb_url' => $media->getUrl('profile_thumb'),
                    'medium_url' => $media->getUrl('profile_medium'),
                    'large_url' => $media->getUrl('profile_large'),
                ],
            ]);
        } catch (\Exception $e) {
            logger()->error('Profile image upload failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Profile image upload failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadCoverImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cover_image' => ['required', 'image', 'max:8192'], // 8MB max for cover images
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();

            // Remove existing cover image
            $user->clearMediaCollection('cover_image');

            // Add new cover image
            $media = $user->addMediaFromRequest('cover_image')
                ->toMediaCollection('cover_image');

            return response()->json([
                'message' => 'Cover image uploaded successfully',
                'cover_image' => [
                    'id' => $media->id,
                    'url' => $media->getUrl(),
                    'thumb_url' => $media->getUrl('cover_thumb'),
                    'medium_url' => $media->getUrl('cover_medium'),
                    'large_url' => $media->getUrl('cover_large'),
                ],
            ]);
        } catch (\Exception $e) {
            logger()->error('Cover image upload failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Cover image upload failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteMedia(Request $request, int $mediaId): JsonResponse
    {
        try {
            $media = Media::findOrFail($mediaId);

            // Check if user owns this media or has permission
            if ($media->model_type === 'App\Models\User' && $media->model_id !== auth()->id()) {
                if (! auth()->user()->can('admin.media')) {
                    return response()->json([
                        'message' => 'Unauthorized to delete this media',
                    ], 403);
                }
            }

            $media->delete();

            return response()->json([
                'message' => 'Media deleted successfully',
            ]);
        } catch (\Exception $e) {
            logger()->error('Media deletion failed', [
                'error' => $e->getMessage(),
                'media_id' => $mediaId,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Failed to delete media',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
