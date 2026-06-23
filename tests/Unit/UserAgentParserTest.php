<?php

use App\Support\UserAgentParser;

it('parses Chrome on macOS', function () {
    $result = UserAgentParser::parse('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36');

    expect($result['browser'])->toBe('Chrome')
        ->and($result['os'])->toBe('macOS')
        ->and($result['device_type'])->toBe('desktop')
        ->and($result['is_mobile'])->toBeFalse()
        ->and($result['label'])->toBe('Chrome on macOS');
});

it('parses Safari on iPhone as mobile', function () {
    $result = UserAgentParser::parse('Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1');

    expect($result['browser'])->toBe('Safari')
        ->and($result['os'])->toBe('iOS')
        ->and($result['device_type'])->toBe('mobile')
        ->and($result['is_mobile'])->toBeTrue();
});

it('parses Firefox on Windows', function () {
    $result = UserAgentParser::parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0');

    expect($result['browser'])->toBe('Firefox')
        ->and($result['os'])->toBe('Windows 10/11')
        ->and($result['device_type'])->toBe('desktop');
});

it('parses Edge before Chrome', function () {
    $result = UserAgentParser::parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0');

    expect($result['browser'])->toBe('Edge');
});

it('parses an Android tablet', function () {
    $result = UserAgentParser::parse('Mozilla/5.0 (Linux; Android 13; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

    expect($result['os'])->toBe('Android')
        ->and($result['device_type'])->toBe('tablet')
        ->and($result['is_mobile'])->toBeTrue();
});

it('parses an Android phone as mobile', function () {
    $result = UserAgentParser::parse('Mozilla/5.0 (Linux; Android 14; Pixel 8 Mobile) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36');

    expect($result['os'])->toBe('Android')
        ->and($result['device_type'])->toBe('mobile');
});

it('detects bots', function () {
    $result = UserAgentParser::parse('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

    expect($result['device_type'])->toBe('bot')
        ->and($result['browser'])->toBe('Bot');
});

it('degrades gracefully for null and empty input', function (?string $ua) {
    $result = UserAgentParser::parse($ua);

    expect($result['browser'])->toBe('Unknown')
        ->and($result['os'])->toBe('Unknown')
        ->and($result['device_type'])->toBe('unknown')
        ->and($result['label'])->toBe('Unknown device');
})->with([null, '', '   ']);
