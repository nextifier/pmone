<?php

namespace App\Models;

use App\Enums\Ticketing\PurchaseType;
use App\Enums\Ticketing\TicketKind;
use App\Enums\Ticketing\TicketVisibility;
use App\Traits\ClearsResponseCache;
use App\Traits\HasMediaManager;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

/**
 * A sellable ticket product belonging to an event. `kind` separates entry
 * tickets (carry `valid_days`) from add-ons (carry sessions + print_on_redeem).
 * Pricing lives in PricePhase; add-on scheduling lives in TicketSession.
 *
 * @property int $id
 * @property string $ulid
 * @property int $event_id
 * @property string $slug
 * @property TicketKind $kind
 * @property array<array-key, mixed>|null $title
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
 * @property TicketVisibility $visibility
 * @property int|null $order_column
 * @property-read Event|null $event
 * @property-read array|null $poster
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
            ->performOnCollections('poster')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(900)
            ->quality(90)
            ->performOnCollections('poster');

        $this->addMediaConversion('lg')
            ->width(1200)
            ->quality(90)
            ->performOnCollections('poster');

        $this->addMediaConversion('xl')
            ->width(1500)
            ->quality(95)
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
