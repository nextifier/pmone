<?php

namespace App\Console\Commands;

use App\Models\GaProperty;
use App\Services\GoogleAnalytics\DailyDataAggregator;
use App\Services\GoogleAnalytics\Period;
use Illuminate\Console\Command;

class RefreshAnalyticsCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:refresh-cache {--property= : Specific property ID to refresh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh 365-day analytics cache for all properties in background';

    /**
     * Execute the console command.
     */
    public function handle(DailyDataAggregator $aggregator)
    {
        $this->info('Starting analytics cache refresh...');

        // Get properties to refresh
        $query = GaProperty::active();

        if ($propertyId = $this->option('property')) {
            $query->where('property_id', $propertyId);
        }

        $properties = $query->get();

        if ($properties->isEmpty()) {
            $this->warn('No active properties found.');

            return Command::SUCCESS;
        }

        $this->info("Found {$properties->count()} properties to refresh.");

        $successCount = 0;
        $failCount = 0;

        foreach ($properties as $property) {
            try {
                $this->info("Refreshing cache for: {$property->name} ({$property->property_id})");

                // Clear existing cache first
                $aggregator->clearDailyCache($property->property_id);

                // Fetch fresh 365-day data
                $period = Period::days(365);
                $aggregator->getDataForPeriod($property, $period);

                // Also fetch top pages, traffic sources, and devices
                $aggregator->getTopPagesForPeriod($property, $period, 100);
                $aggregator->getTrafficSourcesForPeriod($property, $period);
                $aggregator->getDevicesForPeriod($property, $period);

                $this->info("✓ Successfully refreshed cache for: {$property->name}");
                $successCount++;

                // Update last_synced_at timestamp
                $property->update(['last_synced_at' => now()]);
            } catch (\Exception $e) {
                $this->error("✗ Failed to refresh cache for {$property->name}: {$e->getMessage()}");
                $failCount++;

                \Log::error('Failed to refresh analytics cache', [
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info('Cache refresh completed!');
        $this->info("Success: {$successCount} | Failed: {$failCount}");

        return $failCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
