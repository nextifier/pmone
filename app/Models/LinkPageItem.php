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
 * @property string $label
 * @property string|null $url
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, \App\Models\Click> $clicks
 * @property-read int $clicks_count
 * @property-read array|null $poster
 * @property-read \App\Models\LinkPage|null $linkPage
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem active()
 * @method static \Database\Factories\LinkPageItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem whereLinkPageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem whereOgDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem whereOgImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem whereOgTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkPageItem withoutTrashed()
 * @mixin \Eloquent
 */
class LinkPageItem extends Model implements HasMedia
{
    use ClearsResponseCache, HasFactory, HasMediaManager, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'link_page_id',
        'label',
        'url',
        'description',
        'is_active',
        'sort_order',
        'og_title',
        'og_description',
        'og_image',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
        $this->addMediaCollection('poster')->singleFile();
    }

    public function registerMediaConversions($media = null): void
    {
        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('poster')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(300)
            ->quality(85)
            ->performOnCollections('poster')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(600)
            ->quality(90)
            ->performOnCollections('poster');
    }

    public function getPosterAttribute(): ?array
    {
        return $this->getMediaUrls('poster');
    }

    protected static function responseCacheTags(): array
    {
        return ['short-links'];
    }
}
