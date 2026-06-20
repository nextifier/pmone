<?php

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'app_settings.update', 'guard_name' => 'web']);
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);
});

/** Stage a real (committed) audio file into a tmp-upload folder for the move flow. */
function stageTmpScanSound(string $folder): void
{
    $audio = file_get_contents(database_path('seeders/scan-sounds/success.mp3'));
    Storage::disk('local')->put("tmp/uploads/{$folder}/sound.mp3", $audio);
    Storage::disk('local')->put("tmp/uploads/{$folder}/metadata.json", json_encode([
        'original_name' => 'sound.mp3',
    ]));
}

it('returns scan_sounds to any authenticated user (no permission gate on read)', function () {
    AppSetting::set('scan_sounds', ['success_url' => null, 'failed_url' => null, 'enabled' => true]);

    $reader = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($reader)
        ->getJson('/api/app-settings/scan_sounds')
        ->assertOk()
        ->assertJsonPath('value.enabled', true);
});

it('forbids updating scan_sounds without app_settings.update', function () {
    $reader = User::factory()->create(['email_verified_at' => now()]);
    Role::firstOrCreate(['name' => 'reader', 'guard_name' => 'web'])->syncPermissions([]);
    $reader->assignRole('reader');

    $this->actingAs($reader)
        ->putJson('/api/app-settings/scan_sounds', ['value' => ['enabled' => false]])
        ->assertStatus(403);
});

it('moves tmp success + failed sounds into their media collections', function () {
    stageTmpScanSound('tmp-snd-ok');
    stageTmpScanSound('tmp-snd-bad');

    $this->putJson('/api/app-settings/scan_sounds', [
        'value' => ['enabled' => true],
        'tmp_success' => 'tmp-snd-ok',
        'tmp_failed' => 'tmp-snd-bad',
    ])->assertOk();

    $setting = AppSetting::query()->where('key', 'scan_sounds')->first();

    expect($setting->getFirstMedia('scan_success_sound'))->not->toBeNull();
    expect($setting->getFirstMedia('scan_failed_sound'))->not->toBeNull();
    expect($setting->value['success_url'])->not->toBeNull();
    expect($setting->value['failed_url'])->not->toBeNull();
    expect(Storage::disk('local')->exists('tmp/uploads/tmp-snd-ok'))->toBeFalse();
    expect(Storage::disk('local')->exists('tmp/uploads/tmp-snd-bad'))->toBeFalse();
});

it('clears a scan sound when its delete flag is set', function () {
    stageTmpScanSound('tmp-snd-del');

    $this->putJson('/api/app-settings/scan_sounds', [
        'value' => ['enabled' => true],
        'tmp_success' => 'tmp-snd-del',
    ])->assertOk();

    $this->putJson('/api/app-settings/scan_sounds', [
        'value' => ['enabled' => true],
        'delete_success' => true,
    ])->assertOk()
        ->assertJsonPath('value.success_url', null);

    $setting = AppSetting::query()->where('key', 'scan_sounds')->first();

    expect($setting->getFirstMedia('scan_success_sound'))->toBeNull();
});
