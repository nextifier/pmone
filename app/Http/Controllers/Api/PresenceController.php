<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PresenceHeartbeatRequest;
use App\Models\UserPageView;
use Illuminate\Http\Response;

class PresenceController extends Controller
{
    /**
     * Record a presence heartbeat from the admin SPA. Always refreshes the
     * user's last-seen + current page; a navigation heartbeat also appends a
     * page-view row for the analytics history.
     */
    public function heartbeat(PresenceHeartbeatRequest $request): Response
    {
        $user = $request->user();

        // Never trust the client path: keep only the path component (no query
        // string / hash) and cap it to the column width.
        $path = mb_substr(parse_url($request->string('path')->value(), PHP_URL_PATH) ?: '/', 0, 255);
        $title = mb_substr(trim((string) $request->input('title')), 0, 255) ?: null;

        $user->update([
            'last_seen' => now(),
            'last_page' => $path,
            'last_page_title' => $title,
        ]);

        if ($request->boolean('navigation')) {
            UserPageView::create([
                'user_id' => $user->id,
                'path' => $path,
                'title' => $title,
                'visited_at' => now(),
            ]);
        }

        return response()->noContent();
    }
}
