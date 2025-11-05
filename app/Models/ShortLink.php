<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'slug',
        'destination_url',
        'is_active',
        'og_title',
        'og_description',
        'og_image',
        'og_type',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected $appends = ['clicks_count'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($shortLink) {
            if ($shortLink->isForceDeleting()) {
                return;
            }
            $shortLink->deleted_by = auth()->id();
            $shortLink->saveQuietly();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function clicks(): MorphMany
    {
        return $this->morphMany(Click::class, 'clickable');
    }

    public function getClicksCountAttribute(): int
    {
        return $this->clicks()->count();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
