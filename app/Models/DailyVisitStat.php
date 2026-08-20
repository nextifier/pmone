<?php

namespace App\Models;

use App\Support\VisitStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One row per target per day per measurement method: the permanent record of how
 * many times something was viewed.
 *
 * The raw `visits` table is pruned to 90 days, which used to mean a four-year-old
 * article reported a lifetime view count covering only the last quarter, and that
 * number shrank on its own as older days aged out. This table is what makes the
 * figure permanent, so it is deliberately NOT prunable — do not add MassPrunable
 * here to match its neighbours. A year of every post costs single-digit megabytes.
 *
 * @property int $id
 * @property string $visitable_type
 * @property int $visitable_id
 * @property Carbon $date
 * @property string $source
 * @property int $views
 * @property int $authenticated_views
 * @property int|null $unique_visitors
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|DailyVisitStat newModelQuery()
 * @method static Builder<static>|DailyVisitStat newQuery()
 * @method static Builder<static>|DailyVisitStat query()
 *
 * @mixin \Eloquent
 */
class DailyVisitStat extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'visitable_type',
        'visitable_id',
        'date',
        'source',
        'views',
        'authenticated_views',
        'unique_visitors',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'authenticated_views' => 'integer',
            'unique_visitors' => 'integer',
        ];
    }

    /**
     * Carbon on the way out, a bare Y-m-d string on the way in.
     *
     * Deliberately not the built-in `date` cast: that one writes through
     * fromDateTime(), which appends " 00:00:00". PostgreSQL coerces that back to a
     * date and hides the problem, SQLite stores it verbatim, and the next
     * updateOrCreate then fails to match its own row and inserts a duplicate.
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
     * @return MorphTo<Model, DailyVisitStat>
     */
    public function visitable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Only the rows that count toward a real total.
     *
     * Delegates to VisitStats so the per-era rule lives in exactly one place; see
     * that class for why a plain SUM over every row would be wrong.
     *
     * @param  Builder<DailyVisitStat>  $query
     * @return Builder<DailyVisitStat>
     */
    public function scopeCanonical(Builder $query, string $type): Builder
    {
        return VisitStats::constrainToCanonicalSources($query, $type);
    }

    /**
     * @param  Builder<DailyVisitStat>  $query
     * @return Builder<DailyVisitStat>
     */
    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('visitable_type', $type);
    }

    /**
     * @param  Builder<DailyVisitStat>  $query
     * @return Builder<DailyVisitStat>
     */
    public function scopeInDateRange(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('date', [$start, $end]);
    }
}
