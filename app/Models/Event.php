<?php

namespace App\Models;

use App\Observers\EventObserver;
use App\Traits\ClearsResponseCache;
use App\Traits\HasMediaManager;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property string $title
 * @property string $slug
 * @property int|null $edition_number
 * @property string|null $description
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string|null $location
 * @property string|null $location_link
 * @property string|null $hall
 * @property string $status
 * @property string $visibility
 * @property array<array-key, mixed>|null $settings
 * @property array<array-key, mixed>|null $custom_fields
 * @property string|null $order_form_content
 * @property Carbon|null $order_form_deadline
 * @property Carbon|null $promotion_post_deadline
 * @property numeric|null $saleable_area
 * @property bool $is_active
 * @property int|null $order_column
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $normal_order_opens_at
 * @property Carbon|null $normal_order_closes_at
 * @property Carbon|null $onsite_order_opens_at
 * @property Carbon|null $onsite_order_closes_at
 * @property numeric $onsite_penalty_rate
 * @property string|null $badge_vip_info
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, BrandEvent> $brandEvents
 * @property-read int|null $brand_events_count
 * @property-read Collection<int, Brand> $brands
 * @property-read int|null $brands_count
 * @property-read Collection<int, Event> $conjunctionEvents
 * @property-read int|null $conjunction_events_count
 * @property-read User|null $creator
 * @property-read User|null $deleter
 * @property-read Collection<int, EventDocumentSubmission> $eventDocumentSubmissions
 * @property-read int|null $event_document_submissions_count
 * @property-read Collection<int, EventDocument> $eventDocuments
 * @property-read int|null $event_documents_count
 * @property-read Collection<int, EventProductCategory> $eventProductCategories
 * @property-read int|null $event_product_categories_count
 * @property-read Collection<int, EventProduct> $eventProducts
 * @property-read int|null $event_products_count
 * @property-read string|null $date_label
 * @property-read string|null $edition_number_with_ordinal
 * @property-read string|null $end_time
 * @property-read array|null $poster_image
 * @property-read string|null $start_time
 * @property-read Collection<int, Guest> $guests
 * @property-read int|null $guests_count
 * @property-read Collection<int, HotelEvent> $hotelEvents
 * @property-read int|null $hotel_events_count
 * @property-read Collection<int, Hotel> $hotels
 * @property-read int|null $hotels_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, Order> $orders
 * @property-read int|null $orders_count
 * @property-read Collection<int, PartnerCategory> $partnerCategories
 * @property-read int|null $partner_categories_count
 * @property-read Project|null $project
 * @property-read Collection<int, RundownItem> $rundownItems
 * @property-read int|null $rundown_items_count
 * @property-read User|null $updater
 *
 * @method static Builder<static>|Event active()
 * @method static Builder<static>|Event byStatus(string $status)
 * @method static \Database\Factories\EventFactory factory($count = null, $state = [])
 * @method static Builder<static>|Event findSimilarSlugs(string $attribute, array $config, string $slug)
 * @method static Builder<static>|Event newModelQuery()
 * @method static Builder<static>|Event newQuery()
 * @method static Builder<static>|Event onlyTrashed()
 * @method static Builder<static>|Event ordered(string $direction = 'asc')
 * @method static Builder<static>|Event published()
 * @method static Builder<static>|Event query()
 * @method static Builder<static>|Event whereBadgeVipInfo($value)
 * @method static Builder<static>|Event whereCreatedAt($value)
 * @method static Builder<static>|Event whereCreatedBy($value)
 * @method static Builder<static>|Event whereCustomFields($value)
 * @method static Builder<static>|Event whereDeletedAt($value)
 * @method static Builder<static>|Event whereDeletedBy($value)
 * @method static Builder<static>|Event whereDescription($value)
 * @method static Builder<static>|Event whereEditionNumber($value)
 * @method static Builder<static>|Event whereEndDate($value)
 * @method static Builder<static>|Event whereHall($value)
 * @method static Builder<static>|Event whereId($value)
 * @method static Builder<static>|Event whereIsActive($value)
 * @method static Builder<static>|Event whereLocation($value)
 * @method static Builder<static>|Event whereLocationLink($value)
 * @method static Builder<static>|Event whereNormalOrderClosesAt($value)
 * @method static Builder<static>|Event whereNormalOrderOpensAt($value)
 * @method static Builder<static>|Event whereOnsiteOrderClosesAt($value)
 * @method static Builder<static>|Event whereOnsiteOrderOpensAt($value)
 * @method static Builder<static>|Event whereOnsitePenaltyRate($value)
 * @method static Builder<static>|Event whereOrderColumn($value)
 * @method static Builder<static>|Event whereOrderFormContent($value)
 * @method static Builder<static>|Event whereOrderFormDeadline($value)
 * @method static Builder<static>|Event whereProjectId($value)
 * @method static Builder<static>|Event wherePromotionPostDeadline($value)
 * @method static Builder<static>|Event whereSaleableArea($value)
 * @method static Builder<static>|Event whereSettings($value)
 * @method static Builder<static>|Event whereSlug($value)
 * @method static Builder<static>|Event whereStartDate($value)
 * @method static Builder<static>|Event whereStatus($value)
 * @method static Builder<static>|Event whereTitle($value)
 * @method static Builder<static>|Event whereUlid($value)
 * @method static Builder<static>|Event whereUpdatedAt($value)
 * @method static Builder<static>|Event whereUpdatedBy($value)
 * @method static Builder<static>|Event whereVisibility($value)
 * @method static Builder<static>|Event withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Event withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array $config, string $slug)
 * @method static Builder<static>|Event withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[ObservedBy([EventObserver::class])]
