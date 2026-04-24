<?php

namespace App\Models;

use App\Enums\TransferDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $reservation_id
 * @property int $transfer_option_id
 * @property TransferDirection $direction
 * @property \Illuminate\Support\Carbon $transfer_date
 * @property string|null $transfer_time
 * @property string|null $pickup_location
 * @property string|null $dropoff_location
 * @property string|null $flight_number
 * @property string|null $flight_time
 * @property int $pax_count
 * @property int|null $luggage_count
 * @property string|null $note
 * @property numeric $price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Reservation|null $reservation
 * @property-read \App\Models\HotelTransferOption|null $transferOption
 * @method static \Database\Factories\ReservationTransferFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer whereDirection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer whereDropoffLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer whereFlightNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer whereFlightTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer whereLuggageCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer wherePaxCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer wherePickupLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer whereReservationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer whereTransferDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer whereTransferOptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer whereTransferTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReservationTransfer whereUpdatedAt($value)
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
