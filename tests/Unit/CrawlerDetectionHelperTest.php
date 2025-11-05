<?php

use App\Helpers\CrawlerDetectionHelper;

test('detects facebook crawler', function () {
    expect(CrawlerDetectionHelper::isCrawler('facebookexternalhit/1.1'))->toBeTrue();
    expect(CrawlerDetectionHelper::isCrawler('Mozilla/5.0 (compatible; Facebot; +https://developers.facebook.com/)'))->toBeTrue();
});

test('detects twitter crawler', function () {
    expect(CrawlerDetectionHelper::isCrawler('Twitterbot/1.0'))->toBeTrue();
});

test('detects whatsapp crawler', function () {
    expect(CrawlerDetectionHelper::isCrawler('WhatsApp/2.0'))->toBeTrue();
});

test('detects linkedin crawler', function () {
    expect(CrawlerDetectionHelper::isCrawler('LinkedInBot/1.0'))->toBeTrue();
});

test('detects slack crawler', function () {
    expect(CrawlerDetectionHelper::isCrawler('Slackbot-LinkExpanding 1.0'))->toBeTrue();
    expect(CrawlerDetectionHelper::isCrawler('Slackbot 1.0'))->toBeTrue();
});

test('detects discord crawler', function () {
    expect(CrawlerDetectionHelper::isCrawler('Mozilla/5.0 (compatible; Discordbot/2.0;)'))->toBeTrue();
});

test('detects search engine crawlers', function () {
    expect(CrawlerDetectionHelper::isCrawler('Mozilla/5.0 (compatible; Googlebot/2.1;)'))->toBeTrue();
    expect(CrawlerDetectionHelper::isCrawler('Mozilla/5.0 (compatible; bingbot/2.0;)'))->toBeTrue();
});

test('does not detect regular user agents as crawlers', function () {
    $regularUserAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15',
    ];

    foreach ($regularUserAgents as $userAgent) {
        expect(CrawlerDetectionHelper::isCrawler($userAgent))->toBeFalse();
    }
});

test('returns false for empty user agent', function () {
    expect(CrawlerDetectionHelper::isCrawler(''))->toBeFalse();
    expect(CrawlerDetectionHelper::isCrawler(null))->toBeFalse();
});

test('is case insensitive', function () {
    expect(CrawlerDetectionHelper::isCrawler('FACEBOOKEXTERNALHIT/1.1'))->toBeTrue();
    expect(CrawlerDetectionHelper::isCrawler('twitterbot/1.0'))->toBeTrue();
});

test('identifies social media crawlers specifically', function () {
    expect(CrawlerDetectionHelper::isSocialMediaCrawler('facebookexternalhit/1.1'))->toBeTrue();
    expect(CrawlerDetectionHelper::isSocialMediaCrawler('WhatsApp/2.0'))->toBeTrue();
    expect(CrawlerDetectionHelper::isSocialMediaCrawler('Googlebot/2.1'))->toBeFalse(); // Search engine, not social
});

test('gets crawler name from user agent', function () {
    expect(CrawlerDetectionHelper::getCrawlerName('facebookexternalhit/1.1'))->toBe('facebookexternalhit');
    expect(CrawlerDetectionHelper::getCrawlerName('Twitterbot/1.0'))->toBe('Twitterbot');
    expect(CrawlerDetectionHelper::getCrawlerName('Mozilla/5.0 (Windows NT 10.0)'))->toBeNull();
});
