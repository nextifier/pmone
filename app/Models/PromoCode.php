<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PromoCode extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'promotion_rule_id',
        'usage_limit',
        'usage_limit_per_email',
        'usage_count',
        'valid_from',
        'valid_until',
        'is_active',
        'issued_to_email',
        'metadata',
        'event_id',
    ];

    protected function casts(): array
    {
        return [
            'usage_limit' => 'integer',
            'usage_limit_per_email' => 'integer',
            'usage_count' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            if (! empty($model->code)) {
                $model->code = strtoupper(trim($model->code));
            }

            if (! empty($model->issued_to_email)) {
                $model->issued_to_email = strtolower(trim($model->issued_to_email));
            }

            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function (self $model) {
            if ($model->isDirty('code')) {
                $model->code = strtoupper(trim($model->code));
            }

            if ($model->isDirty('issued_to_email') && ! empty($model->issued_to_email)) {
                $model->issued_to_email = strtolower(trim($model->issued_to_email));
            }

            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function (self $model) {
            if ($model->isForceDeleting() === false && auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'code',
                'usage_limit',
                'usage_limit_per_email',
                'is_active',
                'valid_from',
                'valid_until',
                'issued_to_email',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function promotionRule(): BelongsTo
    {
        return $this->belongsTo(PromotionRule::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    public function appliedAdjustments(): HasMany
    {
        return $this->hasMany(AppliedAdjustment::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithinWindow(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $at);
            })
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $at);
            });
    }

    public function isFullyUsed(): bool
    {
        return $this->usage_limit !== null && $this->usage_count >= $this->usage_limit;
    }

    public function resolveWindowStart(): ?Carbon
    {
        $codeStart = $this->valid_from;
        $ruleStart = $this->promotionRule?->starts_at;

        if (! $codeStart) {
            return $ruleStart;
        }

        if (! $ruleStart) {
            return $codeStart;
        }

        return $codeStart->greaterThan($ruleStart) ? $codeStart : $ruleStart;
    }

    public function resolveWindowEnd(): ?Carbon
    {
        $codeEnd = $this->valid_until;
        $ruleEnd = $this->promotionRule?->ends_at;

        if (! $codeEnd) {
            return $ruleEnd;
        }

        if (! $ruleEnd) {
            return $codeEnd;
        }

        return $codeEnd->lessThan($ruleEnd) ? $codeEnd : $ruleEnd;
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
