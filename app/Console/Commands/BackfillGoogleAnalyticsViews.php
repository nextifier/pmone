<?php

namespace App\Console\Commands;

use App\Models\DailyVisitStat;
use App\Models\GaProperty;
use App\Models\Post;
use App\Services\GoogleAnalytics\AnalyticsDataFetcher;
use App\Services\GoogleAnalytics\Period;
use App\Support\VisitStats;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BackfillGoogleAnalyticsViews extends Command
{
    protected $signature = 'visits:backfill-ga4
        {--from= : First day to import (Y-m-d), defaults to 24 months ago}
        {--to= : Last day to import (Y-m-d), defaults to the day before browser counting began}
        {--property= : Limit to one GA4 property id}
        {--chunk-days=31 : Days per API request window}
        {--dry-run : Report what would be written without writing it}';

    protected $description = 'Import per-article daily views from Google Analytics for the years before our own counter existed';

    /**
     * Fills the history that exists nowhere else.
     *
     * The `visits` table is pruned to 90 days and no other table in this database
     * ever held a per-article figure, so for anything older GA4 is the only
     * surviving record. Verified against the live API: page-level daily rows come
     * back at least 17 months out, well past our own floor.
     *
     * Rows land under source `ga4` and are never added to `beacon` rows for the
     * same day — VisitStats picks one per date. That matters because the two do
     * not measure identically: GA4 runs roughly 4% lower, losing readers to ad
     * blockers where our same-origin beacon does not. Close enough to continue the
     * same series, too different to merge.
     */
    public function handle(AnalyticsDataFetcher $fetcher): int
    {
        $from = Carbon::parse($this->option('from') ?: now()->subMonths(24)->startOfMonth()->toDateString());

        // Defaults to the day before the browser beacon took over, because from
        // that date on the beacon is the canonical source and GA4 rows for those
        // days would be stored only to be ignored.
        $to = Carbon::parse(
            $this->option('to') ?: Carbon::parse(VisitStats::browserCountingSince())->subDay()->toDateString()
        );

        if ($from->gt($to)) {
            $this->error("Empty range: {$from->toDateString()} is after {$to->toDateString()}.");

            return self::FAILURE;
        }

        $postIdsBySlug = Post::query()->withTrashed()->pluck('id', 'slug');
        $this->info("Resolving against {$postIdsBySlug->count()} known article slugs.");

        $properties = GaProperty::query()
            ->when($this->option('property'), fn ($q) => $q->where('property_id', $this->option('property')))
            ->where('is_active', true)
            ->get();

        if ($properties->isEmpty()) {
            $this->error('No active GA properties matched.');

            return self::FAILURE;
        }

        $unmatched = [];
        $failedWindows = [];
        $written = 0;
        $chunkDays = max(1, (int) $this->option('chunk-days'));
        $dryRun = (bool) $this->option('dry-run');

        // One window at a time across every property, rather than one property at a
        // time across every window. Two reasons, and the second is the important
        // one: memory stays bounded by a single window instead of two years of
        // article-days, and each window is written before the next begins, so a run
        // that dies at month twenty keeps everything it already imported. Re-running
        // with a later --from then picks up where it stopped.
        //
        // Correctness is unaffected: an article is reachable on several domains and
        // in five languages, and every property is read before the window is
        // written, so the cross-domain sum is always complete. Our own beacon has
        // counted them into one number all along, and the imported history matches.
        $windowStart = $from->copy();

        while ($windowStart->lte($to)) {
            $windowEnd = $windowStart->copy()->addDays($chunkDays - 1)->min($to);
            $label = $windowStart->toDateString().'..'.$windowEnd->toDateString();

            $views = [];

            foreach ($properties as $property) {
                $ok = $this->collectWindow(
                    $fetcher, $property, $windowStart, $windowEnd, $postIdsBySlug, $views, $unmatched
                );

                if (! $ok) {
                    $failedWindows[] = $property->name.' '.$label;
                }
            }

            $written += $dryRun ? count($views) : $this->writeWindow($views);
            $this->line("  {$label}: ".count($views).' article-days');

            $windowStart = $windowEnd->copy()->addDay();
        }

        $this->newLine();
        $this->info($written.' article-days '.($dryRun ? 'would be written' : 'written').'.');

        if ($unmatched !== []) {
            arsort($unmatched);
            $this->warn(count($unmatched).' article paths had no matching slug. Top few:');
            foreach (array_slice($unmatched, 0, 5, true) as $path => $count) {
                $this->line("  {$count} views  {$path}");
            }
        }

        if ($failedWindows !== []) {
            // Named rather than swallowed: a window that failed is a hole in the
            // history, and nothing downstream can tell a hole from a quiet month.
            $this->newLine();
            $this->error(count($failedWindows).' windows failed and are missing from the import:');
            foreach (array_slice($failedWindows, 0, 10) as $window) {
                $this->line('  '.$window);
            }
            $this->line('Re-run with --from/--to covering those dates.');

            return self::FAILURE;
        }

        if (! $dryRun) {
            $this->info('Imported. Run `php artisan visits:rollup` to refresh lifetime totals.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $views
     */
    private function writeWindow(array $views): int
    {
        foreach ($views as $key => $pageviews) {
            [$postId, $date] = explode('|', $key);

            DailyVisitStat::query()->updateOrCreate(
                [
                    'visitable_type' => Post::class,
                    'visitable_id' => (int) $postId,
                    'date' => $date,
                    'source' => VisitStats::SOURCE_GA4,
                ],
                [
                    'views' => $pageviews,
                    'authenticated_views' => 0,
                    // GA4 reports page views, not distinct people, at this
                    // granularity. Null means not measurable rather than zero.
                    'unique_visitors' => null,
                ],
            );
        }

        return count($views);
    }

    /**
     * Read one window for one property into the shared accumulator.
     *
     * @param  Collection<string, int>  $postIdsBySlug
     * @param  array<string, int>  $views
     * @param  array<string, int>  $unmatched
     * @return bool False when the window could not be read at all.
     */
    private function collectWindow(
        AnalyticsDataFetcher $fetcher,
        GaProperty $property,
        Carbon $start,
        Carbon $end,
        $postIdsBySlug,
        array &$views,
        array &$unmatched,
    ): bool {
        $limit = 10000;
        $offset = 0;

        do {
            try {
                $pages = $fetcher->fetchTopPagesDaily($property, Period::create($start, $end), $limit, $offset);
            } catch (\Throwable $e) {
                $this->warn("  {$property->name} {$start->toDateString()}..{$end->toDateString()} failed: ".mb_substr($e->getMessage(), 0, 80));

                return false;
            }

            foreach ($pages as $page) {
                $slug = $this->slugFromPath((string) $page['path']);

                if ($slug === null) {
                    continue;
                }

                if (! $postIdsBySlug->has($slug)) {
                    $unmatched[$slug] = ($unmatched[$slug] ?? 0) + (int) $page['pageviews'];

                    continue;
                }

                $key = $postIdsBySlug[$slug].'|'.$page['date'];
                $views[$key] = ($views[$key] ?? 0) + (int) $page['pageviews'];
            }

            $offset += $limit;
            // A short page means the report is exhausted. Without this the busiest
            // months would stop at whatever the first page happened to hold.
        } while (count($pages) === $limit);

        return true;
    }

    /**
     * Turn a GA4 pagePath into an article slug, or null if it is not an article.
     *
     * Handles the two shapes production actually produces: a locale prefix, since
     * Indonesian readers are redirected to /id, and a trailing query string
     * (`?hl=id-ID` shows up in the referer data). The locale pattern cannot eat
     * `/news` itself — it only matches two letters followed by a slash.
     */
    private function slugFromPath(string $path): ?string
    {
        $path = (string) strtok($path, '?');
        $path = rtrim($path, '/');
        $path = preg_replace('#^/[a-z]{2}(-[A-Za-z]{2,4})?(?=/)#', '', $path) ?? $path;

        return preg_match('#^/news/([^/]+)$#', $path, $matches) === 1 ? $matches[1] : null;
    }
}
