<?php

use App\Helpers\LinkNormalizer;

it('normalizes general URLs to https', function () {
    expect(LinkNormalizer::normalize('example.com', 'Website'))->toBe('https://example.com');
    expect(LinkNormalizer::normalize('http://example.com', 'Website'))->toBe('https://example.com');
    expect(LinkNormalizer::normalize('https://example.com', 'Website'))->toBe('https://example.com');
    expect(LinkNormalizer::normalize('HTTP://EXAMPLE.COM', 'Website'))->toBe('https://example.com');
});

it('removes trailing slashes from general URLs', function () {
    expect(LinkNormalizer::normalize('https://example.com/', 'Website'))->toBe('https://example.com');
});

it('preserves paths in general URLs', function () {
    expect(LinkNormalizer::normalize('https://example.com/about', 'Website'))->toBe('https://example.com/about');
    expect(LinkNormalizer::normalize('https://example.com/blog?page=1', 'Website'))->toBe('https://example.com/blog?page=1');
});

it('normalizes Instagram URLs', function () {
    expect(LinkNormalizer::normalize('johndoe', 'Instagram'))->toBe('https://instagram.com/johndoe');
    expect(LinkNormalizer::normalize('@johndoe', 'Instagram'))->toBe('https://instagram.com/johndoe');
    expect(LinkNormalizer::normalize('https://instagram.com/johndoe', 'Instagram'))->toBe('https://instagram.com/johndoe');
    expect(LinkNormalizer::normalize('https://www.instagram.com/johndoe/', 'Instagram'))->toBe('https://instagram.com/johndoe');
    expect(LinkNormalizer::normalize('http://instagram.com/JohnDoe', 'Instagram'))->toBe('https://instagram.com/johndoe');
});

it('normalizes X (Twitter) URLs', function () {
    expect(LinkNormalizer::normalize('johndoe', 'X'))->toBe('https://x.com/johndoe');
    expect(LinkNormalizer::normalize('https://twitter.com/johndoe', 'X'))->toBe('https://x.com/johndoe');
    expect(LinkNormalizer::normalize('https://x.com/JohnDoe', 'X'))->toBe('https://x.com/johndoe');
});

it('normalizes TikTok URLs', function () {
    expect(LinkNormalizer::normalize('johndoe', 'TikTok'))->toBe('https://tiktok.com/@johndoe');
    expect(LinkNormalizer::normalize('@johndoe', 'TikTok'))->toBe('https://tiktok.com/@johndoe');
    expect(LinkNormalizer::normalize('https://tiktok.com/@johndoe', 'TikTok'))->toBe('https://tiktok.com/@johndoe');
    expect(LinkNormalizer::normalize('https://www.tiktok.com/@JohnDoe/', 'TikTok'))->toBe('https://tiktok.com/@johndoe');
});

it('normalizes LinkedIn URLs', function () {
    expect(LinkNormalizer::normalize('johndoe', 'LinkedIn'))->toBe('https://linkedin.com/in/johndoe');
    expect(LinkNormalizer::normalize('https://linkedin.com/in/johndoe', 'LinkedIn'))->toBe('https://linkedin.com/in/johndoe');
    expect(LinkNormalizer::normalize('https://www.linkedin.com/in/JohnDoe/', 'LinkedIn'))->toBe('https://linkedin.com/in/johndoe');
});

it('normalizes YouTube URLs', function () {
    expect(LinkNormalizer::normalize('johndoe', 'YouTube'))->toBe('https://youtube.com/@johndoe');
    expect(LinkNormalizer::normalize('@johndoe', 'YouTube'))->toBe('https://youtube.com/@johndoe');
    expect(LinkNormalizer::normalize('https://youtube.com/@johndoe', 'YouTube'))->toBe('https://youtube.com/@johndoe');
    expect(LinkNormalizer::normalize('https://www.youtube.com/@JohnDoe/', 'YouTube'))->toBe('https://youtube.com/@johndoe');
});

it('normalizes Facebook URLs', function () {
    expect(LinkNormalizer::normalize('johndoe', 'Facebook'))->toBe('https://facebook.com/johndoe');
    expect(LinkNormalizer::normalize('https://facebook.com/johndoe', 'Facebook'))->toBe('https://facebook.com/johndoe');
    expect(LinkNormalizer::normalize('https://www.facebook.com/JohnDoe/', 'Facebook'))->toBe('https://facebook.com/johndoe');
    expect(LinkNormalizer::normalize('https://fb.com/johndoe', 'Facebook'))->toBe('https://facebook.com/johndoe');
});

it('strips query strings from social media usernames', function () {
    expect(LinkNormalizer::normalize('https://instagram.com/johndoe?hl=en', 'Instagram'))->toBe('https://instagram.com/johndoe');
});

it('normalizes all links in an array', function () {
    $links = [
        ['label' => 'Instagram', 'url' => 'https://www.instagram.com/johndoe/'],
        ['label' => 'Website', 'url' => 'http://example.com'],
    ];

    $result = LinkNormalizer::normalizeAll($links);

    expect($result[0]['url'])->toBe('https://instagram.com/johndoe');
    expect($result[1]['url'])->toBe('https://example.com');
});

it('handles empty URLs gracefully', function () {
    expect(LinkNormalizer::normalize('', 'Instagram'))->toBe('');
    expect(LinkNormalizer::normalize('  ', 'Website'))->toBe('');
});

it('handles unknown labels as general URLs', function () {
    expect(LinkNormalizer::normalize('http://custom-site.com/page', 'CustomLabel'))->toBe('https://custom-site.com/page');
});
