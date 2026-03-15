<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class JobProgressController extends Controller
{
    public function show(string $jobId): JsonResponse
    {
        $data = Cache::get("job:{$jobId}");

        if (! $data) {
            return response()->json(['message' => 'Job not found'], 404);
        }

        return response()->json($data);
    }

    public function download(string $jobId): BinaryFileResponse|JsonResponse
    {
        $data = Cache::get("job:{$jobId}");

        if (! $data || ($data['status'] ?? null) !== 'completed') {
            return response()->json(['message' => 'File not ready'], 404);
        }

        $path = $data['download_path'] ?? null;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $fullPath = Storage::disk('local')->path($path);
        $filename = $data['download_filename'] ?? basename($path);

        return response()->download($fullPath, $filename);
    }
}