class Event extends Model implements HasMedia, Sortable
{
    use ClearsResponseCache;
    use HasFactory;
    use HasMediaManager;
    use HasSlug;
    use HasTranslations;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;
    use SortableTrait;

    protected $fillable = [
        'project_id',
        'title',
        'slug',
        'edition_number',
        'description',
        'start_date',
        'end_date',
        'location',
        'location_link',
        'hall',
        'status',
        'is_active',
        'visibility',
        'settings',
        'custom_fields',
        'order_form_content',
        'order_form_deadline',
        'promotion_post_deadline',
        'saleable_area',
        'normal_order_opens_at',
        'normal_order_closes_at',
        'onsite_order_opens_at',
        'onsite_order_closes_at',
        'onsite_penalty_rate',
        'badge_vip_info',
        'timezone',
        'allow_cross_day',
        'tickets_enabled',
        'business_matching_enabled',
    ];

    public array $translatable = [
        'description',
    ];

    protected $appends = [
        'edition_number_with_ordinal',
        'date_label',
        'start_time',
        'end_time',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'custom_fields' => 'array',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'edition_number' => 'integer',
            'saleable_area' => 'decimal:2',
            'normal_order_opens_at' => 'datetime',
            'normal_order_closes_at' => 'datetime',
            'onsite_order_opens_at' => 'datetime',
            'onsite_order_closes_at' => 'datetime',
            'onsite_penalty_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'order_form_deadline' => 'datetime',
            'promotion_post_deadline' => 'datetime',
            'allow_cross_day' => 'boolean',
            'tickets_enabled' => 'boolean',
            'business_matching_enabled' => 'boolean',
        ];
    }

    protected static function responseCacheTags(): array
    {
        // Public website data is resolved per project's active event (is_active).
        // Changing an event (esp. is_active) must bust every section whose active
        // resolution is cached server-side, so the site reflects the new active
        // event. 'brands' is resolved+cached server-side (activeBrands); rundown/
        // programs re-resolve via the cached events/active ('events') so they
        // follow automatically. 'faqs' also embeds event context tokens.
        return ['events', 'faqs', 'brands', 'gallery'];
    }

    public function getEditionNumberWithOrdinalAttribute(): ?string
    {
        if ($this->edition_number === null) {
            return null;
        }

        return Number::ordinal($this->edition_number);
    }

    public function getDateLabelAttribute(): ?string
    {
        if (! $this->start_date) {
            return null;
        }

        $start = $this->start_date;

        if (! $this->end_date || $start->isSameDay($this->end_date)) {
            return $start->format('D, M j, Y');
        }

        $end = $this->end_date;

        if ($start->year !== $end->year) {
            return $start->format('M j, Y').' - '.$end->format('M j, Y');
        }

        if ($start->month !== $end->month) {
            return $start->format('M j').' - '.$end->format('M j, Y');
        }

        return $start->format('D').'-'.$end->format('D').', '.$start->format('M j').'-'.$end->format('j, Y');
    }

    public function getStartTimeAttribute(): ?string
    {
        if (! $this->start_date) {
            return null;
        }

        return $this->start_date->format('g:i A');
    }

    public function getEndTimeAttribute(): ?string
    {
        if (! $this->end_date) {
            return null;
        }

        return $this->end_date->format('g:i A');
    }

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
        return $query->where('project_id', $model->project_id);
    }

    public function buildSortQuery(): Builder
    {
        return static::query()->where('project_id', $this->project_id);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
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

        static::deleting(function ($model) {
            if ($model->isForceDeleting() === false && auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }

            if ($model->isForceDeleting()) {
                $model->clearMediaCollection();

                // Force-delete media-owning children per-instance. The DB-level FK
                // cascade bypasses model events, which would orphan their media.
                // Products first (in case a category->product cascade exists).
                $model->eventProducts()->get()->each(fn ($child) => $child->delete());
                $model->eventProductCategories()->get()->each(fn ($child) => $child->delete());
                $model->eventDocuments()->get()->each(fn ($child) => $child->delete());
                $model->brandEvents()->get()->each(fn ($child) => $child->delete());
            }
        });

    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'slug', 'status', 'visibility'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->put('project_id', $this->project_id);
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function registerMediaConversions($media = null): void
    {
        // Poster image conversions (square-ish)
        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('poster_image')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(200)
            ->height(200)
            ->quality(85)
            ->performOnCollections('poster_image')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(400)
            ->height(400)
            ->quality(90)
            ->performOnCollections('poster_image')
            ->nonQueued();

        $this->addMediaConversion('lg')
            ->width(800)
            ->height(800)
            ->quality(90)
            ->performOnCollections('poster_image');

        $this->addMediaConversion('xl')
            ->width(1080)
            ->height(1080)
            ->quality(95)
            ->performOnCollections('poster_image');

        // Description content image conversions
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

        // Gallery conversions — only lqip + sm (the full image uses the stored
        // original) to save space, and width-only (no height / no crop) so each
        // photo keeps its native aspect ratio (16:9, 4:5, 1:1, etc.).
        $this->addMediaConversion('lqip')
            ->width(32)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('gallery')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(600)
            ->quality(80)
            ->performOnCollections('gallery')
            ->nonQueued();
    }

    public function getMediaCollections(): array
    {
        return [
            'poster_image' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
            ],
            'description_images' => [
                'single_file' => false,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
                'max_size' => 20480,
            ],
            'visitor_eguide' => [
                'single_file' => true,
                'mime_types' => ['application/pdf'],
                'max_size' => 20480,
            ],
            'gallery' => [
                'single_file' => false,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
                'max_size' => 20480,
            ],
        ];
    }

    /**
     * Get poster image URLs for the event.
     */
    public function getPosterImageAttribute(): ?array
    {
        return $this->getMediaUrls('poster_image');
    }

    // Relationships

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Base URL for this event's public-facing pages (e-ticket, order result,
     * invites). Prefers the event's own website; falls back to the app frontend
     * URL when the event has no "Website" link configured.
     */
    public function publicBaseUrl(): string
    {
        return rtrim((string) ($this->project?->websiteUrl() ?: config('app.frontend_url')), '/');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function brandEvents(): HasMany
    {
        return $this->hasMany(BrandEvent::class)->ordered();
    }

    public function hotels(): BelongsToMany
    {
        return $this->belongsToMany(Hotel::class, 'hotel_event')
            ->withPivot(['id', 'is_active', 'order_column', 'notes', 'created_by', 'updated_by'])
            ->withTimestamps()
            ->orderByPivot('order_column');
    }

    public function hotelEvents(): HasMany
    {
        return $this->hasMany(HotelEvent::class);
    }

    public function partnerCategories(): HasMany
    {
        return $this->hasMany(PartnerCategory::class)->ordered();
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'brand_event')
            ->withPivot(['id', 'booth_number', 'booth_size', 'booth_type', 'sales_id', 'status', 'notes', 'custom_fields', 'order_column'])
            ->withTimestamps();
    }

    public function eventProducts(): HasMany
    {
        return $this->hasMany(EventProduct::class)->ordered();
    }

    public function rundownItems(): HasMany
    {
        return $this->hasMany(RundownItem::class)
            ->orderBy('date')
            ->orderBy('start_time')
            ->orderBy('order_column');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class)->ordered();
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class)->ordered();
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class)->ordered();
    }

    public function mediaCoverages(): HasMany
    {
        return $this->hasMany(MediaCoverage::class)->ordered();
    }

    public function eventProductCategories(): HasMany
    {
        return $this->hasMany(EventProductCategory::class)->ordered();
    }

    public function eventDocuments(): HasMany
    {
        return $this->hasMany(EventDocument::class)->ordered();
    }

    public function eventDocumentSubmissions(): HasMany
    {
        return $this->hasMany(EventDocumentSubmission::class);
    }

    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(
            Order::class,
            BrandEvent::class,
            'event_id',
            'brand_event_id',
        );
    }

    public function conjunctionEvents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'event_conjunctions', 'event_id', 'conjunction_event_id')
            ->withPivot(['conjunction_label', 'allow_cross_scan', 'order_column'])
            ->withTimestamps()
            ->orderByPivot('order_column');
    }

    public function eventDays(): HasMany
    {
        return $this->hasMany(EventDay::class)->ordered();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->ordered();
    }

    public function accessCodes(): HasMany
    {
        return $this->hasMany(AccessCode::class);
    }

    public function accessCodeBatches(): HasMany
    {
        return $this->hasMany(AccessCodeBatch::class);
    }

    public function eventCustomFields(): HasMany
    {
        return $this->hasMany(EventCustomField::class)->ordered();
    }

    /**
     * Alias of {@see eventCustomFields()} so scoped route-model binding can
     * resolve `{customField}` against this relation.
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(EventCustomField::class);
    }

    public function ticketOrders(): HasMany
    {
        return $this->hasMany(TicketOrder::class);
    }

    // Scopes

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'published']);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
