<?php

namespace App\Models;

use App\Enums\AdjustmentKind;
use App\Enums\AdjustmentValueType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

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
