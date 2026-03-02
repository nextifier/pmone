<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property string $title
 * @property string $slug
 * @property int|null $edition_number
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string|null $location
 * @property string|null $location_link
 * @property string|null $hall
 * @property string $status
 * @property string $visibility
 * @property array<array-key, mixed>|null $settings
 * @property array<array-key, mixed>|null $custom_fields
 * @property int|null $order_column
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $order_form_content
 * @property numeric|null $gross_area
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $order_form_deadline
 * @property \Illuminate\Support\Carbon|null $promotion_post_deadline
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BrandEvent> $brandEvents
 * @property-read int|null $brand_events_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Brand> $brands
 * @property-read int|null $brands_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Event> $conjunctionEvents
 * @property-read int|null $conjunction_events_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\User|null $deleter
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EventProduct> $eventProducts
 * @property-read int|null $event_products_count
 * @property-read string|null $date_label
 * @property-read string|null $edition_number_with_ordinal
 * @property-read string|null $end_time
 * @property-read array|null $poster_image
 * @property-read string|null $start_time
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $orders
 * @property-read int|null $orders_count
 * @property-read \App\Models\Project $project
 * @property-read \App\Models\User|null $updater
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event byStatus(string $status)
 * @method static \Database\Factories\EventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event published()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereCustomFields($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereEditionNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereGrossArea($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereHall($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereLocationLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereOrderFormContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereOrderFormDeadline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event wherePromotionPostDeadline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereVisibility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Event extends Model implements HasMedia, Sortable
{
    use ClearsResponseCache;
    use HasFactory;
    use HasMediaManager;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;
    use SortableTrait;

    protected $fillable = [
        'project_id',
        'title',
        'slug',
        'edition_number',
        'description',
        'start_date',
        'end_date',
        'location',
        'location_link',
        'hall',
        'status',
        'is_active',
        'visibility',
        'settings',
        'custom_fields',
        'order_form_content',
        'order_form_deadline',
        'promotion_post_deadline',
        'gross_area',
    ];

    protected $appends = [
        'edition_number_with_ordinal',
        'date_label',
        'start_time',
        'end_time',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'custom_fields' => 'array',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'edition_number' => 'integer',
            'gross_area' => 'decimal:2',
            'is_active' => 'boolean',
            'order_form_deadline' => 'datetime',
            'promotion_post_deadline' => 'datetime',
        ];
    }

    protected static function responseCacheTags(): array
    {
        return ['events'];
    }

    public function getEditionNumberWithOrdinalAttribute(): ?string
    {
        if ($this->edition_number === null) {
            return null;
        }

        return Number::ordinal($this->edition_number);
    }

    public function getDateLabelAttribute(): ?string
    {
        if (! $this->start_date) {
            return null;
        }

        $start = $this->start_date;

        if (! $this->end_date || $start->isSameDay($this->end_date)) {
            return $start->format('D, M j, Y');
        }

        $end = $this->end_date;

        if ($start->year !== $end->year) {
            return $start->format('M j, Y').' - '.$end->format('M j, Y');
        }

        if ($start->month !== $end->month) {
            return $start->format('M j').' - '.$end->format('M j, Y');
        }

        return $start->format('D').'-'.$end->format('D').', '.$start->format('M j').'-'.$end->format('j, Y');
    }

    public function getStartTimeAttribute(): ?string
    {
        if (! $this->start_date) {
            return null;
        }

        return $this->start_date->format('g:i A');
    }

    public function getEndTimeAttribute(): ?string
    {
        if (! $this->end_date) {
            return null;
        }

        return $this->end_date->format('g:i A');
    }

    public function buildSortQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()->where('project_id', $this->project_id);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            // Generate slug from title if not provided
            if (empty($model->slug) && ! empty($model->title)) {
                $baseSlug = Str::slug($model->title);

                if (empty($baseSlug)) {
                    $baseSlug = 'event';
                }

                $slug = $baseSlug;
                $counter = 1;

                while (static::where('project_id', $model->project_id)
                    ->where('slug', $slug)
                    ->exists()
                ) {
                    $slug = $baseSlug.'-'.$counter;
                    $counter++;
                }

                $model->slug = $slug;
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
            ->logOnly(['title', 'slug', 'status', 'visibility'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(\Spatie\Activitylog\Models\Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->put('project_id', $this->project_id);
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function registerMediaConversions($media = null): void
    {
        // Poster image conversions (square-ish)
        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('poster_image')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(200)
            ->height(200)
            ->quality(85)
            ->performOnCollections('poster_image')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(400)
            ->height(400)
            ->quality(90)
            ->performOnCollections('poster_image')
            ->nonQueued();

        $this->addMediaConversion('lg')
            ->width(800)
            ->height(800)
            ->quality(90)
            ->performOnCollections('poster_image');

        $this->addMediaConversion('xl')
            ->width(1080)
            ->height(1080)
            ->quality(95)
            ->performOnCollections('poster_image');

        // Description content image conversions
        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('description_images')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(450)
            ->quality(85)
            ->performOnCollections('description_images')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(900)
            ->quality(90)
            ->performOnCollections('description_images');

        $this->addMediaConversion('lg')
            ->width(1200)
            ->quality(90)
            ->performOnCollections('description_images');

        $this->addMediaConversion('xl')
            ->width(1500)
            ->quality(95)
            ->performOnCollections('description_images');
    }

    public function getMediaCollections(): array
    {
        return [
            'poster_image' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
            ],
            'description_images' => [
                'single_file' => false,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
                'max_size' => 20480,
            ],
        ];
    }

    /**
     * Get poster image URLs for the event.
     */
    public function getPosterImageAttribute(): ?array
    {
        return $this->getMediaUrls('poster_image');
    }

    // Relationships

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    public function brandEvents(): HasMany
    {
        return $this->hasMany(BrandEvent::class)->ordered();
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'brand_event')
            ->withPivot(['id', 'booth_number', 'booth_size', 'booth_type', 'sales_id', 'status', 'notes', 'custom_fields', 'order_column'])
            ->withTimestamps();
    }

    public function eventProducts(): HasMany
    {
        return $this->hasMany(EventProduct::class)->ordered();
    }

    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(
            Order::class,
            BrandEvent::class,
            'event_id',
            'brand_event_id',
        );
    }

    public function conjunctionEvents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'event_conjunctions', 'event_id', 'conjunction_event_id')
            ->withPivot(['conjunction_label', 'order_column'])
            ->withTimestamps()
            ->orderByPivot('order_column');
    }

    // Scopes

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'published']);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
