<?php

namespace App\Traits;

use App\Support\InputNormalizer;
use Illuminate\Database\Eloquent\Model;

trait NormalizesAttributes
{
    protected static bool $normalizationDisabled = false;

    public static function bootNormalizesAttributes(): void
    {
        static::saving(function (Model $model) {
            if ($model::$normalizationDisabled) {
                return;
            }

            foreach ($model->normalizes() as $attribute => $method) {
                if ($model->isDirty($attribute) && $model->{$attribute} !== null) {
                    $model->{$attribute} = InputNormalizer::{$method}($model->{$attribute});
                }
            }
        });
    }

    /**
     * Run the callback with input normalization suspended for this model.
     * For system-generated writes (batch placeholder labels, imports of
     * already-clean data) that must be stored verbatim.
     */
    public static function withoutNormalization(callable $callback): mixed
    {
        static::$normalizationDisabled = true;

        try {
            return $callback();
        } finally {
            static::$normalizationDisabled = false;
        }
    }

    /**
     * Map of attribute => InputNormalizer method applied on save.
     *
     * @return array<string, string>
     */
    public function normalizes(): array
    {
        return $this->normalizes ?? [];
    }
}
