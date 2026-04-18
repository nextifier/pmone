<?php

namespace App\Models;

use App\Enums\TransferDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property TransferDirection $direction
 * @property-read Reservation|null $reservation
 * @property-read HotelTransferOption|null $transferOption
 *
 * @method static \Database\Factories\ReservationTransferFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer query()
 *
 * @mixin \Eloquent
 */
class ReservationTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'transfer_option_id',
        'direction',
        'transfer_date',
        'transfer_time',
        'pickup_location',
        'dropoff_location',
        'flight_number',
        'flight_time',
        'pax_count',
        'luggage_count',
        'note',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'direction' => TransferDirection::class,
            'transfer_date' => 'date',
            'pax_count' => 'integer',
            'luggage_count' => 'integer',
            'price' => 'decimal:2',
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

    public function transferOption(): BelongsTo
    {
        return $this->belongsTo(HotelTransferOption::class, 'transfer_option_id');
    }
}
