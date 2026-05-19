<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Tags\HasTags;
use Spatie\Tags\Tag;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property int $event_id
 * @property Carbon|null $date
 * @property string $type
 * @property string|null $start_time
 * @property string|null $end_time
 * @property array<array-key, mixed>|null $title
 * @property array<array-key, mixed>|null $subtitle
 * @property array<array-key, mixed>|null $description
 * @property array<array-key, mixed>|null $theme
 * @property array<array-key, mixed>|null $location
 * @property array<array-key, mixed>|null $presented_by
 * @property int|null $presented_by_brand_id
 * @property array<array-key, mixed>|null $moderator
 * @property array<array-key, mixed>|null $panelists
 * @property array<array-key, mixed>|null $speakers
 * @property array<array-key, mixed>|null $settings
 * @property array<array-key, mixed>|null $more_details
 * @property int|null $order_column
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read User|null $creator
 * @property-read User|null $deleter
 * @property-read Event|null $event
 * @property-read array|null $poster_image
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 * @property-read mixed $translations
 * @property-read User|null $updater
 *
 * @method static Builder<static>|RundownItem active()
 * @method static \Database\Factories\RundownItemFactory factory($count = null, $state = [])
 * @method static Builder<static>|RundownItem newModelQuery()
 * @method static Builder<static>|RundownItem newQuery()
 * @method static Builder<static>|RundownItem onlyTrashed()
 * @method static Builder<static>|RundownItem ordered(string $direction = 'asc')
 * @method static Builder<static>|RundownItem query()
 * @method static Builder<static>|RundownItem whereCreatedAt($value)
 * @method static Builder<static>|RundownItem whereCreatedBy($value)
 * @method static Builder<static>|RundownItem whereDate($value)
 * @method static Builder<static>|RundownItem whereDeletedAt($value)
 * @method static Builder<static>|RundownItem whereDeletedBy($value)
 * @method static Builder<static>|RundownItem whereDescription($value)
 * @method static Builder<static>|RundownItem whereEndTime($value)
 * @method static Builder<static>|RundownItem whereEventId($value)
 * @method static Builder<static>|RundownItem whereId($value)
 * @method static Builder<static>|RundownItem whereIsActive($value)
 * @method static Builder<static>|RundownItem whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|RundownItem whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|RundownItem whereLocale(string $column, string $locale)
 * @method static Builder<static>|RundownItem whereLocales(string $column, array $locales)
 * @method static Builder<static>|RundownItem whereLocation($value)
 * @method static Builder<static>|RundownItem whereModerator($value)
 * @method static Builder<static>|RundownItem whereMoreDetails($value)
 * @method static Builder<static>|RundownItem whereOrderColumn($value)
 * @method static Builder<static>|RundownItem wherePanelists($value)
 * @method static Builder<static>|RundownItem wherePresentedBy($value)
 * @method static Builder<static>|RundownItem wherePresentedByBrandId($value)
 * @method static Builder<static>|RundownItem whereSettings($value)
 * @method static Builder<static>|RundownItem whereSpeakers($value)
 * @method static Builder<static>|RundownItem whereStartTime($value)
 * @method static Builder<static>|RundownItem whereSubtitle($value)
 * @method static Builder<static>|RundownItem whereTheme($value)
 * @method static Builder<static>|RundownItem whereTitle($value)
 * @method static Builder<static>|RundownItem whereType($value)
 * @method static Builder<static>|RundownItem whereUpdatedAt($value)
 * @method static Builder<static>|RundownItem whereUpdatedBy($value)
 * @method static Builder<static>|RundownItem withAllTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static Builder<static>|RundownItem withAllTagsOfAnyType($tags)
 * @method static Builder<static>|RundownItem withAnyTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static Builder<static>|RundownItem withAnyTagsOfAnyType($tags)
 * @method static Builder<static>|RundownItem withAnyTagsOfType(array|string $type)
 * @method static Builder<static>|RundownItem withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|RundownItem withoutTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static Builder<static>|RundownItem withoutTrashed()
 *
 * @mixin \Eloquent
 */
class RundownItem extends Model implements HasMedia, Sortable
{
    use ClearsResponseCache;
    use HasFactory;
    use HasMediaManager;
    use HasTags;
    use HasTranslations;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;
    use SortableTrait;

    protected $fillable = [
        'event_id',
        'date',
        'start_time',
        'end_time',
        'title',
        'subtitle',
        'description',
        'theme',
        'location',
        'presented_by',
        'moderator',
        'panelists',
        'speakers',
        'settings',
        'more_details',
        'is_active',
    ];

    public array $translatable = [
        'title',
        'subtitle',
        'description',
        'theme',
        'location',
        'presented_by',
        'moderator',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'string',
            'end_time' => 'string',
            'panelists' => 'array',
            'speakers' => 'array',
            'settings' => 'array',
            'more_details' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function responseCacheTags(): array
    {
        return ['rundown'];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function ($model) {
            if ($model->isForceDeleting() === false && auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }

            if ($model->isForceDeleting()) {
                $model->clearMediaCollection();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'date', 'start_time', 'end_time', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function buildSortQuery(): Builder
    {
        return static::query()
            ->where('event_id', $this->event_id)
            ->where('date', $this->date);
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function registerMediaConversions($media = null): void
    {
        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('poster')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(450)
            ->quality(85)
            ->performOnCollections('poster')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(900)
            ->quality(90)
            ->performOnCollections('poster');

        $this->addMediaConversion('lg')
            ->width(1200)
            ->quality(90)
            ->performOnCollections('poster');

        $this->addMediaConversion('xl')
            ->width(1500)
            ->quality(95)
            ->performOnCollections('poster');
    }

    public function getMediaCollections(): array
    {
        return [
            'poster' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
        ];
    }

    public function getPosterImageAttribute(): ?array
    {
        return $this->getMediaUrls('poster');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
