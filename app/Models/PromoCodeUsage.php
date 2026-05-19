<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

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
