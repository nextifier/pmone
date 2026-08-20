<?php

namespace App\Support;

use App\Models\Click;
use App\Models\DailyClickStat;
use App\Models\DailyVisitStat;
use App\Models\Post;
use App\Models\Visit;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use RuntimeException;

/**
 * Single source of truth for how a view is counted.
 *
 * Three measurement methods have written to the `visits` table over its life and
 * they do not measure the same thing. Adding them together produces a number that
 * is wrong in a way nobody can see, so the rule for picking between them lives
 * here and nowhere else.
 *
 * - `server_render` (until 28 Jul 2026): one row per SSR render of the article.
 *   Crawlers, prefetchers and link previews all counted. Measured against Google
 *   Analytics it ran roughly 5x real human traffic, so it is stored for reference
 *   and never summed.
 * - `beacon` (28 Jul 2026 onward): one row per browser that ran the JavaScript on
 *   the page. Bots are filtered, and this is what the dashboard reports.
 * - `ga4`: Google's own browser-based count, used to fill the years before the
 *   beacon existed. It measures roughly 4% lower than our beacon (it loses some
 *   readers to ad blockers while our beacon is same-origin), which is close enough
 *   to join onto the same series and too different to pretend it is identical.
 */
class VisitStats
{
    public const SOURCE_BEACON = 'beacon';

    public const SOURCE_SERVER_RENDER = 'server_render';

    public const SOURCE_GA4 = 'ga4';

    /**
     * The only types a Google Analytics history was imported for.
     *
     * It matters because the era rule below exists for one reason: article counting
     * was broken between 21 May and 27 Jul 2026, and GA4 is what fills that gap.
     * Nothing else was ever counted server-side — banners, brands and link pages
     * have been browser-counted since the day they were added, so applying the era
     * rule to them would silently drop every view they had before 28 July.
     *
     * @var list<class-string>
     */
    public const GA4_BACKED_TYPES = [Post::class];

    /**
     * The date from which the browser beacon is the truth.
     *
     * Before it, `ga4` is; `server_render` is never. Reading from config rather
     * than hardcoding keeps this aligned with the two analytics pages, which show
     * the reader the same date when a selected range straddles it.
     */
    public static function browserCountingSince(): string
    {
        $since = config('visit-tracking.browser_counting_since');

        // An empty value would make every comparison below read as "beacon owns
        // every date", which silently drops the whole GA4 history from every total
        // without any error to notice. Fail loudly instead.
        if (! is_string($since) || $since === '') {
            throw new RuntimeException('visit-tracking.browser_counting_since is not set; view totals cannot be resolved.');
        }

        return $since;
    }

    /**
     * Whether unique visitors mean anything for a range starting on this date.
     *
     * Until the event websites forwarded the visitor IP, every beacon arrived from
     * a single Cloudflare Worker egress address, so a distinct count over those
     * rows collapses to the number of distinct User-Agent strings. Measured on
     * production: 2,998 for a window holding 71,743 views. Null, not a number.
     */
    public static function canCountUniqueVisitors(CarbonInterface $date): bool
    {
        $since = config('visit-tracking.visitor_ip_tracking_since');

        return $since !== null && $date->toDateString() >= $since;
    }

    /**
     * Which source counts for a given day.
     */
    public static function canonicalSourceFor(CarbonInterface $date): string
    {
        return $date->toDateString() >= self::browserCountingSince()
            ? self::SOURCE_BEACON
            : self::SOURCE_GA4;
    }

    /**
     * Which source a raw visit row belongs to.
     *
     * The discriminator is the row itself, not its date: the server-side path went
     * through the Nuxt server and never carried a browser User-Agent, the beacon
     * always does. Verified on production — all 760,549 rows from 20 Jun to 22 Jul
     * are null, and 99.19% of rows since 8 Aug are populated. Classifying per row
     * rather than per date is what makes 27 Jul, the day both paths were live,
     * come out right without a special case.
     */
    public static function sourceForRawVisit(?string $userAgent): string
    {
        return $userAgent === null ? self::SOURCE_SERVER_RENDER : self::SOURCE_BEACON;
    }

