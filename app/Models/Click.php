<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $clickable_type
 * @property int $clickable_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $referer
 * @property \Illuminate\Support\Carbon $clicked_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $clickable
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click inDateRange($startDate, $endDate)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click lastDays(int $days = 7)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereClickableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereClickableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereClickedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereReferer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereUserAgent($value)
 *
 * @mixin \Eloquent
 */
class Click extends Model
{
    use HasFactory;

    protected $fillable = [
        'clickable_type',
        'clickable_id',
        'ip_address',
        'user_agent',
        'referer',
        'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    public function clickable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('clicked_at', [$startDate, $endDate]);
    }

    public function scopeLastDays($query, int $days = 7)
    {
        return $query->where('clicked_at', '>=', now()->subDays($days));
    }
}
