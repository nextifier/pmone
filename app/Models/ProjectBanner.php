<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $project_id
 * @property string $placement
 * @property string $type
 * @property string|null $title
 * @property string|null $description
 * @property string|null $link
 * @property string|null $cta_label
 * @property string|null $aspect_ratio
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $start_time
 * @property Carbon|null $end_time
 * @property array|null $more_details
 * @property array|null $settings
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read array|null $image
 * @property-read Project|null $project
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectBanner active()
 * @method static \Database\Factories\ProjectBannerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectBanner ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectBanner query()
 *
 * @mixin \Eloquent
 */
class ProjectBanner extends Model implements HasMedia
{
    use ClearsResponseCache, HasFactory, HasMediaManager, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'project_id',
        'placement',
        'type',
        'title',
        'description',
        'link',
        'cta_label',
        'aspect_ratio',
        'is_active',
        'sort_order',
        'start_time',
        'end_time',
        'more_details',
        'settings',
    ];

    protected $appends = ['image'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
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
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function clicks(): MorphMany
    {
        return $this->morphMany(Click::class, 'clickable');
    }

    public function impressions(): MorphMany
    {
        return $this->morphMany(Visit::class, 'visitable');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
        $this->addMediaCollection('description_images');
    }

    public function registerMediaConversions($media = null): void
    {
        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('image')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(600)
            ->quality(85)
            ->performOnCollections('image')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(1200)
            ->quality(90)
            ->performOnCollections('image');

        $this->addMediaConversion('lg')
            ->width(1440)
            ->quality(90)
            ->performOnCollections('image');

        $this->addMediaConversion('xl')
            ->width(1600)
            ->quality(90)
            ->performOnCollections('image');

        // Description (TipTap) content image conversions
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

    public function getImageAttribute(): ?array
    {
        return $this->getMediaUrls('image');
    }

    protected static function responseCacheTags(): array
    {
        return ['banners'];
    }
}
