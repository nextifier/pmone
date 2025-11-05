<?php

namespace App\Console\Commands;

use App\Jobs\SyncGoogleAnalyticsData;
use App\Models\GaProperty;
use App\Services\GoogleAnalytics\AnalyticsService;
use Illuminate\Console\Command;
use Spatie\Analytics\Period;

class SyncGoogleAnalytics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:sync
                            {--property= : Sync specific property by ID}
                            {--days=7 : Number of days to sync (default: 7)}
                            {--queue : Dispatch sync jobs to queue}
                            {--only-needed : Only sync properties that need syncing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Google Analytics 4 data for properties';

    /**
     * Execute the console command.
     */
    public function handle(AnalyticsService $analyticsService): int
    {
        $this->info('🚀 Starting Google Analytics sync...');

        $propertyId = $this->option('property');
        $days = (int) $this->option('days');
        $useQueue = $this->option('queue');
        $onlyNeeded = $this->option('only-needed');

        if ($propertyId) {
            return $this->syncSingleProperty((int) $propertyId, $days, $useQueue, $analyticsService);
        }

        if ($onlyNeeded) {
            return $this->syncPropertiesNeedingUpdate($days, $useQueue, $analyticsService);
        }

        return $this->syncAllProperties($days, $useQueue, $analyticsService);
    }

    /**
     * Sync a single property.
     */
    protected function syncSingleProperty(
        int $propertyId,
        int $days,
        bool $useQueue,
        AnalyticsService $analyticsService
    ): int {
        $property = GaProperty::find($propertyId);

        if (! $property) {
            $this->error("❌ Property with ID {$propertyId} not found.");

            return self::FAILURE;
        }

        $this->info("📊 Syncing property: {$property->name} ({$property->property_id})");

        if ($useQueue) {
            SyncGoogleAnalyticsData::dispatch($property->id, $days);
            $this->info('✅ Sync job dispatched to queue');
        } else {
            $period = Period::days($days);
            $result = $analyticsService->syncProperty($property, $period);

            if ($result['success']) {
                $this->info('✅ Sync completed successfully');
                $this->info("   Last synced: {$result['synced_at']}");
            } else {
                $this->error("❌ Sync failed: {$result['error']}");

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * Sync all active properties.
     */
    protected function syncAllProperties(
        int $days,
        bool $useQueue,
        AnalyticsService $analyticsService
    ): int {
        $properties = GaProperty::active()->get();

        if ($properties->isEmpty()) {
            $this->warn('⚠️  No active properties found.');

            return self::SUCCESS;
        }

        $this->info("📊 Syncing {$properties->count()} active properties...");

        $bar = $this->output->createProgressBar($properties->count());
        $bar->start();

        $successful = 0;
        $failed = 0;

        foreach ($properties as $property) {
            if ($useQueue) {
                SyncGoogleAnalyticsData::dispatch($property->id, $days);
                $successful++;
            } else {
                $period = Period::days($days);
                $result = $analyticsService->syncProperty($property, $period);

                if ($result['success']) {
                    $successful++;
                } else {
                    $failed++;
                    $this->newLine();
                    $this->error("   ❌ Failed: {$property->name} - {$result['error']}");
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($useQueue) {
            $this->info("✅ {$successful} sync jobs dispatched to queue");
        } else {
            $this->info("✅ Successful: {$successful}");
            if ($failed > 0) {
                $this->error("❌ Failed: {$failed}");
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Sync only properties that need syncing.
     */
    protected function syncPropertiesNeedingUpdate(
        int $days,
        bool $useQueue,
        AnalyticsService $analyticsService
    ): int {
        $properties = GaProperty::needsSync()->get();

        if ($properties->isEmpty()) {
            $this->info('✅ No properties need syncing at this time.');

            return self::SUCCESS;
        }

        $this->info("📊 Found {$properties->count()} properties that need syncing...");

        $bar = $this->output->createProgressBar($properties->count());
        $bar->start();

        $successful = 0;
        $failed = 0;

        foreach ($properties as $property) {
            if ($useQueue) {
                SyncGoogleAnalyticsData::dispatch($property->id, $days);
                $successful++;
            } else {
                $period = Period::days($days);
                $result = $analyticsService->syncProperty($property, $period);

                if ($result['success']) {
                    $successful++;
                } else {
                    $failed++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($useQueue) {
            $this->info("✅ {$successful} sync jobs dispatched to queue");
        } else {
            $this->info("✅ Successful: {$successful}");
            if ($failed > 0) {
                $this->error("❌ Failed: {$failed}");
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
