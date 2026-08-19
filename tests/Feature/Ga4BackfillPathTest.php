<?php

use App\Console\Commands\BackfillGoogleAnalyticsViews;

/**
 * The path normaliser decides which GA4 rows become article views, so a quiet
 * mistake here would silently drop or misattribute years of history.
 */
function slugFor(string $path): ?string
{
    $command = new BackfillGoogleAnalyticsViews;
    $method = new ReflectionMethod($command, 'slugFromPath');

    return $method->invoke($command, $path);
}

it('maps an article path to its slug', function (string $path, ?string $expected) {
    expect(slugFor($path))->toBe($expected);
})->with([
    'plain' => ['/news/urutan-film-marvel', 'urutan-film-marvel'],
    'indonesian locale' => ['/id/news/urutan-film-marvel', 'urutan-film-marvel'],
    'japanese locale' => ['/ja/news/urutan-film-marvel', 'urutan-film-marvel'],
    'regional locale' => ['/zh-CN/news/urutan-film-marvel', 'urutan-film-marvel'],
    'query string' => ['/news/urutan-film-marvel?hl=id-ID', 'urutan-film-marvel'],
    'trailing slash' => ['/news/urutan-film-marvel/', 'urutan-film-marvel'],
    'locale and query string' => ['/id/news/urutan-film-marvel?hl=id-ID', 'urutan-film-marvel'],

    // Everything that is not a single article must be ignored, not guessed at.
    'listing' => ['/news', null],
    'listing with locale' => ['/id/news', null],
    'home' => ['/', null],
    'other section' => ['/brands/some-brand', null],
    'deeper path' => ['/news/category/movies', null],
]);
