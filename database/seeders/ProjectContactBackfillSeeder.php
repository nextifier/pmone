<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Gap-fills project contact data (email + WhatsApp links) so the event websites
 * can source email/whatsapp/whatsappMarketing entirely from PM One instead of
 * their hardcoded app.config.ts `contact` object.
 *
 * Most projects already have the correct `email` + "WhatsApp Sales"/"WhatsApp
 * Marketing" links from their initial setup, so this only patches the two
 * projects that were still missing pieces:
 *  - askindo (iicc website): had WhatsApp links but an empty email.
 *  - globalaiexpo: had no WhatsApp links yet.
 *
 * Idempotent: email is only set when currently blank; a WhatsApp link is only
 * created when no link with that label (case-insensitive) already exists.
 * Run manually: php artisan db:seed --class=ProjectContactBackfillSeeder
 */
class ProjectContactBackfillSeeder extends Seeder
{
    /**
     * @return array<string, array{email?: string, links?: array<int, array{label: string, url: string}>}>
     */
    private function data(): array
    {
        return [
            'askindo' => [
                'email' => 'iicc@askindo.id',
            ],
            'globalaiexpo' => [
                'links' => [
                    ['label' => 'WhatsApp Sales', 'url' => 'https://wa.me/6287883653918'],
                    ['label' => 'WhatsApp Marketing', 'url' => 'https://wa.me/6287883653918'],
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach ($this->data() as $username => $patch) {
            $project = Project::where('username', $username)->first();

            if (! $project) {
                $this->command->warn("Project '{$username}' not found, skipping.");

                continue;
            }

            if (! empty($patch['email']) && blank($project->email)) {
                $project->email = $patch['email'];
                $project->save();
                $this->command->info("'{$username}': email set to {$patch['email']}.");
            }

            $existing = $project->links()
                ->get()
                ->map(fn ($link) => strtolower((string) $link->label))
                ->all();

            $order = (int) $project->links()->max('order');
            $created = 0;

            foreach ($patch['links'] ?? [] as $link) {
                if (in_array(strtolower($link['label']), $existing, true)) {
                    continue;
                }

                $project->links()->create([
                    'label' => $link['label'],
                    'url' => $link['url'],
                    'order' => ++$order,
                    'is_active' => true,
                ]);
                $created++;
            }

            if ($created > 0) {
                $this->command->info("'{$username}': {$created} WhatsApp link(s) added.");
            }
        }
    }
}
