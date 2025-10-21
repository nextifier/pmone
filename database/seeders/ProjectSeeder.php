<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProjectSeeder extends Seeder
{
    private array $projects = [
        [
            'name' => 'Panorama Media',
            'username' => 'pm',
            'profile_image' => 'dummy/projects/pm.jpg',
            'cover_image' => '',
            'email' => 'hello@panoramamedia.co.id',
            'bio' => '',
            'settings' => [],
            'more_details' => [],
            'status' => 'active',
            'visibility' => 'public',
        ],
        [
            'name' => 'Panorama Events',
            'username' => 'pe',
            'profile_image' => 'dummy/projects/pe.jpg',
            'cover_image' => '',
            'email' => 'events@panoramamedia.co.id',
            'bio' => '',
            'settings' => [],
            'more_details' => [],
            'status' => 'active',
            'visibility' => 'public',
        ],
        [
            'name' => 'Panorama Live',
            'username' => 'pl',
            'profile_image' => 'dummy/projects/pl.jpg',
            'cover_image' => '',
            'email' => 'live@panoramamedia.co.id',
            'bio' => '',
            'settings' => [],
            'more_details' => [],
            'status' => 'active',
            'visibility' => 'public',
        ],
        [
            'name' => 'Megabuild Indonesia',
            'username' => 'megabuild',
            'profile_image' => 'dummy/projects/megabuild.jpg',
            'cover_image' => '',
            'email' => 'megabuild@panoramamedia.co.id',
            'bio' => '',
            'settings' => [],
            'more_details' => [],
            'status' => 'active',
            'visibility' => 'public',
        ],
        [
            'name' => 'Keramika Indonesia',
            'username' => 'keramika',
            'profile_image' => 'dummy/projects/keramika.jpg',
            'cover_image' => '',
            'email' => 'keramika@panoramamedia.co.id',
            'bio' => '',
            'settings' => [],
            'more_details' => [],
            'status' => 'active',
            'visibility' => 'public',
        ],
        [
            'name' => 'Franchise & License Expo Indonesia',
            'username' => 'flei',
            'profile_image' => 'dummy/projects/flei.jpg',
            'cover_image' => '',
            'email' => 'fleiexpo@panoramamedia.co.id',
            'bio' => '',
            'settings' => [],
            'more_details' => [],
            'status' => 'active',
            'visibility' => 'public',
        ],
        [
            'name' => 'Cafe & Brasserie Expo',
            'username' => 'cbe',
            'profile_image' => 'dummy/projects/cbe.jpg',
            'cover_image' => '',
            'email' => 'cafe@panoramamedia.co.id',
            'bio' => '',
            'settings' => [],
            'more_details' => [],
            'status' => 'active',
            'visibility' => 'public',
        ],
        [
            'name' => 'Indonesia Coffee Festival',
            'username' => 'icf',
            'profile_image' => 'dummy/projects/icf.jpg',
            'cover_image' => '',
            'email' => 'cafe@panoramamedia.co.id',
            'bio' => '',
            'settings' => [],
            'more_details' => [],
            'status' => 'active',
            'visibility' => 'public',
        ],
        [
            'name' => 'Cokelat Expo Indonesia',
            'username' => 'cei',
            'profile_image' => 'dummy/projects/cei.jpg',
            'cover_image' => '',
            'email' => 'cafe@panoramamedia.co.id',
            'bio' => '',
            'settings' => [],
            'more_details' => [],
            'status' => 'active',
            'visibility' => 'public',
        ],
        [
            'name' => 'More Food Expo',
            'username' => 'morefood',
            'profile_image' => 'dummy/projects/morefood.jpg',
            'cover_image' => '',
            'email' => 'morefood@panoramamedia.co.id',
            'bio' => '',
            'settings' => [],
            'more_details' => [],
            'status' => 'active',
            'visibility' => 'public',
        ],
        [
            'name' => 'Renovation Expo',
            'username' => 'renex',
            'profile_image' => 'dummy/projects/renex.jpg',
            'cover_image' => '',
            'email' => 'megabuild@panoramamedia.co.id',
            'bio' => '',
            'settings' => [],
            'more_details' => [],
            'status' => 'active',
            'visibility' => 'public',
        ],
        [
            'name' => 'Indonesia Outing Expo',
            'username' => 'ioe',
            'profile_image' => 'dummy/projects/ioe.jpg',
            'cover_image' => '',
            'email' => 'ioe@panoramamedia.co.id',
            'bio' => '',
            'settings' => [],
            'more_details' => [],
            'status' => 'active',
            'visibility' => 'public',
        ],
        [
            'name' => 'Indonesia Comic Con',
            'username' => 'icc',
            'profile_image' => 'dummy/projects/icc.jpg',
            'cover_image' => '',
            'email' => 'icc@panoramamedia.co.id',
            'bio' => '',
            'settings' => [],
            'more_details' => [],
            'status' => 'active',
            'visibility' => 'public',
        ],
        [
            'name' => 'Indonesia Anime Con',
            'username' => 'inacon',
            'profile_image' => 'dummy/projects/inacon.jpg',
            'cover_image' => '',
            'email' => 'inacon@panoramamedia.co.id',
            'bio' => '',
            'settings' => [],
            'more_details' => [],
            'status' => 'active',
            'visibility' => 'public',
        ],
        [
            'name' => 'Indonesia Cleaning Expo',
            'username' => 'ice',
            'profile_image' => 'dummy/projects/cleaning-expo.jpg',
            'cover_image' => '',
            'email' => 'ice@panoramamedia.co.id',
            'bio' => '',
            'settings' => [],
            'more_details' => [],
            'status' => 'draft',
            'visibility' => 'public',
        ],
    ];

    public function run(): void
    {
        $this->command->info('Creating projects...');

        // Get users with appropriate roles to be project creators/members
        $eligibleUsers = User::role(['master', 'admin', 'staff'])->get();

        if ($eligibleUsers->isEmpty()) {
            $this->command->warn('No eligible users found to create projects. Skipping...');

            return;
        }

        // Get master user as the default creator
        $creator = User::role('master')->first();

        $projectsCount = count($this->projects);
        $bar = $this->command->getOutput()->createProgressBar($projectsCount);

        foreach ($this->projects as $projectData) {
            $project = $this->createProject($creator, $projectData);

            // Assign random members (2-5 members)
            $membersCount = fake()->numberBetween(2, 5);
            $members = $eligibleUsers->random(min($membersCount, $eligibleUsers->count()));
            $project->members()->attach($members->pluck('id'));

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("✅ Successfully created $projectsCount projects!");
    }

    private function createProject(User $creator, array $data): Project
    {
        $project = Project::create([
            'ulid' => (string) Str::ulid(),
            'name' => $data['name'],
            'username' => $data['username'],
            'bio' => $data['bio'] ?: null,
            'settings' => $data['settings'] ?: [],
            'more_details' => $data['more_details'] ?: [],
            'status' => $data['status'],
            'visibility' => $data['visibility'],
            'email' => $data['email'] ?: null,
            'phone' => null,
            'created_by' => $creator->id,
        ]);

        // Add profile image if exists
        if (! empty($data['profile_image'])) {
            $imagePath = public_path($data['profile_image']);
            if (file_exists($imagePath)) {
                $project->addMedia($imagePath)
                    ->preservingOriginal()
                    ->toMediaCollection('profile_image');
            }
        }

        // Add cover image if exists
        if (! empty($data['cover_image'])) {
            $imagePath = public_path($data['cover_image']);
            if (file_exists($imagePath)) {
                $project->addMedia($imagePath)
                    ->preservingOriginal()
                    ->toMediaCollection('cover_image');
            }
        }

        return $project;
    }
}
