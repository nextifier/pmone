<?php

namespace App\Models;

use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $event_id
 * @property string $category
 * @property string $name
 * @property string|null $description
 * @property numeric $price
 * @property string $unit
 * @property array<array-key, mixed>|null $booth_types
 * @property bool $is_active
 * @property int|null $order_column
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Event $event
 * @property-read array|null $product_image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $orderItems
 * @property-read int|null $order_items_count
 * @property-read \App\Models\User|null $updater
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct active()
 * @method static \Database\Factories\EventProductFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct whereBoothTypes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct whereUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventProduct whereUpdatedBy($value)
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
        'category',
        'name',
        'description',
        'price',
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
            ->logOnly(['name', 'category', 'price', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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
