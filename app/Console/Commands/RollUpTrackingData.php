<?php

namespace App\Console\Commands;

use App\Models\BrandEvent;
use App\Models\Click;
use App\Models\DailyClickStat;
use App\Models\DailyVisitStat;
use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Models\Post;
use App\Models\ProjectBanner;
use App\Models\Visit;
use App\Support\VisitStats;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class RollUpTrackingData extends Command
{
    protected $signature = 'visits:rollup
        {--date= : Roll up a single day (Y-m-d)}
        {--from= : First day of a range (Y-m-d)}
        {--to= : Last day of a range (Y-m-d), defaults to yesterday}
        {--days=3 : How many days back to re-roll when no date or range is given}
        {--skip-lifetime : Skip recomputing posts.lifetime_views}
        {--dry-run : Report what would be written without writing it}';

    protected $description = 'Summarise visits and clicks into the permanent daily rollup tables';

    /**
     * The raw tables are pruned to 90 days. Without a permanent summary a
     * four-year-old article reports a lifetime view count covering only the last
     * quarter, and that number shrinks on its own as older days age out. This
     * command is what makes it permanent, and `tracking:cleanup` refuses to delete
     * any day this command has not already processed.
     *
     * Re-running a day is safe and produces identical numbers: rows are keyed on
     * (target, date, source) and updated in place, never appended.
     */
    public function handle(): int
    {
        [$from, $to] = $this->resolveRange();

        if ($from->gt($to)) {
            $this->error("Empty range: {$from->toDateString()} is after {$to->toDateString()}.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Rolling up %s to %s%s...',
            $from->toDateString(),
            $to->toDateString(),
            $dryRun ? ' (dry run)' : ''
        ));

        $visitRows = 0;
        $clickRows = 0;

        $cursor = $from->copy();
        $bar = $this->output->createProgressBar($from->diffInDays($to) + 1);
        $bar->start();

        while ($cursor->lte($to)) {
            $visitRows += $this->rollUpVisits($cursor, $dryRun);
            $clickRows += $this->rollUpClicks($cursor, $dryRun);

            $cursor->addDay();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Visit stat rows written: {$visitRows}.");
        $this->info("Click stat rows written: {$clickRows}.");

        if (! $this->option('skip-lifetime')) {
            $updated = $this->recomputeLifetimeCounters($dryRun);
            $this->info("Lifetime totals changed: {$updated}.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(): array
    {
        if ($date = $this->option('date')) {
            $day = Carbon::parse($date)->startOfDay();

            return [$day, $day->copy()];
        }

        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->startOfDay()
            : Carbon::yesterday()->startOfDay();

        if ($from = $this->option('from')) {
            return [Carbon::parse($from)->startOfDay(), $to];
        }

        // Default re-rolls a short trailing window rather than only yesterday, so a
        // beacon that arrived late, or a night the command did not run, is picked up
        // without anyone having to notice and trigger it by hand.
        $days = max(1, (int) $this->option('days'));

        return [$to->copy()->subDays($days - 1), $to];
    }

    /**
     * Summarise one day of visits, one row per target per measurement method.
     */
    private function rollUpVisits(Carbon $day, bool $dryRun): int
    {
        $written = 0;

        // Two queries rather than one CASE expression: each side is a plain indexed
        // scan, and the classification stays readable. `user_agent IS NULL` is the
        // server-side path that ran until 28 Jul 2026; anything else is the browser
        // beacon. See VisitStats::sourceForRawVisit for why the row, not the date,
        // is the discriminator.
        $sources = [
            VisitStats::SOURCE_SERVER_RENDER => true,
            VisitStats::SOURCE_BEACON => false,
        ];

        foreach ($sources as $source => $userAgentIsNull) {
            $base = $this->visitsOn($day);
            $userAgentIsNull ? $base->whereNull('user_agent') : $base->whereNotNull('user_agent');

            $counts = $base->clone()
                ->selectRaw('visitable_type, visitable_id, COUNT(*) as views, COUNT(visitor_id) as authenticated_views')
                ->groupBy('visitable_type', 'visitable_id')
                ->get();

            if ($counts->isEmpty()) {
                continue;
            }

            $uniques = $source === VisitStats::SOURCE_BEACON
                ? $this->uniqueVisitorsPerTarget($base, $day)
                : [];

            foreach ($counts as $row) {
                $written++;

                if ($dryRun) {
                    continue;
                }

                DailyVisitStat::query()->updateOrCreate(
                    [
                        'visitable_type' => $row->visitable_type,
                        'visitable_id' => $row->visitable_id,
                        'date' => $day->toDateString(),
                        'source' => $source,
                    ],
                    [
                        'views' => (int) $row->views,
                        // COUNT(column) skips nulls, so this is the signed-in slice.
                        // Anonymous is the remainder and needs no column.
                        'authenticated_views' => (int) $row->authenticated_views,
                        'unique_visitors' => $uniques[$row->visitable_type.'|'.$row->visitable_id] ?? null,
                    ],
                );
            }
        }

        return $written;
    }

    /**
     * Distinct visitors per target for one day, or an empty map when the figure
     * would be meaningless.
     *
     * @param  Builder<Visit>  $base
     * @return array<string, int>
     */
    private function uniqueVisitorsPerTarget($base, Carbon $day): array
    {
        if (! VisitStats::canCountUniqueVisitors($day)) {
            return [];
        }

        $pairs = $base->clone()
            ->selectRaw('visitable_type, visitable_id, '.VisitStats::visitorKeyExpression().' as visitor_key')
            ->groupBy('visitable_type', 'visitable_id', 'visitor_key')
            ->get();

        $perTarget = [];

        foreach ($pairs as $pair) {
            $key = $pair->visitable_type.'|'.$pair->visitable_id;
            $perTarget[$key] = ($perTarget[$key] ?? 0) + 1;
        }

        return $perTarget;
    }

    /**
     * One calendar day of visits, bounded in the application timezone.
     *
     * `visited_at` is `timestamp without time zone` and Laravel writes it in
     * `app.timezone`, so the stored value is Jakarta wall-clock and these bounds
     * line up with the `DATE(visited_at)` the cleanup interlock compares against.
     * Changing app.timezone later would silently reassign historical days.
     *
     * @return Builder<Visit>
     */
    private function visitsOn(Carbon $day): Builder
    {
        return Visit::query()->whereBetween('visited_at', [
            $day->copy()->startOfDay(),
            $day->copy()->endOfDay(),
        ]);
    }

    /**
     * Summarise one day of clicks. No source dimension: clicks have always been
     * recorded from the browser.
     */
    private function rollUpClicks(Carbon $day, bool $dryRun): int
    {
        $counts = Click::query()
            ->whereBetween('clicked_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->selectRaw('clickable_type, clickable_id, COUNT(*) as clicks')
            ->groupBy('clickable_type', 'clickable_id')
            ->get();

        foreach ($counts as $row) {
            if ($dryRun) {
                continue;
            }

            DailyClickStat::query()->updateOrCreate(
                [
                    'clickable_type' => $row->clickable_type,
                    'clickable_id' => $row->clickable_id,
                    'date' => $day->toDateString(),
                ],
                ['clicks' => (int) $row->clicks],
            );
        }

        return $counts->count();
    }

    /**
     * Refresh the read caches on every tracked model from the canonical rollup.
     *
     * Recomputed, never incremented, so a value that drifted for any reason heals
     * on the next run.
     *
     * Written through the base query builder on purpose. These models use the
     * ClearsResponseCache trait, so saving through the model would purge public
     * cache tags for every row every night — a nightly stampede across 16 event
     * websites. The base builder fires no model events and leaves `updated_at`
     * alone, which matters because sitemap lastmod reads it.
     *
     * That trait's docblock asks any raw write to a publicly exposed column to call
     * clearResponseCacheForRawUpdate(). Skipping it here is deliberate: these
     * numbers move once a day at 01:45, the public payloads are cached for an hour,
     * and they have expired on their own well before anyone is awake.
     */
    private function recomputeLifetimeCounters(bool $dryRun): int
    {
        $changed = 0;

        foreach ([
            [Post::class, 'lifetime_views'],
            [LinkPage::class, 'lifetime_views'],
            [BrandEvent::class, 'lifetime_views'],
            // The banner's own vocabulary. Same morph table underneath.
            [ProjectBanner::class, 'lifetime_impressions'],
        ] as [$model, $column]) {
            $changed += $this->syncColumn($model, $column, $this->viewTotalsFor($model), $dryRun);
        }

        foreach ([
            [BrandEvent::class, 'lifetime_clicks'],
            [ProjectBanner::class, 'lifetime_clicks'],
        ] as [$model, $column]) {
            $changed += $this->syncColumn($model, $column, $this->clickTotalsFor($model), $dryRun);
        }

        $changed += $this->syncColumn(LinkPage::class, 'lifetime_clicks', $this->linkPageClickTotals(), $dryRun);

        return $changed;
    }

    /**
     * @return array<int, int>
     */
    private function viewTotalsFor(string $model): array
    {
        return DailyVisitStat::query()
            ->forType($model)
            ->canonical($model)
            ->selectRaw('visitable_id, SUM(views) as total')
            ->groupBy('visitable_id')
            ->pluck('total', 'visitable_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function clickTotalsFor(string $model): array
    {
        return DailyClickStat::query()
            ->forType($model)
            ->selectRaw('clickable_id, SUM(clicks) as total')
            ->groupBy('clickable_id')
            ->pluck('total', 'clickable_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * A link page is never clicked itself, only the items on it.
     *
     * The `clicks` table has never held a single row for LinkPage, so the page-level
     * count the admin listing showed was always zero. Rolling the items up to their
     * page is what makes that column mean something.
     *
     * @return array<int, int>
     */
    private function linkPageClickTotals(): array
    {
        return DailyClickStat::query()
            ->forType(LinkPageItem::class)
            ->join('link_page_items', 'link_page_items.id', '=', 'daily_click_stats.clickable_id')
            ->selectRaw('link_page_items.link_page_id as owner_id, SUM(daily_click_stats.clicks) as total')
            ->groupBy('link_page_items.link_page_id')
            ->pluck('total', 'owner_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, int>  $totals
     */
    private function syncColumn(string $model, string $column, array $totals, bool $dryRun): int
    {
        $softDeletes = in_array(SoftDeletes::class, class_uses_recursive($model), true);
        $changed = 0;

        $query = $model::query();
        if ($softDeletes) {
            // Without this a trashed row keeps a stale total forever: the trash
            // listing shows the column, and a restored row would read zero.
            $query->withTrashed();
        }

        $query->select(['id', $column])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($model, $column, $totals, $dryRun, $softDeletes, &$changed): void {
                foreach ($rows as $row) {
                    $total = $totals[$row->id] ?? 0;

                    if ($total === (int) $row->{$column}) {
                        continue;
                    }

                    $changed++;

                    if ($dryRun) {
                        continue;
                    }

                    $update = $model::query();
                    if ($softDeletes) {
                        $update->withTrashed();
                    }

                    $update->toBase()->where('id', $row->id)->update([$column => $total]);
                }
            });

        return $changed;
    }
}
