<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdminCommand extends Command
{
    protected $signature = 'pmone:create-super-admin
                          {--name= : The name of the super admin}
                          {--email= : The email of the super admin}
                          {--password= : The password of the super admin}';

    protected $description = 'Create a super admin user for PM One';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Super admin name');
        $email = $this->option('email') ?: $this->ask('Super admin email');
        $password = $this->option('password') ?: $this->secret('Super admin password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->line('  '.$error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $user->assignRole('master');

        $this->info('Super admin created successfully!');
        $this->table(['Field', 'Value'], [
            ['Name', $user->name],
            ['Email', $user->email],
            ['Role', 'master'],
        ]);

        return self::SUCCESS;
    }
}
