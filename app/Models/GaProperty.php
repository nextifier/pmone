<?php

namespace App\Models;

use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string $property_id
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_synced_at
 * @property int $sync_frequency Sync frequency in minutes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property-read \App\Models\Project $project
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\User|null $deleter
 * @property-read \App\Models\User|null $updater
 * @property-read \Illuminate\Support\Carbon|null $next_sync_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty active()
 * @method static \Database\Factories\GaPropertyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty needsSync()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereLastSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereSyncFrequency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty withoutTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty withAllTags($tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty withAllTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty withAnyTags($tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GaProperty withAnyTagsOfAnyType($tags)
 *
 * @mixin \Eloquent
 */
class GaProperty extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\GaPropertyFactory> */
    use HasFactory;

    use HasMediaManager;
    use HasTags;
    use InteractsWithMedia;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'name',
        'property_id',
        'is_active',
        'last_synced_at',
        'sync_frequency',
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
     * PostgreSQL syntax for timestamp + interval calculation.
     */
    public function scopeNeedsSync($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('last_synced_at')
                    ->orWhereRaw('last_synced_at + (sync_frequency || \' minutes\')::interval < NOW()');
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
     * Get the next sync time for this property.
     */
    protected function nextSyncAt(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->is_active) {
                    return null;
                }

                if (! $this->last_synced_at) {
                    return now(); // Should sync immediately
                }

                return $this->last_synced_at->addMinutes($this->sync_frequency);
            }
        );
    }

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    /**
     * Register media conversions.
     */
    public function registerMediaConversions($media = null): void
    {
        // No media conversions needed - profile_image accessed via project relationship
    }

    /**
     * Get media collections configuration.
     */
    public function getMediaCollections(): array
    {
        return [];
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

            // Auto-cleanup media when property is force deleted
            if ($model->isForceDeleting()) {
                $model->clearMediaCollection();
            }
        });
    }

    /**
     * Relationships
     */
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
}
