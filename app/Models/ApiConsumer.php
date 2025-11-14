<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ApiConsumer extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'website_url',
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
            ->logOnlyDirty();
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
