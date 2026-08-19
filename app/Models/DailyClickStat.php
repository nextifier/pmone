<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One row per target per day: the permanent record of outbound clicks on banner
 * CTAs, brand links and link-page items.
 *
 * Same reason for existing as DailyVisitStat, and the same rule applies: this
 * table is never pruned. Banner click totals that vanish after 90 days are a
 * reporting problem for the advertisers those banners are sold to.
 *
 * @property int $id
 * @property string $clickable_type
 * @property int $clickable_id
 * @property Carbon $date
 * @property int $clicks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|DailyClickStat newModelQuery()
 * @method static Builder<static>|DailyClickStat newQuery()
 * @method static Builder<static>|DailyClickStat query()
 *
 * @mixin \Eloquent
 */
class DailyClickStat extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'clickable_type',
        'clickable_id',
        'date',
        'clicks',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clicks' => 'integer',
        ];
    }

    /**
     * See DailyVisitStat::date() for why the built-in `date` cast is wrong here.
     *
     * @return Attribute<Carbon, string>
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): Carbon => Carbon::parse($value),
            set: fn (Carbon|string $value): string => Carbon::parse($value)->toDateString(),
        );
    }

    /**
     * @return MorphTo<Model, DailyClickStat>
     */
    public function clickable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<DailyClickStat>  $query
     * @return Builder<DailyClickStat>
     */
    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('clickable_type', $type);
    }

    /**
     * @param  Builder<DailyClickStat>  $query
     * @return Builder<DailyClickStat>
     */
    public function scopeInDateRange(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('date', [$start, $end]);
    }
}
