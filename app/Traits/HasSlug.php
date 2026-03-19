<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Drop-in replacement for cviebrock/eloquent-sluggable.
 *
 * Models using this trait must implement a sluggable() method returning
 * the slug configuration array, e.g.:
 *
 *     public function sluggable(): array
 *     {
 *         return [
 *             'slug' => [
 *                 'source' => 'title',
 *             ],
 *         ];
 *     }
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function (Model $model) {
            foreach ($model->sluggable() as $attribute => $config) {
                if (is_numeric($attribute)) {
                    $attribute = $config;
                    $config = [];
                }

                $config = array_merge(static::defaultSlugConfig(), $config);

                $slug = static::buildSlugFor($model, $attribute, $config);

                if ($slug !== null) {
                    $model->setAttribute($attribute, $slug);
                }
            }
        });
    }

    abstract public function sluggable(): array;

    /**
     * {@inheritDoc}
     */
    public function replicate(?array $except = null)
    {
        $instance = parent::replicate($except);

        foreach ($instance->sluggable() as $attribute => $config) {
            if (is_numeric($attribute)) {
                $attribute = $config;
                $config = [];
            }

            $config = array_merge(static::defaultSlugConfig(), $config);

            // Clear slug so it gets regenerated
            $instance->setAttribute($attribute, null);

            $slug = static::buildSlugFor($instance, $attribute, $config, force: true);

            if ($slug !== null) {
                $instance->setAttribute($attribute, $slug);
            }
        }

        return $instance;
    }

    /**
     * Query scope for finding "similar" slugs, used to determine uniqueness.
     */
    public function scopeFindSimilarSlugs(Builder $query, string $attribute, array $config, string $slug): Builder
    {
        $separator = $config['separator'] ?? '-';

        return $query->where(function (Builder $q) use ($attribute, $slug, $separator) {
            $q->where($attribute, '=', $slug)
                ->orWhere($attribute, 'LIKE', $slug.$separator.'%');
        });
    }

    /**
     * Optionally add constraints to the query that determines uniqueness.
     * Override in model to scope slug uniqueness (e.g. per-project).
     */
    public function scopeWithUniqueSlugConstraints(
        Builder $query,
        Model $model,
        string $attribute,
        array $config,
        string $slug
    ): Builder {
        return $query;
    }

    protected static function defaultSlugConfig(): array
    {
        return [
            'source' => null,
            'separator' => '-',
            'unique' => true,
            'includeTrashed' => false,
            'onUpdate' => false,
            'maxLength' => null,
            'maxLengthKeepWords' => true,
            'method' => null,
            'reserved' => null,
            'uniqueSuffix' => null,
            'firstUniqueSuffix' => 1,
        ];
    }

    protected static function buildSlugFor(Model $model, string $attribute, array $config, bool $force = false): ?string
    {
        if (! $force && ! static::needsSlugging($model, $attribute, $config)) {
            return null;
        }

        $source = static::getSlugSource($model, $config['source']);

        if (! $source && ! is_numeric($source)) {
            return null;
        }

        $slug = static::generateSlugString($source, $config);
        $slug = static::validateSlugReserved($slug, $config);

        if ($config['unique']) {
            $slug = static::makeSlugUnique($model, $slug, $attribute, $config);
        }

        return $slug;
    }

    protected static function needsSlugging(Model $model, string $attribute, array $config): bool
    {
        $value = $model->getAttributeValue($attribute);

        if ($config['onUpdate'] === true || $value === null || trim((string) $value) === '') {
            return true;
        }

        if ($model->isDirty($attribute)) {
            return false;
        }

        return ! $model->exists;
    }

    protected static function getSlugSource(Model $model, mixed $from): string
    {
        if (is_null($from)) {
            return $model->__toString();
        }

        $sourceStrings = array_map(function ($key) use ($model) {
            $value = data_get($model, $key, $model->getAttribute($key));

            if (is_bool($value)) {
                $value = (int) $value;
            }

            return $value;
        }, (array) $from);

        return implode(' ', $sourceStrings);
    }

    protected static function generateSlugString(string $source, array $config): string
    {
        $separator = $config['separator'];
        $method = $config['method'];
        $maxLength = $config['maxLength'];
        $maxLengthKeepWords = $config['maxLengthKeepWords'];

        if ($method === null) {
            $slug = Str::slug($source, $separator);
        } elseif (is_callable($method)) {
            $slug = $method($source, $separator);
        } else {
            throw new \UnexpectedValueException('Sluggable "method" is not callable nor null.');
        }

        $len = mb_strlen($slug);

        if (is_string($slug) && $maxLength && $len > $maxLength) {
            $reverseOffset = $maxLength - $len;
            $lastSeparatorPos = mb_strrpos($slug, $separator, $reverseOffset);

            if ($maxLengthKeepWords && $lastSeparatorPos !== false) {
                $slug = mb_substr($slug, 0, $lastSeparatorPos);
            } else {
                $slug = trim(mb_substr($slug, 0, $maxLength), $separator);
            }
        }

        return $slug;
    }

    protected static function validateSlugReserved(string $slug, array $config): string
    {
        $reserved = $config['reserved'];

        if ($reserved === null) {
            return $slug;
        }

        if ($reserved instanceof \Closure) {
            $reserved = $reserved();
        }

        if (is_array($reserved) && in_array($slug, $reserved)) {
            $separator = $config['separator'];
            $firstSuffix = $config['firstUniqueSuffix'];

            return $slug.$separator.$firstSuffix;
        }

        return $slug;
    }

    protected static function makeSlugUnique(Model $model, string $slug, string $attribute, array $config): string
    {
        $separator = $config['separator'];

        $list = static::getExistingSlugs($model, $slug, $attribute, $config);

        // Slug not taken at all
        if ($list->count() === 0 || ! $list->contains($slug)) {
            return $slug;
        }

        // Slug belongs to our own model
        if ($list->has($model->getKey())) {
            $currentSlug = $list->get($model->getKey());

            if ($currentSlug === $slug || (! $slug || str_starts_with($currentSlug, $slug))) {
                return $currentSlug;
            }
        }

        // Generate unique suffix
        $firstSuffix = $config['firstUniqueSuffix'];
        $len = strlen($slug.$separator);

        // If the slug already exists but belongs to our model, return current suffix
        if ($list->search($slug) === $model->getKey()) {
            $suffix = explode($separator, $slug);

            return $slug;
        }

        $suffixes = $list->transform(function ($value) use ($len) {
            return (int) substr($value, $len);
        });

        $max = $suffixes->max();

        $suffix = (string) ($max === 0 ? $firstSuffix : $max + 1);

        return $slug.$separator.$suffix;
    }

    protected static function getExistingSlugs(Model $model, string $slug, string $attribute, array $config): Collection
    {
        $query = $model->newQuery()
            ->findSimilarSlugs($attribute, $config, $slug);

        $query->withUniqueSlugConstraints($model, $attribute, $config, $slug);

        if ($config['includeTrashed'] && method_exists($model, 'bootSoftDeletes')) {
            $query->withTrashed();
        }

        $results = $query
            ->withoutEagerLoads()
            ->select([$attribute, $model->getQualifiedKeyName()])
            ->get()
            ->toBase();

        return $results->pluck($attribute, $model->getKeyName());
    }
}
