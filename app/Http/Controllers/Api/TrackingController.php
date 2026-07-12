<?php

namespace App\Http\Controllers\Api;

use App\Helpers\TrackingHelper;
use App\Http\Controllers\Controller;
use App\Models\BrandEvent;
use App\Models\Click;
use App\Models\Link;
use App\Models\LinkPage;
use App\Models\LinkPageBanner;
use App\Models\LinkPageItem;
use App\Models\Project;
use App\Models\ProjectBanner;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class TrackingController extends Controller
{
    private const TRACKABLE_TYPES = [
        Project::class,
        User::class,
        LinkPage::class,
        LinkPageItem::class,
        LinkPageBanner::class,
        Link::class,
        ShortLink::class,
        BrandEvent::class,
        ProjectBanner::class,
    ];

    /**
     * Longest stored `referer`. Callers control this header entirely, and the
     * column is otherwise unbounded (`text`), so it is capped rather than
     * dropped - the admin analytics (blog/short-link referer breakdowns)
     * still group/display it, and real referer URLs are always far shorter.
     */
    private const MAX_REFERER_LENGTH = 2048;

    /**
     * Track link click
     */
    public function trackLinkClick(Request $request): JsonResponse
    {
        if ($this->isBot($request->userAgent())) {
            return response()->json(['message' => 'Bot traffic ignored'], 204);
        }

        // Support both old format (link_id) and new format (clickable_type/clickable_id)
        if ($request->has('link_id')) {
            $request->validate([
                'link_id' => 'required|exists:links,id',
            ]);

            $link = Link::findOrFail($request->link_id);
            TrackingHelper::trackClick($request, $link, $request->link_label);
        } else {
            $request->validate([
                'clickable_type' => ['required', 'string', 'in:'.implode(',', self::TRACKABLE_TYPES)],
                'link_label' => 'nullable|string|max:255',
            ]);

            // clickable_type is now known to be an allowlisted model class, so
            // it is safe to resolve its table for an existence check - without
            // this, anyone could forge clicks/visits rows for any (even
            // nonexistent) entity ID.
            $request->validate([
                'clickable_id' => [
                    'required', 'integer', 'min:1',
                    $this->existsRuleFor($request->string('clickable_type')->toString()),
                ],
            ]);

            Click::create([
                'clickable_type' => $request->clickable_type,
                'clickable_id' => $request->clickable_id,
                'clicker_id' => auth()->id(),
                'link_label' => $request->link_label,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $this->boundedReferer($request),
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
        if ($this->isBot($request->userAgent())) {
            return response()->json(['message' => 'Bot traffic ignored'], 204);
        }

        $request->validate([
            'visitable_type' => ['required', 'string', 'in:'.implode(',', self::TRACKABLE_TYPES)],
        ]);

        $request->validate([
            'visitable_id' => [
                'required', 'integer', 'min:1',
                $this->existsRuleFor($request->string('visitable_type')->toString()),
            ],
        ]);

        Visit::create([
            'visitable_type' => $request->visitable_type,
            'visitable_id' => $request->visitable_id,
            'visitor_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $this->boundedReferer($request),
            'visited_at' => now(),
        ]);

        return response()->json([
            'message' => 'Visit tracked successfully',
        ], 201);
    }

    /**
     * `exists` rule scoped to the table of an already-allowlisted trackable
     * model class (validated by the caller's `in:` rule before this runs).
     */
    private function existsRuleFor(string $modelClass): Exists
    {
        $model = new $modelClass;

        return Rule::exists($model->getTable(), $model->getKeyName());
    }

    /**
     * Truncate the caller-controlled `referer` header to a sane bound.
     */
    private function boundedReferer(Request $request): ?string
    {
        $referer = $request->header('referer');

        return $referer === null ? null : mb_substr($referer, 0, self::MAX_REFERER_LENGTH);
    }

    /**
     * Detect well-known crawlers and link-preview bots so analytics reflect
     * real human engagement only.
     */
    private function isBot(?string $userAgent): bool
    {
        if (! $userAgent) {
            return false;
        }

        return (bool) preg_match(
            '/bot|crawler|spider|slurp|facebookexternalhit|whatsapp|telegram|preview|fetch|monitor|headlesschrome|lighthouse/i',
            $userAgent
        );
    }
}
