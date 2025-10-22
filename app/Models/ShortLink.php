<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property string $destination_url
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Click> $clicks
 * @property-read int|null $clicks_count
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink active()
 * @method static \Database\Factories\ShortLinkFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereDestinationUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereUserId($value)
 *
 * @mixin \Eloquent
 */
class ShortLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'slug',
        'destination_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clicks(): MorphMany
    {
        return $this->morphMany(Click::class, 'clickable');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
