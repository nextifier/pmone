<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.whatsapp.token', 'TEST_TOKEN');
    config()->set('services.whatsapp.phone_number_id', '123456789');

    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('master');
});

it('sends a test message for an authorized admin', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.T']]], 200)]);

    $this->actingAs($this->admin)
        ->postJson('/api/system/whatsapp/test', [
            'to' => '08123456789',
            'template' => 'hello_world',
            'lang' => 'en_US',
        ])
        ->assertSuccessful()
        ->assertJsonPath('to', '628123456789');

    Http::assertSent(
        fn ($request) => $request['to'] === '628123456789'
            && $request['template']['name'] === 'hello_world',
    );
});

it('forbids users without the master or admin role', function () {
    Http::fake();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    $this->actingAs($user)
        ->postJson('/api/system/whatsapp/test', ['to' => '08123456789', 'template' => 'hello_world'])
        ->assertForbidden();

    Http::assertNothingSent();
});

it('returns 422 when whatsapp is not configured', function () {
    config()->set('services.whatsapp.token', null);

    $this->actingAs($this->admin)
        ->postJson('/api/system/whatsapp/test', ['to' => '08123456789', 'template' => 'hello_world'])
        ->assertStatus(422);
});

it('surfaces the whatsapp api error as a 422', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template not found']], 400)]);

    $this->actingAs($this->admin)
        ->postJson('/api/system/whatsapp/test', [
            'to' => '08123456789',
            'template' => 'ticket_confirmation',
            'params' => ['A', 'B', 'C', 'D'],
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.message', 'Template not found');
});

it('validates the required fields', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/system/whatsapp/test', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['to', 'template']);
});
