<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create Master Admin
        $master = User::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Super Admin',
            'username' => 'super.admin',
            'email' => 'master@pmone.id',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone' => '+62812-3456-7890',
            'birth_date' => '1990-01-15',
            'gender' => 'male',
            'bio' => 'Master administrator of PM One platform. Responsible for overall system management and strategic decisions.',
            'links' => [
                'website' => 'https://panoramamedia.co.id',
                'linkedin' => 'https://linkedin.com/in/super-admin',
                'twitter' => 'https://twitter.com/pmone_admin',
            ],
            'user_settings' => [
                'theme' => 'dark',
                'language' => 'id',
                'timezone' => 'Asia/Jakarta',
                'email_notifications' => true,
                'push_notifications' => true,
                'two_factor_enabled' => false,
            ],
            'status' => 'active',
            'visibility' => 'public',
            'last_seen' => now(),
        ]);
        $master->assignRole('master');

        // Create Admin Users
        $admin1 = User::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Admin Manager',
            'username' => 'admin.manager',
            'email' => 'admin@pmone.id',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone' => '+62821-9876-5432',
            'birth_date' => '1985-06-20',
            'gender' => 'female',
            'bio' => 'Administrative manager responsible for user management, event coordination, and operational oversight.',
            'links' => [
                'website' => 'https://panoramamedia.co.id',
                'linkedin' => 'https://linkedin.com/in/admin-manager',
                'instagram' => 'https://instagram.com/pmone_admin',
            ],
            'user_settings' => [
                'theme' => 'light',
                'language' => 'id',
                'timezone' => 'Asia/Jakarta',
                'email_notifications' => true,
                'push_notifications' => true,
            ],
            'status' => 'active',
            'visibility' => 'public',
            'last_seen' => now()->subMinutes(5),
        ]);
        $admin1->assignRole('admin');

        $admin2 = User::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Event Admin',
            'username' => 'event.admin',
            'email' => 'eventadmin@pmone.id',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone' => '+62813-5555-7777',
            'birth_date' => '1988-03-10',
            'gender' => 'male',
            'bio' => 'Event administrator specializing in exhibition management and exhibitor relations.',
            'links' => [
                'website' => 'https://panoramaevents.id',
                'linkedin' => 'https://linkedin.com/in/event-admin',
            ],
            'user_settings' => [
                'theme' => 'auto',
                'language' => 'id',
                'timezone' => 'Asia/Jakarta',
                'email_notifications' => true,
                'push_notifications' => false,
            ],
            'status' => 'active',
            'visibility' => 'public',
            'last_seen' => now()->subHours(1),
        ]);
        $admin2->assignRole('admin');

        // Create Staff Users
        $staff1 = User::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Event Coordinator',
            'username' => 'event.coordinator',
            'email' => 'coordinator@pmone.id',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone' => '+62816-7777-8888',
            'birth_date' => '1995-04-22',
            'gender' => 'female',
            'bio' => 'Event coordinator responsible for logistics, vendor management, and on-site operations.',
            'links' => [
                'linkedin' => 'https://linkedin.com/in/event-coordinator',
                'instagram' => 'https://instagram.com/event_coord',
            ],
            'user_settings' => [
                'theme' => 'light',
                'language' => 'id',
                'timezone' => 'Asia/Jakarta',
                'email_notifications' => true,
                'push_notifications' => true,
            ],
            'status' => 'active',
            'visibility' => 'public',
            'last_seen' => now()->subMinutes(10),
        ]);
        $staff1->assignRole('staff');

        $staff2 = User::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Operations Staff',
            'username' => 'operations.staff',
            'email' => 'operations@pmone.id',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone' => '+62817-9999-0000',
            'birth_date' => '1993-07-14',
            'gender' => 'male',
            'bio' => 'Operations staff handling day-to-day activities and exhibitor support services.',
            'links' => [
                'linkedin' => 'https://linkedin.com/in/operations-staff',
            ],
            'user_settings' => [
                'theme' => 'auto',
                'language' => 'id',
                'timezone' => 'Asia/Jakarta',
                'email_notifications' => true,
                'push_notifications' => false,
            ],
            'status' => 'active',
            'visibility' => 'public',
            'last_seen' => now()->subHours(3),
        ]);
        $staff2->assignRole('staff');

        $this->command->info('✅ Created 5 users with different roles:');
        $this->command->line('   - 1 Master Admin (master@pmone.id)');
        $this->command->line('   - 2 Admin Users (admin@pmone.id, eventadmin@pmone.id)');
        $this->command->line('   - 2 Staff (coordinator@pmone.id, operations@pmone.id)');
        $this->command->line('   - Default password for all: "password"');
    }
}
