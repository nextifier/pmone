<?php

namespace App\Models;

use App\Contracts\Pricing\Purchasable;
use App\Enums\OperationalStatus;
use App\Enums\PaymentStatus;
use App\Traits\HasAdjustments;
use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $ulid
 * @property int $brand_event_id
 * @property string $order_number
 * @property OperationalStatus $operational_status
 * @property string|null $notes
 * @property string|null $internal_notes
 * @property numeric $discount_amount
 * @property numeric $subtotal
 * @property numeric $tax_rate
 * @property numeric $tax_amount
 * @property numeric $total
 * @property Carbon|null $submitted_at
 * @property Carbon|null $confirmed_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property PaymentStatus $payment_status
 * @property string|null $cancellation_reason
 * @property string|null $order_period
 * @property numeric $penalty_amount
 * @property string|null $promo_code_applied
 * @property-read Collection<int, AppliedAdjustment> $activeAdjustments
 * @property-read int|null $active_adjustments_count
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, AppliedAdjustment> $adjustments
 * @property-read int|null $adjustments_count
 * @property-read BrandEvent $brandEvent
 * @property-read User|null $creator
 * @property-read Collection<int, AppliedAdjustment> $discountAdjustments
 * @property-read int|null $discount_adjustments_count
 * @property-read Collection<int, OrderItem> $items
 * @property-read int|null $items_count
 * @property-read Collection<int, AppliedAdjustment> $penaltyAdjustments
 * @property-read int|null $penalty_adjustments_count
 * @property-read User|null $updater
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order byOperationalStatus(string $status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order byPaymentStatus(string $status)
 * @method static \Database\Factories\OrderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereBrandEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCancellationReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereDiscountAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOperationalStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderPeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePenaltyAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePromoCodeApplied($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTaxAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTaxRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedBy($value)
 *
 * @mixin \Eloquent
 */
class Order extends Model implements HasMedia, Purchasable
{
    use HasAdjustments;
    use HasFactory;
    use HasMediaManager;
    use InteractsWithMedia;
    use LogsActivity;

    protected $fillable = [
        'brand_event_id',
        'order_number',
        'operational_status',
        'payment_status',
        'cancellation_reason',
        'order_period',
        'source',
        'notes',
        'internal_notes',
        'subtotal',
        'discount_amount',
        'penalty_amount',
        'promo_code_applied',
        'tax_rate',
        'tax_amount',
        'total',
        'submitted_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'operational_status' => OperationalStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            if (empty($model->order_number)) {
                $model->order_number = static::generateOrderNumber();
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

    public static function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "ORD-{$date}-";

        $lastOrder = static::where('order_number', 'like', "{$prefix}%")
            ->orderByDesc('order_number')
            ->first();

        if ($lastOrder) {
            $lastNumber = (int) substr($lastOrder->order_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getMediaCollections(): array
    {
        return [
            'invoice' => [
                'single_file' => true,
                'mime_types' => ['application/pdf'],
                'max_size' => 20480,
            ],
            'receipt' => [
                'single_file' => true,
                'mime_types' => ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'],
                'max_size' => 20480,
            ],
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'operational_status',
                'payment_status',
                'confirmed_at',
                'discount_amount',
                'penalty_amount',
                'promo_code_applied',
                'total',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($projectId = $this->loadMissing('brandEvent.event')->brandEvent?->event?->project_id) {
            $activity->properties = $activity->properties->put('project_id', $projectId);
        }
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    // Relationships

    public function brandEvent(): BelongsTo
    {
        return $this->belongsTo(BrandEvent::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // --- Purchasable contract ---

    public function pricingLines(): array
    {
        return [
            ['key' => 'subtotal', 'amount' => (float) $this->subtotal, 'taxable' => true],
        ];
    }

    public function taxRate(): float
    {
        return (float) ($this->tax_rate ?? 0) / 100;
    }

    public function serviceChargeRate(): float
    {
        return 0.0;
    }

    public function subtotalForDiscountBase(): float
    {
        return (float) $this->subtotal;
    }

    public function customerEmail(): ?string
    {
        $brandEvent = $this->relationLoaded('brandEvent') ? $this->brandEvent : $this->brandEvent()->with('brand')->first();

        return $brandEvent?->brand?->email ?? null;
    }

    public function persistTotals(array $totals): void
    {
        $this->forceFill([
            'discount_amount' => $totals['discount_amount'] ?? 0,
            'penalty_amount' => $totals['penalty_amount'] ?? 0,
            'tax_amount' => $totals['tax_amount'] ?? 0,
            'total' => $totals['total_amount'] ?? 0,
        ])->save();
    }

    public function getPurchaseContext(): array
    {
        $brandEvent = $this->relationLoaded('brandEvent') ? $this->brandEvent : $this->brandEvent()->first();
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return [
            'event_id' => $brandEvent?->event_id,
            'brand_id' => $brandEvent?->brand_id,
            'event_product_ids' => $items->pluck('event_product_id')->filter()->unique()->values()->all(),
            'category_ids' => $items->pluck('category_id')->filter()->unique()->values()->all(),
            'qty' => (int) $items->sum('quantity'),
            'morph_class' => $this->getMorphClass(),
            'email' => $this->customerEmail(),
        ];
    }

    public function purchaseItems(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        $out = [];

        foreach ($items as $item) {
            $qty = max(1, (int) $item->quantity);
            $unitPrice = (float) $item->unit_price;

            for ($i = 0; $i < $qty; $i++) {
                $out[] = [
                    'line_key' => 'subtotal',
                    'item_id' => $item->id,
                    'item_type' => 'event_product',
                    'category_id' => $item->category_id !== null ? (int) $item->category_id : null,
                    'unit_price' => $unitPrice,
                    'qty' => 1,
                    'taxable' => true,
                    'meta' => [
                        'event_product_id' => $item->event_product_id,
                        'product_name' => $item->product_name,
                    ],
                ];
            }
        }

        return $out;
    }

    // Scopes

    public function scopeByOperationalStatus($query, string $status)
    {
        return $query->where('operational_status', $status);
    }

    public function scopeByPaymentStatus($query, string $status)
    {
        return $query->where('payment_status', $status);
    }
}
