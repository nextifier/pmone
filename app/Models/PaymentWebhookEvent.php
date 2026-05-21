<?php

namespace App\Models;

use Database\Factories\PaymentWebhookEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An inbound payment-provider webhook PM One received, persisted for auditing
 * and debugging. Provider-agnostic: Xendit today, Midtrans later.
 *
 * @property int $id
 * @property string $ulid
 * @property string $provider
 * @property int|null $project_id
 * @property string|null $event_type
 * @property string|null $external_id
 * @property string $status
 * @property int|null $http_status
 * @property string|null $message
 * @property array<array-key, mixed> $payload
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project|null $project
 */
class PaymentWebhookEvent extends Model
{
    /** @use HasFactory<PaymentWebhookEventFactory> */
    use HasFactory;

    protected $fillable = [
        'provider',
        'project_id',
        'event_type',
        'external_id',
        'status',
        'http_status',
        'message',
        'payload',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'http_status' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PaymentWebhookEvent $model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @param  Builder<PaymentWebhookEvent>  $query
     */
    public function scopeForProvider(Builder $query, string $provider): void
    {
        $query->where('provider', $provider);
    }
}
