<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class CrawlerDetectionHelper
{
    /**
     * List of known social media and search engine crawler user agents.
     */
    private const CRAWLER_PATTERNS = [
        'facebookexternalhit', // Facebook
        'Facebot', // Facebook
        'Twitterbot', // Twitter/X
        'WhatsApp', // WhatsApp
        'TelegramBot', // Telegram
        'LinkedInBot', // LinkedIn
        'Slackbot', // Slack
        'SlackBot-LinkExpanding', // Slack Link Preview
        'Pinterest', // Pinterest
        'Discordbot', // Discord
        'SkypeUriPreview', // Skype
        'Googlebot', // Google
        'bingbot', // Bing
        'Baiduspider', // Baidu
        'Yahoo! Slurp', // Yahoo
        'DuckDuckBot', // DuckDuckGo
        'Applebot', // Apple
        'vkShare', // VKontakte
        'Embedly', // Embed.ly
        'quora link preview', // Quora
        'redditbot', // Reddit
        'Tumblr', // Tumblr
        'Snapchat', // Snapchat
        'ia_archiver', // Alexa
        'outbrain', // Outbrain
        'W3C_Validator', // W3C Validator
    ];

    /**
     * Check if the current request is from a known crawler/bot.
     */
    public static function isCrawler(?string $userAgent = null): bool
    {
        if ($userAgent === null) {
            $userAgent = request()->userAgent();
        }

        if (empty($userAgent)) {
            return false;
        }

        foreach (self::CRAWLER_PATTERNS as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the current request is from a social media crawler specifically.
     */
    public static function isSocialMediaCrawler(?string $userAgent = null): bool
    {
        $socialMediaPatterns = [
            'facebookexternalhit',
            'Facebot',
            'Twitterbot',
            'WhatsApp',
            'TelegramBot',
            'LinkedInBot',
            'Slackbot',
            'SlackBot-LinkExpanding',
            'Pinterest',
            'Discordbot',
            'SkypeUriPreview',
            'vkShare',
            'quora link preview',
            'redditbot',
            'Tumblr',
            'Snapchat',
        ];

        if ($userAgent === null) {
            $userAgent = request()->userAgent();
        }

        if (empty($userAgent)) {
            return false;
        }

        foreach ($socialMediaPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the crawler name from user agent if detected.
     */
    public static function getCrawlerName(?string $userAgent = null): ?string
    {
        if ($userAgent === null) {
            $userAgent = request()->userAgent();
        }

        if (empty($userAgent)) {
            return null;
        }

        foreach (self::CRAWLER_PATTERNS as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return $pattern;
            }
        }

        return null;
    }
}
