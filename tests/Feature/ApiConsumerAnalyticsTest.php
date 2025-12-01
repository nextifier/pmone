<?php

use App\Models\ApiConsumer;
use App\Models\ApiConsumerRequest;
use App\Models\User;
use App\Services\ApiConsumerAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

describe('ApiConsumerAnalyticsService', function () {
    it('parses days correctly within bounds', function () {
        $service = new ApiConsumerAnalyticsService;

        expect($service->parseDays(7))->toBe(7)
            ->and($service->parseDays(0))->toBe(1)
            ->and($service->parseDays(-5))->toBe(1)
            ->and($service->parseDays(100))->toBe(90)
            ->and($service->parseDays(90))->toBe(90);
    });

    it('calculates summary statistics correctly', function () {
        $service = new ApiConsumerAnalyticsService;
        $consumer = ApiConsumer::factory()->create();
        $startDate = now()->subDays(7)->startOfDay();

        // Create 5 successful requests
        ApiConsumerRequest::factory()
            ->count(5)
            ->successful()
            ->for($consumer, 'apiConsumer')
            ->create(['created_at' => now()->subDay()]);

        // Create 2 client error requests
        ApiConsumerRequest::factory()
            ->count(2)
            ->clientError()
            ->for($consumer, 'apiConsumer')
            ->create(['created_at' => now()->subDay()]);

        // Create 1 server error request
        ApiConsumerRequest::factory()
            ->serverError()
            ->for($consumer, 'apiConsumer')
            ->create(['created_at' => now()->subDay()]);

        $summary = $service->getSummary($consumer->id, $startDate);

        expect($summary['total_requests'])->toBe(8)
            ->and($summary['successful_requests'])->toBe(5)
            ->and($summary['failed_requests'])->toBe(3)
            ->and($summary['success_rate'])->toBe(62.5);
    });

    it('calculates status distribution correctly', function () {
        $service = new ApiConsumerAnalyticsService;
        $consumer = ApiConsumer::factory()->create();
        $startDate = now()->subDays(7)->startOfDay();

        ApiConsumerRequest::factory()
            ->count(3)
            ->for($consumer, 'apiConsumer')
            ->create(['status_code' => 200, 'created_at' => now()->subDay()]);

        ApiConsumerRequest::factory()
            ->count(2)
            ->for($consumer, 'apiConsumer')
            ->create(['status_code' => 404, 'created_at' => now()->subDay()]);

        ApiConsumerRequest::factory()
            ->for($consumer, 'apiConsumer')
            ->create(['status_code' => 500, 'created_at' => now()->subDay()]);

        $distribution = $service->getStatusDistribution($consumer->id, $startDate);

        expect($distribution['2xx'])->toBe(3)
            ->and($distribution['4xx'])->toBe(2)
            ->and($distribution['5xx'])->toBe(1)
            ->and($distribution['3xx'])->toBe(0);
    });

    it('gets top consumers without N+1 queries', function () {
        $service = new ApiConsumerAnalyticsService;
        $startDate = now()->subDays(7)->startOfDay();

        $consumers = ApiConsumer::factory()->count(3)->create();

        foreach ($consumers as $index => $consumer) {
            ApiConsumerRequest::factory()
                ->count(($index + 1) * 5)
                ->for($consumer, 'apiConsumer')
                ->create(['created_at' => now()->subDay()]);
        }

        $topConsumers = $service->getTopConsumers($startDate, 5);

        expect($topConsumers)->toHaveCount(3)
            ->and($topConsumers->first()['request_count'])->toBe(15)
            ->and($topConsumers->first()['name'])->not->toBe('Unknown');
    });
});

describe('Overall Analytics Endpoint', function () {
    it('returns overall analytics data for admin users', function () {
        $consumer = ApiConsumer::factory()->create();

        ApiConsumerRequest::factory()
            ->count(10)
            ->successful()
            ->for($consumer, 'apiConsumer')
            ->create(['created_at' => now()->subDay()]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/api-consumers/analytics?days=7');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'period' => ['days', 'start_date', 'end_date'],
                    'summary' => [
                        'total_requests',
                        'successful_requests',
                        'failed_requests',
                        'success_rate',
                        'avg_response_time',
                        'total_consumers',
                        'active_consumers',
                        'consumers_with_requests',
                    ],
                    'requests_per_day',
                    'top_consumers',
                    'status_distribution',
                ],
            ]);
    });

    it('returns correct status distribution format', function () {
        $consumer = ApiConsumer::factory()->create();

        ApiConsumerRequest::factory()
            ->for($consumer, 'apiConsumer')
            ->create(['status_code' => 200, 'created_at' => now()->subDay()]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/api-consumers/analytics');

        $response->assertSuccessful()
            ->assertJsonPath('data.status_distribution.2xx', 1)
            ->assertJsonPath('data.status_distribution.3xx', 0)
            ->assertJsonPath('data.status_distribution.4xx', 0)
            ->assertJsonPath('data.status_distribution.5xx', 0);
    });

    it('denies access to non-admin users', function () {
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser, 'sanctum')
            ->getJson('/api/api-consumers/analytics');

        $response->assertForbidden();
    });

    it('denies access to unauthenticated users', function () {
        $response = $this->getJson('/api/api-consumers/analytics');

        $response->assertUnauthorized();
    });
});

describe('Consumer Analytics Endpoint', function () {
    it('returns analytics for a specific consumer', function () {
        $consumer = ApiConsumer::factory()->create();

        ApiConsumerRequest::factory()
            ->count(5)
            ->successful()
            ->for($consumer, 'apiConsumer')
            ->create(['created_at' => now()->subDay()]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/api-consumers/{$consumer->id}/analytics");

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'consumer',
                    'period' => ['days', 'start_date', 'end_date'],
                    'summary' => [
                        'total_requests',
                        'successful_requests',
                        'failed_requests',
                        'success_rate',
                        'avg_response_time',
                        'max_response_time',
                        'min_response_time',
                    ],
                    'requests_per_day',
                    'top_endpoints',
                    'status_distribution',
                    'hourly_distribution',
                ],
            ]);
    });

    it('respects days parameter', function () {
        $consumer = ApiConsumer::factory()->create();

        // Request within 3 days
        ApiConsumerRequest::factory()
            ->for($consumer, 'apiConsumer')
            ->create(['created_at' => now()->subDays(2)]);

        // Request outside 3 days
        ApiConsumerRequest::factory()
            ->for($consumer, 'apiConsumer')
            ->create(['created_at' => now()->subDays(5)]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/api-consumers/{$consumer->id}/analytics?days=3");

        $response->assertSuccessful()
            ->assertJsonPath('data.summary.total_requests', 1)
            ->assertJsonPath('data.period.days', 3);
    });

    it('limits days parameter to maximum 90', function () {
        $consumer = ApiConsumer::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/api-consumers/{$consumer->id}/analytics?days=365");

        $response->assertSuccessful()
            ->assertJsonPath('data.period.days', 90);
    });

    it('returns empty data when no requests exist', function () {
        $consumer = ApiConsumer::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/api-consumers/{$consumer->id}/analytics");

        $response->assertSuccessful()
            ->assertJsonPath('data.summary.total_requests', 0)
            ->assertJsonPath('data.summary.success_rate', 0)
            ->assertJsonPath('data.status_distribution.2xx', 0);
    });
});
