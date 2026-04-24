<?php

namespace App\Models;

use App\Enums\IdentityType;
use App\Enums\PaymentMethod;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
 * @property \Illuminate\Support\Carbon $payment_expires_at
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $voucher_sent_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $refunded_at
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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\User|null $deleter
 * @property-read \App\Models\Event|null $event
 * @property-read \App\Models\Hotel|null $hotel
 * @property-read Collection<int, \App\Models\ReservationItem> $items
 * @property-read int|null $items_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, \App\Models\ReservationTransfer> $transfers
 * @property-read int|null $transfers_count
 * @property-read \App\Models\User|null $updater
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
 * @method static Builder<static>|Reservation whereMagicLinkToken($value)
 * @method static Builder<static>|Reservation whereNotes($value)
 * @method static Builder<static>|Reservation wherePaidAt($value)
 * @method static Builder<static>|Reservation wherePaymentExpiresAt($value)
 * @method static Builder<static>|Reservation wherePaymentMethod($value)
 * @method static Builder<static>|Reservation wherePaymentUrl($value)
 * @method static Builder<static>|Reservation whereProjectUsername($value)
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
 * @method static Builder<static>|Reservation whereXenditRefundId($value)
 * @method static Builder<static>|Reservation withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Reservation withoutTrashed()
 * @mixin \Eloquent
 */
class Reservation extends Model implements HasMedia
{
    use HasFactory;
    use HasMediaManager;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    /**
     * Raw magic link token (only set in-memory after creation, never persisted).
     */
    public ?string $magicLinkRaw = null;

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
        'tax_amount',
        'service_charge_amount',
        'discount_amount',
        'total_amount',
        'xendit_invoice_id',
        'payment_url',
        'payment_method',
        'refund_amount',
        'xendit_refund_id',
        'refund_reason',
        'cancellation_reason',
        'magic_link_token',
        'source',
        'project_username',
        'ip_address',
        'user_agent',
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
            'subtotal_rooms' => 'decimal:2',
            'subtotal_transfer' => 'decimal:2',
            'surcharge_amount' => 'decimal:2',
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
                $model->event_id = Hotel::query()->whereKey($model->hotel_id)->value('event_id');
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
                'total_amount',
                'payment_method',
                'xendit_invoice_id',
                'xendit_refund_id',
                'refund_amount',
                'refund_reason',
                'cancellation_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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
}
