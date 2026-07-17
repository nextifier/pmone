<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User Input Normalization', function () {
    test('single-case names are normalized to title case on save', function () {
        $lowercase = User::factory()->create(['name' => 'antonius richardo']);
        expect($lowercase->name)->toBe('Antonius Richardo');

        $uppercase = User::factory()->create(['name' => 'ANTONIUS RICHARDO']);
        expect($uppercase->name)->toBe('Antonius Richardo');

        $padded = User::factory()->create(['name' => '  antonius   richardo  ']);
        expect($padded->name)->toBe('Antonius Richardo');
    });

    test('intentional mixed-case names are preserved on save', function () {
        $user = User::factory()->create(['name' => 'Alistair McDonald']);

        expect($user->name)->toBe('Alistair McDonald');
    });

    test('email is normalized to lowercase on save', function () {
        $mixed = User::factory()->create(['email' => 'Antonius@panoramamedia.co.id']);
        expect($mixed->email)->toBe('antonius@panoramamedia.co.id');

        $padded = User::factory()->create(['email' => '  ANTONIUS2@PANORAMAMEDIA.CO.ID  ']);
        expect($padded->email)->toBe('antonius2@panoramamedia.co.id');
    });

    test('normalization works during user creation', function () {
        $user = User::create([
            'name' => 'antonius richardo',
            'email' => 'Antonius@panoramamedia.co.id',
            'password' => 'password123',
            'status' => 'active',
            'visibility' => 'public',
        ]);

        expect($user->name)->toBe('Antonius Richardo');
        expect($user->email)->toBe('antonius@panoramamedia.co.id');
    });

    test('normalization works during user update', function () {
        $user = User::factory()->create([
            'name' => 'original name',
            'email' => 'original@email.com',
        ]);

        $user->update([
            'name' => 'ANTONIUS RICHARDO',
            'email' => 'ANTONIUS@PANORAMAMEDIA.CO.ID',
        ]);

        $user->refresh();
        expect($user->name)->toBe('Antonius Richardo');
        expect($user->email)->toBe('antonius@panoramamedia.co.id');
    });

    test('normalization works through mass assignment once saved', function () {
        $user = User::factory()->create();

        $user->fill([
            'name' => 'mass assignment test',
            'email' => 'MASS@ASSIGNMENT.TEST',
        ]);
        $user->save();

        expect($user->name)->toBe('Mass Assignment Test');
        expect($user->email)->toBe('mass@assignment.test');
    });
});
