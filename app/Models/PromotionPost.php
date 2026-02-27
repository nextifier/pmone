<?php

namespace App\Models;

use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PromotionPost extends Model implements HasMedia, Sortable
{
    use HasFactory;
    use HasMediaManager;
    use InteractsWithMedia;
    use LogsActivity;
    use SortableTrait;

    protected $fillable = [
        'brand_event_id',
        'caption',
        'custom_fields',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['caption'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

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

    public function buildSortQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()->where('brand_event_id', $this->brand_event_id);
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
            ->performOnCollections('post_image')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(450)
            ->height(450)
            ->quality(85)
            ->performOnCollections('post_image')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(900)
            ->height(900)
            ->quality(90)
            ->performOnCollections('post_image')
            ->nonQueued();

        $this->addMediaConversion('lg')
            ->width(1200)
            ->height(1200)
            ->quality(90)
            ->performOnCollections('post_image');

        $this->addMediaConversion('xl')
            ->width(1500)
            ->height(1500)
            ->quality(95)
            ->performOnCollections('post_image');
    }

    public function getMediaCollections(): array
    {
        return [
            'post_image' => [
                'single_file' => false,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
                'max_size' => 20480,
            ],
        ];
    }

    /**
     * Get post image URLs (first image, backward compatible).
     */
    public function getPostImageAttribute(): ?array
    {
        return $this->getMediaUrls('post_image');
    }

    /**
     * Get all post image URLs.
     */
    public function getPostImagesAttribute(): array
    {
        if (! $this->hasMedia('post_image')) {
            return [];
        }

        return $this->getMedia('post_image')->map(function ($media) {
            return [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'url' => $media->getUrl(),
                'original' => $media->getUrl(),
                'lqip' => $media->getUrl('lqip'),
                'sm' => $media->getUrl('sm'),
                'md' => $media->getUrl('md'),
                'lg' => $media->getUrl('lg'),
                'xl' => $media->getUrl('xl'),
                'caption' => $media->getCustomProperty('caption'),
                'alt' => $media->getCustomProperty('alt') ?? $media->name,
            ];
        })->toArray();
    }

    // Relationships

    public function brandEvent(): BelongsTo
    {
        return $this->belongsTo(BrandEvent::class);
    }
}