    /**
     * Restrict a rollup query to the rows that count toward a real total.
     *
     * Expressed as one WHERE rather than a join so it composes with sums, and so
     * a caller cannot accidentally get `server_render` rows by forgetting a clause.
     *
     * @param  Builder<DailyVisitStat>  $query
     * @return Builder<DailyVisitStat>
     */
    public static function constrainToCanonicalSources(Builder $query, string $type): Builder
    {
        // Everything except articles has only ever been counted one way, so the
        // beacon is simply the answer for every date.
        if (! in_array($type, self::GA4_BACKED_TYPES, true)) {
            return $query->where('source', self::SOURCE_BEACON);
        }

        $cutover = self::browserCountingSince();

        return $query->where(function (Builder $inner) use ($cutover): void {
            $inner
                ->where(function (Builder $modern) use ($cutover): void {
                    $modern->where('date', '>=', $cutover)
                        ->where('source', self::SOURCE_BEACON);
                })
                ->orWhere(function (Builder $historical) use ($cutover): void {
                    $historical->where('date', '<', $cutover)
                        ->where('source', self::SOURCE_GA4);
                });
        });
    }

    /**
     * Daily view totals for one target type over a date range.
     *
     * Reads the permanent rollup for every complete day and adds one live query
     * for today, which the 01:45 job has not summarised yet. That split is the
     * whole reason the numbers survive: `visits` only holds 90 days, so anything
     * older than that exists nowhere else.
     *
     * The live slice applies the same source rule as the rollup rather than
     * counting every row, so today is measured the way yesterday was.
     *
     * If the rollup has fallen behind, the days it is missing read as zero here
     * rather than being quietly patched from the raw table. That is deliberate:
     * a visible dip is a signal someone can act on, and patching would mean
     * mixing measurement methods to hide a broken job.
     *
     * @param  (callable(\Illuminate\Database\Eloquent\Builder<*>): void)|null  $constrain
     *                                                                                     Applied to both queries; `visitable_id` means the same thing in each.
     * @return array{per_day: array<string, array{views: int, authenticated: int}>, total: int, authenticated: int}
     */
    public static function viewSeries(
        string $type,
        CarbonInterface $start,
        CarbonInterface $end,
        ?callable $constrain = null,
    ): array {
        $perDay = [];

        $today = now()->startOfDay();
        $rollupEnd = $end->copy()->startOfDay()->min($today->copy()->subDay());

        if ($start->copy()->startOfDay()->lte($rollupEnd)) {
            $rollup = DailyVisitStat::query()
                ->where('visitable_type', $type)
                ->whereBetween('date', [$start->toDateString(), $rollupEnd->toDateString()]);

            self::constrainToCanonicalSources($rollup, $type);

            if ($constrain) {
                $constrain($rollup);
            }

            $rows = $rollup
                ->selectRaw('date, SUM(views) as views, SUM(authenticated_views) as authenticated')
                ->groupBy('date')
                ->get();

            foreach ($rows as $row) {
                $perDay[Carbon::parse((string) $row->getRawOriginal('date'))->toDateString()] = [
                    'views' => (int) $row->views,
                    'authenticated' => (int) $row->authenticated,
                ];
            }
        }

        if ($end->copy()->startOfDay()->gte($today)) {
            $live = Visit::query()
                ->where('visitable_type', $type)
                ->whereBetween('visited_at', [$today, $today->copy()->endOfDay()]);

            self::constrainRawToCanonicalSource($live, $today, $type);

            if ($constrain) {
                $constrain($live);
            }

            $row = $live
                ->selectRaw('COUNT(*) as views, COUNT(visitor_id) as authenticated')
                ->first();

            if ($row && (int) $row->views > 0) {
                $perDay[$today->toDateString()] = [
                    'views' => (int) $row->views,
                    'authenticated' => (int) $row->authenticated,
                ];
            }
        }

        return [
            'per_day' => $perDay,
            'total' => array_sum(array_column($perDay, 'views')),
            'authenticated' => array_sum(array_column($perDay, 'authenticated')),
        ];
    }

