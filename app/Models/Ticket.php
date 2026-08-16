<?php

namespace App\Models;

use App\Enums\Ticketing\PurchaseType;
use App\Enums\Ticketing\TicketKind;
use App\Enums\Ticketing\TicketVisibility;
use App\Traits\ClearsResponseCache;
use App\Traits\HasMediaManager;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
 * A sellable ticket product belonging to an event. `kind` separates entry
 * tickets (carry `valid_days`) from add-ons (carry sessions + print_on_redeem).
 *
 * Pricing lives in PricePhase; add-on scheduling lives in TicketSession.
 *
 * @property int $id
 * @property string $ulid
 * @property int $event_id
 * @property string $slug
 * @property TicketKind $kind
 * @property array<array-key, mixed> $title
 * @property string|null $tier
 * @property array<array-key, mixed>|null $benefits
 * @property string $currency
 * @property PurchaseType $purchase_type
 * @property string|null $external_url
 * @property array<array-key, mixed>|null $more_details
 * @property bool $print_on_redeem
 * @property int|null $stock
 * @property int $sold_count
 * @property int $min_quantity
 * @property int|null $max_quantity
 * @property array<array-key, mixed>|null $settings
 * @property bool $is_active
 * @property int|null $order_column
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property bool $requires_day_selection
 * @property TicketVisibility $visibility
 * @property int|null $max_per_buyer
 * @property-read Collection<int, AccessCode> $accessCodes
 * @property-read int|null $access_codes_count
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read User|null $creator
 * @property-read Event|null $event
 * @property-read array|null $poster
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, TicketPricePhase> $pricePhases
 * @property-read int|null $price_phases_count
 * @property-read Collection<int, TicketSession> $sessions
 * @property-read int|null $sessions_count
 * @property-read Collection<int, TicketOrderItem> $ticketOrderItems
 * @property-read int|null $ticket_order_items_count
 * @property-read mixed $translations
 * @property-read User|null $updater
 * @property-read Collection<int, EventDay> $validDays
 * @property-read int|null $valid_days_count
 * @property-read Collection<int, TicketWaitlistEntry> $waitlistEntries
 * @property-read int|null $waitlist_entries_count
 *
 * @method static Builder<static>|Ticket active()
 * @method static \Database\Factories\TicketFactory factory($count = null, $state = [])
 * @method static Builder<static>|Ticket findSimilarSlugs(string $attribute, array $config, string $slug)
 * @method static Builder<static>|Ticket newModelQuery()
 * @method static Builder<static>|Ticket newQuery()
 * @method static Builder<static>|Ticket onlyTrashed()
 * @method static Builder<static>|Ticket ordered(string $direction = 'asc')
 * @method static Builder<static>|Ticket query()
 * @method static Builder<static>|Ticket whereBenefits($value)
 * @method static Builder<static>|Ticket whereCreatedAt($value)
 * @method static Builder<static>|Ticket whereCreatedBy($value)
 * @method static Builder<static>|Ticket whereCurrency($value)
 * @method static Builder<static>|Ticket whereDeletedAt($value)
 * @method static Builder<static>|Ticket whereDeletedBy($value)
 * @method static Builder<static>|Ticket whereEventId($value)
 * @method static Builder<static>|Ticket whereExternalUrl($value)
 * @method static Builder<static>|Ticket whereId($value)
 * @method static Builder<static>|Ticket whereIsActive($value)
 * @method static Builder<static>|Ticket whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|Ticket whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|Ticket whereKind($value)
 * @method static Builder<static>|Ticket whereLocale(string $column, string $locale)
 * @method static Builder<static>|Ticket whereLocales(string $column, array $locales)
 * @method static Builder<static>|Ticket whereMaxPerBuyer($value)
 * @method static Builder<static>|Ticket whereMaxQuantity($value)
 * @method static Builder<static>|Ticket whereMinQuantity($value)
 * @method static Builder<static>|Ticket whereMoreDetails($value)
 * @method static Builder<static>|Ticket whereOrderColumn($value)
 * @method static Builder<static>|Ticket wherePrintOnRedeem($value)
 * @method static Builder<static>|Ticket wherePurchaseType($value)
 * @method static Builder<static>|Ticket whereRequiresDaySelection($value)
 * @method static Builder<static>|Ticket whereSettings($value)
 * @method static Builder<static>|Ticket whereSlug($value)
 * @method static Builder<static>|Ticket whereSoldCount($value)
 * @method static Builder<static>|Ticket whereStock($value)
 * @method static Builder<static>|Ticket whereTier($value)
 * @method static Builder<static>|Ticket whereTitle($value)
 * @method static Builder<static>|Ticket whereUlid($value)
 * @method static Builder<static>|Ticket whereUpdatedAt($value)
 * @method static Builder<static>|Ticket whereUpdatedBy($value)
 * @method static Builder<static>|Ticket whereVisibility($value)
 * @method static Builder<static>|Ticket withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Ticket withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array $config, string $slug)
 * @method static Builder<static>|Ticket withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Ticket extends Model implements HasMedia, Sortable
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
        'event_id',
        'slug',
        'kind',
        'title',
        'tier',
        'benefits',
        'currency',
        'purchase_type',
        'external_url',
        'more_details',
        'print_on_redeem',
        'requires_day_selection',
        'stock',
        'min_quantity',
        'max_quantity',
        'max_per_buyer',
        'settings',
        'is_active',
        'visibility',
    ];

    public array $translatable = [
        'title',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'kind' => TicketKind::class,
            'purchase_type' => PurchaseType::class,
            'visibility' => TicketVisibility::class,
            'benefits' => 'array',
            'more_details' => 'array',
            'settings' => 'array',
            'print_on_redeem' => 'boolean',
            'requires_day_selection' => 'boolean',
            'is_active' => 'boolean',
            'stock' => 'integer',
            'sold_count' => 'integer',
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'max_per_buyer' => 'integer',
        ];
    }

    protected static function responseCacheTags(): array
    {
        return ['tickets'];
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }

    /**
     * Slugs are unique per event, so two events can both have a "vip" ticket.
     */
    public function scopeWithUniqueSlugConstraints(Builder $query, Model $model, string $attribute, array $config, string $slug): Builder
    {
        return $query->where('event_id', $model->event_id);
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
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['slug', 'kind', 'tier', 'purchase_type', 'is_active', 'visibility'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($projectId = $this->event?->project_id) {
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
            ->performOnCollections('poster')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(450)
            ->quality(85)
            ->sharpen(10)
            ->performOnCollections('poster')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(900)
            ->quality(90)
            ->sharpen(10)
            ->performOnCollections('poster');

        $this->addMediaConversion('lg')
            ->width(1200)
            ->quality(90)
            ->sharpen(10)
            ->performOnCollections('poster');

        $this->addMediaConversion('xl')
            ->width(1600)
            ->quality(95)
            ->sharpen(10)
            ->performOnCollections('poster');
    }

    public function getMediaCollections(): array
    {
        return [
            'poster' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
        ];
    }

    public function getPosterAttribute(): ?array
    {
        return $this->getMediaUrls('poster');
    }

    public function isEntry(): bool
    {
        return $this->kind === TicketKind::Entry;
    }

    public function isAddOn(): bool
    {
        return $this->kind === TicketKind::AddOn;
    }

    /**
     * An entry "Day Pass" where the buyer chooses a single day at purchase
     * (vs a bundle that is valid on every one of its valid_days).
     */
    public function offersDaySelection(): bool
    {
        return $this->isEntry() && $this->requires_day_selection;
    }

    /**
     * Whether buying this ticket requires a valid access code (visibility is
     * `hidden` or `code_required`).
     */
    public function isGated(): bool
    {
        return $this->visibility !== TicketVisibility::Public;
    }

    /**
     * Cheapest currently-active phase price; null when not on sale.
     */
    public function currentPrice(): ?string
    {
        $now = now();

        return $this->pricePhases
            ->where('is_active', true)
            ->filter(fn (TicketPricePhase $phase) => $phase->isActiveAt($now))
            ->min('price');
    }

    /**
     * Atomically reserve $qty units of stock with a single conditional
     * UPDATE (no SELECT ... FOR UPDATE): it only succeeds when the current
     * stock still has room for $qty more, so concurrent buyers of the same
     * hot ticket serialize on this row-level UPDATE instead of an
     * application-side lock-then-check. Null stock is unlimited (always
     * succeeds). Returns whether the reservation was granted.
     */
    public function reserve(int $qty): bool
    {
        $reserved = static::query()
            ->whereKey($this->id)
            // Parenthesized: SQL's AND binds tighter than OR, so an
            // unparenthesized "stock IS NULL OR sold_count + ? <= stock"
            // combined with the whereKey() above via AND would parse as
            // "(id = ? AND stock IS NULL) OR (sold_count + ? <= stock)" -
            // matching (and reserving stock on) every OTHER unlimited-or-
            // roomy ticket in the table, not just this one.
            ->whereRaw('(stock IS NULL OR sold_count + ? <= stock)', [$qty])
            ->update(['sold_count' => DB::raw('sold_count + '.(int) $qty)]) > 0;

        if ($reserved) {
            $this->clearResponseCacheForRawUpdate();
        }

        return $reserved;
    }

    /**
     * Guarded release of a previously reserved $qty: the `sold_count >= $qty`
     * condition means a redundant or out-of-order release call is a no-op
     * rather than driving the counter negative.
     */
    public function release(int $qty): void
    {
        $released = static::query()->whereKey($this->id)->where('sold_count', '>=', $qty)->decrement('sold_count', $qty);

        if ($released > 0) {
            $this->clearResponseCacheForRawUpdate();
        }
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function pricePhases(): HasMany
    {
        return $this->hasMany(TicketPricePhase::class)->ordered();
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TicketSession::class)->ordered();
    }

    public function validDays(): BelongsToMany
    {
        return $this->belongsToMany(EventDay::class, 'ticket_event_day');
    }

    public function accessCodes(): BelongsToMany
    {
        return $this->belongsToMany(AccessCode::class, 'access_code_ticket');
    }

    public function ticketOrderItems(): HasMany
    {
        return $this->hasMany(TicketOrderItem::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(TicketWaitlistEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
