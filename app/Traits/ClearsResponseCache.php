<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\ResponseCache\Facades\ResponseCache;

trait ClearsResponseCache
{
    public static function bootClearsResponseCache(): void
    {
        static::saved(fn () => ResponseCache::clear(static::responseCacheTags()));
        static::deleted(fn () => ResponseCache::clear(static::responseCacheTags()));

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class))) {
            static::restored(fn () => ResponseCache::clear(static::responseCacheTags()));
        }
    }

    /**
     * @return string[]
     */
    abstract protected static function responseCacheTags(): array;
}
