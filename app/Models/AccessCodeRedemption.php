<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Audit ledger of an access code redemption. Created as a hold (redeemed_at
 * null) when a checkout starts, stamped redeemed_at when the order confirms,
 * and voided (voided_at) when the order expires/cancels.
 *
 * @property int $id
 * @property string $ulid
 * @property int $access_code_id
 * @property int|null $ticket_order_id
 * @property int|null $applied_adjustment_id
 * @property string|null $email
 * @property Carbon|null $redeemed_at
 * @property Carbon|null $voided_at
 * @property-read AccessCode|null $accessCode
 * @property-read TicketOrder|null $ticketOrder
 *
 * @mixin \Eloquent
 */
class AccessCodeRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'access_code_id',
        'ticket_order_id',
        'applied_adjustment_id',
        'email',
        'redeemed_at',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'redeemed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            if (! empty($model->email)) {
                $model->email = strtolower(trim($model->email));
            }
        });
    }

    public function accessCode(): BelongsTo
    {
        return $this->belongsTo(AccessCode::class);
    }

    public function ticketOrder(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class);
    }

    public function scopeNotVoided(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }
}
