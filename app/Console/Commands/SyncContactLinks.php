<?php

namespace App\Console\Commands;

use App\Helpers\LinkSyncHelper;
use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;

class SyncContactLinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'links:sync-contacts {--users : Sync only users} {--projects : Sync only projects}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Email and WhatsApp links for all existing users and projects';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $syncUsers = $this->option('users') || (! $this->option('users') && ! $this->option('projects'));
        $syncProjects = $this->option('projects') || (! $this->option('users') && ! $this->option('projects'));

        if ($syncUsers) {
            $this->info('Syncing contact links for users...');
            $this->syncUsers();
        }

        if ($syncProjects) {
            $this->info('Syncing contact links for projects...');
            $this->syncProjects();
        }

        $this->newLine();
        $this->info('✓ Contact links synced successfully!');

        return Command::SUCCESS;
    }

    /**
     * Sync contact links for all users
     */
    protected function syncUsers(): void
    {
        $users = User::all();
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        $synced = 0;
        $skipped = 0;

        foreach ($users as $user) {
            // Only sync if user has email or phone
            if ($user->email || $user->phone) {
                LinkSyncHelper::syncUserContactLinks($user);
                $synced++;
            } else {
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Users synced: {$synced}");
        if ($skipped > 0) {
            $this->comment("  Users skipped (no email/phone): {$skipped}");
        }
    }

    /**
     * Sync contact links for all projects
     */
    protected function syncProjects(): void
    {
        $projects = Project::all();
        $bar = $this->output->createProgressBar($projects->count());
        $bar->start();

        $synced = 0;
        $skipped = 0;

        foreach ($projects as $project) {
            // Only sync if project has email or phones
            if ($project->email || ($project->phone && is_array($project->phone) && count($project->phone) > 0)) {
                LinkSyncHelper::syncProjectContactLinks($project);
                $synced++;
            } else {
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Projects synced: {$synced}");
        if ($skipped > 0) {
            $this->comment("  Projects skipped (no email/phones): {$skipped}");
        }
    }
}
