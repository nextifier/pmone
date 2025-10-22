<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $visitable_type
 * @property int $visitable_id
 * @property int|null $visitor_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $referer
 * @property \Illuminate\Support\Carbon $visited_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $visitable
 * @property-read \App\Models\User|null $visitor
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit anonymous()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit authenticated()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit inDateRange($startDate, $endDate)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit lastDays(int $days = 7)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit whereReferer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit whereVisitableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit whereVisitableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit whereVisitedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Visit whereVisitorId($value)
 *
 * @mixin \Eloquent
 */
class Visit extends Model
{
    use HasFactory;

    protected $fillable = [
        'visitable_type',
        'visitable_id',
        'visitor_id',
        'ip_address',
        'user_agent',
        'referer',
        'visited_at',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
        ];
    }

    public function visitable(): MorphTo
    {
        return $this->morphTo();
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visitor_id');
    }

    public function scopeAuthenticated($query)
    {
        return $query->whereNotNull('visitor_id');
    }

    public function scopeAnonymous($query)
    {
        return $query->whereNull('visitor_id');
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('visited_at', [$startDate, $endDate]);
    }

    public function scopeLastDays($query, int $days = 7)
    {
        return $query->where('visited_at', '>=', now()->subDays($days));
    }
}
