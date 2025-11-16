<?php

namespace Database\Seeders;

use App\Models\ContactFormSubmission;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContactFormSubmissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating contact form submissions...');

        $projects = Project::all();

        if ($projects->isEmpty()) {
            $this->command->warn('No projects found. Please run ProjectSeeder first!');

            return;
        }

        $users = User::all();
        $submissionsCount = 30;
        $bar = $this->command->getOutput()->createProgressBar($submissionsCount);

        for ($i = 0; $i < $submissionsCount; $i++) {
            $this->createSubmission($projects, $users);
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("✅ Successfully created $submissionsCount contact form submissions!");
    }

    private function createSubmission($projects, $users): void
    {
        $project = $projects->random();

        // Randomly determine submission state
        $states = ['new', 'in_progress', 'completed', 'new', 'new'];
        $state = fake()->randomElement($states);

        // Create submission with appropriate state
        $submission = match ($state) {
            'in_progress' => ContactFormSubmission::factory()->inProgress()->create([
                'project_id' => $project->id,
            ]),
            'completed' => ContactFormSubmission::factory()->completed()->create([
                'project_id' => $project->id,
            ]),
            default => ContactFormSubmission::factory()->create([
                'project_id' => $project->id,
            ]),
        };

        // Randomly add brand name field (30% chance)
        if (fake()->boolean(30)) {
            $formData = $submission->form_data;
            $formData['brand_name'] = fake()->company();
            $submission->update(['form_data' => $formData]);
        }

        // Randomly add product category field (25% chance)
        if (fake()->boolean(25)) {
            $formData = $submission->form_data;
            $formData['product_category'] = fake()->randomElement([
                'Electronics',
                'Fashion',
                'Food & Beverage',
                'Services',
                'Technology',
            ]);
            $submission->update(['form_data' => $formData]);
        }

        // For completed submissions, assign a follow-up user
        if ($state === 'completed' && $users->isNotEmpty()) {
            $submission->update([
                'followed_up_by' => $users->random()->id,
            ]);
        }
    }
}
