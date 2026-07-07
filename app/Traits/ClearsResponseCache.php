<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\ResponseCache\Facades\ResponseCache;

trait ClearsResponseCache
{
    public static function bootClearsResponseCache(): void
    {
        static::saved(fn () => static::clearResponseCacheAfterCommit());
        static::deleted(fn () => static::clearResponseCacheAfterCommit());

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class))) {
            static::restored(fn () => static::clearResponseCacheAfterCommit());
        }
    }

    /**
     * Model events fire INSIDE any surrounding transaction; clearing there
     * lets a concurrent public request re-cache the pre-commit data with no
     * clear afterwards. Deferring to afterCommit closes that window (the
     * callback runs immediately when no transaction is open).
     */
    protected static function clearResponseCacheAfterCommit(): void
    {
        DB::afterCommit(fn () => ResponseCache::clear(static::responseCacheTags()));
    }

    /**
     * @return string[]
     */
    abstract protected static function responseCacheTags(): array;
}
