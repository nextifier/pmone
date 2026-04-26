<?php

namespace App\Models;

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

/**
 * @property int $id
 * @property string $ulid
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property numeric $commission_rate
 * @property numeric $tax_percentage
 * @property numeric $service_charge_percentage
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int $event_id
 * @property int|null $star_rating
 * @property string|null $google_maps_link
 * @property string|null $google_maps_embed_src
 * @property string|null $cancellation_policy
 * @property array<array-key, mixed>|null $settings
 * @property array<array-key, mixed>|null $more_details
 * @property int|null $order_column
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, \App\Models\HotelEventAllotment> $allotments
 * @property-read int|null $allotments_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\User|null $deleter
 * @property-read \App\Models\Event|null $event
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, \App\Models\Reservation> $reservations
 * @property-read int|null $reservations_count
 * @property-read Collection<int, \App\Models\RoomType> $roomTypes
 * @property-read int|null $room_types_count
 * @property Collection<int, \Spatie\Tags\Tag> $tags
 * @property-read int|null $tags_count
 * @property-read Collection<int, \App\Models\HotelTransferOption> $transferOptions
 * @property-read int|null $transfer_options_count
 * @property-read \App\Models\User|null $updater
 * @method static Builder<static>|Hotel active()
 * @method static \Database\Factories\HotelFactory factory($count = null, $state = [])
 * @method static Builder<static>|Hotel findSimilarSlugs(string $attribute, array $config, string $slug)
 * @method static Builder<static>|Hotel newModelQuery()
 * @method static Builder<static>|Hotel newQuery()
 * @method static Builder<static>|Hotel onlyTrashed()
 * @method static Builder<static>|Hotel ordered(string $direction = 'asc')
 * @method static Builder<static>|Hotel query()
 * @method static Builder<static>|Hotel whereAddress($value)
 * @method static Builder<static>|Hotel whereCancellationPolicy($value)
 * @method static Builder<static>|Hotel whereCity($value)
 * @method static Builder<static>|Hotel whereCommissionRate($value)
 * @method static Builder<static>|Hotel whereContactEmail($value)
 * @method static Builder<static>|Hotel whereContactPhone($value)
 * @method static Builder<static>|Hotel whereCountry($value)
 * @method static Builder<static>|Hotel whereCreatedAt($value)
 * @method static Builder<static>|Hotel whereCreatedBy($value)
 * @method static Builder<static>|Hotel whereDeletedAt($value)
 * @method static Builder<static>|Hotel whereDeletedBy($value)
 * @method static Builder<static>|Hotel whereDescription($value)
 * @method static Builder<static>|Hotel whereEventId($value)
 * @method static Builder<static>|Hotel whereGoogleMapsEmbedSrc($value)
 * @method static Builder<static>|Hotel whereGoogleMapsLink($value)
 * @method static Builder<static>|Hotel whereId($value)
 * @method static Builder<static>|Hotel whereIsActive($value)
 * @method static Builder<static>|Hotel whereMoreDetails($value)
 * @method static Builder<static>|Hotel whereName($value)
 * @method static Builder<static>|Hotel whereOrderColumn($value)
 * @method static Builder<static>|Hotel whereServiceChargePercentage($value)
 * @method static Builder<static>|Hotel whereSettings($value)
 * @method static Builder<static>|Hotel whereSlug($value)
 * @method static Builder<static>|Hotel whereStarRating($value)
 * @method static Builder<static>|Hotel whereTaxPercentage($value)
 * @method static Builder<static>|Hotel whereUlid($value)
 * @method static Builder<static>|Hotel whereUpdatedAt($value)
 * @method static Builder<static>|Hotel whereUpdatedBy($value)
 * @method static Builder<static>|Hotel withAllTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static Builder<static>|Hotel withAllTagsOfAnyType($tags)
 * @method static Builder<static>|Hotel withAnyTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static Builder<static>|Hotel withAnyTagsOfAnyType($tags)
 * @method static Builder<static>|Hotel withAnyTagsOfType(array|string $type)
 * @method static Builder<static>|Hotel withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Hotel withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array $config, string $slug)
 * @method static Builder<static>|Hotel withoutTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static Builder<static>|Hotel withoutTrashed()
 * @mixin \Eloquent
 */
class Hotel extends Model implements HasMedia, Sortable
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
        'event_id',
        'slug',
        'name',
        'description',
        'star_rating',
        'address',
        'city',
        'country',
        'google_maps_link',
        'google_maps_embed_src',
        'contact_email',
        'contact_phone',
        'cancellation_policy',
        'commission_rate',
        'tax_percentage',
        'service_charge_percentage',
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
            'commission_rate' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'service_charge_percentage' => 'decimal:2',
            'is_active' => 'boolean',
            'star_rating' => 'integer',
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

    public function scopeWithUniqueSlugConstraints(Builder $query, Model $model, string $attribute, array $config, string $slug): Builder
    {
        if (! empty($model->event_id)) {
            $query->where('event_id', $model->event_id);
        }

        return $query;
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
            ->logOnly(['name', 'slug', 'is_active', 'commission_rate', 'tax_percentage'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function registerMediaConversions($media = null): void
    {
        foreach (['featured', 'gallery'] as $collection) {
            $this->addMediaConversion('lqip')
                ->width(20)
                ->height(20)
                ->quality(10)
                ->blur(10)
                ->performOnCollections($collection)
                ->nonQueued();

            $this->addMediaConversion('sm')
                ->width(450)
                ->quality(85)
                ->performOnCollections($collection)
                ->nonQueued();

            $this->addMediaConversion('md')
                ->width(900)
                ->quality(90)
                ->performOnCollections($collection);

            $this->addMediaConversion('lg')
                ->width(1200)
                ->quality(90)
                ->performOnCollections($collection);

            $this->addMediaConversion('xl')
                ->width(1500)
                ->quality(95)
                ->performOnCollections($collection);
        }
    }

    public function getMediaCollections(): array
    {
        return [
            'featured' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
            'gallery' => [
                'single_file' => false,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
                'max_size' => 20480,
            ],
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    public function transferOptions(): HasMany
    {
        return $this->hasMany(HotelTransferOption::class);
    }

    public function allotments(): HasMany
    {
        return $this->hasMany(HotelEventAllotment::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
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
