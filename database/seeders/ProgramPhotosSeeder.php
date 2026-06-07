<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Program;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Attaches images to image-variant programs seeded by ProgramsSeeder.
 *
 * Photos are stored locally in `database/seeders/program-photos/{username}/{order}.{ext}`
 * (committed to git, copied from the pmone-events apps' public dirs), so production
 * seeding does not require external CDN access at run time. The filename's numeric
 * stem matches the program's `order_column`.
 *
 * Idempotent: skips programs that already have media in the `image` collection.
 *
 * Run with: php artisan db:seed --class=ProgramPhotosSeeder
 */
class ProgramPhotosSeeder extends Seeder
{
    private string $photosRoot;

    public function run(): void
    {
        $this->photosRoot = database_path('seeders/program-photos');

        if (! is_dir($this->photosRoot)) {
            $this->command?->error("Photos folder not found: {$this->photosRoot}");

            return;
        }

        foreach (scandir($this->photosRoot) as $username) {
            $dir = "{$this->photosRoot}/{$username}";

            if ($username === '.' || $username === '..' || ! is_dir($dir)) {
                continue;
            }

            $event = $this->resolveEvent($username);

            if (! $event) {
                $this->command?->warn("Skipping '{$username}': no project/event found.");

                continue;
            }

            $attached = 0;
            foreach ($this->imageFiles($dir) as $order => $file) {
                $program = Program::where('event_id', $event->id)
                    ->where('order_column', $order)
                    ->first();

                if (! $program || $program->getMedia('image')->isNotEmpty()) {
                    continue;
                }

                $this->safeAddFromFile($program, $file, "{$username}/".basename($file));
                $attached++;
            }

            $this->command?->info("Attached {$attached} image(s) for {$username} ({$event->slug}).");
        }
    }

    private function resolveEvent(string $username): ?Event
    {
        $project = Project::where('username', $username)->first();

        if (! $project) {
            return null;
        }

        return $project->events()->where('is_active', true)->first()
            ?? $project->events()->orderByDesc('start_date')->first();
    }

    /**
     * Map files keyed by their numeric stem (= program order_column).
     *
     * @return array<int, string>
     */
    private function imageFiles(string $dir): array
    {
        $out = [];

        foreach (scandir($dir) as $file) {
            if (! preg_match('/^(\d+)\.(jpe?g|png|webp)$/i', $file, $m)) {
                continue;
            }

            $out[(int) $m[1]] = "{$dir}/{$file}";
        }

        ksort($out);

        return $out;
    }

    private function safeAddFromFile(Program $program, string $path, string $label): void
    {
        try {
            $program->addMedia($path)
                ->preservingOriginal()
                ->toMediaCollection('image');
        } catch (\Throwable $e) {
            $this->command?->warn("  ! Failed to attach {$label}: ".$e->getMessage());
        }
    }
}