    /**
     * Views recorded today, per target id.
     *
     * The lifetime columns are refreshed by the 01:45 rollup, which only ever
     * summarises completed days, so on its own the number a reader sees is up to a
     * day behind. Anyone who opens an article to check that tracking works looks at
     * the list straight afterwards and sees nothing move. Adding today at read time
     * costs one grouped query over a single indexed day and makes the figure
     * current to the second.
     *
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    public static function todayViewsByTarget(string $type, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $today = now()->startOfDay();

        $query = Visit::query()
            ->where('visitable_type', $type)
            ->whereIn('visitable_id', $ids)
            ->whereBetween('visited_at', [$today, $today->copy()->endOfDay()]);

        self::constrainRawToCanonicalSource($query, $today, $type);

        return $query
            ->selectRaw('visitable_id, COUNT(*) as views')
            ->groupBy('visitable_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->visitable_id => (int) $row->views])
            ->all();
    }

    /**
     * Daily click totals for one target type over a date range.
     *
     * Mirrors viewSeries: permanent rollup for completed days, one live query for
     * today. Clicks have only ever been counted one way, so there is no source rule
     * to apply here.
     *
     * @param  (callable(\Illuminate\Database\Eloquent\Builder<*>): void)|null  $constrain
     * @return array<string, int>
     */
    public static function clickSeries(
        string $type,
        CarbonInterface $start,
        CarbonInterface $end,
        ?callable $constrain = null,
    ): array {
        $perDay = [];

        $today = now()->startOfDay();
        $rollupEnd = $end->copy()->startOfDay()->min($today->copy()->subDay());

        if ($start->copy()->startOfDay()->lte($rollupEnd)) {
            $rollup = DailyClickStat::query()
                ->where('clickable_type', $type)
                ->whereBetween('date', [$start->toDateString(), $rollupEnd->toDateString()]);

            if ($constrain) {
                $constrain($rollup);
            }

            foreach ($rollup->selectRaw('date, SUM(clicks) as clicks')->groupBy('date')->get() as $row) {
                $perDay[Carbon::parse((string) $row->getRawOriginal('date'))->toDateString()] = (int) $row->clicks;
            }
        }

        if ($end->copy()->startOfDay()->gte($today)) {
            $live = Click::query()
                ->where('clickable_type', $type)
                ->whereBetween('clicked_at', [$today, $today->copy()->endOfDay()]);

            if ($constrain) {
                $constrain($live);
            }

            $count = (int) $live->count();

            if ($count > 0) {
                $perDay[$today->toDateString()] = $count;
            }
        }

        return $perDay;
    }

    /**
     * Fold today's views into a set of records before they are rendered.
     *
     * The lifetime columns stop at yesterday because the rollup only summarises
     * completed days. Anyone who opens a page to check that tracking works looks at
     * the listing straight afterwards, and without this they see the number sit
     * still. Costs one grouped query over a single indexed day.
     *
     * @param  iterable<Model>  $records
     */
    public static function foldTodayInto(iterable $records, string $type, string $column): void
    {
        $ids = [];
        foreach ($records as $record) {
            $ids[] = (int) $record->getKey();
        }

        $today = self::todayViewsByTarget($type, $ids);

        foreach ($records as $record) {
            $record->{$column} = (int) $record->{$column} + ($today[(int) $record->getKey()] ?? 0);
        }
    }

