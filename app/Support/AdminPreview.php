<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Http\Request;

/**
 * Staff bypasses for the public event websites, driven by a query parameter.
 *
 * Each parameter lets an admin see (or buy) something the public cannot, so
 * that content can be staged and checkout smoke-tested on production before
 * sales open. The parameters are deliberately plain and guessable - a product
 * decision, taken knowing that anyone who types `?force_show_brands=1` sees
 * hidden brands. Every read funnels through wants(), so adding a shared secret
 * later is a one-line change here rather than a hunt across eleven call sites.
 */
final class AdminPreview
{
    public const BRANDS = 'force_show_brands';

    public const RUNDOWN = 'force_show_rundown';

    public const CHECKOUT = 'force_checkout_ticket';

    /**
     * Whether this event's brands may render for this request.
     */
    public static function brands(Request $request, ?Event $event): bool
    {
        // wants() FIRST, deliberately: it is what marks the response
        // do-not-cache, and `||` short-circuits. Testing the column first would
        // skip that marking on every event whose brands are still visible,
        // which is exactly the case that mints the most cache entries.
        $forced = self::wants($request, self::BRANDS);

        return $event !== null && ($forced || (bool) $event->brands_public_visible);
    }

    /**
     * Whether this event's rundown may render for this request.
     */
    public static function rundown(Request $request, ?Event $event): bool
    {
        $forced = self::wants($request, self::RUNDOWN);

        return $event !== null && ($forced || (bool) $event->rundown_public_visible);
    }

    /**
     * Query-level variant for the resolvers that PICK an event rather than
     * receive one: activeBrand()'s conjunction + previous-edition steps,
     * activeBrandSitemapSlugs(), and activeBrands()' fallback borrow.
     *
     * Always qualifies with `events.` - every call site joins another table
     * (event_conjunctions, brand_event) where a bare column is ambiguous.
     *
     * @template TQuery
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function brandsVisibleScope($query, Request $request)
    {
        if (self::wants($request, self::BRANDS)) {
            return $query;
        }

        return $query->where('events.brands_public_visible', true);
    }

    /**
     * Read a bypass flag from the QUERY STRING only, never the request body.
     *
     * The event websites proxy the browser's body verbatim, so a body field
     * would be attacker-controllable in exactly the way
     * StorePublicTicketOrderRequest's docblock warns about.
     *
     * A forced response is also never stored: spatie's hasher keys on the full
     * query string, so without this an anonymous caller could mint unbounded
     * cache entries (`=1`, `=true`, `=1&_=2` ...) each holding an hour of
     * content an admin deliberately hid. It also keeps the preview live, which
     * is the only reason to have a preview parameter at all.
     */
    public static function wants(Request $request, string $param): bool
    {
        if (! $request->query->has($param)) {
            return false;
        }

        $raw = $request->query($param);

        // A bare `?force_show_brands` arrives as an empty string, which
        // FILTER_VALIDATE_BOOLEAN reads as false. Mirror useForceShow() on the
        // event websites: bare, `=1` and `=true` are all truthy.
        $isTruthy = is_array($raw)
            ? false
            : ($raw === null || $raw === '' || $request->boolean($param));

        if (! $isTruthy) {
            return false;
        }

        $request->attributes->add(['responsecache.doNotCache' => true]);

        return true;
    }
}
