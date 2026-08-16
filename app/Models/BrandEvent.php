<?php

namespace App\Models;

use App\Enums\BoothType;
use App\Services\Currency\CurrencyResolver;
use App\Support\InputNormalizer;
use App\Traits\ClearsResponseCache;
use App\Traits\HasMediaManager;
use App\Traits\NormalizesAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
 * @property string|null $booth_sort_key
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
 * @property string|null $currency_override
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Brand|null $brand
 * @property-read Collection<int, Click> $clicks
 * @property-read int|null $clicks_count
 * @property-read Event|null $event
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, Order> $orders
 * @property-read int|null $orders_count
 * @property-read Collection<int, PromotionPost> $promotionPosts
 * @property-read int|null $promotion_posts_count
 * @property-read User|null $sales
 * @property-read Collection<int, Visit> $visits
 * @property-read int|null $visits_count
 *
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
 * @method static Builder<static>|BrandEvent whereCurrencyOverride($value)
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
 *
 * @mixin \Eloquent
 */
class BrandEvent extends Model implements HasMedia, Sortable
{
    use ClearsResponseCache;
    use HasFactory;
    use HasMediaManager;
    use InteractsWithMedia;
    use LogsActivity;
    use NormalizesAttributes;
    use SortableTrait;

    protected $table = 'brand_event';

    /** @var array<string, string> */
    protected array $normalizes = [
        'booth_number' => 'boothNumber',
    ];

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
        'currency_override',
        'promotion_post_limit',
        'custom_fields',
        'fascia_name',
        'badge_name',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected static function boot(): void
    {
        parent::boot();

        // booth_sort_key is derived, never assigned, so it is not fillable. This
        // listener is registered after parent::boot() has run bootTraits(),
        // which is what registers the NormalizesAttributes `saving` hook - so
        // booth_number is already canonical by the time the key is built.
        static::saving(function (self $model) {
            if ($model->isDirty('booth_number')) {
                $model->booth_sort_key = InputNormalizer::boothSortKey($model->booth_number);
            }
        });

        static::deleting(function ($model) {
            // Delete promotion posts per-instance so their media is removed.
            // Parent Brand/Event force-deletes route brand events through here
            // per-instance; the posts' DB FK cascade would otherwise orphan media.
            $model->promotionPosts()->get()->each(fn ($child) => $child->delete());
        });
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

    /**
     * Several brands can share one physical booth (same booth_number in the same
     * event). Operational documents and the order form for that booth are the
     * responsibility of a single "primary" brand-event: the earliest one (lowest
     * id) sharing the booth. A brand-event without a booth number shares nothing
     * and is always its own primary.
     */
    public function boothPrimaryId(): int
    {
        if (empty($this->booth_number)) {
            return $this->id;
        }

        return (int) static::query()
            ->where('event_id', $this->event_id)
            ->where('booth_number', $this->booth_number)
            ->min('id');
    }

    public function isBoothPrimary(): bool
    {
        return $this->boothPrimaryId() === $this->id;
    }

    /**
     * Fascia is a shell-scheme fitting, so raw space has no board to print on.
     * Mirrors exhibitorShowFascia() in frontend/app/utils/exhibitorDashboard.js.
     */
    public function requiresFasciaName(): bool
    {
        return in_array(
            $this->booth_type,
            [BoothType::StandardShellScheme, BoothType::EnhancedShellScheme],
            true,
        );
    }

    /**
     * Any booth with a type gets exhibitor badges printed.
     * Mirrors exhibitorShowBadge() in frontend/app/utils/exhibitorDashboard.js.
     */
    public function requiresBadgeName(): bool
    {
        return $this->booth_type !== null;
    }

    /**
     * Name of the brand that owns this booth's documents/order form, or null when
     * this brand-event is itself the primary.
     */
    public function boothPrimaryBrandName(): ?string
    {
        $primaryId = $this->boothPrimaryId();

        if ($primaryId === $this->id) {
            return null;
        }

        return static::query()->with('brand:id,name')->find($primaryId)?->brand?->name;
    }

    /**
     * Resolve the billing currency for this exhibitor (manual override, else the
     * brand's country). Thin delegate to the CurrencyResolver service.
     */
    public function resolveCurrency(): string
    {
        return app(CurrencyResolver::class)->resolveForBrandEvent($this);
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

    public function visits(): MorphMany
    {
        return $this->morphMany(Visit::class, 'visitable');
    }

    public function clicks(): MorphMany
    {
        return $this->morphMany(Click::class, 'clickable');
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

    /**
     * Physical booth order: A-01, A-02, A-10 rather than A-1, A-10, A-2, and
     * brands sharing a booth number always land next to each other. Replaces
     * `ordered()` at the call site instead of stacking on top of it - a manual
     * drag still decides between booths whose key ties or is missing.
     *
     * Nulls are sunk with a CASE rather than NULLS LAST because the two engines
     * disagree on the default: PostgreSQL puts nulls last on ASC, the SQLite the
     * tests run on puts them first.
     */
    public function scopeOrderedByBooth($query, string $direction = 'asc')
    {
        return $query
            ->orderByRaw('CASE WHEN brand_event.booth_sort_key IS NULL THEN 1 ELSE 0 END')
            ->orderBy('brand_event.booth_sort_key', $direction)
            ->orderBy('brand_event.order_column')
            ->orderBy('brand_event.id');
    }
}
