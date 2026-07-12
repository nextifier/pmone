<?php

namespace App\Models;

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
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $website_url
 * @property string|null $api_key legacy plaintext, kept only as a migration safety net; never read for auth, never returned by any resource
 * @property string $api_key_hash sha256 hash of the raw key; the only value used for authentication lookups
 * @property array<array-key, mixed>|null $allowed_origins
 * @property int $rate_limit
 * @property array<array-key, mixed>|null $filters
 * @property bool $is_active
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property string|null $description
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read User|null $creator
 * @property-read User|null $deleter
 * @property-read Collection<int, Project> $projects
 * @property-read int|null $projects_count
 * @property-read Collection<int, ApiConsumerRequest> $requests
 * @property-read int|null $requests_count
 * @property-read User|null $updater
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

    /**
     * Never serialize the raw key or its hash, even if a resource forgets to
     * exclude them explicitly.
     */
    protected $hidden = [
        'api_key',
        'api_key_hash',
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

            // Always keep the hash in sync with whatever raw key ends up set
            // above (auto-generated, or explicitly provided by a caller).
            if (empty($model->api_key_hash)) {
                $model->api_key_hash = self::hashApiKey($model->api_key);
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
     * Generate a secure, random raw API key. The caller is responsible for
     * showing it to the user exactly once; it is never read back from
     * storage afterwards.
     */
    public static function generateApiKey(): string
    {
        return 'pk_'.Str::random(40);
    }

    /**
     * Hash a raw API key the same way for both storage and lookup.
     */
    public static function hashApiKey(string $apiKey): string
    {
        return hash('sha256', $apiKey);
    }

    /**
     * Regenerate the API key for this consumer. Stores only the hash going
     * forward and returns the raw value once so the caller can display it.
     */
    public function regenerateApiKey(): string
    {
        $raw = self::generateApiKey();

        $this->api_key = $raw;
        $this->api_key_hash = self::hashApiKey($raw);
        $this->save();

        return $raw;
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
     * Scope: Find by API key (compares the hash, never the plaintext)
     */
    public function scopeByApiKey($query, string $apiKey)
    {
        return $query->where('api_key_hash', self::hashApiKey($apiKey));
    }

    /**
     * Opt-in per-project scope. Zero related projects (the default) means
     * unscoped: the consumer may read any project, preserving current
     * behavior for the 16 live sites. One or more related projects
     * restricts the consumer to only those.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'api_consumer_project')
            ->withTimestamps();
    }

    /**
     * Whether this consumer has been restricted to specific projects.
     */
    public function hasProjectScope(): bool
    {
        if (! $this->relationLoaded('projects')) {
            $this->load('projects');
        }

        return $this->projects->isNotEmpty();
    }

    /**
     * Whether the consumer is allowed to access the given project username.
     * Unscoped consumers (no related projects) are always allowed.
     */
    public function isProjectAllowed(string $username): bool
    {
        if (! $this->hasProjectScope()) {
            return true;
        }

        return $this->projects->contains('username', $username);
    }
}
