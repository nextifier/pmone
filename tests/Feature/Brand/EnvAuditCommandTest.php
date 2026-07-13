<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->fixtureDir = storage_path('framework/testing/env-audit');
    File::ensureDirectoryExists($this->fixtureDir);
});

afterEach(function () {
    File::deleteDirectory($this->fixtureDir);
});

function envAuditFixture(string $name, string $contents): string
{
    $path = storage_path("framework/testing/env-audit/{$name}");
    File::put($path, $contents);

    return $path;
}

it('passes when the env file defines every manifest key', function () {
    $example = envAuditFixture('example.env', "APP_NAME=Example\nBRAND_SUPPORT_EMAIL=support@example.test\n");
    $env = envAuditFixture('actual.env', "APP_NAME=BrandX\nBRAND_SUPPORT_EMAIL=help@brandx.test\n");

    $this->artisan('env:audit', ['--env-file' => $env, '--example' => $example])
        ->expectsOutputToContain('env:audit passed')
        ->assertSuccessful();
});

it('fails and lists the keys when the env file misses a manifest key', function () {
    $example = envAuditFixture('example.env', "APP_NAME=Example\nBRAND_SUPPORT_EMAIL=support@example.test\n");
    $env = envAuditFixture('actual.env', "APP_NAME=BrandX\n");

    $this->artisan('env:audit', ['--env-file' => $env, '--example' => $example])
        ->expectsOutputToContain('BRAND_SUPPORT_EMAIL')
        ->assertFailed();
});

it('warns about extra env keys but still passes', function () {
    $example = envAuditFixture('example.env', "APP_NAME=Example\n");
    $env = envAuditFixture('actual.env', "APP_NAME=BrandX\nLEGACY_FLAG=true\n");

    $this->artisan('env:audit', ['--env-file' => $env, '--example' => $example])
        ->expectsOutputToContain('LEGACY_FLAG')
        ->assertSuccessful();
});

it('ignores commented keys in the manifest', function () {
    $example = envAuditFixture('example.env', "APP_NAME=Example\n# OPTIONAL_FLAG=true\n");
    $env = envAuditFixture('actual.env', "APP_NAME=BrandX\n");

    $this->artisan('env:audit', ['--env-file' => $env, '--example' => $example])
        ->assertSuccessful();
});

it('does not warn about env keys documented as comments in the manifest', function () {
    $example = envAuditFixture('example.env', "APP_NAME=Example\n# OPTIONAL_FLAG=true\n");
    $env = envAuditFixture('actual.env', "APP_NAME=BrandX\nOPTIONAL_FLAG=false\n");

    $this->artisan('env:audit', ['--env-file' => $env, '--example' => $example])
        ->doesntExpectOutputToContain('OPTIONAL_FLAG')
        ->assertSuccessful();
});

it('fails when the env file does not exist', function () {
    $example = envAuditFixture('example.env', "APP_NAME=Example\n");

    $this->artisan('env:audit', ['--env-file' => $this->fixtureDir.'/missing.env', '--example' => $example])
        ->assertFailed();
});
