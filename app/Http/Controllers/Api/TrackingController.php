<?php

namespace App\Http\Controllers\Api;

use App\Helpers\TrackingHelper;
use App\Http\Controllers\Controller;
use App\Models\Link;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    /**
     * Track link click
     */
    public function trackLinkClick(Request $request): JsonResponse
    {
        $request->validate([
            'link_id' => 'required|exists:links,id',
        ]);

        $link = Link::findOrFail($request->link_id);

        TrackingHelper::trackClick($request, $link);

        return response()->json([
            'message' => 'Click tracked successfully',
        ]);
    }
}
