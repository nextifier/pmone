<?php

use Illuminate\Mail\Transport\SesV2Transport;
use Illuminate\Support\Facades\Mail;

/**
 * @var list<string>
 */
$envKeys = [
    'SES_KEY',
    'SES_SECRET',
    'SES_REGION',
    'SES_CONFIGURATION_SET',
    'AWS_ACCESS_KEY_ID',
    'AWS_SECRET_ACCESS_KEY',
    'AWS_DEFAULT_REGION',
];

/**
 * Dotenv writes each variable to $_SERVER, $_ENV *and* putenv(), and Laravel's
 * Env repository reads all three. Clearing only the superglobals would let the
 * developer's real .env leak into these assertions.
 */
beforeEach(function () use ($envKeys) {
    $this->originalEnv = [];

    foreach ($envKeys as $key) {
        $putenv = getenv($key);

        $this->originalEnv[$key] = [
            'server' => $_SERVER[$key] ?? null,
            'env' => $_ENV[$key] ?? null,
            'putenv' => $putenv === false ? null : $putenv,
        ];

        unset($_SERVER[$key], $_ENV[$key]);
        putenv($key);
    }

    $this->setEnv = function (array $vars): void {
        foreach ($vars as $key => $value) {
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    };
});

afterEach(function () use ($envKeys) {
    foreach ($envKeys as $key) {
        ['server' => $server, 'env' => $env, 'putenv' => $putenv] = $this->originalEnv[$key];

        if ($server === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $server;
        }

        if ($env === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $env;
        }

        if ($putenv === null) {
            putenv($key);
        } else {
            putenv("{$key}={$putenv}");
        }
    }
});

it('resolves the ses-v2 mailer to the SES v2 transport', function () {
    config([
        'services.ses' => [
            'key' => 'test-key',
            'secret' => 'test-secret',
            'region' => 'ap-southeast-1',
        ],
    ]);

    $transport = Mail::mailer('ses-v2')->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(SesV2Transport::class)
        ->and((string) $transport)->toBe('ses-v2');
});

it('never passes a null ConfigurationSetName to the SESv2 API', function () {
    $options = config('mail.mailers.ses-v2.options');

    expect($options)->toBeArray()
        ->and(array_filter($options, fn ($value) => $value === null))->toBe([]);
});

it('drops ConfigurationSetName when SES_CONFIGURATION_SET is empty', function () {
    $mail = require config_path('mail.php');

    expect($mail['mailers']['ses-v2']['options'])->toBe([]);
});

it('includes ConfigurationSetName when SES_CONFIGURATION_SET is set', function () {
    ($this->setEnv)(['SES_CONFIGURATION_SET' => 'pmone-transactional']);

    $mail = require config_path('mail.php');

    expect($mail['mailers']['ses-v2']['options'])
        ->toBe(['ConfigurationSetName' => 'pmone-transactional']);
});

it('reads SES credentials from SES_* instead of the shared AWS_* variables', function () {
    ($this->setEnv)([
        'SES_KEY' => 'ses-key',
        'SES_SECRET' => 'ses-secret',
        'SES_REGION' => 'ap-southeast-1',
        'AWS_ACCESS_KEY_ID' => 'r2-key',
        'AWS_SECRET_ACCESS_KEY' => 'r2-secret',
        'AWS_DEFAULT_REGION' => 'auto',
    ]);

    $services = require config_path('services.php');

    expect($services['ses'])->toBe([
        'key' => 'ses-key',
        'secret' => 'ses-secret',
        'region' => 'ap-southeast-1',
    ]);
});

it('falls back to the AWS_* variables when SES_* are absent', function () {
    ($this->setEnv)([
        'AWS_ACCESS_KEY_ID' => 'shared-key',
        'AWS_SECRET_ACCESS_KEY' => 'shared-secret',
        'AWS_DEFAULT_REGION' => 'us-east-1',
    ]);

    $services = require config_path('services.php');

    expect($services['ses'])->toBe([
        'key' => 'shared-key',
        'secret' => 'shared-secret',
        'region' => 'us-east-1',
    ]);
});

it('keeps the cloudflare and resend mailers available for switching', function () {
    $mailers = config('mail.mailers');

    expect($mailers)->toHaveKeys(['cloudflare', 'resend', 'ses', 'ses-v2'])
        ->and($mailers['cloudflare']['transport'])->toBe('cloudflare')
        ->and($mailers['resend']['transport'])->toBe('resend');
});
