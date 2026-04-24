<?php

namespace App\Models;

use App\Enums\BoothType;
use App\Traits\ClearsResponseCache;
use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property int $brand_id
 * @property int $event_id
 * @property string|null $booth_number
 * @property numeric|null $booth_size
 * @property BoothType|null $booth_type
 * @property numeric|null $booth_price
 * @property int|null $sales_id
 * @property string $status
 * @property string|null $notes
 * @property int $promotion_post_limit
 * @property array<array-key, mixed>|null $custom_fields
 * @property int|null $order_column
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $fascia_name
 * @property string|null $badge_name
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\Brand|null $brand
 * @property-read \App\Models\Event|null $event
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, \App\Models\Order> $orders
 * @property-read int|null $orders_count
 * @property-read Collection<int, \App\Models\PromotionPost> $promotionPosts
 * @property-read int|null $promotion_posts_count
 * @property-read \App\Models\User|null $sales
 * @method static Builder<static>|BrandEvent active()
 * @method static Builder<static>|BrandEvent byStatus(string $status)
 * @method static \Database\Factories\BrandEventFactory factory($count = null, $state = [])
 * @method static Builder<static>|BrandEvent newModelQuery()
 * @method static Builder<static>|BrandEvent newQuery()
 * @method static Builder<static>|BrandEvent ordered(string $direction = 'asc')
 * @method static Builder<static>|BrandEvent query()
 * @method static Builder<static>|BrandEvent whereBadgeName($value)
 * @method static Builder<static>|BrandEvent whereBoothNumber($value)
 * @method static Builder<static>|BrandEvent whereBoothPrice($value)
 * @method static Builder<static>|BrandEvent whereBoothSize($value)
 * @method static Builder<static>|BrandEvent whereBoothType($value)
 * @method static Builder<static>|BrandEvent whereBrandId($value)
 * @method static Builder<static>|BrandEvent whereCreatedAt($value)
 * @method static Builder<static>|BrandEvent whereCustomFields($value)
 * @method static Builder<static>|BrandEvent whereEventId($value)
 * @method static Builder<static>|BrandEvent whereFasciaName($value)
 * @method static Builder<static>|BrandEvent whereId($value)
 * @method static Builder<static>|BrandEvent whereNotes($value)
 * @method static Builder<static>|BrandEvent whereOrderColumn($value)
 * @method static Builder<static>|BrandEvent wherePromotionPostLimit($value)
 * @method static Builder<static>|BrandEvent whereSalesId($value)
 * @method static Builder<static>|BrandEvent whereStatus($value)
 * @method static Builder<static>|BrandEvent whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class BrandEvent extends Model implements HasMedia, Sortable
{
    use ClearsResponseCache;
    use HasFactory;
    use HasMediaManager;
    use InteractsWithMedia;
    use LogsActivity;
    use SortableTrait;

    protected $table = 'brand_event';

    protected $fillable = [
        'brand_id',
        'event_id',
        'booth_number',
        'booth_size',
        'booth_price',
        'booth_type',
        'sales_id',
        'status',
        'notes',
        'promotion_post_limit',
        'custom_fields',
        'fascia_name',
        'badge_name',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function boothNumber(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? collect(preg_split('/[\s,]+/', trim($value)))->filter()->implode(', ') : null,
        );
    }

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'booth_size' => 'decimal:2',
            'booth_price' => 'decimal:2',
            'booth_type' => BoothType::class,
        ];
    }

    protected static function responseCacheTags(): array
    {
        return ['brands', 'promotion-posts'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['booth_number', 'booth_size', 'booth_type', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($this->event) {
            $activity->properties = $activity->properties->put('project_id', $this->event->project_id);
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
        // Promotion post image conversions
        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('promotion_images')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(450)
            ->quality(85)
            ->performOnCollections('promotion_images')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(900)
            ->quality(90)
            ->performOnCollections('promotion_images');

        $this->addMediaConversion('lg')
            ->width(1200)
            ->quality(90)
            ->performOnCollections('promotion_images');

        $this->addMediaConversion('xl')
            ->width(1500)
            ->quality(95)
            ->performOnCollections('promotion_images');
    }

    public function getMediaCollections(): array
    {
        return [
            'promotion_images' => [
                'single_file' => false,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
                'max_size' => 20480,
            ],
        ];
    }

    // Relationships

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function promotionPosts(): HasMany
    {
        return $this->hasMany(PromotionPost::class)->ordered();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
