<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    /**
     * The AWS_* variables are shared with the R2/S3 disks, the SQS queue, and the
     * DynamoDB cache store. SES needs its own credentials and region, so it reads
     * SES_* first and only falls back to AWS_* when they are absent.
     */
    'ses' => [
        'key' => env('SES_KEY', env('AWS_ACCESS_KEY_ID')),
        'secret' => env('SES_SECRET', env('AWS_SECRET_ACCESS_KEY')),
        'region' => env('SES_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
    ],

    /**
     * Kept out of "services.ses" on purpose: MailManager passes every key of
     * that array straight into the SesV2Client constructor.
     *
     * An SNS signature only proves the message came from SNS, not from our own
     * topic, so the ARN below is the allowlist that stops anyone from pointing
     * their own topic at our webhook.
     */
    'ses_sns' => [
        'topic_arn' => env('SES_SNS_TOPIC_ARN'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => '/auth/github/callback',
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => '/auth/google/callback',
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => '/auth/facebook/callback',
    ],

    'sheets' => [
        'api_token' => env('SHEETS_API_TOKEN'),
    ],

    'exchange_rate' => [
        'api_url' => env('EXCHANGE_RATE_API_URL', 'https://api.exchangerate-api.com/v4/latest/USD'),
        'base_currency' => env('EXCHANGE_RATE_BASE_CURRENCY', 'USD'),
        'sync_interval_minutes' => (int) env('EXCHANGE_RATE_SYNC_INTERVAL', 60),
        'cache_ttl_minutes' => (int) env('EXCHANGE_RATE_CACHE_TTL', 120),
    ],

    'cloudflare' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'key' => env('CLOUDFLARE_KEY'),
    ],

    'whatsapp' => [
        'token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    ],
];
