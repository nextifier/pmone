<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $ulid
 * @property int $brand_event_id
 * @property string $order_number
 * @property string $status
 * @property string|null $notes
 * @property numeric $subtotal
 * @property numeric $tax_rate
 * @property numeric $tax_amount
 * @property numeric $total
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $discount_type
 * @property numeric|null $discount_value
 * @property numeric|null $discount_amount
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\BrandEvent $brandEvent
 * @property-read \App\Models\User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\User|null $updater
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order byStatus(string $status)
 * @method static \Database\Factories\OrderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereBrandEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereDiscountAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereDiscountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereDiscountValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStatus($value)
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
class Order extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'brand_event_id',
        'order_number',
        'status',
        'notes',
        'discount_type',
        'discount_value',
        'discount_amount',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'submitted_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'confirmed_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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

    // Methods

    public function recalculateTotal(): void
    {
        $subtotal = (float) $this->subtotal;
        $discountAmount = 0;

        if ($this->discount_type === 'percentage' && $this->discount_value > 0) {
            $discountAmount = round($subtotal * (float) $this->discount_value / 100, 2);
        } elseif ($this->discount_type === 'fixed' && $this->discount_value > 0) {
            $discountAmount = min((float) $this->discount_value, $subtotal);
        }

        $this->discount_amount = $discountAmount;
        $taxableAmount = $subtotal - $discountAmount;
        $this->tax_amount = round($taxableAmount * (float) $this->tax_rate / 100, 2);
        $this->total = $taxableAmount + (float) $this->tax_amount;
    }

    // Scopes

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
