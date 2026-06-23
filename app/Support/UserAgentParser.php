<?php

namespace App\Support;

/**
 * Lightweight, dependency-free user-agent parser.
 *
 * Extracts a human-friendly browser / OS / device label from a raw
 * user-agent string for display in the admin sessions & login-history UI.
 * It is intentionally heuristic: good enough for "Chrome on macOS" labels,
 * not a full device-detection engine.
 */
class UserAgentParser
{
    /**
     * @return array{browser: string, os: string, device_type: string, is_mobile: bool, label: string}
     */
    public static function parse(?string $userAgent): array
    {
        $ua = trim((string) $userAgent);

        if ($ua === '') {
            return self::unknown();
        }

        if (self::isBot($ua)) {
            return [
                'browser' => 'Bot',
                'os' => 'Unknown',
                'device_type' => 'bot',
                'is_mobile' => false,
                'label' => 'Bot / Crawler',
            ];
        }

        $browser = self::detectBrowser($ua);
        $os = self::detectOs($ua);
        $deviceType = self::detectDeviceType($ua);
        $isMobile = in_array($deviceType, ['mobile', 'tablet'], true);

        $label = $browser === 'Unknown' && $os === 'Unknown'
            ? 'Unknown device'
            : trim(sprintf('%s on %s', $browser, $os));

        return [
            'browser' => $browser,
            'os' => $os,
            'device_type' => $deviceType,
            'is_mobile' => $isMobile,
            'label' => $label,
        ];
    }

    /**
     * @return array{browser: string, os: string, device_type: string, is_mobile: bool, label: string}
     */
    protected static function unknown(): array
    {
        return [
            'browser' => 'Unknown',
            'os' => 'Unknown',
            'device_type' => 'unknown',
            'is_mobile' => false,
            'label' => 'Unknown device',
        ];
    }

    protected static function isBot(string $ua): bool
    {
        return (bool) preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegrambot|headless|python-requests|curl|wget|axios|okhttp|go-http-client/i', $ua);
    }

    /**
     * Order matters: more specific tokens (Edge, OPR, Samsung) must be checked
     * before Chrome, and Chrome before Safari (their UA strings overlap).
     */
    protected static function detectBrowser(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/Edg(?:e|A|iOS)?\//i', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $ua) => 'Opera',
            (bool) preg_match('/SamsungBrowser/i', $ua) => 'Samsung Internet',
            (bool) preg_match('/(?:CriOS|Chrome|Chromium)\//i', $ua) => 'Chrome',
            (bool) preg_match('/Firefox\/|FxiOS\//i', $ua) => 'Firefox',
            (bool) preg_match('/Safari\//i', $ua) => 'Safari',
            (bool) preg_match('/MSIE |Trident\//i', $ua) => 'Internet Explorer',
            default => 'Unknown',
        };
    }

    protected static function detectOs(string $ua): string
    {
        if (preg_match('/Windows NT ([0-9.]+)/i', $ua, $m)) {
            return 'Windows '.self::windowsVersion($m[1]);
        }

        return match (true) {
            (bool) preg_match('/(iPhone|iPad|iPod)/i', $ua) => 'iOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/CrOS/i', $ua) => 'ChromeOS',
            (bool) preg_match('/Mac OS X|Macintosh/i', $ua) => 'macOS',
            (bool) preg_match('/Linux/i', $ua) => 'Linux',
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            default => 'Unknown',
        };
    }

    protected static function windowsVersion(string $nt): string
    {
        return match ($nt) {
            '10.0' => '10/11',
            '6.3' => '8.1',
            '6.2' => '8',
            '6.1' => '7',
            '6.0' => 'Vista',
            '5.1', '5.2' => 'XP',
            default => '',
        };
    }

    protected static function detectDeviceType(string $ua): string
    {
        if (preg_match('/iPad|Tablet|Nexus 7|Nexus 10|Kindle|Silk|PlayBook/i', $ua)) {
            return 'tablet';
        }

        if (preg_match('/Android/i', $ua) && ! preg_match('/Mobile/i', $ua)) {
            return 'tablet';
        }

        if (preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry|IEMobile/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }
}
