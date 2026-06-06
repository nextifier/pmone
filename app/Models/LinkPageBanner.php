<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Collection;
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
 * @property int $link_page_id
 * @property string|null $url
 * @property string|null $caption
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Click> $clicks
 * @property-read int $clicks_count
 * @property-read array|null $image
 * @property-read LinkPage|null $linkPage
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageBanner active()
 * @method static \Database\Factories\LinkPageBannerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageBanner newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageBanner newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageBanner onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageBanner ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageBanner query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageBanner withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageBanner withoutTrashed()
 *
 * @mixin \Eloquent
 */
class LinkPageBanner extends Model implements HasMedia
{
    use ClearsResponseCache, HasFactory, HasMediaManager, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'link_page_id',
        'url',
        'caption',
        'is_active',
        'sort_order',
        'starts_at',
        'ends_at',
    ];

    protected $appends = ['clicks_count', 'image'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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

    public function linkPage(): BelongsTo
    {
        return $this->belongsTo(LinkPage::class);
    }

    public function clicks(): MorphMany
    {
        return $this->morphMany(Click::class, 'clickable');
    }

    public function getClicksCountAttribute(): int
    {
        return $this->clicks()->count();
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
    }

    public function getImageAttribute(): ?array
    {
        return $this->getMediaUrls('image');
    }

    protected static function responseCacheTags(): array
    {
        return ['short-links'];
    }
}
