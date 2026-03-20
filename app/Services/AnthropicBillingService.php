<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicBillingService
{
    private const BASE_URL = 'https://api.anthropic.com/v1/organizations';

    private const CACHE_KEY = 'anthropic_billing_usage';

    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private string $adminApiKey,
        private float $totalCredits,
        private string $creditGrantDate,
    ) {}

    /**
     * Get credit usage from Anthropic's Usage Report API.
     *
     * @return array{total_credits: float, used_credits: float, remaining_credits: float, total_input_tokens: int, total_output_tokens: int}|null
     */
    public function getCreditUsage(): ?array
    {
        if (empty($this->adminApiKey)) {
            return null;
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->fetchUsageFromApi();
        });
    }

    /**
     * Fetch usage data from Anthropic's Usage Report API (hourly buckets).
     */
    private function fetchUsageFromApi(): ?array
    {
        $pricing = config('ai.pricing', []);

        $start = Carbon::parse($this->creditGrantDate, config('app.timezone', 'UTC'))
            ->utc()
            ->startOfDay();
        $end = Carbon::now('UTC')->startOfHour();

        if ($end->lte($start)) {
            $start = $end->copy()->subHours(2);
        }

        $totalCost = 0.0;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;

        // Process in 7-day chunks (168 hours max per request)
        $chunkStart = $start->copy();

        while ($chunkStart->lt($end)) {
            $chunkEnd = $chunkStart->copy()->addDays(7);
            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end->copy();
            }

            // Skip chunks where start == end
            if ($chunkStart->eq($chunkEnd)) {
                break;
            }

            $page = null;

            do {
                $query = [
                    'starting_at' => $chunkStart->toIso8601ZuluString(),
                    'ending_at' => $chunkEnd->toIso8601ZuluString(),
                    'bucket_width' => '1h',
                    'group_by[]' => 'model',
                    'limit' => 168,
                ];

                if ($page) {
                    $query['page'] = $page;
                }

                $response = Http::withHeaders([
                    'x-api-key' => $this->adminApiKey,
                    'anthropic-version' => '2023-06-01',
                ])->get(self::BASE_URL.'/usage_report/messages', $query);

                if ($response->failed()) {
                    Log::warning('Anthropic Usage Report API failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return null;
                }

                $data = $response->json();

                foreach ($data['data'] ?? [] as $bucket) {
                    foreach ($bucket['results'] ?? [] as $result) {
                        $inputTokens = ($result['uncached_input_tokens'] ?? 0)
                            + ($result['cache_read_input_tokens'] ?? 0);
                        $cacheCreationTokens = ($result['cache_creation']['ephemeral_1h_input_tokens'] ?? 0)
                            + ($result['cache_creation']['ephemeral_5m_input_tokens'] ?? 0);
                        $outputTokens = $result['output_tokens'] ?? 0;

                        $totalInputTokens += $inputTokens + $cacheCreationTokens;
                        $totalOutputTokens += $outputTokens;

                        $model = $result['model'] ?? null;
                        $modelPricing = $model ? ($pricing[$model] ?? null) : null;

                        if ($modelPricing) {
                            $totalCost += ($inputTokens / 1_000_000) * $modelPricing['input'];
                            $totalCost += ($cacheCreationTokens / 1_000_000) * ($modelPricing['cache_creation'] ?? $modelPricing['input'] * 1.25);
                            $totalCost += ($outputTokens / 1_000_000) * $modelPricing['output'];
                        }
                    }
                }

                $hasMore = $data['has_more'] ?? false;
                $page = $data['next_page'] ?? null;
            } while ($hasMore && $page);

            $chunkStart = $chunkEnd->copy();
        }

        return [
            'total_credits' => $this->totalCredits,
            'used_credits' => round($totalCost, 4),
            'remaining_credits' => round(max(0, $this->totalCredits - $totalCost), 4),
            'total_input_tokens' => $totalInputTokens,
            'total_output_tokens' => $totalOutputTokens,
        ];
    }

    /**
     * Clear the cached billing data.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
