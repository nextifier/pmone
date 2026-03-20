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

    private const CACHE_TTL = 120; // 2 minutes

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
     * Fetch usage using daily buckets for past days + minute buckets for today.
     */
    private function fetchUsageFromApi(): ?array
    {
        $pricing = config('ai.pricing', []);

        $grantStart = Carbon::parse($this->creditGrantDate, config('app.timezone', 'UTC'))
            ->utc()
            ->startOfDay();
        $now = Carbon::now('UTC');
        $todayStart = $now->copy()->startOfDay();

        $totalCost = 0.0;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;

        // 1) Past complete days: use 1d buckets (max 31 per request)
        if ($grantStart->lt($todayStart)) {
            $result = $this->fetchBuckets(
                $grantStart->toIso8601ZuluString(),
                $todayStart->toIso8601ZuluString(),
                '1d',
                31,
                $pricing,
            );

            if ($result === null) {
                return null;
            }

            $totalCost += $result['cost'];
            $totalInputTokens += $result['input_tokens'];
            $totalOutputTokens += $result['output_tokens'];
        }

        // 2) Current day: use 1m buckets for near-real-time data (max 1440 per request)
        $minuteEnd = $now->copy()->startOfMinute();
        $minuteStart = $todayStart->lt($grantStart) ? $grantStart : $todayStart;

        if ($minuteStart->lt($minuteEnd)) {
            $result = $this->fetchBuckets(
                $minuteStart->toIso8601ZuluString(),
                $minuteEnd->toIso8601ZuluString(),
                '1m',
                1440,
                $pricing,
            );

            if ($result === null) {
                return null;
            }

            $totalCost += $result['cost'];
            $totalInputTokens += $result['input_tokens'];
            $totalOutputTokens += $result['output_tokens'];
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
     * Fetch and aggregate usage buckets from the API.
     *
     * @return array{cost: float, input_tokens: int, output_tokens: int}|null
     */
    private function fetchBuckets(string $startingAt, string $endingAt, string $bucketWidth, int $limit, array $pricing): ?array
    {
        $totalCost = 0.0;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $page = null;

        do {
            $query = [
                'starting_at' => $startingAt,
                'ending_at' => $endingAt,
                'bucket_width' => $bucketWidth,
                'group_by[]' => 'model',
                'limit' => $limit,
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

        return [
            'cost' => $totalCost,
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
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