    /**
     * View totals per target over a date range, same sources as viewSeries().
     *
     * Separate from viewSeries because the two group differently and doing both
     * in one pass would mean pulling every (target, day) pair into PHP.
     *
     * @param  (callable(\Illuminate\Database\Eloquent\Builder<*>): void)|null  $constrain
     * @return array<int, int>
     */
    public static function viewTotalsByTarget(
        string $type,
        CarbonInterface $start,
        CarbonInterface $end,
        ?callable $constrain = null,
    ): array {
        $totals = [];

        $today = now()->startOfDay();
        $rollupEnd = $end->copy()->startOfDay()->min($today->copy()->subDay());

        if ($start->copy()->startOfDay()->lte($rollupEnd)) {
            $rollup = DailyVisitStat::query()
                ->where('visitable_type', $type)
                ->whereBetween('date', [$start->toDateString(), $rollupEnd->toDateString()]);

            self::constrainToCanonicalSources($rollup, $type);

            if ($constrain) {
                $constrain($rollup);
            }

            foreach ($rollup->selectRaw('visitable_id, SUM(views) as views')->groupBy('visitable_id')->get() as $row) {
                $totals[(int) $row->visitable_id] = (int) $row->views;
            }
        }

        if ($end->copy()->startOfDay()->gte($today)) {
            $live = Visit::query()
                ->where('visitable_type', $type)
                ->whereBetween('visited_at', [$today, $today->copy()->endOfDay()]);

            self::constrainRawToCanonicalSource($live, $today, $type);

            if ($constrain) {
                $constrain($live);
            }

            foreach ($live->selectRaw('visitable_id, COUNT(*) as views')->groupBy('visitable_id')->get() as $row) {
                $id = (int) $row->visitable_id;
                $totals[$id] = ($totals[$id] ?? 0) + (int) $row->views;
            }
        }

        return $totals;
    }

    /**
     * Narrow a raw `visits` query to the rows that count for that day.
     *
     * @param  Builder<Visit>  $query
     */
    public static function constrainRawToCanonicalSource(Builder $query, CarbonInterface $day, string $type): void
    {
        // Today is always browser-counted, whatever the type: the server-side path
        // was removed in July. The date check only matters for a backdated read of
        // an article, where GA4 owns the day and no raw row should be counted.
        if (! in_array($type, self::GA4_BACKED_TYPES, true) || self::canonicalSourceFor($day) === self::SOURCE_BEACON) {
            $query->whereNotNull('user_agent');

            return;
        }

        $query->whereNull('user_agent');
    }

    /**
     * SQL identifying one visitor.
     *
     * A visitor is an IP + User-Agent pair rather than a bare IP, because
     * Indonesian carriers put thousands of readers behind a single CGNAT address —
     * the same reason the `short-link` rate limiter runs a wider ceiling. The
     * result is a lower bound either way.
     *
     * Written as a concatenation rather than a row constructor so it runs on
     * PostgreSQL (production) and SQLite (the test suite) alike.
     */
    public static function visitorKeyExpression(): string
    {
        return "COALESCE(ip_address, '') || '~' || COALESCE(user_agent, '')";
    }

    /**
     * Per-day and range-wide unique visitor counts from a single pass.
     *
     * `COUNT(DISTINCT ...)` forces PostgreSQL into a sort: measured against
     * production, a 90-day range took 2.9s and spilled ~100 MB of temp files.
     * Grouping the pairs in a subquery lets the planner use a hash aggregate
     * instead — same numbers, 1.6s, nothing written to disk. The range-wide total
     * cannot be summed from the daily figures either (a reader returning on three
     * days is one visitor, not three), so both are derived here from the same
     * (day, visitor) set, which stays in the low tens of thousands.
     *
     * @param  Builder<\App\Models\Visit>|Relation<\App\Models\Visit, *, *>  $query
     * @return array{total: int, per_day: array<string, int>}
     */
    public static function uniqueVisitorCounts($query): array
    {
        $pairs = $query->clone()
            ->selectRaw('DATE(visited_at) as date, '.self::visitorKeyExpression().' as visitor_key')
            ->groupBy('date', 'visitor_key')
            ->get();

        $perDay = [];
        $visitors = [];

        foreach ($pairs as $pair) {
            $perDay[$pair->date] = ($perDay[$pair->date] ?? 0) + 1;
            $visitors[$pair->visitor_key] = true;
        }

        return ['total' => count($visitors), 'per_day' => $perDay];
    }
}
