<?php

namespace App\Helpers;

class LinkNormalizer
{
    /**
     * Social media domain patterns for normalization.
     *
     * @var array<string, array{prefix: string, patterns: array<string>}>
     */
    private static array $socialPlatforms = [
        'Instagram' => [
            'prefix' => 'https://instagram.com/',
            'patterns' => ['instagram.com', 'www.instagram.com'],
        ],
        'Facebook' => [
            'prefix' => 'https://facebook.com/',
            'patterns' => ['facebook.com', 'www.facebook.com', 'fb.com', 'www.fb.com'],
        ],
        'X' => [
            'prefix' => 'https://x.com/',
            'patterns' => ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com'],
        ],
        'TikTok' => [
            'prefix' => 'https://tiktok.com/@',
            'patterns' => ['tiktok.com', 'www.tiktok.com'],
        ],
        'LinkedIn' => [
            'prefix' => 'https://linkedin.com/in/',
            'patterns' => ['linkedin.com/in', 'www.linkedin.com/in'],
        ],
        'YouTube' => [
            'prefix' => 'https://youtube.com/@',
            'patterns' => ['youtube.com', 'www.youtube.com', 'youtu.be'],
        ],
    ];

    /**
     * Normalize an array of links (typically from form submission).
     *
     * @param  array<int, array{label: string, url: string}>  $links
     * @return array<int, array{label: string, url: string}>
     */
    public static function normalizeAll(array $links): array
    {
        return array_map(function (array $link) {
            $link['url'] = self::normalize($link['url'], $link['label'] ?? '');

            return $link;
        }, $links);
    }

    /**
     * Normalize a single URL based on its label.
     */
    public static function normalize(string $url, string $label = ''): string
    {
        $url = trim($url);

        if ($url === '') {
            return $url;
        }

        // For known social platforms, extract the username/path and rebuild
        if (isset(self::$socialPlatforms[$label])) {
            return self::normalizeSocial($url, $label);
        }

        // For general URLs, ensure https:// prefix and clean up
        return self::normalizeGeneral($url);
    }

    /**
     * Normalize a social media URL to canonical form.
     */
    private static function normalizeSocial(string $url, string $label): string
    {
        $platform = self::$socialPlatforms[$label];
        $username = self::extractUsername($url, $platform['patterns']);

        // Clean username: remove leading @, trailing slashes, lowercase
        $username = ltrim($username, '@');
        $username = rtrim($username, '/');
        $username = strtolower($username);

        // Remove query strings and fragments from username
        $username = strtok($username, '?');
        $username = strtok($username, '#');

        if ($username === '' || $username === false) {
            return $url;
        }

        return $platform['prefix'].$username;
    }

    /**
     * Extract the username/path portion from a social media URL.
     */
    private static function extractUsername(string $url, array $domainPatterns): string
    {
        // Strip protocol
        $stripped = preg_replace('#^https?://#i', '', $url);

        // Try to match known domain patterns and extract path
        foreach ($domainPatterns as $domain) {
            if (stripos($stripped, $domain) === 0) {
                $path = substr($stripped, strlen($domain));
                $path = ltrim($path, '/');
                // Remove @ prefix that some platforms use in URLs
                $path = ltrim($path, '@');

                return $path;
            }
        }

        // If no domain matched, treat the whole thing as a username
        // (user may have entered just a username)
        $stripped = preg_replace('#^https?://#i', '', $url);

        return ltrim($stripped, '/');
    }

    /**
     * Normalize a general URL.
     */
    private static function normalizeGeneral(string $url): string
    {
        // If no protocol, add https://
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        // Upgrade http to https
        $url = preg_replace('#^http://#i', 'https://', $url);

        // Lowercase the scheme and host
        $parsed = parse_url($url);
        if ($parsed && isset($parsed['host'])) {
            $parsed['host'] = strtolower($parsed['host']);
            $parsed['scheme'] = 'https';

            $url = $parsed['scheme'].'://'.$parsed['host'];
            if (isset($parsed['port']) && $parsed['port'] !== 443) {
                $url .= ':'.$parsed['port'];
            }
            $url .= $parsed['path'] ?? '';
            if (isset($parsed['query'])) {
                $url .= '?'.$parsed['query'];
            }
            if (isset($parsed['fragment'])) {
                $url .= '#'.$parsed['fragment'];
            }
        }

        // Remove trailing slash for clean URLs (but keep if path has more segments)
        $url = rtrim($url, '/');

        return $url;
    }
}
