<?php

namespace App\Models;

use App\Enums\AdjustmentKind;
use App\Enums\AdjustmentValueType;
use App\Enums\PenaltyTriggerType;
use App\Enums\StackingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property AdjustmentKind $kind
 * @property AdjustmentValueType $value_type
 * @property numeric $value
 * @property array<array-key, mixed>|null $value_config
 * @property numeric|null $max_discount_amount
 * @property numeric|null $min_purchase_amount
 * @property bool $applies_before_tax
 * @property StackingMode $stacking_mode
 * @property int $priority
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property array<array-key, mixed>|null $target_types
 * @property array<array-key, mixed>|null $applicability
 * @property PenaltyTriggerType $trigger_type
 * @property array<array-key, mixed>|null $trigger_config
 * @property bool $revert_usage_on_cancel
 * @property bool $is_system_manual
 * @property int|null $event_id
 * @property int|null $project_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, AppliedAdjustment> $appliedAdjustments
 * @property-read int|null $applied_adjustments_count
 * @property-read Collection<int, PromoCode> $codes
 * @property-read int|null $codes_count
 * @property-read User|null $creator
 * @property-read User|null $deleter
 * @property-read Event|null $event
 * @property-read Project|null $project
 * @property-read User|null $updater
 *
 * @method static Builder<static>|PromotionRule active()
 * @method static \Database\Factories\PromotionRuleFactory factory($count = null, $state = [])
 * @method static Builder<static>|PromotionRule newModelQuery()
 * @method static Builder<static>|PromotionRule newQuery()
 * @method static Builder<static>|PromotionRule ofKind(\App\Enums\AdjustmentKind|string $kind)
 * @method static Builder<static>|PromotionRule onlyTrashed()
 * @method static Builder<static>|PromotionRule query()
 * @method static Builder<static>|PromotionRule whereApplicability($value)
 * @method static Builder<static>|PromotionRule whereAppliesBeforeTax($value)
 * @method static Builder<static>|PromotionRule whereCreatedAt($value)
 * @method static Builder<static>|PromotionRule whereCreatedBy($value)
 * @method static Builder<static>|PromotionRule whereDeletedAt($value)
 * @method static Builder<static>|PromotionRule whereDeletedBy($value)
 * @method static Builder<static>|PromotionRule whereDescription($value)
 * @method static Builder<static>|PromotionRule whereEndsAt($value)
 * @method static Builder<static>|PromotionRule whereEventId($value)
 * @method static Builder<static>|PromotionRule whereId($value)
 * @method static Builder<static>|PromotionRule whereIsActive($value)
 * @method static Builder<static>|PromotionRule whereIsSystemManual($value)
 * @method static Builder<static>|PromotionRule whereKind($value)
 * @method static Builder<static>|PromotionRule whereMaxDiscountAmount($value)
 * @method static Builder<static>|PromotionRule whereMinPurchaseAmount($value)
 * @method static Builder<static>|PromotionRule whereName($value)
 * @method static Builder<static>|PromotionRule wherePriority($value)
 * @method static Builder<static>|PromotionRule whereProjectId($value)
 * @method static Builder<static>|PromotionRule whereRevertUsageOnCancel($value)
 * @method static Builder<static>|PromotionRule whereSlug($value)
 * @method static Builder<static>|PromotionRule whereStackingMode($value)
 * @method static Builder<static>|PromotionRule whereStartsAt($value)
 * @method static Builder<static>|PromotionRule whereTargetTypes($value)
 * @method static Builder<static>|PromotionRule whereTriggerConfig($value)
 * @method static Builder<static>|PromotionRule whereTriggerType($value)
 * @method static Builder<static>|PromotionRule whereUlid($value)
 * @method static Builder<static>|PromotionRule whereUpdatedAt($value)
 * @method static Builder<static>|PromotionRule whereUpdatedBy($value)
 * @method static Builder<static>|PromotionRule whereValue($value)
 * @method static Builder<static>|PromotionRule whereValueConfig($value)
 * @method static Builder<static>|PromotionRule whereValueType($value)
 * @method static Builder<static>|PromotionRule withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|PromotionRule withinWindow(?\Illuminate\Support\Carbon $at = null)
 * @method static Builder<static>|PromotionRule withoutTrashed()
 *
 * @mixin \Eloquent
 */
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
