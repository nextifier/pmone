<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
