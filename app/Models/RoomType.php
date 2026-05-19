<?php

namespace App\Models;

use App\Enums\PricingType;
use App\Traits\ClearsResponseCache;
use App\Traits\HasMediaManager;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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

/**
 * @property int $id
 * @property string $ulid
 * @property int $hotel_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $max_pax
 * @property string|null $bed_type
 * @property numeric|null $area_sqm
 * @property numeric $base_rate
 * @property bool $breakfast_included
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property bool $smoking_allowed
 * @property string|null $cancellation_policy
 * @property array<array-key, mixed>|null $settings
 * @property array<array-key, mixed>|null $more_details
 * @property int|null $order_column
 * @property PricingType $pricing_type
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, HotelEventAllotment> $allotments
 * @property-read int|null $allotments_count
 * @property-read User|null $creator
 * @property-read User|null $deleter
 * @property-read Hotel|null $hotel
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, RoomTypePricingPeriod> $pricingPeriods
 * @property-read int|null $pricing_periods_count
 * @property-read Collection<int, ReservationItem> $reservationItems
 * @property-read int|null $reservation_items_count
 * @property Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 * @property-read User|null $updater
 *
 * @method static Builder<static>|RoomType active()
 * @method static \Database\Factories\RoomTypeFactory factory($count = null, $state = [])
 * @method static Builder<static>|RoomType findSimilarSlugs(string $attribute, array $config, string $slug)
 * @method static Builder<static>|RoomType newModelQuery()
 * @method static Builder<static>|RoomType newQuery()
 * @method static Builder<static>|RoomType onlyTrashed()
 * @method static Builder<static>|RoomType ordered(string $direction = 'asc')
 * @method static Builder<static>|RoomType query()
 * @method static Builder<static>|RoomType whereAreaSqm($value)
 * @method static Builder<static>|RoomType whereBaseRate($value)
 * @method static Builder<static>|RoomType whereBedType($value)
 * @method static Builder<static>|RoomType whereBreakfastIncluded($value)
 * @method static Builder<static>|RoomType whereCancellationPolicy($value)
 * @method static Builder<static>|RoomType whereCreatedAt($value)
 * @method static Builder<static>|RoomType whereCreatedBy($value)
 * @method static Builder<static>|RoomType whereDeletedAt($value)
 * @method static Builder<static>|RoomType whereDeletedBy($value)
 * @method static Builder<static>|RoomType whereDescription($value)
 * @method static Builder<static>|RoomType whereHotelId($value)
 * @method static Builder<static>|RoomType whereId($value)
 * @method static Builder<static>|RoomType whereIsActive($value)
 * @method static Builder<static>|RoomType whereMaxPax($value)
 * @method static Builder<static>|RoomType whereMoreDetails($value)
 * @method static Builder<static>|RoomType whereName($value)
 * @method static Builder<static>|RoomType whereOrderColumn($value)
 * @method static Builder<static>|RoomType wherePricingType($value)
 * @method static Builder<static>|RoomType whereSettings($value)
 * @method static Builder<static>|RoomType whereSlug($value)
 * @method static Builder<static>|RoomType whereSmokingAllowed($value)
 * @method static Builder<static>|RoomType whereUlid($value)
 * @method static Builder<static>|RoomType whereUpdatedAt($value)
 * @method static Builder<static>|RoomType whereUpdatedBy($value)
 * @method static Builder<static>|RoomType withAllTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static Builder<static>|RoomType withAllTagsOfAnyType($tags)
 * @method static Builder<static>|RoomType withAnyTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static Builder<static>|RoomType withAnyTagsOfAnyType($tags)
 * @method static Builder<static>|RoomType withAnyTagsOfType(array|string $type)
 * @method static Builder<static>|RoomType withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|RoomType withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array $config, string $slug)
 * @method static Builder<static>|RoomType withoutTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static Builder<static>|RoomType withoutTrashed()
 *
 * @mixin \Eloquent
 */
class RoomType extends Model implements HasMedia, Sortable
{
    use ClearsResponseCache;
    use HasFactory;
    use HasMediaManager;
    use HasSlug;
    use HasTags;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;
    use SortableTrait;

    protected $fillable = [
        'hotel_id',
        'slug',
        'name',
        'description',
        'max_pax',
        'bed_type',
        'area_sqm',
        'base_rate',
        'pricing_type',
        'breakfast_included',
        'smoking_allowed',
        'cancellation_policy',
        'is_active',
        'settings',
        'more_details',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'max_pax' => 'integer',
            'area_sqm' => 'decimal:2',
            'base_rate' => 'decimal:2',
            'pricing_type' => PricingType::class,
            'breakfast_included' => 'boolean',
            'smoking_allowed' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
            'more_details' => 'array',
        ];
    }

    protected static function responseCacheTags(): array
    {
        return ['hotels'];
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    public function scopeWithUniqueSlugConstraints(
        Builder $query,
        Model $model,
        string $attribute,
        array $config,
        string $slug
    ): Builder {
        return $query->where('hotel_id', $model->hotel_id);
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
            ->logOnly(['name', 'slug', 'base_rate', 'pricing_type', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function registerMediaConversions($media = null): void
    {
        $this->addMediaConversion('lqip')
            ->width(20)->height(20)->quality(10)->blur(10)
            ->performOnCollections('gallery')->nonQueued();

        $this->addMediaConversion('sm')
            ->width(450)->quality(85)
            ->performOnCollections('gallery')->nonQueued();

        $this->addMediaConversion('md')
            ->width(900)->quality(90)
            ->performOnCollections('gallery');

        $this->addMediaConversion('lg')
            ->width(1200)->quality(90)
            ->performOnCollections('gallery');

        $this->addMediaConversion('xl')
            ->width(1500)->quality(95)
            ->performOnCollections('gallery');
    }

    public function getMediaCollections(): array
    {
        return [
            'gallery' => [
                'single_file' => false,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
                'max_size' => 20480,
            ],
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function allotments(): HasMany
    {
        return $this->hasMany(HotelEventAllotment::class);
    }

    public function pricingPeriods(): HasMany
    {
        return $this->hasMany(RoomTypePricingPeriod::class)->orderBy('start_date');
    }

    public function reservationItems(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
