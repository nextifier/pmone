<?php

use App\Models\User;
use App\Services\Shaders\SvgToSdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

const CIRCLE_SVG = '<?xml version="1.0" encoding="UTF-8"?><svg width="200" height="200" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><circle cx="100" cy="100" r="80" fill="black"/></svg>';

const SDF_DIR = 'shaders/sdf';

beforeEach(function () {
    Role::findOrCreate('master');
    Role::findOrCreate('admin');
    Role::findOrCreate('user');

    config(['shaders.sdf_disk' => 'sdf-test']);
    $this->disk = Storage::fake('sdf-test');
});

function admin(): User
{
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    return $admin;
}

function fakeLogo(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('logo.svg', CIRCLE_SVG);
}

function putFakeSdf(string $name): void
{
    Storage::disk('sdf-test')->put(SDF_DIR.'/'.$name, str_repeat('x', 524288));
}

it('converts an SVG to a 512x512 SDF stored on the configured disk for an admin', function () {
    $response = $this->actingAs(admin())->postJson(route('shaders.sdf.store'), [
        'file' => fakeLogo(),
    ]);

    $response->assertOk()
        ->assertJson(['bytes' => 524288])
        ->assertJsonStructure(['url', 'filename', 'bytes']);

    $files = $this->disk->allFiles(SDF_DIR);
    expect($files)->toHaveCount(1);
    expect($this->disk->size($files[0]))->toBe(524288);
});

it('lists stored SDF files newest first', function () {
    putFakeSdf('one.bin');
    putFakeSdf('two.bin');

    $this->actingAs(admin())->getJson(route('shaders.sdf.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['filename', 'url', 'bytes', 'modified_at']]]);
});

it('deletes a single SDF file', function () {
    putFakeSdf('one.bin');

    $this->actingAs(admin())->deleteJson(route('shaders.sdf.destroy', ['filename' => 'one.bin']))
        ->assertOk();

    $this->disk->assertMissing(SDF_DIR.'/one.bin');
});

it('bulk-deletes multiple SDF files', function () {
    putFakeSdf('one.bin');
    putFakeSdf('two.bin');
    putFakeSdf('three.bin');

    $this->actingAs(admin())->deleteJson(route('shaders.sdf.bulk-destroy'), [
        'filenames' => ['one.bin', 'two.bin'],
    ])->assertOk()->assertJson(['deleted' => 2]);

    $this->disk->assertMissing(SDF_DIR.'/one.bin');
    $this->disk->assertMissing(SDF_DIR.'/two.bin');
    $this->disk->assertExists(SDF_DIR.'/three.bin');
});

it('ignores path traversal in delete filenames', function () {
    $this->actingAs(admin())->deleteJson(route('shaders.sdf.bulk-destroy'), [
        'filenames' => ['../../secret.bin'],
    ])->assertOk()->assertJson(['deleted' => 0]);
});

it('publicly serves a stored SDF file through Laravel (for CORS)', function () {
    putFakeSdf('one.bin');

    $this->get(route('shaders.sdf.serve', ['filename' => 'one.bin']))
        ->assertOk()
        ->assertHeader('content-type', 'application/octet-stream');
});

it('returns 404 when serving a missing SDF file', function () {
    $this->get(route('shaders.sdf.serve', ['filename' => 'missing.bin']))->assertNotFound();
});

it('forbids users without the master/admin role', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    $this->actingAs($user)->postJson(route('shaders.sdf.store'), ['file' => fakeLogo()])->assertForbidden();
    $this->actingAs($user)->getJson(route('shaders.sdf.index'))->assertForbidden();
});

it('requires authentication', function () {
    $this->postJson(route('shaders.sdf.store'), ['file' => fakeLogo()])->assertUnauthorized();
});

it('generates a smooth signed distance field with the correct format', function () {
    $bin = (new SvgToSdfGenerator)->generate(CIRCLE_SVG, 'image/svg+xml');

    expect(strlen($bin))->toBe(524288);

    $u = unpack('v*', $bin); // 1-indexed
    $size = 512;
    $dec = fn (int $i): float => $u[$i + 1] / 32767.5 - 1;

    // Centre of the circle is inside (negative); the corner is outside (positive).
    expect($dec(256 * $size + 256))->toBeLessThan(0.0);
    expect($dec(0))->toBeGreaterThan(0.0);

    // Smoothness: mean |laplacian| over a central band stays low (official ~0.000127).
    $lapSum = 0.0;
    $lapCnt = 0;
    for ($y = 120; $y < 392; $y += 4) {
        for ($x = 120; $x < 392; $x += 4) {
            $lap = abs(
                4 * $dec($y * $size + $x)
                - $dec($y * $size + $x - 1)
                - $dec($y * $size + $x + 1)
                - $dec(($y - 1) * $size + $x)
                - $dec(($y + 1) * $size + $x)
            );
            $lapSum += $lap;
            $lapCnt++;
        }
    }
    expect($lapSum / $lapCnt)->toBeLessThan(0.001);
});
