<?php

namespace App\Models;

use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $event_id
 * @property string $name
 * @property string|null $description
 * @property numeric $price
 * @property numeric|null $price_usd
 * @property string $unit
 * @property array<array-key, mixed>|null $booth_types
 * @property bool $is_active
 * @property int|null $order_column
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $category_id
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read User|null $creator
 * @property-read Event|null $event
 * @property-read array|null $product_image
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, OrderItem> $orderItems
 * @property-read int|null $order_items_count
 * @property-read EventProductCategory|null $productCategory
 * @property-read User|null $updater
 *
 * @method static Builder<static>|EventProduct active()
 * @method static \Database\Factories\EventProductFactory factory($count = null, $state = [])
 * @method static Builder<static>|EventProduct newModelQuery()
 * @method static Builder<static>|EventProduct newQuery()
 * @method static Builder<static>|EventProduct ordered(string $direction = 'asc')
 * @method static Builder<static>|EventProduct query()
 * @method static Builder<static>|EventProduct whereBoothTypes($value)
 * @method static Builder<static>|EventProduct whereCategoryId($value)
 * @method static Builder<static>|EventProduct whereCreatedAt($value)
 * @method static Builder<static>|EventProduct whereCreatedBy($value)
 * @method static Builder<static>|EventProduct whereDescription($value)
 * @method static Builder<static>|EventProduct whereEventId($value)
 * @method static Builder<static>|EventProduct whereId($value)
 * @method static Builder<static>|EventProduct whereIsActive($value)
 * @method static Builder<static>|EventProduct whereName($value)
 * @method static Builder<static>|EventProduct whereOrderColumn($value)
 * @method static Builder<static>|EventProduct wherePrice($value)
 * @method static Builder<static>|EventProduct wherePriceUsd($value)
 * @method static Builder<static>|EventProduct whereUnit($value)
 * @method static Builder<static>|EventProduct whereUpdatedAt($value)
 * @method static Builder<static>|EventProduct whereUpdatedBy($value)
 *
 * @mixin \Eloquent
 */
class EventProduct extends Model implements HasMedia, Sortable
{
    use HasFactory;
    use HasMediaManager;
    use InteractsWithMedia;
    use LogsActivity;
    use SortableTrait;

    protected $fillable = [
        'event_id',
        'category_id',
        'name',
        'description',
        'price',
        'price_usd',
        'unit',
        'booth_types',
        'is_active',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_usd' => 'decimal:2',
            'booth_types' => 'array',
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'category_id', 'price', 'price_usd', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($projectId = $this->loadMissing('event')->event?->project_id) {
            $activity->properties = $activity->properties->put('project_id', $projectId);
        }
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
            ->performOnCollections('product_image')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(200)
            ->height(200)
            ->quality(85)
            ->performOnCollections('product_image')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(400)
            ->height(400)
            ->quality(90)
            ->performOnCollections('product_image');

        $this->addMediaConversion('lg')
            ->width(800)
            ->height(800)
            ->quality(90)
            ->performOnCollections('product_image');

        $this->addMediaConversion('xl')
            ->width(1080)
            ->height(1080)
            ->quality(95)
            ->performOnCollections('product_image');
    }

    public function getMediaCollections(): array
    {
        return [
            'product_image' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
        ];
    }

    /**
     * Get product image URLs.
     */
    public function getProductImageAttribute(): ?array
    {
        return $this->getMediaUrls('product_image');
    }

    // Relationships

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(EventProductCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
