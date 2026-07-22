<?php

namespace App\Traits;

use App\Jobs\PurgeEdgeCache;
use App\Support\EdgeCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\ResponseCache\Facades\ResponseCache;

trait ClearsResponseCache
{
    public static function bootClearsResponseCache(): void
    {
        static::saved(fn (Model $model) => static::clearResponseCacheAfterCommit($model));
        static::deleted(fn (Model $model) => static::clearResponseCacheAfterCommit($model));

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class))) {
            static::restored(fn (Model $model) => static::clearResponseCacheAfterCommit($model));
        }
    }

    /**
     * Model events fire INSIDE any surrounding transaction; clearing there
     * lets a concurrent public request re-cache the pre-commit data with no
     * clear afterwards. Deferring to afterCommit closes that window (the
     * callback runs immediately when no transaction is open).
     */
    protected static function clearResponseCacheAfterCommit(Model $model): void
    {
        DB::afterCommit(function () use ($model) {
            // Clearing through the facade also invalidates the event websites'
            // edge cache for this tag's list pages and API endpoints — see
            // App\Support\TagAwareResponseCache, which decorates the binding.
            ResponseCache::clear(static::responseCacheTags());

            static::purgeEdgeDetailPages($model);
        });
    }

    /**
     * Purge the specific public URLs this record renders as.
     *
     * The tag-level purge above can only name list pages, because a tag carries
     * no identity — "blog-posts changed" cannot tell you which article. A model
     * that implements `edgeCachePaths()` gets its detail page dropped too, which
     * is what makes editing an article show up immediately instead of after the
     * 1-hour TTL. Models without it simply keep the list-page behaviour.
     */
    protected static function purgeEdgeDetailPages(Model $model): void
    {
        if (! method_exists($model, 'edgeCachePaths') || ! EdgeCache::isConfigured()) {
            return;
        }

        $paths = array_values(array_filter((array) $model->edgeCachePaths()));

        if ($paths === []) {
            return;
        }

        $project = method_exists($model, 'edgeCacheProject')
            ? $model->edgeCacheProject()
            : null;

        PurgeEdgeCache::dispatch(static::responseCacheTags(), $paths, $project)
            ->delay(now()->addSeconds(PurgeEdgeCache::DEBOUNCE_SECONDS));
    }

    /**
     * @return string[]
     */
    abstract protected static function responseCacheTags(): array;
}
