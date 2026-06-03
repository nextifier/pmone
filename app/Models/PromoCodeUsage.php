<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $promo_code_id
 * @property int|null $applied_adjustment_id
 * @property string $adjustable_type
 * @property int $adjustable_id
 * @property string $email
 * @property int|null $user_id
 * @property numeric $amount_discounted
 * @property Carbon|null $voided_at
 * @property Carbon $created_at
 * @property-read Model|\Eloquent $adjustable
 * @property-read AppliedAdjustment|null $appliedAdjustment
 * @property-read PromoCode|null $promoCode
 * @property-read User|null $user
 *
 * @method static Builder<static>|PromoCodeUsage active()
 * @method static Builder<static>|PromoCodeUsage newModelQuery()
 * @method static Builder<static>|PromoCodeUsage newQuery()
 * @method static Builder<static>|PromoCodeUsage query()
 * @method static Builder<static>|PromoCodeUsage whereAdjustableId($value)
 * @method static Builder<static>|PromoCodeUsage whereAdjustableType($value)
 * @method static Builder<static>|PromoCodeUsage whereAmountDiscounted($value)
 * @method static Builder<static>|PromoCodeUsage whereAppliedAdjustmentId($value)
 * @method static Builder<static>|PromoCodeUsage whereCreatedAt($value)
 * @method static Builder<static>|PromoCodeUsage whereEmail($value)
 * @method static Builder<static>|PromoCodeUsage whereId($value)
 * @method static Builder<static>|PromoCodeUsage wherePromoCodeId($value)
 * @method static Builder<static>|PromoCodeUsage whereUlid($value)
 * @method static Builder<static>|PromoCodeUsage whereUserId($value)
 * @method static Builder<static>|PromoCodeUsage whereVoidedAt($value)
 *
 * @mixin \Eloquent
 */
class PromoCodeUsage extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'promo_code_id',
        'applied_adjustment_id',
        'adjustable_type',
        'adjustable_id',
        'email',
        'user_id',
        'amount_discounted',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_discounted' => 'decimal:2',
            'voided_at' => 'datetime',
            'created_at' => 'datetime',
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

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function appliedAdjustment(): BelongsTo
    {
        return $this->belongsTo(AppliedAdjustment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adjustable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }
}
