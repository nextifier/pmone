<?php

namespace App\Traits;

use Spatie\ResponseCache\Facades\ResponseCache;

trait ClearsResponseCache
{
    public static function bootClearsResponseCache(): void
    {
        static::saved(fn () => ResponseCache::clear(static::responseCacheTags()));
        static::deleted(fn () => ResponseCache::clear(static::responseCacheTags()));
    }

    /**
     * @return string[]
     */
    abstract protected static function responseCacheTags(): array;
}
