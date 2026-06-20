<?php

namespace App\Models;

use App\Enums\Ticketing\AccessCodeKind;
use App\Enums\Ticketing\AccessCodePriceEffect;
use App\Enums\Ticketing\AccessCodeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A gate-keeping code that unlocks `hidden`/`code_required` tickets in a single
 * event. Eligibility-first (who may buy); price effect is optional. Mirrors the
 * PromoCode lifecycle (atomic used_count gate + soft window + revoke).
 *
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property AccessCodeKind $kind
 * @property int $event_id
 * @property int|null $batch_id
 * @property int|null $max_uses
 * @property int $used_count
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property string|null $bind_email
 * @property string|null $bind_phone
 * @property AccessCodePriceEffect $price_effect
 * @property string|null $price_value
 * @property bool $stackable
 * @property int $max_qty_per_redemption
 * @property AccessCodeStatus $status
 * @property array<array-key, mixed>|null $metadata
 * @property-read Event|null $event
 * @property-read AccessCodeBatch|null $batch
 * @property-read Collection<int, Ticket> $unlocks
 * @property-read Collection<int, AccessCodeRedemption> $redemptions
 *
 * @mixin \Eloquent
 */
class AccessCode extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'kind',
        'event_id',
        'batch_id',
        'max_uses',
        'used_count',
        'valid_from',
        'valid_until',
        'bind_email',
        'bind_phone',
        'price_effect',
        'price_value',
        'stackable',
        'max_qty_per_redemption',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AccessCodeKind::class,
            'price_effect' => AccessCodePriceEffect::class,
            'status' => AccessCodeStatus::class,
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'max_qty_per_redemption' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'price_value' => 'decimal:2',
            'stackable' => 'boolean',
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

            if (! empty($model->bind_email)) {
                $model->bind_email = strtolower(trim($model->bind_email));
            }

            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function (self $model) {
            if ($model->isDirty('code')) {
                $model->code = strtoupper(trim($model->code));
            }

            if ($model->isDirty('bind_email') && ! empty($model->bind_email)) {
                $model->bind_email = strtolower(trim($model->bind_email));
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
                'kind',
                'status',
                'max_uses',
                'valid_from',
                'valid_until',
                'bind_email',
                'bind_phone',
                'price_effect',
                'price_value',
                'stackable',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($projectId = $this->loadMissing('event')->event?->project_id) {
            $activity->properties = $activity->properties->put('project_id', $projectId);
        }
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AccessCodeBatch::class, 'batch_id');
    }

    public function unlocks(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'access_code_ticket');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(AccessCodeRedemption::class);
    }

    public function appliedAdjustments(): HasMany
    {
        return $this->hasMany(AppliedAdjustment::class);
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
        return $query->where('status', AccessCodeStatus::Active->value);
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

    public function isActive(): bool
    {
        return $this->status === AccessCodeStatus::Active;
    }

    public function isFullyUsed(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }

    public function unlocksTicket(int $ticketId): bool
    {
        return $this->unlocks->contains('id', $ticketId);
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
