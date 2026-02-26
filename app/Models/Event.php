<?php

namespace App\Models;

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

class Event extends Model implements HasMedia, Sortable
{
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
