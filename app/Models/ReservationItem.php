<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $reservation_id
 * @property int $room_type_id
 * @property int|null $allotment_id
 * @property Carbon $check_in_date
 * @property Carbon $check_out_date
 * @property int $nights
 * @property int $qty
 * @property string|null $guest_name
 * @property string|null $guest_identity
 * @property numeric $rate_per_night
 * @property numeric $subtotal
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property array<array-key, mixed>|null $daily_breakdown
 * @property-read HotelEventAllotment|null $allotment
 * @property-read Reservation|null $reservation
 * @property-read RoomType|null $roomType
 *
 * @method static \Database\Factories\ReservationItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereAllotmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereCheckInDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereCheckOutDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereDailyBreakdown($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereGuestIdentity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereGuestName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereNights($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereRatePerNight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereReservationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereRoomTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ReservationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'room_type_id',
        'allotment_id',
        'check_in_date',
        'check_out_date',
        'nights',
        'qty',
        'guest_name',
        'guest_identity',
        'rate_per_night',
        'subtotal',
        'notes',
        'daily_breakdown',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'nights' => 'integer',
            'qty' => 'integer',
            'rate_per_night' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'daily_breakdown' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function allotment(): BelongsTo
    {
        return $this->belongsTo(HotelEventAllotment::class, 'allotment_id');
    }
}
