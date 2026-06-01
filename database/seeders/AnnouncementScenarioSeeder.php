<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Idempotent scenario seeder for manual/browser verification of the Announcements feature.
 *
 * Creates one verified test user per role and a set of announcements that exercises every
 * visibility branch of Announcement::visibleTo(). All rows are prefixed "[TEST]" so they are
 * easy to recognise and remove later. Safe to run repeatedly (firstOrCreate).
 *
 * Run on the LOCAL dev database only:
 *   php artisan db:seed --class=AnnouncementScenarioSeeder
 */
class AnnouncementScenarioSeeder extends Seeder
{
    private const PASSWORD = 'windrunner';

    private const PREFIX = '[TEST] ';

    /** @var array<string, string> role => login email */
    private const TEST_USERS = [
        'master' => 'test-master@pmone.test',
        'admin' => 'test-admin@pmone.test',
        'staff' => 'test-staff@pmone.test',
        'writer' => 'test-writer@pmone.test',
        'user' => 'test-user@pmone.test',
        'exhibitor' => 'test-exhibitor@pmone.test',
    ];

    public function run(): void
    {
        $users = $this->seedUsers();
        $this->seedAnnouncements($users['user']);

        $this->command->newLine();
        $this->command->info('Announcement scenario data ready.');
        $this->command->info('Login password for every test user: "'.self::PASSWORD.'"');
        $this->command->table(
            ['Role', 'Email'],
            collect(self::TEST_USERS)->map(fn ($email, $role) => [$role, $email])->values()->all()
        );
    }

    /**
     * @return array<string, User>
     */
    private function seedUsers(): array
    {
        $created = [];

        foreach (self::TEST_USERS as $role => $email) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

            $user = User::withTrashed()->firstOrCreate(
                ['email' => $email],
                [
                    'ulid' => (string) Str::ulid(),
                    'name' => 'Test '.Str::title($role),
                    'username' => 'test.'.$role,
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                    'status' => 'active',
                    'visibility' => 'public',
                ]
            );

            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            $created[$role] = $user;
        }

        return $created;
    }

    private function seedAnnouncements(User $targetUser): void
    {
        // Global announcements - visible to everyone.
        $this->announcement('Scheduled maintenance this weekend', [
            'type' => 'info',
            'description' => 'The platform will be briefly unavailable on Saturday 02:00-04:00 WIB while we upgrade our servers.',
            'is_global' => true,
            'is_dismissible' => true,
            'order_column' => 1,
            'cta_actions' => [
                ['label' => 'Read details', 'url' => '/help', 'style' => 'button-outline', 'icon' => 'hugeicons:link-square-02'],
            ],
        ]);

        $this->announcement('Hotel reservations are now live', [
            'type' => 'marketing',
            'description' => 'Book accommodation for your next event directly from the dashboard.',
            'is_global' => true,
            'is_dismissible' => true,
            'order_column' => 2,
            'cta_actions' => [
                ['label' => 'Explore hotels', 'url' => '/projects', 'style' => 'button-primary', 'icon' => null],
            ],
        ]);

        // Global, NOT dismissible - the X button should be absent.
        $this->announcement('Action required: confirm your billing details', [
            'type' => 'error',
            'description' => 'Update your payment information before the end of the month to avoid interruption.',
            'is_global' => true,
            'is_dismissible' => false,
            'order_column' => 0,
        ]);

        // Role-targeted - only the matching role sees these.
        $roleTargets = [
            'admin' => ['type' => 'warning', 'desc' => 'Quarterly access review is due. Please audit your team members.'],
            'staff' => ['type' => 'info', 'desc' => 'New operational checklist has been published for event coordinators.'],
            'writer' => ['type' => 'success', 'desc' => 'The editorial calendar for next month is open for submissions.'],
            'user' => ['type' => 'info', 'desc' => 'Complete your profile to get the most out of your account.'],
            'exhibitor' => ['type' => 'marketing', 'desc' => 'Early-bird booth pricing ends soon. Reserve your spot today.'],
        ];

        foreach ($roleTargets as $role => $meta) {
            $this->announcement('For '.Str::title($role).' team only', [
                'type' => $meta['type'],
                'description' => $meta['desc'],
                'is_global' => false,
                'target_roles' => [$role],
                'is_dismissible' => true,
                'order_column' => 5,
            ]);
        }

        // User-targeted - only the specific test user sees this.
        $personal = $this->announcement('A personal message just for you', [
            'type' => 'success',
            'description' => 'Thanks for being an early tester of PM One. Here is a note targeted to your account specifically.',
            'is_global' => false,
            'is_dismissible' => true,
            'order_column' => 4,
        ]);
        $personal->users()->syncWithoutDetaching([$targetUser->id]);

        // Negative cases - none of these should appear on any dashboard.
        $this->announcement('Draft announcement (should be hidden)', [
            'type' => 'info',
            'description' => 'This is still a draft and must never appear on a dashboard.',
            'is_global' => true,
            'status' => 'draft',
        ]);

        $this->announcement('Archived announcement (should be hidden)', [
            'type' => 'info',
            'description' => 'This has been archived and must never appear on a dashboard.',
            'is_global' => true,
            'status' => 'archived',
        ]);

        $this->announcement('Scheduled for the future (should be hidden)', [
            'type' => 'info',
            'description' => 'This is published but its start_time is in the future, so it is not active yet.',
            'is_global' => true,
            'start_time' => Carbon::now()->addMonth(),
        ]);

        $this->announcement('Expired last week (should be hidden)', [
            'type' => 'info',
            'description' => 'This is published but its end_time has passed, so it is no longer active.',
            'is_global' => true,
            'end_time' => Carbon::now()->subWeek(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function announcement(string $title, array $attributes): Announcement
    {
        return Announcement::firstOrCreate(
            ['title' => self::PREFIX.$title],
            array_merge([
                'icon' => 'hugeicons:notification-02',
                'type' => 'info',
                'status' => 'published',
                'is_global' => true,
                'is_dismissible' => true,
                'order_column' => 0,
            ], $attributes)
        );
    }
}
