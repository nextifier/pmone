<?php

namespace App\Console\Commands;

use App\Models\Click;
use App\Models\Visit;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class CleanupOldTrackingData extends Command
{
    protected $signature = 'tracking:cleanup
        {--days=90 : Number of days of tracking data to keep}
        {--chunk=5000 : Rows deleted per statement}';

    protected $description = 'Delete visits and clicks older than the retention window';

    /**
     * These are the highest-volume tables in the application (public page
     * views). Deleting in chunks keeps each statement short so a purge cannot
     * hold a long lock or trip a statement timeout — the previous unbounded
     * DELETE would have had to remove millions of rows in one statement.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $chunk = (int) $this->option('chunk');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up tracking data older than {$days} days (before {$cutoffDate->toDateString()})...");

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
