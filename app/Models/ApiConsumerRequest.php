<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $api_consumer_id
 * @property string $endpoint
 * @property string $method
 * @property int|null $status_code
 * @property int|null $response_time_ms
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $origin
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\ApiConsumer $apiConsumer
 * @method static \Database\Factories\ApiConsumerRequestFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumerRequest forConsumer(int $consumerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumerRequest inPeriod(int $days)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumerRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumerRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumerRequest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumerRequest whereApiConsumerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumerRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumerRequest whereEndpoint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumerRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumerRequest whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumerRequest whereMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumerRequest whereOrigin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumerRequest whereResponseTimeMs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumerRequest whereStatusCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiConsumerRequest whereUserAgent($value)
 * @mixin \Eloquent
 */
class ApiConsumerRequest extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'api_consumer_id',
        'endpoint',
        'method',
        'status_code',
        'response_time_ms',
        'ip_address',
        'user_agent',
        'origin',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'status_code' => 'integer',
            'response_time_ms' => 'integer',
        ];
    }

    public function apiConsumer(): BelongsTo
    {
        return $this->belongsTo(ApiConsumer::class);
    }

    /**
     * Scope: Filter by consumer
     */
    public function scopeForConsumer($query, int $consumerId)
    {
        return $query->where('api_consumer_id', $consumerId);
    }

    /**
     * Scope: Filter by period (days)
     */
    public function scopeInPeriod($query, int $days)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
