<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasMediaManager;
use App\Traits\HasSlug;
use App\Traits\NormalizesAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
 * @property int $event_id
 * @property string $name
 * @property string $slug
 * @property string|null $organization
 * @property array<array-key, mixed>|null $more_details
 * @property array<array-key, mixed>|null $settings
 * @property string $status
 * @property string $visibility
 * @property bool $is_featured
 * @property int|null $order_column
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $title
 * @property string|null $bio
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read User|null $creator
 * @property-read User|null $deleter
 * @property-read Event|null $event
 * @property-read array|null $profile_image
 * @property-read Collection<int, Link> $links
 * @property-read int|null $links_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 * @property-read User|null $updater
 *
 * @method static Builder<static>|Guest active()
 * @method static Builder<static>|Guest byStatus(string $status)
 * @method static \Database\Factories\GuestFactory factory($count = null, $state = [])
 * @method static Builder<static>|Guest featured()
 * @method static Builder<static>|Guest findSimilarSlugs(string $attribute, array $config, string $slug)
 * @method static Builder<static>|Guest forEvent(int $eventId)
 * @method static Builder<static>|Guest newModelQuery()
 * @method static Builder<static>|Guest newQuery()
 * @method static Builder<static>|Guest onlyTrashed()
 * @method static Builder<static>|Guest ordered(string $direction = 'asc')
 * @method static Builder<static>|Guest query()
 * @method static Builder<static>|Guest whereBio($value)
 * @method static Builder<static>|Guest whereCreatedAt($value)
 * @method static Builder<static>|Guest whereCreatedBy($value)
 * @method static Builder<static>|Guest whereDeletedAt($value)
 * @method static Builder<static>|Guest whereDeletedBy($value)
 * @method static Builder<static>|Guest whereEventId($value)
 * @method static Builder<static>|Guest whereId($value)
 * @method static Builder<static>|Guest whereIsFeatured($value)
 * @method static Builder<static>|Guest whereMoreDetails($value)
 * @method static Builder<static>|Guest whereName($value)
 * @method static Builder<static>|Guest whereOrderColumn($value)
 * @method static Builder<static>|Guest whereOrganization($value)
 * @method static Builder<static>|Guest whereSettings($value)
 * @method static Builder<static>|Guest whereSlug($value)
 * @method static Builder<static>|Guest whereStatus($value)
 * @method static Builder<static>|Guest whereTitle($value)
 * @method static Builder<static>|Guest whereUlid($value)
 * @method static Builder<static>|Guest whereUpdatedAt($value)
 * @method static Builder<static>|Guest whereUpdatedBy($value)
 * @method static Builder<static>|Guest whereVisibility($value)
 * @method static Builder<static>|Guest withAllTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static Builder<static>|Guest withAllTagsOfAnyType($tags)
 * @method static Builder<static>|Guest withAnyTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static Builder<static>|Guest withAnyTagsOfAnyType($tags)
 * @method static Builder<static>|Guest withAnyTagsOfType(array|string $type)
 * @method static Builder<static>|Guest withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Guest withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array $config, string $slug)
 * @method static Builder<static>|Guest withoutTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static Builder<static>|Guest withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Guest extends Model implements HasMedia, Sortable
{
    use ClearsResponseCache;
    use HasFactory;
    use HasMediaManager;
    use HasSlug;
    use HasTags;
    use InteractsWithMedia;
    use LogsActivity;
    use NormalizesAttributes;
    use SoftDeletes;
    use SortableTrait;

    /** @var array<string, string> */
    protected array $normalizes = [
        'name' => 'personName',
    ];

    protected $fillable = [
        'event_id',
        'name',
        'slug',
        'title',
        'bio',
        'organization',
        'more_details',
        'settings',
        'status',
        'visibility',
        'is_featured',
        'order_column',
    ];

    /** @var array<string, mixed> */
    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'more_details' => 'array',
            'settings' => 'array',
            'is_featured' => 'boolean',
        ];
    }

    protected static function responseCacheTags(): array
    {
        return ['guests'];
    }

    /**
     * Public URLs this guest renders as on the event websites.
     *
     * Required, not optional: /guests/* is cached at the edge for 7 days
     * (HTML_TTL_DETAIL in the events repo), which is only safe because saving
     * here drops the exact URL. Both slugs are returned so a rename also clears
     * the old URL.
     */
    public function edgeCachePaths(): array
    {
        $slugs = array_unique(array_filter([
            $this->slug,
            $this->getOriginal('slug'),
        ]));

        return array_map(fn ($slug) => "/guests/{$slug}", array_values($slugs));
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
                'includeTrashed' => true,
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
        return $query->where('event_id', $model->event_id);
    }

    public function buildSortQuery(): Builder
    {
        return static::query()->where('event_id', $this->event_id);
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
            ->logOnly(['name', 'slug', 'organization', 'status', 'visibility', 'is_featured'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($projectId = $this->event?->project_id) {
            $activity->properties = $activity->properties->put('project_id', $projectId);
        }
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // profile_image conversions (4:5 portrait ratio).
        // webp format keeps alpha transparency (e.g. cut-out cosplayer PNGs)
        // and is smaller; the frontend already requests webp.
        $this->addMediaConversion('lqip')
            ->width(16)
            ->height(20)
            ->format('webp')
            ->quality(10)
            ->blur(10)
            ->performOnCollections('profile_image')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(240)
            ->height(300)
            ->format('webp')
            ->quality(85)
            ->performOnCollections('profile_image')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(480)
            ->height(600)
            ->format('webp')
            ->quality(90)
            ->performOnCollections('profile_image');

        $this->addMediaConversion('lg')
            ->width(800)
            ->height(1000)
            ->format('webp')
            ->quality(90)
            ->performOnCollections('profile_image');

        $this->addMediaConversion('xl')
            ->width(1080)
            ->height(1350)
            ->format('webp')
            ->quality(95)
            ->performOnCollections('profile_image');

        // bio_images conversions (responsive width-only for TipTap content)
        $this->addMediaConversion('lqip')
            ->width(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('bio_images')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(450)
            ->quality(85)
            ->performOnCollections('bio_images')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(900)
            ->quality(90)
            ->performOnCollections('bio_images');

        $this->addMediaConversion('lg')
            ->width(1200)
            ->quality(90)
            ->performOnCollections('bio_images');

        $this->addMediaConversion('xl')
            ->width(1500)
            ->quality(95)
            ->performOnCollections('bio_images');
    }

    public function getMediaCollections(): array
    {
        return [
            'profile_image' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
            'bio_images' => [
                'single_file' => false,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                'max_size' => 20480,
            ],
        ];
    }

    public function getProfileImageAttribute(): ?array
    {
        return $this->getMediaUrls('profile_image');
    }

    // Relationships

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function links(): MorphMany
    {
        return $this->morphMany(Link::class, 'linkable')
            ->orderBy('order');
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

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }
}
