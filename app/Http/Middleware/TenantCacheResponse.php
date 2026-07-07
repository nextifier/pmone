<?php

namespace App\Http\Middleware;

use Illuminate\Support\Str;
use Spatie\ResponseCache\Configuration\CacheConfiguration;
use Spatie\ResponseCache\Middlewares\CacheResponse;

/**
 * Dual-tags cached responses on project-scoped public routes: every entry
 * carries the global tag (e.g. `rundown`) PLUS a tenant-scoped variant
 * (`rundown:{username}`) derived from the route's {username} parameter.
 *
 * Global clears (model traits, admin flush) still bust everything, while
 * high-frequency per-project clears (Project settings writes) can target
 * `{tag}:{username}` and leave the other projects' caches warm. Routes
 * without a {username} parameter behave exactly like the parent middleware.
 */
class TenantCacheResponse extends CacheResponse
{
    protected function getConfigurationFromArgs(array $args): ?CacheConfiguration
    {
        $config = parent::getConfigurationFromArgs($args);

        if ($config === null || $config->tags === []) {
            return $config;
        }

        $username = request()->route()?->parameter('username');

        if (! is_string($username) || $username === '') {
            return $config;
        }

        $username = Str::lower($username);
        $tenantTags = array_map(fn (string $tag) => "{$tag}:{$username}", $config->tags);

        return new CacheConfiguration(
            lifetime: $config->lifetime,
            tags: [...$config->tags, ...$tenantTags],
        );
    }
}
