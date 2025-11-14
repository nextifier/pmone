<?php

namespace App\Models;

use App\Traits\HasMediaManager;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'og_type',
        'status',
        'visibility',
        'published_at',
        'featured',
        'reading_time',
        'view_count',
        'settings',
        'source',
        'source_id',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'published_at' => 'datetime',
            'featured' => 'boolean',
            'reading_time' => 'integer',
            'view_count' => 'integer',
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

        // Create revision when post is updated
        static::updated(function ($model) {
            if ($model->isDirty(['title', 'excerpt', 'content'])) {
                $model->createRevision();
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

    /**
     * Create a revision snapshot of the current post
     */
    protected function createRevision(): void
    {
        $latestRevision = $this->revisions()->latest('revision_number')->first();
        $revisionNumber = $latestRevision ? $latestRevision->revision_number + 1 : 1;

        $this->revisions()->create([
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'revision_number' => $revisionNumber,
            'created_by' => auth()->id(),
        ]);
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
    }

    public function getMediaCollections(): array
    {
        return [
            'featured_image' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
            ],
        ];
    }

    /**
     * Get the post's authors (many-to-many with User)
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_user')
            ->withPivot(['role', 'order'])
            ->withTimestamps()
            ->orderBy('post_user.order');
    }

    /**
     * Get the primary author of the post
     */
    public function primaryAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the post's categories (many-to-many)
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_post')
            ->withPivot(['is_primary', 'order'])
            ->withTimestamps()
            ->orderBy('category_post.order');
    }

    /**
     * Get the primary category of the post
     */
    public function primaryCategory()
    {
        return $this->categories()->wherePivot('is_primary', true)->first();
    }

    /**
     * Get the post's revisions
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class)->orderBy('revision_number', 'desc');
    }

    /**
     * Get the post's visits (polymorphic)
     */
    public function visits(): MorphMany
    {
        return $this->morphMany(Visit::class, 'visitable');
    }

    /**
     * Get computed visits count from relationship
     */
    public function getVisitsCountAttribute(): int
    {
        return $this->visits()->count();
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
     * Scope: Filter by author
     */
    public function scopeByAuthor($query, int $userId)
    {
        return $query->whereHas('authors', function ($q) use ($userId) {
            $q->where('users.id', $userId);
        });
    }

    /**
     * Scope: Filter by category
     */
    public function scopeByCategory($query, int $categoryId)
    {
        return $query->whereHas('categories', function ($q) use ($categoryId) {
            $q->where('categories.id', $categoryId);
        });
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
     * Increment cached view count (for performance)
     * For detailed analytics, use visits() relationship
     * This count can be synced from visits via scheduled job
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * Sync cached view_count from actual visits
     */
    public function syncViewCount(): void
    {
        $this->update(['view_count' => $this->visits()->count()]);
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
