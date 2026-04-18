<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property-read HotelEventAllotment|null $allotment
 * @property-read Reservation|null $reservation
 * @property-read RoomType|null $roomType
 *
 * @method static \Database\Factories\ReservationItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationItem query()
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
