<?php

namespace Tests;

use App\Services\Og\OgScreenshotService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeOgScreenshotService;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Set env vars BEFORE parent setup to ensure SQLite is used
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_ENV['REDIS_CLIENT'] = 'predis';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';
        $_SERVER['REDIS_CLIENT'] = 'predis';

        parent::setUp();

        // Browsershot cannot run in CI; the sync queue would invoke it from
        // any test saving a post with a featured image. Tests needing to
        // inspect captures can resolve or re-bind this singleton themselves.
        $this->app->singleton(OgScreenshotService::class, FakeOgScreenshotService::class);

        // No test may reach the network. The suite runs with QUEUE_CONNECTION=sync,
        // so jobs like ExtractOpenGraphMetadata fire inline and used to fetch
        // whatever URL a factory invented - a domain that does not resolve, or
        // localhost:3000, where the request hangs on the socket until it times
        // out. That is what made the suite stall for minutes at 0% CPU.
        //
        // A test that needs an outbound call declares it with Http::fake();
        // anything else fails immediately, naming the URL it tried to reach.
        Http::preventStrayRequests();
    }
}
