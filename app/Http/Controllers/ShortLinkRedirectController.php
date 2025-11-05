<?php

namespace App\Http\Controllers;

use App\Helpers\CrawlerDetectionHelper;
use App\Helpers\TrackingHelper;
use App\Models\ShortLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ShortLinkRedirectController extends Controller
{
    /**
     * Handle short link redirect with OpenGraph meta tags for crawlers.
     */
    public function __invoke(Request $request, string $slug): View|RedirectResponse
    {
        // Find active short link by slug
        $shortLink = ShortLink::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $userAgent = $request->userAgent();
        $isCrawler = CrawlerDetectionHelper::isCrawler($userAgent);

        // Log the access for analytics
        try {
            TrackingHelper::trackClick($request, $shortLink);
        } catch (\Throwable $e) {
            // Don't fail the redirect if tracking fails
            Log::warning('Failed to track short link click', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);
        }

        // If it's a crawler, return view with OG meta tags
        if ($isCrawler) {
            $crawlerName = CrawlerDetectionHelper::getCrawlerName($userAgent);

            Log::info('Serving OG meta tags to crawler', [
                'slug' => $slug,
                'crawler' => $crawlerName,
                'destination' => $shortLink->destination_url,
            ]);

            return view('short-link.redirect', [
                'shortLink' => $shortLink,
                'ogTitle' => $shortLink->og_title ?? $shortLink->slug,
                'ogDescription' => $shortLink->og_description ?? 'Click to visit this link',
                'ogImage' => $shortLink->og_image,
                'ogType' => $shortLink->og_type ?? 'website',
                'destinationUrl' => $shortLink->destination_url,
            ]);
        }

        // For regular users, do instant redirect
        return redirect($shortLink->destination_url, 302);
    }
}
