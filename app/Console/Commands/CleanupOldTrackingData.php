<?php

namespace App\Console\Commands;

use App\Models\Click;
use App\Models\Visit;
use Illuminate\Console\Command;

class CleanupOldTrackingData extends Command
{
    protected $signature = 'tracking:cleanup {--years=5 : Number of years to keep data}';

    protected $description = 'Delete visits and clicks older than specified years (default: 5 years)';

    public function handle(): int
    {
        $years = (int) $this->option('years');
        $cutoffDate = now()->subYears($years);

        $this->info("Cleaning up tracking data older than {$years} years (before {$cutoffDate->toDateString()})...");

        // Delete old visits
        $deletedVisits = Visit::where('visited_at', '<', $cutoffDate)->delete();
        $this->info("Deleted {$deletedVisits} old visits.");

        // Delete old clicks
        $deletedClicks = Click::where('clicked_at', '<', $cutoffDate)->delete();
        $this->info("Deleted {$deletedClicks} old clicks.");

        $this->newLine();
        $this->info('Cleanup completed successfully!');
        $this->info('Total records deleted: '.($deletedVisits + $deletedClicks));

        return Command::SUCCESS;
    }
}
