<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ImportProgressController extends Controller
{
    public function show(string $importId): JsonResponse
    {
        $data = Cache::get("import:{$importId}");

        if (! $data) {
            return response()->json(['message' => 'Import not found'], 404);
        }

        return response()->json($data);
    }
}
