<?php

namespace App\Models;

use App\Enums\AdjustmentKind;
use App\Enums\AdjustmentValueType;
use App\Enums\PenaltyTriggerType;
use App\Enums\StackingMode;
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

class PromotionRule extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'kind',
        'value_type',
        'value',
        'value_config',
        'max_discount_amount',
        'min_purchase_amount',
        'applies_before_tax',
        'stacking_mode',
        'priority',
        'starts_at',
        'ends_at',
        'is_active',
        'target_types',
        'applicability',
        'trigger_type',
        'trigger_config',
        'revert_usage_on_cancel',
        'is_system_manual',
        'event_id',
        'project_id',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AdjustmentKind::class,
            'value_type' => AdjustmentValueType::class,
            'stacking_mode' => StackingMode::class,
            'trigger_type' => PenaltyTriggerType::class,
            'value' => 'decimal:4',
            'value_config' => 'array',
            'max_discount_amount' => 'decimal:2',
            'min_purchase_amount' => 'decimal:2',
            'applies_before_tax' => 'boolean',
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'target_types' => 'array',
            'applicability' => 'array',
            'trigger_config' => 'array',
            'revert_usage_on_cancel' => 'boolean',
            'is_system_manual' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            if (empty($model->slug) && ! empty($model->name)) {
                $model->slug = static::generateUniqueSlug($model->name);
            }

            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function (self $model) {
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

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'kind',
                'value_type',
                'value',
                'is_active',
                'starts_at',
                'ends_at',
                'stacking_mode',
                'trigger_type',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function codes(): HasMany
    {
        return $this->hasMany(PromoCode::class);
    }

    public function appliedAdjustments(): HasMany
    {
        return $this->hasMany(AppliedAdjustment::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    public function scopeOfKind(Builder $query, AdjustmentKind|string $kind): Builder
    {
        $value = $kind instanceof AdjustmentKind ? $kind->value : $kind;

        return $query->where('kind', $value);
    }

    public function scopeWithinWindow(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }

    public function appliesToType(string $morphClass): bool
    {
        $targets = $this->target_types;

        if (empty($targets)) {
            return true;
        }

        if (in_array($morphClass, $targets, true)) {
            return true;
        }

        // Accept short name (e.g. "Reservation") as well as fully-qualified class.
        $basename = class_basename($morphClass);

        return in_array($basename, $targets, true);
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
