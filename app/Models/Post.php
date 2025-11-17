<?php

namespace App\Models;

use App\Traits\HasMediaManager;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

class Post extends Model implements HasMedia
{
    use HasFactory;
    use HasMediaManager;
    use HasTags;
    use InteractsWithMedia;
    use LogsActivity;
    use Sluggable;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'content_format',
        'featured_image',
        'meta_title',
        'meta_description',
        'og_image',
        'status',
        'visibility',
        'published_at',
        'featured',
        'reading_time',
        'settings',
        'source',
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

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            // Auto-calculate reading time based on content
            if (! empty($model->content) && empty($model->reading_time)) {
                $model->reading_time = $model->calculateReadingTime($model->content);
            }

            // Auto-generate meta fields if empty
            if (empty($model->meta_title)) {
                $model->meta_title = $model->title;
            }

            if (empty($model->meta_description) && ! empty($model->excerpt)) {
                $model->meta_description = Str::limit($model->excerpt, 160);
            }

            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            // Recalculate reading time if content changed
            if ($model->isDirty('content')) {
                $model->reading_time = $model->calculateReadingTime($model->content);
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
            if ($model->isForceDeleting()) {
                $model->clearMediaCollection();
            }
        });
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
            ->logOnlyDirty();
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function registerMediaConversions($media = null): void
    {
        // Featured image conversions (crop for consistent aspect ratio)
        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('featured_image')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->fit(Fit::Crop, 450, 300)
            ->quality(85)
            ->performOnCollections('featured_image')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->fit(Fit::Crop, 900, 600)
            ->quality(90)
            ->performOnCollections('featured_image');

        $this->addMediaConversion('lg')
            ->fit(Fit::Crop, 1200, 800)
            ->quality(90)
            ->performOnCollections('featured_image');

        $this->addMediaConversion('xl')
            ->fit(Fit::Crop, 1500, 1000)
            ->quality(95)
            ->performOnCollections('featured_image');

        // Content images conversions (maintain aspect ratio, no crop)
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
            ->performOnCollections('content_images');

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
                'max_size' => 10240, // 10MB
            ],
            'content_images' => [
                'single_file' => false,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
                'max_size' => 5120, // 5MB per image
            ],
        ];
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
    public function authors(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_authors')
            ->withPivot('role', 'order')
            ->orderBy('post_authors.order')
            ->withTimestamps();
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
        return $query->withAnyTags([$tagName]);
    }

    /**
     * Scope: Search posts by title, excerpt, or content
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('excerpt', 'like', "%{$search}%")
                ->orWhere('content', 'like', "%{$search}%");
        });
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
}
