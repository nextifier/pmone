<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Console\Command;

class SyncProfileShortLinks extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'shortlinks:sync-profiles
                            {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Sync all user and project short links with current frontend URL';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $frontendUrl = config('app.frontend_url', config('app.url'));

        $this->info('Syncing profile short links...');
        $this->info("Frontend URL: {$frontendUrl}");
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $userCount = 0;
        $projectCount = 0;

        // Sync user profile short links
        $this->info('Processing user profiles...');
        $users = User::all();

        foreach ($users as $user) {
            $expectedUrl = rtrim($frontendUrl, '/').'/users/'.$user->username;
            $shortLink = ShortLink::where('user_id', $user->id)
                ->where('slug', $user->username)
                ->first();

            if ($shortLink && $shortLink->destination_url !== $expectedUrl) {
                $this->line("  User: {$user->username}");
                $this->line("    Old: {$shortLink->destination_url}");
                $this->line("    New: {$expectedUrl}");

                if (!$dryRun) {
                    $shortLink->update(['destination_url' => $expectedUrl]);
                }

                $userCount++;
            }
        }

        $this->info("Updated {$userCount} user short links");
        $this->newLine();

        // Sync project profile short links
        $this->info('Processing projects...');
        $projects = Project::all();

        foreach ($projects as $project) {
            $expectedUrl = rtrim($frontendUrl, '/').'/projects/'.$project->username;
            $shortLink = ShortLink::where('slug', $project->username)
                ->whereHas('user')
                ->first();

            if ($shortLink && $shortLink->destination_url !== $expectedUrl) {
                $this->line("  Project: {$project->username}");
                $this->line("    Old: {$shortLink->destination_url}");
                $this->line("    New: {$expectedUrl}");

                if (!$dryRun) {
                    $shortLink->update(['destination_url' => $expectedUrl]);
                }

                $projectCount++;
            }
        }

        $this->info("Updated {$projectCount} project short links");
        $this->newLine();

        $total = $userCount + $projectCount;

        if ($dryRun) {
            $this->warn("DRY RUN: {$total} short links would be updated");
            $this->info('Run without --dry-run to apply changes');
        } else {
            $this->components->info("Successfully synced {$total} profile short links!");
        }

        return self::SUCCESS;
    }
}
