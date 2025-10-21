<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
