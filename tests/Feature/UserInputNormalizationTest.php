<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User Input Normalization', function () {
    test('name is normalized to title case on model assignment', function () {
        $user = new User;

        // Test lowercase input
        $user->name = 'antonius richardo';
        expect($user->name)->toBe('Antonius Richardo');

        // Test uppercase input
        $user->name = 'ANTONIUS RICHARDO';
        expect($user->name)->toBe('Antonius Richardo');

        // Test mixed case input
        $user->name = 'aNtOnIuS rIcHaRdO';
        expect($user->name)->toBe('Antonius Richardo');

        // Test input with extra spaces
        $user->name = '  antonius richardo  ';
        expect($user->name)->toBe('Antonius Richardo');
    });

    test('email is normalized to lowercase on model assignment', function () {
        $user = new User;

        // Test mixed case email
        $user->email = 'Antonius@panoramamedia.co.id';
        expect($user->email)->toBe('antonius@panoramamedia.co.id');

        // Test uppercase email
        $user->email = 'ANTONIUS@PANORAMAMEDIA.CO.ID';
        expect($user->email)->toBe('antonius@panoramamedia.co.id');

        // Test email with extra spaces
        $user->email = '  Antonius@PanoramaMEDIA.co.id  ';
        expect($user->email)->toBe('antonius@panoramamedia.co.id');
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

    test('normalization works through mass assignment', function () {
        $user = User::factory()->create();

        $user->fill([
            'name' => 'mass assignment test',
            'email' => 'MASS@ASSIGNMENT.TEST',
        ]);

        expect($user->name)->toBe('Mass Assignment Test');
        expect($user->email)->toBe('mass@assignment.test');
    });
});
