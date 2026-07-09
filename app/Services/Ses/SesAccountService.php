<?php

namespace App\Services\Ses;

use Aws\Ses\SesClient;
use Aws\SesV2\SesV2Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Reads the parts of SES that only AWS knows: the live sending quota and the
 * account-level suppression list. Everything else the dashboard shows comes
 * from our own tables, which are cheaper and never rate limited.
 */
class SesAccountService
{
    private const CACHE_TTL_SECONDS = 60;

    /**
     * Live quota and account standing.
     *
     * @return array{
     *     max_24_hour_send: float,
     *     sent_last_24_hours: float,
     *     max_send_rate: float,
     *     production_access: bool,
     *     enforcement_status: ?string,
     *     available: bool,
     * }
     */
    public function quota(): array
    {
        return Cache::remember('ses:quota', self::CACHE_TTL_SECONDS, function (): array {
            try {
                $account = $this->sesV2()->getAccount();
                $sendQuota = $account['SendQuota'] ?? [];

                return [
                    'max_24_hour_send' => (float) ($sendQuota['Max24HourSend'] ?? 0),
                    'sent_last_24_hours' => (float) ($sendQuota['SentLast24Hours'] ?? 0),
                    'max_send_rate' => (float) ($sendQuota['MaxSendRate'] ?? 0),
                    'production_access' => (bool) ($account['ProductionAccessEnabled'] ?? false),
                    'enforcement_status' => $account['EnforcementStatus'] ?? null,
                    'available' => true,
                ];
            } catch (\Throwable $e) {
                Log::warning('Could not read the SES account quota.', ['error' => $e->getMessage()]);

                return [
                    'max_24_hour_send' => 0.0,
                    'sent_last_24_hours' => 0.0,
                    'max_send_rate' => 0.0,
                    'production_access' => false,
                    'enforcement_status' => null,
                    'available' => false,
                ];
            }
        });
    }

    /**
     * SES keeps roughly two weeks of 15-minute buckets. They are folded into
     * days here because nobody reads a 15-minute bucket.
     *
     * @return list<array{date: string, sends: int, bounces: int, complaints: int, rejects: int}>
     */
    public function dailyStatistics(): array
    {
        return Cache::remember('ses:daily-statistics', self::CACHE_TTL_SECONDS, function (): array {
            try {
                $points = $this->sesV1()->getSendStatistics()['SendDataPoints'] ?? [];
            } catch (\Throwable $e) {
                Log::warning('Could not read SES send statistics.', ['error' => $e->getMessage()]);

                return [];
            }

            $byDay = [];

            foreach ($points as $point) {
                $day = Carbon::instance($point['Timestamp'])->toDateString();

                $byDay[$day] ??= ['date' => $day, 'sends' => 0, 'bounces' => 0, 'complaints' => 0, 'rejects' => 0];
                $byDay[$day]['sends'] += (int) ($point['DeliveryAttempts'] ?? 0);
                $byDay[$day]['bounces'] += (int) ($point['Bounces'] ?? 0);
                $byDay[$day]['complaints'] += (int) ($point['Complaints'] ?? 0);
                $byDay[$day]['rejects'] += (int) ($point['Rejects'] ?? 0);
            }

            ksort($byDay);

            return array_values($byDay);
        });
    }

    private function sesV2(): SesV2Client
    {
        return new SesV2Client($this->clientConfig());
    }

    private function sesV1(): SesClient
    {
        return new SesClient($this->clientConfig());
    }

    /**
     * @return array<string, mixed>
     */
    private function clientConfig(): array
    {
        $config = [
            'version' => 'latest',
            'region' => config('services.ses.region'),
        ];

        $key = config('services.ses.key');
        $secret = config('services.ses.secret');

        // Falling back to the default provider chain lets this work on an
        // instance profile without hard-coded keys.
        if (! empty($key) && ! empty($secret)) {
            $config['credentials'] = ['key' => $key, 'secret' => $secret];
        }

        return $config;
    }
}
