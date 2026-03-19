<?php

namespace App\Models;

use App\Traits\HasMediaManager;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $event_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property int|null $order_column
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Event|null $event
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, EventProduct> $products
 * @property-read int|null $products_count
 * @property-read User|null $updater
 *
 * @method static \Database\Factories\EventProductCategoryFactory factory($count = null, $state = [])
 * @method static Builder<static>|EventProductCategory newModelQuery()
 * @method static Builder<static>|EventProductCategory newQuery()
 * @method static Builder<static>|EventProductCategory ordered(string $direction = 'asc')
 * @method static Builder<static>|EventProductCategory query()
 * @method static Builder<static>|EventProductCategory whereCreatedAt($value)
 * @method static Builder<static>|EventProductCategory whereCreatedBy($value)
 * @method static Builder<static>|EventProductCategory whereDescription($value)
 * @method static Builder<static>|EventProductCategory whereEventId($value)
 * @method static Builder<static>|EventProductCategory whereId($value)
 * @method static Builder<static>|EventProductCategory whereOrderColumn($value)
 * @method static Builder<static>|EventProductCategory whereSlug($value)
 * @method static Builder<static>|EventProductCategory whereTitle($value)
 * @method static Builder<static>|EventProductCategory whereUpdatedAt($value)
 * @method static Builder<static>|EventProductCategory whereUpdatedBy($value)
 *
 * @mixin \Eloquent
 */
class EventProductCategory extends Model implements HasMedia, Sortable
{
    use HasFactory;
    use HasMediaManager;
    use HasSlug;
    use InteractsWithMedia;
    use SortableTrait;

    protected $fillable = [
        'event_id',
        'title',
        'slug',
        'description',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
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

    public function buildSortQuery(): Builder
    {
        return static::query()->where('event_id', $this->event_id);
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
            'catalog_files' => [
                'single_file' => false,
                'mime_types' => ['application/pdf'],
                'max_size' => 51200,
            ],
            'description_images' => [
                'single_file' => false,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
                'max_size' => 20480,
            ],
        ];
    }

    // Relationships

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(EventProduct::class, 'category_id')->ordered();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
