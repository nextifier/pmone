<?php

namespace App\Models;

use App\Contracts\Payment\CheckoutPayable;
use App\Contracts\Pricing\Purchasable;
use App\Enums\Ticketing\TicketOrderStatus;
use App\Support\PaymentChannels;
use App\Traits\HasAdjustments;
use App\Traits\NormalizesAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A buyer's ticket purchase for a single event. Free vs paid is derived from
 * `total` (0 => Claim, > 0 => pay via the project's gateway). Tickets are only
 * valid once `status` is Confirmed. Mirrors the Reservation pattern.
 *
 * @property int $id
 * @property string $ulid
 * @property string $order_number
 * @property int $event_id
 * @property int|null $user_id
 * @property TicketOrderStatus $status
 * @property string|null $buyer_name
 * @property string|null $buyer_email
 * @property string|null $buyer_phone
 * @property numeric $subtotal
 * @property numeric $discount_amount
 * @property numeric $total
 * @property string|null $promo_code_applied
 * @property string|null $payment_ref
 * @property int|null $payment_gateway_id
 * @property string|null $xendit_invoice_id
 * @property string|null $payment_url
 * @property string|null $payment_channel
 * @property Carbon|null $payment_expires_at
 * @property Carbon|null $paid_at
 * @property string|null $magic_link_token
 * @property Carbon|null $magic_link_expires_at
 * @property string $source
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $return_origin
 * @property string|null $batch_label
 * @property string|null $batch_status
 * @property string|null $access_code_applied
 * @property Carbon|null $marked_paid_manually_at
 * @property int|null $marked_paid_by
 * @property string|null $idempotency_key
 * @property Carbon|null $paid_after_expiry_at
 * @property-read Collection<int, AccessCodeRedemption> $accessCodeRedemptions
 * @property-read int|null $access_code_redemptions_count
 * @property-read Collection<int, AppliedAdjustment> $activeAdjustments
 * @property-read int|null $active_adjustments_count
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, AppliedAdjustment> $adjustments
 * @property-read int|null $adjustments_count
 * @property-read Collection<int, Attendee> $attendees
 * @property-read int|null $attendees_count
 * @property-read User|null $creator
 * @property-read Collection<int, AppliedAdjustment> $discountAdjustments
 * @property-read int|null $discount_adjustments_count
 * @property-read Event|null $event
 * @property-read Collection<int, TicketOrderItem> $items
 * @property-read int|null $items_count
 * @property-read User|null $markedPaidBy
 * @property-read ProjectPaymentGateway|null $paymentGateway
 * @property-read Collection<int, AppliedAdjustment> $penaltyAdjustments
 * @property-read int|null $penalty_adjustments_count
 * @property-read User|null $updater
 * @property-read User|null $user
 *
 * @method static Builder<static>|TicketOrder confirmed()
 * @method static \Database\Factories\TicketOrderFactory factory($count = null, $state = [])
 * @method static Builder<static>|TicketOrder newModelQuery()
 * @method static Builder<static>|TicketOrder newQuery()
 * @method static Builder<static>|TicketOrder onlyTrashed()
 * @method static Builder<static>|TicketOrder query()
 * @method static Builder<static>|TicketOrder whereAccessCodeApplied($value)
 * @method static Builder<static>|TicketOrder whereBatchLabel($value)
 * @method static Builder<static>|TicketOrder whereBatchStatus($value)
 * @method static Builder<static>|TicketOrder whereBuyerEmail($value)
 * @method static Builder<static>|TicketOrder whereBuyerName($value)
 * @method static Builder<static>|TicketOrder whereBuyerPhone($value)
 * @method static Builder<static>|TicketOrder whereCreatedAt($value)
 * @method static Builder<static>|TicketOrder whereCreatedBy($value)
 * @method static Builder<static>|TicketOrder whereDeletedAt($value)
 * @method static Builder<static>|TicketOrder whereDeletedBy($value)
 * @method static Builder<static>|TicketOrder whereDiscountAmount($value)
 * @method static Builder<static>|TicketOrder whereEventId($value)
 * @method static Builder<static>|TicketOrder whereId($value)
 * @method static Builder<static>|TicketOrder whereIdempotencyKey($value)
 * @method static Builder<static>|TicketOrder whereIpAddress($value)
 * @method static Builder<static>|TicketOrder whereMagicLinkExpiresAt($value)
 * @method static Builder<static>|TicketOrder whereMagicLinkToken($value)
 * @method static Builder<static>|TicketOrder whereMarkedPaidBy($value)
 * @method static Builder<static>|TicketOrder whereMarkedPaidManuallyAt($value)
 * @method static Builder<static>|TicketOrder whereNotes($value)
 * @method static Builder<static>|TicketOrder whereOrderNumber($value)
 * @method static Builder<static>|TicketOrder wherePaidAfterExpiryAt($value)
 * @method static Builder<static>|TicketOrder wherePaidAt($value)
 * @method static Builder<static>|TicketOrder wherePaymentChannel($value)
 * @method static Builder<static>|TicketOrder wherePaymentExpiresAt($value)
 * @method static Builder<static>|TicketOrder wherePaymentGatewayId($value)
 * @method static Builder<static>|TicketOrder wherePaymentRef($value)
 * @method static Builder<static>|TicketOrder wherePaymentUrl($value)
 * @method static Builder<static>|TicketOrder wherePromoCodeApplied($value)
 * @method static Builder<static>|TicketOrder whereReturnOrigin($value)
 * @method static Builder<static>|TicketOrder whereSource($value)
 * @method static Builder<static>|TicketOrder whereStatus($value)
 * @method static Builder<static>|TicketOrder whereSubtotal($value)
 * @method static Builder<static>|TicketOrder whereTotal($value)
 * @method static Builder<static>|TicketOrder whereUlid($value)
 * @method static Builder<static>|TicketOrder whereUpdatedAt($value)
 * @method static Builder<static>|TicketOrder whereUpdatedBy($value)
 * @method static Builder<static>|TicketOrder whereUserAgent($value)
 * @method static Builder<static>|TicketOrder whereUserId($value)
 * @method static Builder<static>|TicketOrder whereXenditInvoiceId($value)
 * @method static Builder<static>|TicketOrder withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|TicketOrder withoutTrashed()
 *
 * @mixin \Eloquent
 */
class TicketOrder extends Model implements CheckoutPayable, Purchasable
{
    use HasAdjustments;
    use HasFactory;
    use LogsActivity;
    use NormalizesAttributes;
    use SoftDeletes;

    /**
     * Raw (unhashed) magic-link token, only available in-memory right after
     * creation so it can be embedded in the e-ticket link / email.
     */
    public ?string $magicLinkRaw = null;

    /** @var array<string, string> */
    protected array $normalizes = [
        'buyer_name' => 'personName',
        'buyer_email' => 'email',
        'buyer_phone' => 'phone',
    ];

    protected $fillable = [
        'order_number',
        'event_id',
        'user_id',
        'status',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'subtotal',
        'discount_amount',
        'total',
        'promo_code_applied',
        'access_code_applied',
        'payment_ref',
        'payment_gateway_id',
        'xendit_invoice_id',
        'payment_url',
        'payment_channel',
        'payment_expires_at',
        'paid_at',
        'paid_after_expiry_at',
        'marked_paid_manually_at',
        'marked_paid_by',
        'magic_link_token',
        'magic_link_expires_at',
        'source',
        'batch_label',
        'batch_status',
        'return_origin',
        'ip_address',
        'user_agent',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketOrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'paid_after_expiry_at' => 'datetime',
            'marked_paid_manually_at' => 'datetime',
            'magic_link_expires_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (TicketOrder $model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            if (empty($model->order_number)) {
                $model->order_number = static::generateOrderNumber();
            }

            if (empty($model->magic_link_token)) {
                // Deterministic HMAC so jobs/webhooks can rebuild the identical
                // link without rolling it. Only the SHA-256 hash is stored;
                // resolveByMagicLink() hashes the incoming token the same way.
                $model->magicLinkRaw = self::magicLinkTokenFor($model->order_number);
                $model->magic_link_token = hash('sha256', $model->magicLinkRaw);
            }

            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function (TicketOrder $model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function (TicketOrder $model) {
            if ($model->isForceDeleting() === false && auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        do {
            $number = 'TIX-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (static::withTrashed()->where('order_number', $number)->exists());

        return $number;
    }

    /**
     * Deterministic magic-link token (the raw value embedded in URLs/emails).
     */
    public static function magicLinkTokenFor(string $orderNumber): string
    {
        return hash_hmac('sha256', 'ticket-order-magic:'.$orderNumber, (string) config('app.key'));
    }

    /**
     * Resolve an order from a raw magic-link token (constant-time column lookup
     * on the stored SHA-256 hash).
     */
    public static function resolveByMagicLink(string $token): ?self
    {
        return static::query()->where('magic_link_token', hash('sha256', $token))->first();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total', 'discount_amount', 'promo_code_applied', 'paid_at', 'payment_channel', 'payment_gateway_id', 'marked_paid_manually_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($projectId = $this->loadMissing('event')->event?->project_id) {
            $activity->properties = $activity->properties->put('project_id', $projectId);
        }
    }

    public function isFree(): bool
    {
        return (float) $this->total <= 0.0;
    }

    public function isConfirmed(): bool
    {
        return $this->status === TicketOrderStatus::Confirmed;
    }

    public function isRefunded(): bool
    {
        return $this->status === TicketOrderStatus::Refunded;
    }

    // ─── Purchasable contract (promo/adjustment engine) ──────────────────
    // Tickets carry no tax or service charge, so total = subtotal - discount.

    /**
     * The pricing engine applies discounts to the "taxable" base, so the ticket
     * line must be flagged taxable for promo codes to discount it. Tickets carry
     * no tax (taxRate() = 0), so this adds the line to the discount base without
     * ever charging tax.
     *
     * @return array<int, array{key: string, amount: float, taxable: bool}>
     */
    public function pricingLines(): array
    {
        return [
            ['key' => 'tickets', 'amount' => (float) $this->subtotal, 'taxable' => true],
        ];
    }

    public function taxRate(): float
    {
        return 0.0;
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
        return $this->buyer_email;
    }

    public function checkoutReference(): string
    {
        return (string) $this->order_number;
    }

    public function checkoutAmount(): float
    {
        return (float) $this->total;
    }

    public function checkoutDescription(): string
    {
        return "Ticket order {$this->order_number} - {$this->event?->title}";
    }

    public function checkoutCustomer(): array
    {
        return [
            'given_names' => $this->buyer_name ?: null,
            'email' => $this->buyer_email ?: null,
            'mobile_number' => $this->buyer_phone ?: null,
        ];
    }

    /**
     * Canonical channel codes this event restricts ticket checkout to, or null
     * for no restriction. Defensively drops any stored code we no longer know.
     *
     * @return array<int, string>|null
     */
    public function allowedPaymentChannels(): ?array
    {
        $codes = data_get($this->event?->settings, 'tickets.allowed_payment_channels');
        if (! is_array($codes) || $codes === []) {
            return null;
        }

        $valid = array_values(array_unique(array_filter(
            array_map(fn ($code) => is_string($code) ? strtoupper($code) : null, $codes),
            fn ($code) => $code !== null && PaymentChannels::isValid($code),
        )));

        return $valid === [] ? null : $valid;
    }

    /**
     * @param  array<string, float|string|null>  $totals
     */
    public function persistTotals(array $totals): void
    {
        $this->forceFill([
            'discount_amount' => $totals['discount_amount'] ?? 0,
            'total' => $totals['total_amount'] ?? 0,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPurchaseContext(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return [
            'event_id' => $this->event_id,
            'ticket_ids' => $items->pluck('ticket_id')->filter()->unique()->values()->all(),
            'qty' => (int) $items->sum('quantity'),
            'email' => $this->buyer_email,
            'morph_class' => $this->getMorphClass(),
        ];
    }

    /**
     * @return array<int, array{line_key: string, item_id: int|string|null, item_type: string|null, category_id: int|null, unit_price: float, qty: int, taxable: bool, meta?: array<string, mixed>}>
     */
    public function purchaseItems(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $out = [];

        foreach ($items as $item) {
            $qty = max(1, (int) $item->quantity);

            for ($i = 0; $i < $qty; $i++) {
                $out[] = [
                    'line_key' => 'tickets',
                    'item_id' => $item->id,
                    'item_type' => 'ticket',
                    'category_id' => (int) $item->ticket_id,
                    'unit_price' => (float) $item->unit_price,
                    'qty' => 1,
                    'taxable' => false,
                    'meta' => ['ticket_id' => $item->ticket_id],
                ];
            }
        }

        return $out;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(ProjectPaymentGateway::class, 'payment_gateway_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TicketOrderItem::class);
    }

    public function attendees(): HasManyThrough
    {
        return $this->hasManyThrough(Attendee::class, TicketOrderItem::class);
    }

    public function accessCodeRedemptions(): HasMany
    {
        return $this->hasMany(AccessCodeRedemption::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function markedPaidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_paid_by');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', TicketOrderStatus::Confirmed->value);
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
