<?php

namespace App\Helpers;

use App\Models\Click;
use App\Models\Visit;
use Illuminate\Http\Request;

class TrackingHelper
{
    public static function trackVisit(Request $request, $visitable): Visit
    {
        return Visit::create([
            'visitable_type' => get_class($visitable),
            'visitable_id' => $visitable->id,
            'visitor_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'visited_at' => now(),
        ]);
    }

    public static function trackClick(Request $request, $clickable): Click
    {
        return Click::create([
            'clickable_type' => get_class($clickable),
            'clickable_id' => $clickable->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'clicked_at' => now(),
        ]);
    }

    public static function getRequestInfo(Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
        ];
    }
}
