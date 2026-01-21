<?php

namespace App\Jobs;

use App\Models\ExchangeRate;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchExchangeRates implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [60, 300, 900];

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $baseCurrency = 'USD',
    ) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return "fetch-exchange-rates-{$this->baseCurrency}";
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $apiUrl = config('services.exchange_rate.api_url');

        Log::info('Fetching exchange rates', [
            'base_currency' => $this->baseCurrency,
            'api_url' => $apiUrl,
        ]);

        try {
            $response = Http::timeout(30)->get($apiUrl);

            if (! $response->successful()) {
                Log::error('Exchange rate API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException("API request failed with status: {$response->status()}");
            }

            $data = $response->json();

            if (! isset($data['rates']) || ! is_array($data['rates'])) {
                throw new \RuntimeException('Invalid API response: rates not found');
            }

            // Parse the API timestamp
            $apiUpdatedAt = isset($data['time_last_updated'])
                ? \Carbon\Carbon::createFromTimestamp($data['time_last_updated'])
                : null;

            // Create new exchange rate record
            ExchangeRate::create([
                'base_currency' => $data['base'] ?? $this->baseCurrency,
                'rates' => $data['rates'],
                'api_updated_at' => $apiUpdatedAt,
                'fetched_at' => now(),
            ]);

            // Clean up old records (keep only last 10)
            $this->cleanupOldRecords();

            Log::info('Exchange rates fetched successfully', [
                'base_currency' => $data['base'] ?? $this->baseCurrency,
                'rates_count' => count($data['rates']),
                'api_date' => $data['date'] ?? null,
            ]);

        } catch (Throwable $e) {
            Log::error('Failed to fetch exchange rates', [
                'error' => $e->getMessage(),
                'base_currency' => $this->baseCurrency,
            ]);

            throw $e;
        }
    }

    /**
     * Clean up old exchange rate records, keeping only the most recent ones.
     */
    private function cleanupOldRecords(int $keepCount = 10): void
    {
        $oldRecords = ExchangeRate::forCurrency($this->baseCurrency)
            ->orderByDesc('fetched_at')
            ->skip($keepCount)
            ->take(100)
            ->get();

        foreach ($oldRecords as $record) {
            $record->delete();
        }

        if ($oldRecords->count() > 0) {
            Log::info('Cleaned up old exchange rate records', [
                'deleted_count' => $oldRecords->count(),
                'base_currency' => $this->baseCurrency,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('FetchExchangeRates job failed permanently', [
            'base_currency' => $this->baseCurrency,
            'error' => $exception->getMessage(),
        ]);
    }
}
