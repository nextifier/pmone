<?php

use App\Enums\ContactFormStatus;
use App\Models\ContactFormSubmission;
use App\Models\GaProperty;
use App\Models\Project;
use App\Models\User;
use App\Services\GoogleAnalytics\AnalyticsService;
use App\Services\GoogleAnalytics\Period;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('analytics.view', 'web');

    $this->project = Project::factory()->create();
    $this->member = User::factory()->create(['email_verified_at' => now()]);
    $this->project->members()->attach($this->member);
});

it('returns analytics structure for a project member', function () {
    ContactFormSubmission::factory()
        ->count(3)
        ->create([
            'project_id' => $this->project->id,
            'created_at' => now()->subDays(2),
        ]);

    ContactFormSubmission::factory()->completed()->create([
        'project_id' => $this->project->id,
        'created_at' => now()->subDays(1),
    ]);

    actingAs($this->member);

    $response = getJson(route('dashboard.staff-analytics', ['project_id' => $this->project->id]))
        ->assertSuccessful();

    $data = $response->json('data');

    expect($data['inquiries_per_day'])->toHaveCount(7);
    expect($data['inquiries_per_day'][5])->toMatchArray(['date' => now()->subDays(1)->toDateString(), 'count' => 1]);
    expect($data['inquiries_per_day'][4])->toMatchArray(['date' => now()->subDays(2)->toDateString(), 'count' => 3]);

    expect($data['inquiries_by_status'])->toMatchArray([
        ContactFormStatus::New->value => 3,
        ContactFormStatus::InProgress->value => 0,
        ContactFormStatus::Completed->value => 1,
        ContactFormStatus::Archived->value => 0,
    ]);

    expect($data['visitors_per_month'])->toHaveCount(6);
    expect($data['sessions_per_month'])->toHaveCount(6);
    expect($data['visitors_per_month'][0])->toHaveKeys(['month', 'active_users']);
    expect($data['sessions_per_month'][0])->toHaveKeys(['month', 'sessions']);
});

it('returns zeroed GA metrics when the project has no active GA properties', function () {
    actingAs($this->member);

    $data = getJson(route('dashboard.staff-analytics', ['project_id' => $this->project->id]))
        ->assertSuccessful()
        ->json('data');

    expect(array_column($data['visitors_per_month'], 'active_users'))->each->toBe(0);
    expect(array_column($data['sessions_per_month'], 'sessions'))->each->toBe(0);
});

it('rolls up GA daily rows into the six most recent months', function () {
    $gaProperty = GaProperty::factory()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);

    $thisMonth = now()->startOfMonth()->format('Y-m');
    $lastMonth = now()->subMonth()->startOfMonth()->format('Y-m');

    $this->mock(AnalyticsService::class, function ($mock) use ($gaProperty, $thisMonth, $lastMonth) {
        $mock->shouldReceive('createPeriodFromDates')->andReturn(
            Period::create(now()->subMonths(5)->startOfMonth(), now()->endOfMonth())
        );
        $mock->shouldReceive('getAggregatedAnalytics')->andReturn([
            'property_breakdown' => [[
                'property_id' => $gaProperty->property_id,
                'rows' => [
                    ['date' => $thisMonth.'-01', 'activeUsers' => 50, 'sessions' => 90],
                    ['date' => $thisMonth.'-15', 'activeUsers' => 25, 'sessions' => 40],
                    ['date' => $lastMonth.'-10', 'activeUsers' => 10, 'sessions' => 20],
                ],
            ]],
        ]);
    });

    actingAs($this->member);

    $data = getJson(route('dashboard.staff-analytics', ['project_id' => $this->project->id]))
        ->assertSuccessful()
        ->json('data');

    $visitorsByMonth = collect($data['visitors_per_month'])->keyBy('month');
    $sessionsByMonth = collect($data['sessions_per_month'])->keyBy('month');

    expect($visitorsByMonth[$thisMonth]['active_users'])->toBe(75);
    expect($visitorsByMonth[$lastMonth]['active_users'])->toBe(10);
    expect($sessionsByMonth[$thisMonth]['sessions'])->toBe(130);
    expect($sessionsByMonth[$lastMonth]['sessions'])->toBe(20);
});

it('forbids non-members without the analytics.view permission', function () {
    $outsider = User::factory()->create(['email_verified_at' => now()]);

    actingAs($outsider);

    getJson(route('dashboard.staff-analytics', ['project_id' => $this->project->id]))
        ->assertForbidden();
});

it('allows non-members that have the analytics.view permission', function () {
    Permission::findOrCreate('analytics.view', 'web');

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->givePermissionTo('analytics.view');

    actingAs($admin);

    getJson(route('dashboard.staff-analytics', ['project_id' => $this->project->id]))
        ->assertSuccessful();
});

it('requires a project_id query parameter', function () {
    actingAs($this->member);

    getJson(route('dashboard.staff-analytics'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['project_id']);
});

it('returns 404 when project_id does not exist', function () {
    actingAs($this->member);

    getJson(route('dashboard.staff-analytics', ['project_id' => 999999]))
        ->assertNotFound();
});
