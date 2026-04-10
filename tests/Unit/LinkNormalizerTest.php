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

// --- Label Detection ---

it('detects Instagram label from URL', function () {
    expect(LinkNormalizer::detectLabel('https://instagram.com/johndoe'))->toBe('Instagram');
    expect(LinkNormalizer::detectLabel('https://www.instagram.com/johndoe'))->toBe('Instagram');
    expect(LinkNormalizer::detectLabel('instagram.com/tehtarikperanakan'))->toBe('Instagram');
});

it('detects Facebook label from URL', function () {
    expect(LinkNormalizer::detectLabel('https://facebook.com/somepage'))->toBe('Facebook');
    expect(LinkNormalizer::detectLabel('https://fb.com/somepage'))->toBe('Facebook');
});

it('detects X label from Twitter and X URLs', function () {
    expect(LinkNormalizer::detectLabel('https://twitter.com/johndoe'))->toBe('X');
    expect(LinkNormalizer::detectLabel('https://x.com/johndoe'))->toBe('X');
});

it('detects TikTok label from URL', function () {
    expect(LinkNormalizer::detectLabel('https://tiktok.com/@johndoe'))->toBe('TikTok');
    expect(LinkNormalizer::detectLabel('https://www.tiktok.com/@johndoe'))->toBe('TikTok');
});

it('detects LinkedIn label from URL', function () {
    expect(LinkNormalizer::detectLabel('https://linkedin.com/in/johndoe'))->toBe('LinkedIn');
    expect(LinkNormalizer::detectLabel('https://linkedin.com/company/my-corp'))->toBe('LinkedIn');
    expect(LinkNormalizer::detectLabel('https://www.linkedin.com/company/my-corp/'))->toBe('LinkedIn');
});

it('detects YouTube label from URL', function () {
    expect(LinkNormalizer::detectLabel('https://youtube.com/@channel'))->toBe('YouTube');
    expect(LinkNormalizer::detectLabel('https://www.youtube.com/@channel'))->toBe('YouTube');
    expect(LinkNormalizer::detectLabel('https://youtu.be/videoid'))->toBe('YouTube');
});

it('returns null for non-social URLs', function () {
    expect(LinkNormalizer::detectLabel('https://example.com'))->toBeNull();
    expect(LinkNormalizer::detectLabel('https://myshop.com/about'))->toBeNull();
    expect(LinkNormalizer::detectLabel(''))->toBeNull();
});

// --- Label Override (detectAndNormalize) ---

it('overrides wrong label with detected platform', function () {
    $result = LinkNormalizer::detectAndNormalize('instagram.com/tehtarikperanakan', 'Website');
    expect($result['label'])->toBe('Instagram');
    expect($result['url'])->toBe('https://instagram.com/tehtarikperanakan');
});

it('overrides wrong label for all social platforms', function () {
    expect(LinkNormalizer::detectAndNormalize('https://tiktok.com/@user', 'Website')['label'])->toBe('TikTok');
    expect(LinkNormalizer::detectAndNormalize('https://facebook.com/page', 'Instagram')['label'])->toBe('Facebook');
    expect(LinkNormalizer::detectAndNormalize('https://twitter.com/user', 'Website')['label'])->toBe('X');
    expect(LinkNormalizer::detectAndNormalize('https://linkedin.com/company/corp', 'Website')['label'])->toBe('LinkedIn');
    expect(LinkNormalizer::detectAndNormalize('https://youtube.com/@chan', 'Website')['label'])->toBe('YouTube');
});

it('keeps label unchanged for non-social URLs', function () {
    $result = LinkNormalizer::detectAndNormalize('https://example.com', 'Website');
    expect($result['label'])->toBe('Website');
    expect($result['url'])->toBe('https://example.com');
});

it('keeps custom label for non-social URLs', function () {
    $result = LinkNormalizer::detectAndNormalize('https://myshop.com', 'Online Shop');
    expect($result['label'])->toBe('Online Shop');
});

// --- LinkedIn /company/ vs /in/ ---

it('normalizes LinkedIn company URLs', function () {
    expect(LinkNormalizer::normalize('https://www.linkedin.com/company/my-corp/', 'LinkedIn'))
        ->toBe('https://linkedin.com/company/my-corp');
});

it('normalizes LinkedIn personal URLs', function () {
    expect(LinkNormalizer::normalize('https://www.linkedin.com/in/johndoe/', 'LinkedIn'))
        ->toBe('https://linkedin.com/in/johndoe');
});

it('detects and normalizes LinkedIn company URL with wrong label', function () {
    $result = LinkNormalizer::detectAndNormalize('https://linkedin.com/company/my-corp', 'Website');
    expect($result['label'])->toBe('LinkedIn');
    expect($result['url'])->toBe('https://linkedin.com/company/my-corp');
});

// --- normalizeAll with label detection ---

it('normalizeAll fixes labels and URLs together', function () {
    $links = [
        ['label' => 'Website', 'url' => 'instagram.com/tehtarikperanakan'],
        ['label' => 'Website', 'url' => 'https://tiktok.com/@someuser'],
        ['label' => 'Website', 'url' => 'https://example.com'],
    ];

    $result = LinkNormalizer::normalizeAll($links);

    expect($result[0]['label'])->toBe('Instagram');
    expect($result[0]['url'])->toBe('https://instagram.com/tehtarikperanakan');
    expect($result[1]['label'])->toBe('TikTok');
    expect($result[1]['url'])->toBe('https://tiktok.com/@someuser');
    expect($result[2]['label'])->toBe('Website');
    expect($result[2]['url'])->toBe('https://example.com');
});
