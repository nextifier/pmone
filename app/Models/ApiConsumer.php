<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $website_url
 * @property string $api_key
 * @property array<array-key, mixed>|null $allowed_origins
 * @property int $rate_limit
 * @property array<array-key, mixed>|null $filters
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property string|null $description
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\User|null $deleter
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ApiConsumerRequest> $requests
 * @property-read int|null $requests_count
 * @property-read \App\Models\User|null $updater
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer byApiKey(string $apiKey)
 * @method static \Database\Factories\ApiConsumerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereAllowedOrigins($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereApiKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereFilters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereLastUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereRateLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer whereWebsiteUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumer withoutTrashed()
 *
 * @mixin \Eloquent
 */
class ApiConsumer extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'website_url',
        'description',
        'api_key',
        'allowed_origins',
        'rate_limit',
        'filters',
        'is_active',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'allowed_origins' => 'array',
            'filters' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'rate_limit' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            // Auto-generate API key if not provided
            if (empty($model->api_key)) {
                $model->api_key = self::generateApiKey();
            }

            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function ($model) {
            if ($model->isForceDeleting() === false && auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });
    }

    /**
     * Generate a secure API key
     */
    public static function generateApiKey(): string
    {
        return 'pk_'.Str::random(40);
    }

    /**
     * Regenerate API key for this consumer
     */
    public function regenerateApiKey(): string
    {
        $this->api_key = self::generateApiKey();
        $this->save();

        return $this->api_key;
    }

    /**
     * Update last used timestamp
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Check if consumer is active
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Check if origin is allowed
     */
    public function isOriginAllowed(string $origin): bool
    {
        if (empty($this->allowed_origins)) {
            return true; // Allow all if not specified
        }

        return in_array($origin, $this->allowed_origins);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'website_url', 'is_active', 'rate_limit'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Audit trail relationships
     */
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

    /**
     * API request logs relationship
     */
    public function requests(): HasMany
    {
        return $this->hasMany(ApiConsumerRequest::class);
    }

    /**
     * Scope: Active consumers only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Find by API key
     */
    public function scopeByApiKey($query, string $apiKey)
    {
        return $query->where('api_key', $apiKey);
    }
}
