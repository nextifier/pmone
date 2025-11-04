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
        // Support both old format (link_id) and new format (clickable_type/clickable_id)
        if ($request->has('link_id')) {
            $request->validate([
                'link_id' => 'required|exists:links,id',
            ]);

            $link = Link::findOrFail($request->link_id);
            TrackingHelper::trackClick($request, $link, $request->link_label);
        } else {
            $request->validate([
                'clickable_type' => 'required|string',
                'clickable_id' => 'required|integer',
                'link_label' => 'nullable|string',
            ]);

            // Create click record directly with polymorphic data
            \App\Models\Click::create([
                'clickable_type' => $request->clickable_type,
                'clickable_id' => $request->clickable_id,
                'clicker_id' => auth()->id(),
                'link_label' => $request->link_label,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
                'clicked_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Click tracked successfully',
        ], 201);
    }

    /**
     * Track profile visit
     */
    public function trackProfileVisit(Request $request): JsonResponse
    {
        $request->validate([
            'visitable_type' => 'required|string',
            'visitable_id' => 'required|integer',
        ]);

        \App\Models\Visit::create([
            'visitable_type' => $request->visitable_type,
            'visitable_id' => $request->visitable_id,
            'visitor_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'visited_at' => now(),
        ]);

        return response()->json([
            'message' => 'Visit tracked successfully',
        ], 201);
    }
}
