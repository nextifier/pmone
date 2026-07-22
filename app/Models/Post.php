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
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Tags\HasTags;
use Spatie\Tags\Tag;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $ulid
 * @property string $title
 * @property string|null $slug
 * @property string|null $excerpt
 * @property string $content
 * @property string $content_format
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string $status
 * @property string $visibility
 * @property Carbon|null $published_at
 * @property bool $featured
 * @property int|null $reading_time
 * @property array<array-key, mixed> $settings
 * @property string $source
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, User> $authors
 * @property-read int|null $authors_count
 * @property-read Collection<int, Tag> $categories
 * @property-read int|null $categories_count
 * @property-read User|null $creator
 * @property-read User|null $deleter
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read User|null $primaryAuthor
 * @property Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 * @property-read User|null $updater
 * @property-read Collection<int, Visit> $visits
 * @property-read int|null $visits_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post byAuthor(int $authorId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post byCreator(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post byStatus(string $status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post byTag(string $tagName)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post byVisibility(string $visibility)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post draft()
 * @method static \Database\Factories\PostFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post featured()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post findSimilarSlugs(string $attribute, array $config, string $slug)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post public()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post published()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post scheduled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post search(string $search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereContentFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereExcerpt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereFeatured($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereMetaDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereMetaTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereReadingTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereVisibility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post withAllTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post withAllTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post withAnyTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post withAnyTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post withAnyTagsOfType(array|string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array $config, string $slug)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post withoutTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Post extends Model implements HasMedia
{
    use ClearsResponseCache;
    use HasFactory;
    use HasMediaManager;
    use HasSlug;
    use HasTags;
    use HasTranslations;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    public array $translatable = ['title', 'excerpt', 'content', 'meta_title', 'meta_description'];

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'content_format',
        'meta_title',
        'meta_description',
        'status',
        'visibility',
        'published_at',
        'featured',
        'reading_time',
        'settings',
        'source',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'published_at' => 'datetime',
            'featured' => 'boolean',
            'reading_time' => 'integer',
        ];
    }

    protected static function responseCacheTags(): array
    {
        return ['blog-posts'];
    }

    /**
     * Public URLs on the event websites that this post renders as, so editing or
     * unpublishing an article drops its own page from the Cloudflare edge cache
     * rather than only the /news listing.
     *
     * This matters more than it looks: a single popular article was being
     * re-rendered ~4,000 times a day before edge caching existed, and it is
     * exactly the kind of page an editor expects to see update instantly.
     *
     * The slug is expanded across every locale by App\Support\EdgeCache. Both
     * the current and the original slug are returned so that renaming an
     * article also clears the URL it used to live at.
     */
    public function edgeCachePaths(): array
    {
        $slugs = array_unique(array_filter([
            $this->slug,
            $this->getOriginal('slug'),
        ]));

        return array_map(fn ($slug) => "/news/{$slug}", array_values($slugs));
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
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

        // The status follows the publish date (Ghost-style): a "published"
        // post dated in the future is really scheduled, and a "scheduled"
        // post whose date has passed (or is missing) is really published.
        // Normalizing here keeps every write path consistent - editor, API,
        // and the posts:publish-scheduled command.
        static::saving(function ($model) {
            if ($model->status === 'published' && $model->published_at?->isFuture()) {
                $model->status = 'scheduled';
            } elseif ($model->status === 'scheduled' && (! $model->published_at || $model->published_at->isPast())) {
                $model->status = 'published';
                $model->published_at ??= now();
            }
        });

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            // Auto-calculate reading time; the accessor resolves through the
            // global fallback chain (requested -> en -> any filled locale)
            $content = (string) $model->content;
            if ($content !== '' && empty($model->reading_time)) {
                $model->reading_time = $model->calculateReadingTime($content);
            }

            $model->fillMissingMetaTranslations();

            // Auto-set published_at when status is published
            if ($model->status === 'published' && empty($model->published_at)) {
                $model->published_at = now();
            }

            // Only set created_by if not already set (for imports)
            if (empty($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            // Recalculate reading time if content changed
            if ($model->isDirty('content')) {
                $content = (string) $model->content;
                if ($content !== '') {
                    $model->reading_time = $model->calculateReadingTime($content);
                }
            }

            $model->fillMissingMetaTranslations();

            // Auto-set published_at when status changes to published
            if ($model->isDirty('status') && $model->status === 'published' && empty($model->published_at)) {
                $model->published_at = now();
            }

            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function ($model) {
            if ($model->isForceDeleting() === false && auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }

            // Auto-cleanup media when post is force deleted
            // Note: Spatie MediaLibrary also handles this via its own observer,
            // but we keep this as a safety net for edge cases
            if ($model->isForceDeleting()) {
                foreach (array_keys($model->getMediaCollections()) as $collectionName) {
                    $model->clearMediaCollection($collectionName);
                }
            }
        });

    }

    /**
     * Backfill meta_title from title and meta_description from excerpt for
     * every locale that has a source value but no meta value yet.
     */
    public function fillMissingMetaTranslations(): void
    {
        foreach ($this->getTranslations('title') as $locale => $title) {
            if (filled($title) && blank($this->getTranslation('meta_title', $locale, false))) {
                $this->setTranslation('meta_title', $locale, $title);
            }
        }

        foreach ($this->getTranslations('excerpt') as $locale => $excerpt) {
            if (filled($excerpt) && blank($this->getTranslation('meta_description', $locale, false))) {
                $this->setTranslation('meta_description', $locale, Str::limit($excerpt, 160));
            }
        }
    }

    /**
     * Calculate reading time in minutes based on word count
     */
    protected function calculateReadingTime(string $content): int
    {
        $wordCount = str_word_count(strip_tags($content));
        $wordsPerMinute = 200; // Average reading speed

        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'slug', 'status', 'visibility', 'published_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function registerMediaConversions($media = null): void
    {
        // Featured image conversions (maintain aspect ratio, no crop).
        // og_image collections get no conversions: only the 1200x630 original
        // is ever served, so extra sizes would just waste disk.
        $this->addMediaConversion('lqip')
            ->width(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('featured_image')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(450)
            ->quality(85)
            ->performOnCollections('featured_image')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(900)
            ->quality(90)
            ->performOnCollections('featured_image')
            ->nonQueued();

        $this->addMediaConversion('lg')
            ->width(1200)
            ->quality(90)
            ->performOnCollections('featured_image')
            ->nonQueued();

        $this->addMediaConversion('xl')
            ->width(1500)
            ->quality(95)
            ->performOnCollections('featured_image')
            ->nonQueued();

        // Content images conversions (maintain aspect ratio, no crop)
        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('content_images')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(450)
            ->quality(85)
            ->performOnCollections('content_images')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(900)
            ->quality(90)
            ->performOnCollections('content_images');

        $this->addMediaConversion('lg')
            ->width(1200)
            ->quality(90)
            ->performOnCollections('content_images')
            ->nonQueued();

        $this->addMediaConversion('xl')
            ->width(1500)
            ->quality(95)
            ->performOnCollections('content_images');
    }

    public function getMediaCollections(): array
    {
        return [
            'featured_image' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
                'max_size' => 20480, // 20MB
            ],
            'og_image' => [
                'single_file' => true,
                // No SVG: crawlers don't support SVG og:images and cropToOg
                // can't normalize them to 1200x630.
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
                'max_size' => 20480, // 20MB
            ],
            'og_image_generated' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
            'content_images' => [
                'single_file' => false,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
                'max_size' => 20480, // 20MB per image
            ],
        ];
    }

    /**
     * The OG image URL to serve: a manual upload always wins over the
     * auto-generated card; null means the website renders its default card.
     */
    public function effectiveOgImageUrl(): ?string
    {
        return $this->getFirstMediaUrl('og_image')
            ?: $this->getFirstMediaUrl('og_image_generated')
            ?: null;
    }

    /**
     * Get the post's visits (polymorphic)
     */
    public function visits(): MorphMany
    {
        return $this->morphMany(Visit::class, 'visitable');
    }

    /**
     * Audit trail relationships
     */
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

    /**
     * Post authors (many-to-many with pivot data)
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_authors')
            ->withPivot('order')
            ->orderBy('post_authors.order')
            ->withTimestamps();
    }

    /**
     * Get the primary author (first author based on order)
     */
    public function primaryAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get categories as tags with type 'category'
     * This returns a MorphToMany relationship to support eager loading
     */
    public function categories(): MorphToMany
    {
        return $this
            ->morphToMany(Tag::class, 'taggable', 'taggables', null, 'tag_id')
            ->where('type', 'category')
            ->orderBy('order_column');
    }

    /**
     * Scope: Published posts
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope: Scheduled posts (to be published later)
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')
            ->where('published_at', '>', now());
    }

    /**
     * Scope: Draft posts
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope: Public posts
     */
    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    /**
     * Scope: Featured posts
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    /**
     * Scope: Filter by author (using post_authors pivot table)
     */
    public function scopeByAuthor($query, int $authorId)
    {
        return $query->whereHas('authors', function ($q) use ($authorId) {
            $q->where('users.id', $authorId);
        });
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by visibility
     */
    public function scopeByVisibility($query, string $visibility)
    {
        return $query->where('visibility', $visibility);
    }

    /**
     * Scope: Filter by creator
     */
    public function scopeByCreator($query, int $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope: Filter by tag
     */
    public function scopeByTag($query, string $tagName)
    {
        return $query->withAnyTags([$tagName], 'post');
    }

    /**
     * Scope: Search posts by title, excerpt, or content (case-insensitive).
     * Columns hold locale-keyed json, so the search spans every locale;
     * PostgreSQL json columns have no LIKE operator and need a text cast.
     */
    public function scopeSearch($query, string $search)
    {
        if (config('database.default') === 'pgsql') {
            return $query->where(function ($q) use ($search) {
                $q->whereRaw('title::text ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('excerpt::text ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('content::text ILIKE ?', ["%{$search}%"]);
            });
        }

        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('excerpt', 'like', "%{$search}%")
                ->orWhere('content', 'like', "%{$search}%");
        });
    }

    /**
     * Scope: Order by the resolved title. Plain ORDER BY on a PostgreSQL json
     * column has no ordering operator, so sort on the extracted value using
     * the same precedence as the display fallback (en, then other locales).
     */
    public function scopeOrderByTitle($query, string $direction = 'asc')
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        if (config('database.default') === 'pgsql') {
            return $query->orderByRaw(
                "COALESCE(title->>'en', title->>'id', title->>'zh', title->>'ja', title->>'ko') {$direction}"
            );
        }

        return $query->orderByRaw(
            "COALESCE(json_extract(title, '$.en'), json_extract(title, '$.id'), json_extract(title, '$.zh'), json_extract(title, '$.ja'), json_extract(title, '$.ko')) {$direction}"
        );
    }

    /**
     * Check if post is published
     */
    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at?->isPast();
    }

    /**
     * Check if post is scheduled
     */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled' && $this->published_at?->isFuture();
    }

    /**
     * Publish the post now
     */
    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => $this->published_at ?? now(),
        ]);
    }

    /**
     * Schedule the post for later
     */
    public function schedule(\DateTimeInterface $publishAt): void
    {
        $this->update([
            'status' => 'scheduled',
            'published_at' => $publishAt,
        ]);
    }

    /**
     * Update existing NULL type tags before syncing to prevent duplicates
     *
     * Since Spatie's findOrCreate() searches by name AND type, if a tag
     * exists with type=NULL and we try to create with type='post', it will
     * create a duplicate. This method updates NULL tags first.
     */
    public function syncPostTags(array $tagNames): void
    {
        foreach ($tagNames as $tagName) {
            // Find tag by name regardless of type
            $existingTag = Tag::query()
                ->whereRaw("LOWER(name->>'en') = ?", [strtolower($tagName)])
                ->whereNull('type')
                ->first();

            // Update type to 'post' if found
            if ($existingTag) {
                $existingTag->update(['type' => 'post']);
            }
        }

        // Now use native Spatie syncTagsWithType
        $this->syncTagsWithType($tagNames, 'post');
    }
}
