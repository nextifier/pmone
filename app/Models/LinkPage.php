<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property bool $is_active
 * @property string $visibility
 * @property array<array-key, mixed>|null $more_details
 * @property array<array-key, mixed>|null $settings
 * @property int $order_column
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image
 * @property string $og_type
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, Click> $clicks
 * @property-read int $clicks_count
 * @property-read User|null $creator
 * @property-read User|null $deleter
 * @property-read array|null $cover_image
 * @property-read int|null $items_count
 * @property-read int|null $visits_count
 * @property-read Collection<int, LinkPageItem> $items
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read User|null $updater
 * @property-read User|null $user
 * @property-read Collection<int, Visit> $visits
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage active()
 * @method static \Database\Factories\LinkPageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage public()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereMoreDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereOgDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereOgImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereOgTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereOgType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage whereVisibility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPage withoutTrashed()
 *
 * @mixin \Eloquent
 */
class LinkPage extends Model implements HasMedia
{
    use ClearsResponseCache, HasFactory, HasMediaManager, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'user_id',
        'slug',
        'title',
        'description',
        'is_active',
        'visibility',
        'more_details',
        'settings',
        'order_column',
        'og_title',
        'og_description',
        'og_image',
        'og_type',
    ];

    protected $appends = ['items_count', 'visits_count', 'clicks_count'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'more_details' => 'array',
            'settings' => 'array',
        ];
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
            if ($model->isForceDeleting()) {
                $model->clearMediaCollection();

                return;
            }
            if (auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['slug', 'title', 'is_active', 'visibility'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function items(): HasMany
    {
        return $this->hasMany(LinkPageItem::class);
    }

    public function visits(): MorphMany
    {
        return $this->morphMany(Visit::class, 'visitable');
    }

    public function clicks(): MorphMany
    {
        return $this->morphMany(Click::class, 'clickable');
    }

    public function getItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    public function getVisitsCountAttribute(): int
    {
        return $this->visits()->count();
    }

    public function getClicksCountAttribute(): int
    {
        return $this->clicks()->count();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_column');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover_image')->singleFile();
    }

    public function registerMediaConversions($media = null): void
    {
        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('cover_image')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(450)
            ->quality(85)
            ->performOnCollections('cover_image')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(900)
            ->quality(90)
            ->performOnCollections('cover_image');

        $this->addMediaConversion('lg')
            ->width(1200)
            ->quality(90)
            ->performOnCollections('cover_image');
    }

    public function getCoverImageAttribute(): ?array
    {
        return $this->getMediaUrls('cover_image');
    }

    protected static function responseCacheTags(): array
    {
        return ['short-links'];
    }
}
