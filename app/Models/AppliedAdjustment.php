<?php

namespace App\Models;

use App\Enums\AdjustmentKind;
use App\Enums\AdjustmentValueType;
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
 * @property string $adjustable_type
 * @property int $adjustable_id
 * @property int|null $promotion_rule_id
 * @property int|null $promo_code_id
 * @property AdjustmentKind $kind
 * @property string $label
 * @property AdjustmentValueType $value_type
 * @property numeric $value
 * @property array<array-key, mixed>|null $value_config
 * @property numeric $base_amount
 * @property numeric $amount
 * @property array<array-key, mixed>|null $line_breakdown
 * @property array<array-key, mixed>|null $rule_snapshot
 * @property string $applied_by
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|\Eloquent $adjustable
 * @property-read PromoCode|null $promoCode
 * @property-read PromotionRule|null $promotionRule
 * @property-read PromoCodeUsage|null $usage
 *
 * @method static Builder<static>|AppliedAdjustment active()
 * @method static Builder<static>|AppliedAdjustment newModelQuery()
 * @method static Builder<static>|AppliedAdjustment newQuery()
 * @method static Builder<static>|AppliedAdjustment ofKind(\App\Enums\AdjustmentKind|string $kind)
 * @method static Builder<static>|AppliedAdjustment query()
 * @method static Builder<static>|AppliedAdjustment whereAdjustableId($value)
 * @method static Builder<static>|AppliedAdjustment whereAdjustableType($value)
 * @method static Builder<static>|AppliedAdjustment whereAmount($value)
 * @method static Builder<static>|AppliedAdjustment whereAppliedBy($value)
 * @method static Builder<static>|AppliedAdjustment whereBaseAmount($value)
 * @method static Builder<static>|AppliedAdjustment whereCreatedAt($value)
 * @method static Builder<static>|AppliedAdjustment whereId($value)
 * @method static Builder<static>|AppliedAdjustment whereKind($value)
 * @method static Builder<static>|AppliedAdjustment whereLabel($value)
 * @method static Builder<static>|AppliedAdjustment whereLineBreakdown($value)
 * @method static Builder<static>|AppliedAdjustment wherePromoCodeId($value)
 * @method static Builder<static>|AppliedAdjustment wherePromotionRuleId($value)
 * @method static Builder<static>|AppliedAdjustment whereRuleSnapshot($value)
 * @method static Builder<static>|AppliedAdjustment whereUlid($value)
 * @method static Builder<static>|AppliedAdjustment whereUpdatedAt($value)
 * @method static Builder<static>|AppliedAdjustment whereValue($value)
 * @method static Builder<static>|AppliedAdjustment whereValueConfig($value)
 * @method static Builder<static>|AppliedAdjustment whereValueType($value)
 * @method static Builder<static>|AppliedAdjustment whereVoidReason($value)
 * @method static Builder<static>|AppliedAdjustment whereVoidedAt($value)
 *
 * @mixin \Eloquent
 */
class AppliedAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'adjustable_type',
        'adjustable_id',
        'promotion_rule_id',
        'promo_code_id',
        'kind',
        'label',
        'value_type',
        'value',
        'value_config',
        'base_amount',
        'amount',
        'line_breakdown',
        'rule_snapshot',
        'applied_by',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AdjustmentKind::class,
            'value_type' => AdjustmentValueType::class,
            'value' => 'decimal:4',
            'value_config' => 'array',
            'base_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'line_breakdown' => 'array',
            'rule_snapshot' => 'array',
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
        });
    }

    public function adjustable(): MorphTo
    {
        return $this->morphTo();
    }

    public function promotionRule(): BelongsTo
    {
        return $this->belongsTo(PromotionRule::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function usage(): BelongsTo
    {
        return $this->belongsTo(PromoCodeUsage::class, 'id', 'applied_adjustment_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function scopeOfKind(Builder $query, AdjustmentKind|string $kind): Builder
    {
        $value = $kind instanceof AdjustmentKind ? $kind->value : $kind;

        return $query->where('kind', $value);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
