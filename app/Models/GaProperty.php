<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $property_id
 * @property string $account_name
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_synced_at
 * @property int $sync_frequency Sync frequency in minutes
 * @property int $rate_limit_per_hour Max requests per hour
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\User|null $deleter
 * @property-read \App\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty active()
 * @method static \Database\Factories\GaPropertyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty needsSync()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereAccountName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereLastSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereRateLimitPerHour($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereSyncFrequency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty withoutTrashed()
 * @mixin \Eloquent
 */
class GaProperty extends Model
{
    /** @use HasFactory<\Database\Factories\GaPropertyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'property_id',
        'account_name',
        'is_active',
        'last_synced_at',
        'sync_frequency',
        'rate_limit_per_hour',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'sync_frequency' => 'integer',
        'rate_limit_per_hour' => 'integer',
    ];

    /**
     * Scope a query to only include active properties.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive properties.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to include properties that need syncing.
     */
    public function scopeNeedsSync($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('last_synced_at')
                    ->orWhereRaw('last_synced_at < NOW() - INTERVAL sync_frequency MINUTE');
            });
    }

    /**
     * Check if this property needs to be synced.
     */
    public function needsSync(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->last_synced_at) {
            return true;
        }

        return $this->last_synced_at->addMinutes($this->sync_frequency)->isPast();
    }

    /**
     * Mark this property as synced.
     */
    public function markAsSynced(): void
    {
        $this->update(['last_synced_at' => now()]);
    }

    /**
     * Boot method to handle audit columns.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
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
     * Relationships
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
}
