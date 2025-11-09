<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $sync_type
 * @property int|null $ga_property_id
 * @property int $days
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int|null $duration_seconds
 * @property array|null $metadata
 * @property string|null $error_message
 * @property string|null $job_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GaProperty|null $property
 */
class AnalyticsSyncLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sync_type',
        'ga_property_id',
        'days',
        'status',
        'started_at',
        'completed_at',
        'duration_seconds',
        'metadata',
        'error_message',
        'job_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Scope for property syncs.
     */
    public function scopePropertySyncs($query)
    {
        return $query->where('sync_type', 'property');
    }

    /**
     * Scope for aggregate syncs.
     */
    public function scopeAggregateSyncs($query)
    {
        return $query->where('sync_type', 'aggregate');
    }

    /**
     * Scope for successful syncs.
     */
    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for failed syncs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for recent syncs.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Get the property that owns the sync log.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(GaProperty::class, 'ga_property_id');
    }

    /**
     * Helper to create a new sync log entry.
     */
    public static function startSync(string $syncType, ?int $gaPropertyId, int $days, ?string $jobId = null): self
    {
        return self::create([
            'sync_type' => $syncType,
            'ga_property_id' => $gaPropertyId,
            'days' => $days,
            'status' => 'started',
            'started_at' => now(),
            'job_id' => $jobId,
        ]);
    }

    /**
     * Mark sync as successful.
     */
    public function markSuccess(?array $metadata = null): void
    {
        $this->update([
            'status' => 'success',
            'completed_at' => now(),
            'duration_seconds' => $this->started_at ? $this->started_at->diffInSeconds(now()) : null,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Mark sync as failed.
     */
    public function markFailed(string $errorMessage, ?array $metadata = null): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'duration_seconds' => $this->started_at ? $this->started_at->diffInSeconds(now()) : null,
            'error_message' => $errorMessage,
            'metadata' => $metadata,
        ]);
    }
}
