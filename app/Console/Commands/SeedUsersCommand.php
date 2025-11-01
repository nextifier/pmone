<?php

namespace App\Console\Commands;

use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;

class SeedUsersCommand extends Command
{
    protected $signature = 'seed:users
                            {--total=100 : Total number of users to create}
                            {--masters= : Number of master users (auto-calculated if not provided)}
                            {--admins= : Number of admin users (auto-calculated if not provided)}
                            {--staff= : Number of staff users (auto-calculated if not provided)}
                            {--fresh : Fresh start - truncate users table first}';

    protected $description = 'Seed users with customizable quantities for each role';

    public function handle(): int
    {
        $total = (int) $this->option('total');
        $fresh = $this->option('fresh');

        // Auto-calculate defaults if not provided
        $masters = $this->option('masters');
        $admins = $this->option('admins');
        $staff = $this->option('staff');

        if ($masters === null || $admins === null || $staff === null) {
            // Let the seeder handle auto-calculation
            $masters = $masters ? (int) $masters : null;
            $admins = $admins ? (int) $admins : null;
            $staff = $staff ? (int) $staff : null;
        } else {
            // Convert to integers if all are provided
            $masters = (int) $masters;
            $admins = (int) $admins;
            $staff = (int) $staff;

            // Only validate if all values are explicitly provided
            if (($masters + $admins + $staff) > $total) {
                $this->error('Sum of masters, admins, and staff cannot exceed total users');

                return self::FAILURE;
            }
        }

        // Validate input
        if ($total <= 0) {
            $this->error('Total must be greater than 0');

            return self::FAILURE;
        }

        // Confirm action
        if ($fresh) {
            if (! $this->confirm('This will delete ALL existing users. Are you sure?')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info('🚀 Starting user seeding process...');
        $this->newLine();

        // Fresh start if requested
        if ($fresh) {
            $this->warn('🗑️  Truncating users table...');

            // Database agnostic truncation
            \DB::table('model_has_roles')->delete();
            \DB::table('users')->delete();

            $this->info('✅ Users table cleared');
            $this->newLine();
        }

        // Run seeder with options
        $seeder = new UserSeeder;
        $seeder->setCommand($this);
        $seeder->run();

        $this->newLine();
        $this->info('🎉 User seeding completed successfully!');

        return self::SUCCESS;
    }
}
