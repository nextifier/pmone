<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            $this->command->info('No user found, skipping TaskSeeder');

            return;
        }

        // Create various tasks for testing
        Task::factory()
            ->count(3)
            ->todo()
            ->public()
            ->create(['created_by' => $user->id]);

        Task::factory()
            ->count(2)
            ->inProgress()
            ->highPriority()
            ->create(['created_by' => $user->id]);

        Task::factory()
            ->count(2)
            ->completed()
            ->create(['created_by' => $user->id]);

        Task::factory()
            ->count(1)
            ->overdue()
            ->highPriority()
            ->create(['created_by' => $user->id]);

        Task::factory()
            ->count(2)
            ->private()
            ->mediumPriority()
            ->create(['created_by' => $user->id]);

        // Create some archived tasks
        Task::factory()
            ->count(1)
            ->archived()
            ->create(['created_by' => $user->id]);

        // Soft delete a few tasks for trash testing
        $tasksToDelete = Task::factory()
            ->count(2)
            ->create(['created_by' => $user->id]);

        foreach ($tasksToDelete as $task) {
            $task->delete();
        }

        $this->command->info('Created ' . Task::count() . ' tasks + ' . Task::onlyTrashed()->count() . ' trashed tasks');
    }
}
