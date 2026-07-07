<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * @property int $id
 * @property string $linkable_type
 * @property int $linkable_id
 * @property string $label
 * @property string $url
 * @property int $order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Click> $clicks
 * @property-read int|null $clicks_count
 * @property-read Model|\Eloquent $linkable
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link active()
 * @method static \Database\Factories\LinkFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereLinkableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereLinkableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereUrl($value)
 *
 * @mixin \Eloquent
 */
class Link extends Model
{
    use HasFactory;

    protected $fillable = [
        'linkable_type',
        'linkable_id',
        'label',
        'url',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Bust the response cache of the owning resource so brand/guest/project
     * profiles (and their completeness scores) and public user profiles
     * (served via /resolve/{slug}, tag 'short-links') refresh when links
     * change.
     */
    protected static function booted(): void
    {
        $clearCache = function (Link $link): void {
            $tags = match ($link->linkable_type) {
                Brand::class => ['brands'],
                Guest::class => ['guests'],
                // The project's "Website" link is embedded as website_url in
                // every cached public event payload (EventResource), including
                // conjunction projects.
                Project::class => ['projects', 'events'],
                User::class => ['short-links'],
                default => [],
            };

            if ($tags !== []) {
                DB::afterCommit(fn () => ResponseCache::clear($tags));
            }
        };

        static::saved($clearCache);
        static::deleted($clearCache);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
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
