<?php

namespace App\Jobs;

use App\Models\GaProperty;
use App\Services\GoogleAnalytics\AnalyticsService;
use App\Services\GoogleAnalytics\Period;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncGoogleAnalyticsData implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [60, 120, 300];

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $propertyId,
        public int $days = 30,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AnalyticsService $analyticsService): void
    {
        $property = GaProperty::find($this->propertyId);

        if (! $property) {
            Log::warning('GaProperty not found for sync', [
                'property_id' => $this->propertyId,
            ]);

            return;
        }

        if (! $property->is_active) {
            Log::info('Skipping inactive property', [
                'property_id' => $property->property_id,
                'property_name' => $property->name,
            ]);

            return;
        }

        try {
            $period = Period::days($this->days);

            $result = $analyticsService->syncProperty($property, $period);

            if ($result['success']) {
                Log::info('GA4 property synced successfully', [
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'synced_at' => $result['synced_at'],
                ]);
            } else {
                Log::error('Failed to sync GA4 property', [
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Exception while syncing GA4 property', [
                'property_id' => $property->property_id,
                'property_name' => $property->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to allow retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('SyncGoogleAnalyticsData job failed permanently', [
            'property_id' => $this->propertyId,
            'days' => $this->days,
            'error' => $exception->getMessage(),
        ]);
    }
}
