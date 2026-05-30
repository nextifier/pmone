<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Spatie\ResponseCache\Facades\ResponseCache;

class ResponseCacheController extends Controller
{
    /**
     * Flush the entire spatie/laravel-responsecache store.
     *
     * Used after a deploy that changes the public API response shape or logic
     * (e.g. a new sort/field), since a code deploy fires no Eloquent events and
     * therefore never triggers the automatic tag invalidation. Lets an admin
     * refresh the public cache without SSH.
     */
    public function clear(): JsonResponse
    {
        ResponseCache::clear();

        return response()->json([
            'message' => 'Response cache cleared successfully.',
        ]);
    }
}
