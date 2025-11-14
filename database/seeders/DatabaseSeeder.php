<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            UserSeeder::class,
            ProjectSeeder::class,
            GaPropertySeeder::class,
            LinkSeeder::class,
            ShortLinkSeeder::class,
            CategorySeeder::class,
            PostSeeder::class,
        ]);
    }
}
