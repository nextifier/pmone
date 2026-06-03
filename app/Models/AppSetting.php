<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $ulid
 * @property string $key
 * @property array<array-key, mixed>|null $value
 * @property string|null $description
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read User|null $creator
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read User|null $updater
 *
 * @method static \Database\Factories\AppSettingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppSetting whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppSetting whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppSetting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppSetting whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppSetting whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppSetting whereValue($value)
 *
 * @mixin \Eloquent
 */
class AppSetting extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use LogsActivity;

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::saved(function ($model) {
            Cache::forget(self::cacheKey($model->key));
        });

        static::deleted(function ($model) {
            Cache::forget(self::cacheKey($model->key));
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['key', 'value', 'description'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(self::cacheKey($key), function () use ($key, $default) {
            $setting = self::query()->where('key', $key)->first();

            return $setting?->value ?? $default;
        });
    }

    public static function set(string $key, mixed $value, ?string $description = null): self
    {
        $setting = self::query()->where('key', $key)->first();

        if ($setting) {
            $setting->value = $value;

            if ($description !== null) {
                $setting->description = $description;
            }

            $setting->save();

            return $setting;
        }

        return self::create([
            'key' => $key,
            'value' => $value,
            'description' => $description,
        ]);
    }

    protected static function cacheKey(string $key): string
    {
        return "app_setting:{$key}";
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('branding_logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml']);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
