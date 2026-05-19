<?php

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($this->user);
});

it('logs activity when an AppSetting is created via set()', function () {
    AppSetting::set('site_name', 'PM One');

    expect(Activity::query()
        ->where('subject_type', AppSetting::class)
        ->where('event', 'created')
        ->exists()
    )->toBeTrue();
});

it('logs activity when an AppSetting value changes', function () {
    $setting = AppSetting::set('feature_flag', ['enabled' => false]);
    $setting->update(['value' => ['enabled' => true]]);

    $activity = Activity::query()
        ->where('subject_type', $setting->getMorphClass())
        ->where('subject_id', $setting->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['attributes']['value'] ?? null)->toBe(['enabled' => true]);
});
