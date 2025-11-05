<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Console\Command;

class GenerateProfileShortLinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shortlinks:generate-profiles {--force : Force regeneration even if short links already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate short links for all existing users and projects';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating short links for user and project profiles...');
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $force = $this->option('force');

        // Generate short links for users
        $this->info('Processing users...');
        $users = User::all();
        $userCreated = 0;
        $userSkipped = 0;

        foreach ($users as $user) {
            $exists = ShortLink::where('user_id', $user->id)
                ->where('slug', $user->username)
                ->exists();

            if ($exists && ! $force) {
                $userSkipped++;

                continue;
            }

            if ($exists && $force) {
                ShortLink::where('user_id', $user->id)
                    ->where('slug', $user->username)
                    ->delete();
            }

            $profileUrl = rtrim($frontendUrl, '/').'/users/'.$user->username;

            ShortLink::create([
                'user_id' => $user->id,
                'slug' => $user->username,
                'destination_url' => $profileUrl,
                'is_active' => true,
            ]);

            $userCreated++;
        }

        $this->info("Users: {$userCreated} short links created, {$userSkipped} skipped");

        // Generate short links for projects
        $this->info('Processing projects...');
        $projects = Project::all();
        $projectCreated = 0;
        $projectSkipped = 0;

        foreach ($projects as $project) {
            $exists = ShortLink::where('slug', $project->username)
                ->exists();

            if ($exists && ! $force) {
                $projectSkipped++;

                continue;
            }

            if ($exists && $force) {
                ShortLink::where('slug', $project->username)
                    ->delete();
            }

            $profileUrl = rtrim($frontendUrl, '/').'/projects/'.$project->username;

            ShortLink::create([
                'user_id' => $project->created_by ?? 1, // Fallback to user ID 1 if no creator
                'slug' => $project->username,
                'destination_url' => $profileUrl,
                'is_active' => true,
            ]);

            $projectCreated++;
        }

        $this->info("Projects: {$projectCreated} short links created, {$projectSkipped} skipped");

        $this->newLine();
        $this->info('✓ Short link generation completed!');
        $this->info('Total: '.($userCreated + $projectCreated).' short links created, '.($userSkipped + $projectSkipped).' skipped');

        return Command::SUCCESS;
    }
}
