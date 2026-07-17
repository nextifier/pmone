<?php

namespace App\Models;

use App\Contracts\Payment\CheckoutPayable;
use App\Contracts\Pricing\Purchasable;
use App\Enums\IdentityType;
use App\Enums\PaymentMethod;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Traits\HasAdjustments;
use App\Traits\HasMediaManager;
use App\Traits\NormalizesAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $ulid
 * @property string $reservation_number
 * @property int $event_id
 * @property int $hotel_id
 * @property ReservationStatus $status
 * @property Carbon|null $payment_expires_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $voucher_sent_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $refunded_at
 * @property string $guest_name
 * @property string $guest_email
 * @property string $guest_phone
 * @property IdentityType $guest_identity_type
 * @property string $guest_identity_number
 * @property string|null $guest_nationality
 * @property string|null $guest_company
 * @property string|null $special_request
 * @property numeric $subtotal_rooms
 * @property numeric $subtotal_transfer
 * @property numeric $surcharge_amount
 * @property numeric $tax_amount
 * @property numeric $service_charge_amount
 * @property numeric $discount_amount
 * @property numeric $total_amount
 * @property string|null $xendit_invoice_id
 * @property string|null $payment_url
 * @property PaymentMethod|null $payment_method
 * @property numeric|null $refund_amount
 * @property string|null $xendit_refund_id
 * @property string|null $refund_reason
 * @property string|null $cancellation_reason
 * @property string $magic_link_token
 * @property ReservationSource $source
 * @property string|null $project_username
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property numeric $penalty_amount
 * @property string|null $promo_code_applied
 * @property Carbon|null $magic_link_expires_at
 * @property string|null $payment_channel
 * @property string|null $payment_destination
 * @property string|null $xendit_payment_id
 * @property int|null $payment_gateway_id
 * @property-read Collection<int, AppliedAdjustment> $activeAdjustments
 * @property-read int|null $active_adjustments_count
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, AppliedAdjustment> $adjustments
 * @property-read int|null $adjustments_count
 * @property-read User|null $creator
 * @property-read User|null $deleter
 * @property-read Collection<int, AppliedAdjustment> $discountAdjustments
 * @property-read int|null $discount_adjustments_count
 * @property-read Event|null $event
 * @property-read Hotel|null $hotel
 * @property-read Collection<int, ReservationItem> $items
 * @property-read int|null $items_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read ProjectPaymentGateway|null $paymentGateway
 * @property-read Collection<int, AppliedAdjustment> $penaltyAdjustments
 * @property-read int|null $penalty_adjustments_count
 * @property-read Collection<int, ReservationTransfer> $transfers
 * @property-read int|null $transfers_count
 * @property-read User|null $updater
 *
 * @method static \Database\Factories\ReservationFactory factory($count = null, $state = [])
 * @method static Builder<static>|Reservation forEvent(?int $eventId)
 * @method static Builder<static>|Reservation forHotel(int $hotelId)
 * @method static Builder<static>|Reservation newModelQuery()
 * @method static Builder<static>|Reservation newQuery()
 * @method static Builder<static>|Reservation onlyTrashed()
 * @method static Builder<static>|Reservation query()
 * @method static Builder<static>|Reservation status(\App\Enums\ReservationStatus|string $status)
 * @method static Builder<static>|Reservation whereCancellationReason($value)
 * @method static Builder<static>|Reservation whereCancelledAt($value)
 * @method static Builder<static>|Reservation whereCreatedAt($value)
 * @method static Builder<static>|Reservation whereCreatedBy($value)
 * @method static Builder<static>|Reservation whereDeletedAt($value)
 * @method static Builder<static>|Reservation whereDeletedBy($value)
 * @method static Builder<static>|Reservation whereDiscountAmount($value)
 * @method static Builder<static>|Reservation whereEventId($value)
 * @method static Builder<static>|Reservation whereGuestCompany($value)
 * @method static Builder<static>|Reservation whereGuestEmail($value)
 * @method static Builder<static>|Reservation whereGuestIdentityNumber($value)
 * @method static Builder<static>|Reservation whereGuestIdentityType($value)
 * @method static Builder<static>|Reservation whereGuestName($value)
 * @method static Builder<static>|Reservation whereGuestNationality($value)
 * @method static Builder<static>|Reservation whereGuestPhone($value)
 * @method static Builder<static>|Reservation whereHotelId($value)
 * @method static Builder<static>|Reservation whereId($value)
 * @method static Builder<static>|Reservation whereIpAddress($value)
 * @method static Builder<static>|Reservation whereMagicLinkExpiresAt($value)
 * @method static Builder<static>|Reservation whereMagicLinkToken($value)
 * @method static Builder<static>|Reservation whereNotes($value)
 * @method static Builder<static>|Reservation wherePaidAt($value)
 * @method static Builder<static>|Reservation wherePaymentChannel($value)
 * @method static Builder<static>|Reservation wherePaymentDestination($value)
 * @method static Builder<static>|Reservation wherePaymentExpiresAt($value)
 * @method static Builder<static>|Reservation wherePaymentGatewayId($value)
 * @method static Builder<static>|Reservation wherePaymentMethod($value)
 * @method static Builder<static>|Reservation wherePaymentUrl($value)
 * @method static Builder<static>|Reservation wherePenaltyAmount($value)
 * @method static Builder<static>|Reservation whereProjectUsername($value)
 * @method static Builder<static>|Reservation wherePromoCodeApplied($value)
 * @method static Builder<static>|Reservation whereRefundAmount($value)
 * @method static Builder<static>|Reservation whereRefundReason($value)
 * @method static Builder<static>|Reservation whereRefundedAt($value)
 * @method static Builder<static>|Reservation whereReservationNumber($value)
 * @method static Builder<static>|Reservation whereServiceChargeAmount($value)
 * @method static Builder<static>|Reservation whereSource($value)
 * @method static Builder<static>|Reservation whereSpecialRequest($value)
 * @method static Builder<static>|Reservation whereStatus($value)
 * @method static Builder<static>|Reservation whereSubtotalRooms($value)
 * @method static Builder<static>|Reservation whereSubtotalTransfer($value)
 * @method static Builder<static>|Reservation whereSurchargeAmount($value)
 * @method static Builder<static>|Reservation whereTaxAmount($value)
 * @method static Builder<static>|Reservation whereTotalAmount($value)
 * @method static Builder<static>|Reservation whereUlid($value)
 * @method static Builder<static>|Reservation whereUpdatedAt($value)
 * @method static Builder<static>|Reservation whereUpdatedBy($value)
 * @method static Builder<static>|Reservation whereUserAgent($value)
 * @method static Builder<static>|Reservation whereVoucherSentAt($value)
 * @method static Builder<static>|Reservation whereXenditInvoiceId($value)
 * @method static Builder<static>|Reservation whereXenditPaymentId($value)
 * @method static Builder<static>|Reservation whereXenditRefundId($value)
 * @method static Builder<static>|Reservation withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Reservation withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Reservation extends Model implements CheckoutPayable, HasMedia, Purchasable
{
    use HasAdjustments;
    use HasFactory;
    use HasMediaManager;
    use InteractsWithMedia;
    use LogsActivity;
    use NormalizesAttributes;
    use SoftDeletes;

    /**
     * Raw magic link token (only set in-memory after creation, never persisted).
     */
    public ?string $magicLinkRaw = null;

    /** @var array<string, string> */
    protected array $normalizes = [
        'guest_name' => 'personName',
        'guest_email' => 'email',
        'guest_phone' => 'phone',
        'guest_company' => 'orgName',
        'guest_nationality' => 'orgName',
    ];

    protected $fillable = [
        'reservation_number',
        'event_id',
        'hotel_id',
        'status',
        'payment_expires_at',
        'paid_at',
        'voucher_sent_at',
        'cancelled_at',
        'refunded_at',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_identity_type',
        'guest_identity_number',
        'guest_nationality',
        'guest_company',
        'special_request',
        'subtotal_rooms',
        'subtotal_transfer',
        'surcharge_amount',
        'penalty_amount',
        'tax_amount',
        'service_charge_amount',
        'discount_amount',
        'promo_code_applied',
        'total_amount',
        'xendit_invoice_id',
        'xendit_payment_id',
        'payment_url',
        'payment_method',
        'payment_gateway_id',
        'payment_channel',
        'payment_destination',
        'refund_amount',
        'xendit_refund_id',
        'refund_reason',
        'cancellation_reason',
        'magic_link_token',
        'magic_link_expires_at',
        'source',
        'project_username',
        'ip_address',
        'user_agent',
        'return_origin',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'source' => ReservationSource::class,
            'guest_identity_type' => IdentityType::class,
            'payment_method' => PaymentMethod::class,
            'payment_expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'voucher_sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'magic_link_expires_at' => 'datetime',
            'subtotal_rooms' => 'decimal:2',
            'subtotal_transfer' => 'decimal:2',
            'surcharge_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'service_charge_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            if (empty($model->event_id) && $model->hotel_id) {
                // BC-safe fallback: pick first active event the hotel is attached to.
                // Callers should pass event_id explicitly (see ReservationService).
                $model->event_id = DB::table('hotel_event')
                    ->where('hotel_id', $model->hotel_id)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->value('event_id');
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
            ->logOnly([
                'status',
                'paid_at',
                'voucher_sent_at',
                'cancelled_at',
                'refunded_at',
                'discount_amount',
                'penalty_amount',
                'total_amount',
                'promo_code_applied',
                'payment_channel',
                'payment_gateway_id',
                'refund_amount',
                'refund_reason',
                'cancellation_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($projectId = $this->loadMissing('event')->event?->project_id) {
            $activity->properties = $activity->properties->put('project_id', $projectId);
        }
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function getMediaCollections(): array
    {
        return [
            'voucher' => [
                'single_file' => true,
                'mime_types' => ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'],
                'max_size' => 20480,
            ],
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(ProjectPaymentGateway::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(ReservationTransfer::class);
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

    public function scopeStatus(Builder $query, ReservationStatus|string $status): Builder
    {
        $value = $status instanceof ReservationStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    public function scopeForEvent(Builder $query, ?int $eventId): Builder
    {
        if ($eventId === null) {
            return $query->whereNull('event_id');
        }

        return $query->where('event_id', $eventId);
    }

    public function scopeForHotel(Builder $query, int $hotelId): Builder
    {
        return $query->where('hotel_id', $hotelId);
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    // --- Purchasable contract ---

    public function pricingLines(): array
    {
        return [
            // `subtotal_rooms` already includes the per-allotment surcharge folded
            // into the room price, so surcharge must NOT be a separate line here -
            // that would double-count it in the subtotal and total.
            ['key' => 'rooms', 'amount' => (float) $this->subtotal_rooms, 'taxable' => true],
            ['key' => 'transfer', 'amount' => (float) $this->subtotal_transfer, 'taxable' => true],
        ];
    }

    public function taxRate(): float
    {
        $hotel = $this->relationLoaded('hotel') ? $this->hotel : $this->hotel()->first();

        return (float) ($hotel?->tax_percentage ?? 11) / 100;
    }

    public function serviceChargeRate(): float
    {
        $hotel = $this->relationLoaded('hotel') ? $this->hotel : $this->hotel()->first();

        return (float) ($hotel?->service_charge_percentage ?? 0) / 100;
    }

    public function subtotalForDiscountBase(): float
    {
        // `subtotal_rooms` already includes the folded-in allotment surcharge -
        // adding `surcharge_amount` again would double-count it in the base.
        return (float) ($this->subtotal_rooms + $this->subtotal_transfer);
    }

    public function customerEmail(): ?string
    {
        return $this->guest_email;
    }

    public function checkoutReference(): string
    {
        return (string) $this->reservation_number;
    }

    public function checkoutAmount(): float
    {
        return (float) $this->total_amount;
    }

    public function checkoutDescription(): string
    {
        return "Hotel reservation {$this->reservation_number} - {$this->hotel?->name}";
    }

    public function checkoutCustomer(): array
    {
        return [
            'given_names' => $this->guest_name ?: null,
            'email' => $this->guest_email ?: null,
            'mobile_number' => $this->guest_phone ?: null,
        ];
    }

    /**
     * Hotel reservations accept every channel enabled on the gateway - the
     * per-event allowlist is a ticketing-only feature.
     */
    public function allowedPaymentChannels(): ?array
    {
        return null;
    }

    public function persistTotals(array $totals): void
    {
        $this->forceFill([
            'penalty_amount' => $totals['penalty_amount'] ?? 0,
            'discount_amount' => $totals['discount_amount'] ?? 0,
            'tax_amount' => $totals['tax_amount'] ?? 0,
            'service_charge_amount' => $totals['service_charge_amount'] ?? 0,
            'total_amount' => $totals['total_amount'] ?? 0,
        ])->save();
    }

    public function getPurchaseContext(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        $checkInDates = $items->pluck('check_in_date')->filter()->map(fn ($d) => (string) $d)->all();
        $nearestCheckIn = ! empty($checkInDates) ? min($checkInDates) : null;

        return [
            'event_id' => $this->event_id,
            'hotel_id' => $this->hotel_id,
            'room_type_ids' => $items->pluck('room_type_id')->filter()->unique()->values()->all(),
            'nights' => (int) $items->sum('nights'),
            'qty' => (int) $items->sum('qty'),
            'check_in_dates' => $checkInDates,
            'nearest_check_in' => $nearestCheckIn,
            'email' => $this->guest_email,
            'morph_class' => $this->getMorphClass(),
        ];
    }

    /**
     * Allocate bonus quantities to room items for "buy X get Y" promos. Increases
     * each item's qty + subtotal in place, and recomputes the parent reservation's
     * subtotal_rooms so PricingService sees the new base.
     *
     * Returns the per-item allocation record so the promo adjustment can store it
     * for later reversal (when the promo is voided).
     *
     * @param  callable(ReservationItem): int  $bonusFor  Function that returns bonus qty for a given item.
     * @return array<int, array{item_id: int, original_qty: int, bonus_qty: int, new_qty: int}>
     */
    public function allocateItemBonuses(callable $bonusFor): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $allocations = [];

        foreach ($items as $item) {
            $bonusQty = (int) $bonusFor($item);
            if ($bonusQty <= 0) {
                continue;
            }

            $originalQty = (int) $item->qty;
            $newQty = $originalQty + $bonusQty;
            $nights = max(1, (int) $item->nights);
            $newSubtotal = (float) $item->rate_per_night * $nights * $newQty;

            $item->forceFill([
                'qty' => $newQty,
                'subtotal' => $newSubtotal,
            ])->save();

            $allocations[] = [
                'item_id' => $item->id,
                'original_qty' => $originalQty,
                'bonus_qty' => $bonusQty,
                'new_qty' => $newQty,
            ];
        }

        if (! empty($allocations)) {
            $this->refreshRoomsSubtotal();
        }

        return $allocations;
    }

    /**
     * Revert previously-allocated bonus qty (e.g. when the buy-x-get-y promo is voided).
     *
     * @param  array<int, array{item_id: int, original_qty: int, bonus_qty: int, new_qty: int}>  $allocations
     */
    public function revertItemBonuses(array $allocations): void
    {
        foreach ($allocations as $alloc) {
            $item = $this->items()->whereKey($alloc['item_id'])->first();
            if (! $item) {
                continue;
            }

            $nights = max(1, (int) $item->nights);
            $originalQty = (int) $alloc['original_qty'];

            $item->forceFill([
                'qty' => $originalQty,
                'subtotal' => (float) $item->rate_per_night * $nights * $originalQty,
            ])->save();
        }

        $this->refreshRoomsSubtotal();
    }

    /**
     * Sum item subtotals back into reservation.subtotal_rooms.
     */
    protected function refreshRoomsSubtotal(): void
    {
        $sum = (float) $this->items()->sum('subtotal');
        $this->forceFill(['subtotal_rooms' => $sum])->save();
        $this->setRelation('items', $this->items()->get());
    }

    public function purchaseItems(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $transfers = $this->relationLoaded('transfers') ? $this->transfers : $this->transfers()->get();

        $out = [];

        foreach ($items as $item) {
            $qty = max(1, (int) $item->qty);
            $nights = max(1, (int) $item->nights);
            $unitPrice = $qty > 0 ? (float) $item->subtotal / $qty : 0.0;

            for ($i = 0; $i < $qty; $i++) {
                $out[] = [
                    'line_key' => 'rooms',
                    'item_id' => $item->id,
                    'item_type' => 'room_type',
                    'category_id' => (int) $item->room_type_id,
                    'unit_price' => $unitPrice,
                    'qty' => 1,
                    'taxable' => true,
                    'meta' => [
                        'room_type_id' => $item->room_type_id,
                        'nights' => $nights,
                        'check_in_date' => (string) $item->check_in_date,
                    ],
                ];
            }
        }

        foreach ($transfers as $transfer) {
            $out[] = [
                'line_key' => 'transfer',
                'item_id' => $transfer->id,
                'item_type' => 'transfer_option',
                'category_id' => (int) $transfer->transfer_option_id,
                'unit_price' => (float) $transfer->price,
                'qty' => 1,
                'taxable' => true,
                'meta' => [
                    'transfer_option_id' => $transfer->transfer_option_id,
                    'direction' => $transfer->direction?->value ?? $transfer->direction,
                ],
            ];
        }

        return $out;
    }
}
