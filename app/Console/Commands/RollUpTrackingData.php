<?php

namespace App\Console\Commands;

use App\Models\Click;
use App\Models\DailyClickStat;
use App\Models\DailyVisitStat;
use App\Models\Post;
use App\Models\Visit;
use App\Support\VisitStats;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
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
            $updated = $this->recomputePostLifetimeViews($dryRun);
            $this->info("Posts with a changed lifetime view total: {$updated}.");
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
     * Refresh the read cache on posts from the canonical rollup.
     *
     * Recomputed, never incremented, so a value that drifted for any reason heals
     * on the next run.
     *
     * Written through the base query builder on purpose. Post uses the
     * ClearsResponseCache trait, so saving through the model would purge the
     * `blog-posts` tag for every article every night — a nightly cache stampede
     * across 16 event websites. The base builder fires no model events and leaves
     * `updated_at` alone, which matters because sitemap lastmod reads it.
     *
     * That trait's docblock asks any raw write to a publicly exposed column to
     * call clearResponseCacheForRawUpdate(). Skipping it here is deliberate, not
     * an oversight: this number moves once a day at 01:45, the public payload is
     * cached for an hour, and it has expired on its own well before anyone is
     * awake. A stale lifetime view count is worth far less than a nightly purge
     * of every article on every site.
     */
    private function recomputePostLifetimeViews(bool $dryRun): int
    {
        $totals = DailyVisitStat::query()
            ->forType(Post::class)
            ->canonical()
            ->selectRaw('visitable_id, SUM(views) as total')
            ->groupBy('visitable_id')
            ->pluck('total', 'visitable_id');

        $changed = 0;

        Post::query()
            ->withTrashed()
            ->select(['id', 'lifetime_views'])
            ->orderBy('id')
            ->chunkById(500, function ($posts) use ($totals, $dryRun, &$changed): void {
                foreach ($posts as $post) {
                    $total = (int) ($totals[$post->id] ?? 0);

                    if ($total === (int) $post->lifetime_views) {
                        continue;
                    }

                    $changed++;

                    if ($dryRun) {
                        continue;
                    }

                    Post::query()->toBase()
                        ->where('id', $post->id)
                        ->update(['lifetime_views' => $total]);
                }
            });

        return $changed;
    }
}
