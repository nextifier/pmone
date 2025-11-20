<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemporaryUploadController extends Controller
{
    /**
     * Upload file to temporary storage.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // 20MB max
        ]);

        $file = $request->file('file');
        $folder = uniqid('tmp-', true);
        $filename = $file->getClientOriginalName();

        // Store file in temporary storage
        $path = Storage::disk('local')->putFileAs(
            "tmp/uploads/{$folder}",
            $file,
            $filename
        );

        // Store metadata
        Storage::disk('local')->put(
            "tmp/uploads/{$folder}/metadata.json",
            json_encode([
                'original_name' => $filename,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_at' => now()->toISOString(),
            ])
        );

        return response()->json(['folder' => $folder], 200);
    }

    /**
     * Revert/delete uploaded file from temporary storage.
     */
    public function revert(Request $request)
    {
        $folder = $request->getContent();

        if (! $folder || ! Str::startsWith($folder, 'tmp-')) {
            return response()->json(['error' => 'Invalid folder'], 400);
        }

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$folder}");

        return response()->json([], 200);
    }

    /**
     * Load file from temporary storage.
     */
    public function load(Request $request)
    {
        $folder = $request->query('folder');

        if (! $folder || ! Str::startsWith($folder, 'tmp-')) {
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

    /**
     * Get metadata for uploaded file.
     */
    public function metadata(Request $request)
    {
        $folder = $request->query('folder');

        if (! $folder || ! Str::startsWith($folder, 'tmp-')) {
            return response()->json(['error' => 'Invalid folder'], 400);
        }

        $metadataPath = "tmp/uploads/{$folder}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);

        return response()->json($metadata, 200);
    }
}
