<?php

namespace App\Models;

use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $event_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property int|null $order_column
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Event $event
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EventProduct> $products
 * @property-read int|null $products_count
 * @property-read \App\Models\User|null $updater
 * @method static \Database\Factories\EventProductCategoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProductCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProductCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProductCategory ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProductCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProductCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProductCategory whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProductCategory whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProductCategory whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProductCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProductCategory whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProductCategory whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProductCategory whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProductCategory whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProductCategory whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class EventProductCategory extends Model implements HasMedia, Sortable
{
    use HasFactory;
    use HasMediaManager;
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

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug) && ! empty($model->title)) {
                $baseSlug = Str::slug($model->title);

                if (empty($baseSlug)) {
                    $baseSlug = 'category';
                }

                $slug = $baseSlug;
                $counter = 1;

                while (static::where('event_id', $model->event_id)
                    ->where('slug', $slug)
                    ->exists()
                ) {
                    $slug = $baseSlug.'-'.$counter;
                    $counter++;
                }

                $model->slug = $slug;
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
    }

    public function buildSortQuery(): \Illuminate\Database\Eloquent\Builder
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
