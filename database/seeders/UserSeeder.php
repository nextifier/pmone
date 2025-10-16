<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    private const DEFAULT_TOTAL_ITEMS = 80;

    private const DEFAULT_PASSWORD = 'password';

    private const MASTER_USERNAME = 'super.admin';

    private const MASTER_EMAIL = 'master@pmone.id';

    private const AVATAR_COUNT = 144;

    private const CHANCE_USER_HAVING_AVATAR = 80;

    public function run(): void
    {
        $totalUsers = $this->getOptionValue('total', self::DEFAULT_TOTAL_ITEMS);
        $defaultCounts = $this->calculateDefaultRoleCounts($totalUsers);

        $masterUsers = $this->getOptionValue('masters', $defaultCounts['masters']);
        $adminUsers = $this->getOptionValue('admins', $defaultCounts['admins']);
        $staffUsers = $this->getOptionValue('staff', $defaultCounts['staff']);

        $regularUsers = max(0, $totalUsers - $masterUsers - $adminUsers - $staffUsers);

        $this->command->info("Creating $totalUsers users with the following distribution:");
        $this->command->line("   - Masters: $masterUsers");
        $this->command->line("   - Admins: $adminUsers");
        $this->command->line("   - Staff: $staffUsers");
        $this->command->line("   - Regular Users: $regularUsers");
        $this->command->newLine();

        $createdUsers = [];

        // Create users by role
        $roles = [
            'master' => $masterUsers,
            'admin' => $adminUsers,
            'staff' => $staffUsers,
            'user' => $regularUsers,
        ];

        foreach ($roles as $role => $count) {
            if ($count > 0) {
                $createdUsers[$role] = $this->createUsersForRole($role, $count);
            }
        }

        $this->command->newLine();
        $this->command->info("✅ Successfully created $totalUsers users!");

        // Display created users summary
        $this->displayUserSummary($createdUsers);
    }

    private function createUsersForRole(string $role, int $count): array
    {
        $this->command->info('Creating '.ucfirst($role).' Users...');
        $bar = $this->command->getOutput()->createProgressBar($count);
        $users = [];

        for ($i = 0; $i < $count; $i++) {
            $user = $this->createUser($role, $role === 'master' && $i === 0);
            $user->assignRole($role);

            if ($role === 'master') {
                $user->autoVerifyIfPrivileged();
            }

            $users[] = $user;
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();

        return $users;
    }

    private function createUser(string $role, bool $isMainMaster = false): User
    {
        $faker = fake('id_ID');

        // Special data for main master user
        if ($isMainMaster) {
            // Check if master user already exists
            $existingMaster = User::where('username', self::MASTER_USERNAME)
                ->orWhere('email', self::MASTER_EMAIL)
                ->first();

            if ($existingMaster) {
                // Ensure master role is assigned
                if (! $existingMaster->hasRole('master')) {
                    $existingMaster->assignRole('master');
                }
                // Add profile image if not exists
                if (! $existingMaster->hasMedia('profile_image')) {
                    $this->attachRandomProfileImage($existingMaster, $faker);
                }

                return $existingMaster;
            }

            $masterUser = User::create([
                'ulid' => (string) Str::ulid(),
                'name' => 'Super Admin',
                'username' => self::MASTER_USERNAME,
                'email' => self::MASTER_EMAIL,
                'email_verified_at' => now(),
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'phone' => '+62812-3456-7890',
                'birth_date' => '1990-01-15',
                'gender' => 'male',
                'bio' => 'Master administrator of PM One platform. Responsible for overall system management and strategic decisions.',
                'links' => [
                    ['label' => 'Website', 'url' => 'https://panoramamedia.co.id'],
                    ['label' => 'LinkedIn', 'url' => 'https://linkedin.com/in/super-admin'],
                    ['label' => 'X', 'url' => 'https://twitter.com/pmone_admin'],
                ],
                'user_settings' => [
                    'theme' => 'dark',
                    'language' => 'id',
                    'timezone' => 'Asia/Jakarta',
                    'email_notifications' => true,
                    'push_notifications' => true,
                ],
                'status' => 'active',
                'visibility' => 'public',
                'last_seen' => now(),
            ]);

            // Add profile image to master user
            $this->attachRandomProfileImage($masterUser, $faker);

            return $masterUser;
        }

        // Generate random user data
        $name = $faker->name();
        $username = $this->generateUniqueUsername($name);
        $themes = ['light', 'dark', 'auto'];
        $languages = ['id', 'en'];
        $genders = ['male', 'female', 'other'];
        $statuses = ['active', 'inactive'];
        $visibilities = ['public', 'private'];

        // Role-specific data
        $roleSpecificData = $this->getRoleSpecificData($role, $faker);

        // Generate optional links
        $links = $this->generateUserLinks($faker, $name, $username);

        $user = User::create([
            'ulid' => (string) Str::ulid(),
            'name' => $name,
            'username' => $username,
            'email' => $faker->unique()->safeEmail(),
            'email_verified_at' => $this->shouldAutoVerify($role, $faker) ? now() : null,
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'phone' => $faker->boolean(70) ? $faker->phoneNumber() : null,
            'birth_date' => $faker->boolean(60) ? $faker->date('Y-m-d', '-18 years') : null,
            'gender' => $faker->boolean(80) ? $faker->randomElement($genders) : null,
            'bio' => $faker->boolean(40) ? $faker->realText($faker->numberBetween(50, 200)) : null,
            'links' => ! empty($links) ? $links : null,
            'user_settings' => [
                'theme' => $faker->randomElement($themes),
                'language' => $faker->randomElement($languages),
                'timezone' => 'Asia/Jakarta',
                'email_notifications' => $faker->boolean(80),
                'push_notifications' => $faker->boolean(60),
            ],
            'more_details' => $roleSpecificData,
            'status' => $faker->randomElement($statuses),
            'visibility' => $faker->randomElement($visibilities),
            'last_seen' => $faker->boolean(70) ? $faker->dateTimeBetween('-1 week') : null,
        ]);

        // Attach random profile image using media library
        $this->attachRandomProfileImage($user, $faker);

        return $user;
    }

    private function generateUniqueUsername(string $name): string
    {
        $baseUsername = Str::slug(strtolower($name), '.');
        $username = $baseUsername;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $baseUsername.'.'.$counter;
            $counter++;
        }

        return $username;
    }

    private function generateUserLinks($faker, string $name, string $username): array
    {
        $links = [];
        $linkOptions = [
            ['label' => 'Website', 'chance' => 30, 'url' => $faker->url()],
            ['label' => 'LinkedIn', 'chance' => 50, 'url' => 'https://linkedin.com/in/'.Str::slug($name)],
            ['label' => 'Instagram', 'chance' => 40, 'url' => 'https://instagram.com/'.$username],
            ['label' => 'X', 'chance' => 25, 'url' => 'https://twitter.com/'.$username],
            ['label' => 'GitHub', 'chance' => 20, 'url' => 'https://github.com/'.$username],
            ['label' => 'YouTube', 'chance' => 15, 'url' => 'https://youtube.com/@'.$username],
        ];

        foreach ($linkOptions as $linkOption) {
            if ($faker->boolean($linkOption['chance'])) {
                $links[] = [
                    'label' => $linkOption['label'],
                    'url' => $linkOption['url'],
                ];
            }
        }

        return $links;
    }

    private function attachRandomProfileImage(User $user, $faker): void
    {
        if ($faker->boolean(self::CHANCE_USER_HAVING_AVATAR)) {
            $avatarNumber = $faker->numberBetween(1, self::AVATAR_COUNT);
            $avatarPath = public_path("dummy-avatars/avatar-{$avatarNumber}.jpg");

            // Copy to temp file to avoid moving original
            $tempPath = tempnam(sys_get_temp_dir(), 'avatar_').'.jpg';
            copy($avatarPath, $tempPath);

            $user->addMedia($tempPath)
                ->toMediaCollection('profile_image');
        }
    }

    private function shouldAutoVerify(string $role, $faker): bool
    {
        // Master and Admin users are always verified
        if (in_array($role, ['master', 'admin'])) {
            return true;
        }

        // Staff users have 95% chance to be verified
        if ($role === 'staff') {
            return $faker->boolean(95);
        }

        // Regular users have 85% chance to be verified (default)
        return $faker->boolean(85);
    }

    private function getRoleSpecificData(string $role, $faker): array
    {
        switch ($role) {
            case 'master':
                return [
                    'department' => 'Management',
                    'position' => 'CEO/CTO',
                    'access_level' => 'full',
                    'responsibilities' => ['System Administration', 'Strategic Planning', 'Team Management'],
                ];

            case 'admin':
                $departments = ['Operations', 'Events', 'Marketing', 'HR', 'Finance'];
                $positions = ['Manager', 'Senior Administrator', 'Department Head'];

                return [
                    'department' => $faker->randomElement($departments),
                    'position' => $faker->randomElement($positions),
                    'access_level' => 'administrative',
                    'responsibilities' => $faker->randomElements([
                        'User Management', 'Event Coordination', 'Content Management',
                        'Reporting', 'Vendor Relations', 'Quality Control',
                    ], $faker->numberBetween(2, 4)),
                ];

            case 'staff':
                $departments = ['Operations', 'Events', 'Customer Support', 'Logistics', 'Technical'];
                $positions = ['Coordinator', 'Specialist', 'Assistant', 'Officer'];

                return [
                    'department' => $faker->randomElement($departments),
                    'position' => $faker->randomElement($positions),
                    'access_level' => 'operational',
                    'responsibilities' => $faker->randomElements([
                        'Event Support', 'Customer Service', 'Data Entry',
                        'Logistics Support', 'Technical Support', 'Documentation',
                    ], $faker->numberBetween(1, 3)),
                ];

            default: // user
                return [
                    'user_type' => $faker->randomElement(['exhibitor', 'visitor', 'partner', 'vendor']),
                    'industry' => $faker->randomElement([
                        'Technology', 'Healthcare', 'Education', 'Retail', 'Manufacturing',
                        'Finance', 'Real Estate', 'Food & Beverage', 'Automotive', 'Tourism',
                    ]),
                    'company_size' => $faker->randomElement(['1-10', '11-50', '51-200', '201-1000', '1000+']),
                    'interests' => $faker->randomElements([
                        'Networking', 'Business Development', 'Innovation', 'Technology Trends',
                        'Market Research', 'Partnership Opportunities', 'Product Showcase',
                    ], $faker->numberBetween(1, 4)),
                ];
        }
    }

    private function displayUserSummary(array $createdUsers): void
    {
        $this->command->table(
            ['Role', 'Count', 'Sample Users (Email)'],
            collect($createdUsers)->map(function ($users, $role) {
                $sampleEmails = collect($users)->take(3)->pluck('email')->join(', ');
                $count = count($users);

                return [
                    ucfirst($role),
                    $count,
                    $sampleEmails.($count > 3 ? '...' : ''),
                ];
            })->values()->toArray()
        );

        $this->command->newLine();
        $this->command->info('Default password for all users: "'.self::DEFAULT_PASSWORD.'"');

        if (isset($createdUsers['master'])) {
            $masterEmail = $createdUsers['master'][0]->email ?? self::MASTER_EMAIL;
            $this->command->info("Main master user: $masterEmail");
        }
    }

    private function getOptionValue(string $optionName, int $defaultValue): int
    {
        if ($this->command->hasOption($optionName) && $this->command->option($optionName)) {
            return (int) $this->command->option($optionName);
        }

        return $defaultValue;
    }

    private function calculateDefaultRoleCounts(int $totalUsers): array
    {
        if ($totalUsers <= 5) {
            return [
                'masters' => 1,
                'admins' => min(1, $totalUsers - 1),
                'staff' => 0,
            ];
        }

        if ($totalUsers <= 20) {
            $admins = min(3, $totalUsers - 1);

            return [
                'masters' => 1,
                'admins' => $admins,
                'staff' => min(5, max(0, $totalUsers - 1 - $admins)),
            ];
        }

        return [
            'masters' => 1,
            'admins' => 5,
            'staff' => min(20, max(0, $totalUsers - 6)),
        ];
    }
}
