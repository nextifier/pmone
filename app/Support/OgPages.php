<?php

namespace App\Support;

/**
 * Canonical Open Graph page keys shared with the pmone-events websites.
 * Keys are kebab-case URL-path-like identifiers; the events monorepo maps
 * its content-store keys (bookSpace, ticket) onto these.
 */
class OgPages
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** @var list<string> */
    public const KEYS = [
        'home',
        'brands',
        'rundown',
        'programs',
        'contact',
        'book-space',
        'tickets',
        'gallery',
        'partners',
        'winner',
        'guests',
    ];

    /**
     * Media collection name on Project for a page key (og_image_book_space).
     */
    public static function collectionFor(string $key): string
    {
        return 'og_image_'.str_replace('-', '_', $key);
    }

    /**
     * Public website path for a page key (home lives at the root).
     */
    public static function pathFor(string $key): string
    {
        return $key === 'home' ? '/' : '/'.$key;
    }
}
