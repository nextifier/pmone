<?php

namespace App\Console\Commands;

use App\Models\Click;
use App\Models\DailyClickStat;
use App\Models\DailyVisitStat;
use App\Models\Visit;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CleanupOldTrackingData extends Command
{
    protected $signature = 'tracking:cleanup
        {--days=90 : Number of days of tracking data to keep}
        {--chunk=5000 : Rows deleted per statement}
        {--skip-rollup-check : Delete even where no rollup exists. Data is lost permanently}';

    protected $description = 'Delete visits and clicks older than the retention window';

    /**
     * These are the highest-volume tables in the application (public page
     * views). Deleting in chunks keeps each statement short so a purge cannot
     * hold a long lock or trip a statement timeout — the previous unbounded
     * DELETE would have had to remove millions of rows in one statement.
     *
     * The 90-day window is only safe because `visits:rollup` has already turned
     * each of those days into a permanent daily summary. This command therefore
     * refuses to run ahead of the rollup: it checks first that every day it is
     * about to delete has been summarised, and aborts if any has not. Letting the
     * raw table grow for a few days costs disk; deleting an unsummarised day costs
     * the number forever.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $chunk = (int) $this->option('chunk');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up tracking data older than {$days} days (before {$cutoffDate->toDateString()})...");

        if (! $this->option('skip-rollup-check') && ! $this->assertRolledUp($cutoffDate)) {
            return self::FAILURE;
        }

        $deletedVisits = $this->deleteInChunks(
            Visit::query()->where('visited_at', '<', $cutoffDate),
            $chunk
        );
        $this->info("Deleted {$deletedVisits} old visits.");

        $deletedClicks = $this->deleteInChunks(
            Click::query()->where('clicked_at', '<', $cutoffDate),
            $chunk
        );
        $this->info("Deleted {$deletedClicks} old clicks.");

        $this->newLine();
        $this->info('Cleanup completed successfully!');
        $this->info('Total records deleted: '.($deletedVisits + $deletedClicks));

        return Command::SUCCESS;
    }

    /**
     * Refuse to delete any day the rollup has not already summarised.
     *
     * Compares the days that actually hold rows against the days present in the
     * rollup, rather than trusting a high-water mark: a day with no traffic
     * produces no rollup row, and treating that as "behind" would wedge the purge
     * forever.
     */
    private function assertRolledUp(Carbon $cutoffDate): bool
    {
        $missingVisitDays = $this->missingDays(
            Visit::query()->where('visited_at', '<', $cutoffDate),
            'visited_at',
            DailyVisitStat::query(),
        );

        $missingClickDays = $this->missingDays(
            Click::query()->where('clicked_at', '<', $cutoffDate),
            'clicked_at',
            DailyClickStat::query(),
        );

        $missing = array_values(array_unique(array_merge($missingVisitDays, $missingClickDays)));

        if ($missing === []) {
            return true;
        }

        sort($missing);

        $this->error('Refusing to delete: these days hold tracking rows that were never rolled up.');
        $this->error('Missing: '.implode(', ', array_slice($missing, 0, 10)).(count($missing) > 10 ? ' (+'.(count($missing) - 10).' more)' : ''));
        $this->newLine();
        $this->line('Run `php artisan visits:rollup --from='.$missing[0].' --to='.end($missing).'` first.');
        $this->line('Deleting them now would lose those view counts permanently.');

        return false;
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $source
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $rollup
     * @return list<string>
     */
    private function missingDays(Builder $source, string $column, Builder $rollup): array
    {
        $days = $source->clone()
            ->selectRaw("DATE({$column}) as day")
            ->distinct()
            ->pluck('day')
            ->map(fn ($day): string => Carbon::parse((string) $day)->toDateString())
            ->all();

        if ($days === []) {
            return [];
        }

        $rolledUp = $rollup->clone()
            ->whereIn('date', $days)
            ->distinct()
            ->pluck('date')
            ->map(fn ($day): string => Carbon::parse((string) $day)->toDateString())
            ->all();

        return array_values(array_diff($days, $rolledUp));
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function deleteInChunks(Builder $query, int $chunk): int
    {
        $total = 0;

        do {
            $deleted = (clone $query)->limit($chunk)->delete();
            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }
}
