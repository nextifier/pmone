<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasMediaManager;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $company_name
 * @property string|null $company_address
 * @property string|null $company_email
 * @property string|null $company_phone
 * @property array<array-key, mixed>|null $custom_fields
 * @property string $status
 * @property string $visibility
 * @property int|null $order_column
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, BrandEvent> $brandEvents
 * @property-read int|null $brand_events_count
 * @property-read User|null $creator
 * @property-read User|null $deleter
 * @property-read Collection<int, Event> $events
 * @property-read int|null $events_count
 * @property-read array|null $brand_logo
 * @property-read array $business_categories_list
 * @property-read Collection<int, Link> $links
 * @property-read int|null $links_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 * @property-read User|null $updater
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand byStatus(string $status)
 * @method static \Database\Factories\BrandFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand findSimilarSlugs(string $attribute, array $config, string $slug)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereCompanyAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereCompanyEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereCompanyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereCompanyPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereCustomFields($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereVisibility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand withAllTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand withAllTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand withAnyTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand withAnyTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand withAnyTagsOfType(array|string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array $config, string $slug)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand withoutTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Brand extends Model implements HasMedia, Sortable
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
        'name',
        'slug',
        'description',
        'company_name',
        'company_address',
        'company_email',
        'company_phone',
        'custom_fields',
        'status',
        'visibility',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
        ];
    }

    protected static function responseCacheTags(): array
    {
        return ['brands'];
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

    public function getRouteKeyName(): string
    {
        return 'slug';
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

                // Force-delete brand events per-instance so their media (and their
                // promotion posts' media) is removed; DB FK cascade bypasses events.
                $model->brandEvents()->get()->each(fn ($child) => $child->delete());
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'status', 'visibility'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function registerMediaConversions($media = null): void
    {
        // Brand logo conversions (square)
        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('brand_logo')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(200)
            ->height(200)
            ->quality(85)
            ->performOnCollections('brand_logo')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(400)
            ->height(400)
            ->quality(90)
            ->performOnCollections('brand_logo');

        $this->addMediaConversion('lg')
            ->width(800)
            ->height(800)
            ->quality(90)
            ->performOnCollections('brand_logo');

        $this->addMediaConversion('xl')
            ->width(1080)
            ->height(1080)
            ->quality(95)
            ->performOnCollections('brand_logo');

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
            'brand_logo' => [
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
     * Get brand logo URLs.
     */
    public function getBrandLogoAttribute(): ?array
    {
        return $this->getMediaUrls('brand_logo');
    }

    /**
     * Sync business categories using spatie/laravel-tags, scoped to a project.
     *
     * @param  array<string>  $names
     */
    public function syncBusinessCategories(array $names, ?int $projectId = null): void
    {
        if ($projectId) {
            $this->syncTagsWithType($names, "business_category:{$projectId}");
        } else {
            $this->syncTagsWithType($names, 'business_category');
        }
    }

    /**
     * Get business categories for a specific project.
     *
     * @return array<string>
     */
    public function getBusinessCategoriesForProject(int $projectId): array
    {
        return $this->tagsWithType("business_category:{$projectId}")->pluck('name')->toArray();
    }

    /**
     * Get all business categories (aggregated across all project-scoped types + legacy unscoped).
     */
    public function getBusinessCategoriesListAttribute(): array
    {
        return $this->tags
            ->filter(fn ($tag) => str_starts_with($tag->type, 'business_category'))
            ->pluck('name')
            ->unique()
            ->values()
            ->toArray();
    }

    // Relationships

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'brand_event')
            ->withPivot(['id', 'booth_number', 'booth_size', 'booth_type', 'sales_id', 'status', 'notes', 'custom_fields', 'order_column'])
            ->withTimestamps();
    }

    public function brandEvents(): HasMany
    {
        return $this->hasMany(BrandEvent::class)->ordered();
    }

    public function exhibitorLeads(): HasMany
    {
        return $this->hasMany(ExhibitorLead::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'brand_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Customer-facing notification recipients: all brand members plus the
     * company email, de-duplicated case-insensitively. Used for order
     * confirmation, invoice, and receipt emails.
     *
     * @return array<int, string>
     */
    public function recipientEmails(): array
    {
        $this->loadMissing('users');

        return $this->users
            ->pluck('email')
            ->push($this->company_email)
            ->filter(fn ($email) => is_string($email) && trim($email) !== '')
            ->map(fn ($email) => trim($email))
            ->unique(fn ($email) => strtolower($email))
            ->values()
            ->all();
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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
