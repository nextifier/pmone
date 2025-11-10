<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ClearAnalyticsRateLimit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:clear-rate-limit {--all : Clear all rate limits} {--property= : Clear rate limit for specific property ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear analytics rate limit cache to resolve stuck rate limiting issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $propertyId = $this->option('property');
        $clearAll = $this->option('all');

        if ($clearAll) {
            return $this->clearAllRateLimits();
        }

        if ($propertyId) {
            return $this->clearPropertyRateLimit($propertyId);
        }

        $this->error('Please specify either --all or --property=ID');

        return Command::FAILURE;
    }

    /**
     * Clear all analytics rate limit cache entries.
     */
    protected function clearAllRateLimits(): int
    {
        $this->info('Clearing all analytics rate limit cache...');

        // Get cache driver
        $driver = config('cache.default');

        if ($driver === 'database') {
            // For database cache, delete entries matching rate limit keys
            $deleted = DB::table('cache')
                ->where('key', 'like', '%analytics-fetch:%')
                ->orWhere('key', 'like', '%limiter:%')
                ->delete();

            $this->info("Deleted {$deleted} rate limit cache entries from database.");
        } else {
            // For other cache drivers (redis, memcached, etc)
            // Use Cache facade with pattern matching
            $this->warn('Non-database cache driver detected. Using Cache::flush() to clear all cache.');
            Cache::flush();
            $this->info('All cache cleared (including rate limits).');
        }

        $this->newLine();
        $this->info('✓ Analytics rate limits have been reset successfully!');
        $this->info('You can now fetch analytics data again.');

        return Command::SUCCESS;
    }

    /**
     * Clear rate limit for a specific property.
     */
    protected function clearPropertyRateLimit(string $propertyId): int
    {
        $this->info("Clearing rate limit for property: {$propertyId}");

        // Generate the rate limit key pattern used in SmartAnalyticsCache
        $pattern = "%analytics-fetch:{$propertyId}%";

        $driver = config('cache.default');

        if ($driver === 'database') {
            $deleted = DB::table('cache')
                ->where('key', 'like', $pattern)
                ->delete();

            if ($deleted > 0) {
                $this->info("Deleted {$deleted} rate limit cache entries for property {$propertyId}.");
                $this->newLine();
                $this->info("✓ Rate limit cleared for property {$propertyId}!");
            } else {
                $this->warn("No rate limit cache found for property {$propertyId}.");
            }
        } else {
            $this->warn('Non-database cache driver. Please use --all to clear all cache.');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
