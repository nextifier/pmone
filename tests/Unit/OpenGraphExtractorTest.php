<?php

use App\Services\OpenGraph\OpenGraphExtractor;
use Illuminate\Support\Facades\Http;

test('extracts og meta tags from valid html', function () {
    $html = <<<'HTML'
    <!DOCTYPE html>
    <html>
    <head>
        <meta property="og:title" content="Test Article Title" />
        <meta property="og:description" content="This is a test description" />
        <meta property="og:image" content="https://example.com/image.jpg" />
        <meta property="og:type" content="article" />
    </head>
    <body>Content</body>
    </html>
    HTML;

    Http::fake([
        'example.com/*' => Http::response($html, 200, ['Content-Type' => 'text/html']),
    ]);

    $extractor = new OpenGraphExtractor;
    $metadata = $extractor->extract('https://example.com/article');

    expect($metadata['og_title'])->toBe('Test Article Title');
    expect($metadata['og_description'])->toBe('This is a test description');
    expect($metadata['og_image'])->toBe('https://example.com/image.jpg');
    expect($metadata['og_type'])->toBe('article');
});

test('returns default metadata when url is invalid', function () {
    $extractor = new OpenGraphExtractor;
    $metadata = $extractor->extract('not-a-valid-url');

    expect($metadata['og_title'])->toBeNull();
    expect($metadata['og_description'])->toBeNull();
    expect($metadata['og_image'])->toBeNull();
    expect($metadata['og_type'])->toBe('website');
});

test('returns default metadata when url is not accessible', function () {
    Http::fake([
        'example.com/*' => Http::response(null, 404),
    ]);

    $extractor = new OpenGraphExtractor;
    $metadata = $extractor->extract('https://example.com/not-found');

    expect($metadata['og_title'])->toBeNull();
    expect($metadata['og_description'])->toBeNull();
    expect($metadata['og_image'])->toBeNull();
    expect($metadata['og_type'])->toBe('website');
});

test('returns default metadata when response is not html', function () {
    Http::fake([
        'example.com/*' => Http::response('{"json": "data"}', 200, ['Content-Type' => 'application/json']),
    ]);

    $extractor = new OpenGraphExtractor;
    $metadata = $extractor->extract('https://example.com/api/data');

    expect($metadata['og_title'])->toBeNull();
    expect($metadata['og_description'])->toBeNull();
    expect($metadata['og_image'])->toBeNull();
    expect($metadata['og_type'])->toBe('website');
});

test('falls back to title tag when og:title is missing', function () {
    $html = <<<'HTML'
    <!DOCTYPE html>
    <html>
    <head>
        <title>Fallback Title</title>
        <meta name="description" content="Fallback description" />
    </head>
    <body>Content</body>
    </html>
    HTML;

    Http::fake([
        'example.com/*' => Http::response($html, 200, ['Content-Type' => 'text/html']),
    ]);

    $extractor = new OpenGraphExtractor;
    $metadata = $extractor->extract('https://example.com/page');

    expect($metadata['og_title'])->toBe('Fallback Title');
    expect($metadata['og_description'])->toBe('Fallback description');
});

test('converts relative image urls to absolute', function () {
    $html = <<<'HTML'
    <!DOCTYPE html>
    <html>
    <head>
        <meta property="og:image" content="/images/photo.jpg" />
    </head>
    <body>Content</body>
    </html>
    HTML;

    Http::fake([
        'example.com/*' => Http::response($html, 200, ['Content-Type' => 'text/html']),
    ]);

    $extractor = new OpenGraphExtractor;
    $metadata = $extractor->extract('https://example.com/article');

    expect($metadata['og_image'])->toBe('https://example.com/images/photo.jpg');
});

test('handles timeout gracefully', function () {
    Http::fake([
        'example.com/*' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
        },
    ]);

    $extractor = new OpenGraphExtractor(timeout: 1);
    $metadata = $extractor->extract('https://example.com/slow-page');

    expect($metadata['og_title'])->toBeNull();
    expect($metadata['og_description'])->toBeNull();
    expect($metadata['og_image'])->toBeNull();
    expect($metadata['og_type'])->toBe('website');
});
