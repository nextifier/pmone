<?php

namespace Database\Seeders;

use App\Helpers\LinkSyncHelper;
use App\Models\Link;
use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectContactSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->data() as $row) {
            $project = Project::where('username', $row['username'])->first();

            if (! $project) {
                $this->command->warn("✗ Skip: project '{$row['username']}' not found");

                continue;
            }

            $project->update(['phone' => $row['phones']]);

            LinkSyncHelper::syncProjectContactLinks($project->fresh());

            foreach ($row['links'] as $order => $link) {
                Link::updateOrCreate(
                    [
                        'linkable_type' => Project::class,
                        'linkable_id' => $project->id,
                        'label' => $link['label'],
                    ],
                    [
                        'url' => $link['url'],
                        'order' => $order,
                        'is_active' => true,
                    ],
                );
            }

            $this->command->info("✓ {$project->username}");
        }
    }

    /**
     * @return list<array{username: string, phones: list<array{label: string, number: string}>, links: list<array{label: string, url: string}>}>
     */
    private function data(): array
    {
        return [
            $this->entry(
                username: 'cbe',
                website: 'https://cafebrasserieexpo.com',
                instagram: 'cafebrasserieexpo',
                facebook: 'cafebrasserieexpo',
                tiktok: '@cafebrasserieexpo',
                youtube: null,
                linkedin: null,
                x: null,
                waSales: '+6285180646401',
                waMarketing: '+6289633446848',
            ),
            $this->entry(
                username: 'campx',
                website: 'https://campx.id',
                instagram: 'campx.id',
                facebook: null,
                tiktok: '@campx.id',
                youtube: '@campx-jatiluhur',
                linkedin: null,
                x: null,
                waSales: '+6281398885402',
                waMarketing: '+6281398885402',
            ),
            $this->entry(
                username: 'cei',
                website: 'https://cokelatexpo.id',
                instagram: 'cokelatexpo.id',
                facebook: 'cafebrasserieexpo',
                tiktok: '@cafebrasserieexpo',
                youtube: null,
                linkedin: null,
                x: null,
                waSales: '+6285180646401',
                waMarketing: '+6289633446848',
            ),
            $this->entry(
                username: 'flei',
                website: 'https://franchise-expo.co.id',
                instagram: 'fleiexpoid',
                facebook: 'fleiexpoid',
                tiktok: '@fleiexpoid',
                youtube: '@fleiexpoid',
                linkedin: 'flei-franchise-and-license-expo-indonesia',
                x: null,
                waSales: '+6288977742709',
                waMarketing: '+6288977742709',
            ),
            $this->entry(
                username: 'icc',
                website: 'https://indonesiacomiccon.com',
                instagram: 'indocomiccon',
                facebook: null,
                tiktok: null,
                youtube: null,
                linkedin: null,
                x: 'indocomicconx',
                waSales: '+6281119220005',
                waMarketing: '+6281119220018',
            ),
            $this->entry(
                username: 'icf',
                website: 'https://indocoffeefestival.com',
                instagram: 'indocoffeefest',
                facebook: 'cafebrasserieexpo',
                tiktok: '@cafebrasserieexpo',
                youtube: null,
                linkedin: null,
                x: null,
                waSales: '+6285180646401',
                waMarketing: '+6289633446848',
            ),
            $this->entry(
                username: 'askindo',
                website: 'https://iicc.askindo.id',
                instagram: 'askindo.secretariat',
                facebook: 'askindo.secretariat',
                tiktok: null,
                youtube: null,
                linkedin: null,
                x: null,
                waSales: '+6287781235071',
                waMarketing: '+6287781235071',
            ),
            $this->entry(
                username: 'inacon',
                website: 'https://indonesiaanimecon.com',
                instagram: 'indoanimecon',
                facebook: null,
                tiktok: null,
                youtube: null,
                linkedin: null,
                x: 'indocomicconx',
                waSales: '+6281119220005',
                waMarketing: '+6281119220018',
            ),
            $this->entry(
                username: 'keramika',
                website: 'https://keramika.co.id',
                instagram: 'keramikaid',
                facebook: 'keramikaid',
                tiktok: null,
                youtube: '@megabuildindo',
                linkedin: 'keramikaindonesia',
                x: null,
                waSales: '+6281190083309',
                waMarketing: '+6281190083309',
            ),
            $this->entry(
                username: 'megabuild',
                website: 'https://megabuild.co.id',
                instagram: 'megabuildindo',
                facebook: 'megabuildindo',
                tiktok: null,
                youtube: '@megabuildindo',
                linkedin: 'megabuildid',
                x: null,
                waSales: '+628118805638',
                waMarketing: '+628118805638',
            ),
            $this->entry(
                username: 'morefood',
                website: 'https://morefoodexpo.com',
                instagram: 'morefoodexpo.id',
                facebook: 'morefoodexpo.id',
                tiktok: '@morefoodexpo.id',
                youtube: null,
                linkedin: null,
                x: null,
                waSales: '+6281190083305',
                waMarketing: '+6281190083305',
            ),
            $this->entry(
                username: 'ioe',
                website: 'https://indooutingexpo.co.id',
                instagram: 'indooutingexpo',
                facebook: null,
                tiktok: null,
                youtube: null,
                linkedin: null,
                x: null,
                waSales: '+6281293235557',
                waMarketing: '+6281119220015',
            ),
            $this->entry(
                username: 'pe',
                website: 'https://panoramaevents.id',
                instagram: 'panoramaevents.id',
                facebook: 'hellopanoramaevents',
                tiktok: null,
                youtube: '@panoramaevents',
                linkedin: null,
                x: null,
                waSales: '+6281110529527',
                waMarketing: '+6281110529527',
            ),
            $this->entry(
                username: 'pm',
                website: 'https://panoramamedia.co.id',
                instagram: 'panoramamediaid',
                facebook: null,
                tiktok: null,
                youtube: '@pmoneid',
                linkedin: 'panorama-media',
                x: null,
                waSales: '+6281110529527',
                waMarketing: '+6281110529527',
            ),
            $this->entry(
                username: 'renex',
                website: 'https://renex.megabuild.co.id',
                instagram: 'megabuildindo',
                facebook: 'megabuildindo',
                tiktok: null,
                youtube: '@megabuildindo',
                linkedin: 'megabuildid',
                x: null,
                waSales: '+628118805638',
                waMarketing: '+628118805638',
            ),
        ];
    }

    /**
     * @return array{username: string, phones: list<array{label: string, number: string}>, links: list<array{label: string, url: string}>}
     */
    private function entry(
        string $username,
        string $website,
        ?string $instagram,
        ?string $facebook,
        ?string $tiktok,
        ?string $youtube,
        ?string $linkedin,
        ?string $x,
        string $waSales,
        string $waMarketing,
    ): array {
        $links = [
            ['label' => 'Website', 'url' => $website],
        ];

        if ($instagram !== null) {
            $links[] = ['label' => 'Instagram', 'url' => "https://instagram.com/{$instagram}"];
        }

        if ($facebook !== null) {
            $links[] = ['label' => 'Facebook', 'url' => "https://facebook.com/{$facebook}"];
        }

        if ($tiktok !== null) {
            $links[] = ['label' => 'TikTok', 'url' => "https://tiktok.com/{$tiktok}"];
        }

        if ($youtube !== null) {
            $links[] = ['label' => 'YouTube', 'url' => "https://youtube.com/{$youtube}"];
        }

        if ($linkedin !== null) {
            $links[] = ['label' => 'LinkedIn', 'url' => "https://linkedin.com/company/{$linkedin}"];
        }

        if ($x !== null) {
            $links[] = ['label' => 'X', 'url' => "https://x.com/{$x}"];
        }

        return [
            'username' => $username,
            'phones' => [
                ['label' => 'WhatsApp Sales', 'number' => $waSales],
                ['label' => 'WhatsApp Marketing', 'number' => $waMarketing],
            ],
            'links' => $links,
        ];
    }
}
