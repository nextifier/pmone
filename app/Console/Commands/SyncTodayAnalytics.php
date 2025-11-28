<?php

namespace App\Console\Commands;

use App\Jobs\SyncTodayAnalyticsJob;
use App\Models\GaProperty;
use Illuminate\Console\Command;

class SyncTodayAnalytics extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'analytics:sync-today
                            {--queue : Dispatch sync jobs to queue}';

    /**
     * The console command description.
     */
    protected $description = 'Sync today\'s Google Analytics data for all active properties (optimized for instant loading)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Syncing today\'s analytics data...');

        $properties = GaProperty::active()->get();

        if ($properties->isEmpty()) {
            $this->warn('⚠️  No active properties found.');

            return self::SUCCESS;
        }

        $this->info("📊 Syncing today's data for {$properties->count()} properties...");

        $useQueue = $this->option('queue');

        if ($useQueue) {
            // Dispatch single job to sync all properties
            SyncTodayAnalyticsJob::dispatch();
            $this->info('✅ Sync job dispatched to queue');
        } else {
            // Run sync synchronously
            $job = new SyncTodayAnalyticsJob;
            $job->handle();
            $this->info('✅ Today\'s analytics synced successfully');
        }

        return self::SUCCESS;
    }
}
