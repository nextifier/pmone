<?php

namespace App\Console\Commands;

use App\Models\GaProperty;
use App\Services\GoogleAnalytics\AnalyticsDataFetcher;
use App\Services\GoogleAnalytics\Period;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProbeGoogleAnalyticsHistory extends Command
{
    protected $signature = 'visits:ga4-probe
        {--property= : GA4 property id to probe, defaults to every active property}
        {--from= : First day to ask for (Y-m-d), defaults to 18 months ago}
        {--to= : Last day to ask for (Y-m-d), defaults to 17 months ago}';

    protected $description = 'Report how far back GA4 will still return per-page daily views';

    /**
     * Answers one question before any backfill work is planned: does GA4 still
     * hand back page-level daily rows from before our own records begin?
     *
     * The `visits` table is pruned to 90 days and nothing else in the database
     * holds a per-article history, so GA4 is the only possible source for the
     * years before that. Aggregate reports are generally not subject to the 2 or
     * 14 month event-data retention setting, but that is a claim worth testing
     * rather than trusting: one call settles it per property.
     */
    public function handle(AnalyticsDataFetcher $fetcher): int
    {
        $from = Carbon::parse($this->option('from') ?: now()->subMonths(18)->startOfMonth()->toDateString());
        $to = Carbon::parse($this->option('to') ?: now()->subMonths(17)->startOfMonth()->toDateString());

        $properties = GaProperty::query()
            ->when($this->option('property'), fn ($q) => $q->where('property_id', $this->option('property')))
            ->where('is_active', true)
            ->get();

        if ($properties->isEmpty()) {
            $this->error('No active GA properties matched.');

            return self::FAILURE;
        }

        $this->info("Asking GA4 for page-level daily views, {$from->toDateString()} to {$to->toDateString()}.");
        $this->newLine();

        $rows = [];

        foreach ($properties as $property) {
            try {
                $pages = $fetcher->fetchTopPagesDaily($property, Period::create($from, $to), 1000);
            } catch (\Throwable $e) {
                $rows[] = [$property->name, 'ERROR', '-', '-', mb_substr($e->getMessage(), 0, 60)];

                continue;
            }

            $dates = array_column($pages, 'date');
            $newsPages = array_filter($pages, fn (array $page): bool => str_contains((string) $page['path'], '/news/'));

            $rows[] = [
                $property->name,
                count($pages),
                count($newsPages),
                $dates === [] ? '-' : min($dates).' .. '.max($dates),
                array_sum(array_column($newsPages, 'pageviews')),
            ];
        }

        $this->table(['Property', 'Rows', 'News rows', 'Dates returned', 'News views'], $rows);
        $this->newLine();
        $this->line('Rows > 0 means the history is reachable and a backfill is worth building.');
        $this->line('Rows = 0 everywhere means GA4 has aged the window out and 21 May 2026 is the floor.');

        return self::SUCCESS;
    }
}
