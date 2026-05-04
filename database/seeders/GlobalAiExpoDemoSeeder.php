<?php

namespace Database\Seeders;

use App\Enums\BoothType;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Project;
use App\Models\RundownItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo seeder for Global AI Expo prototype.
 *
 * Populates 20 speakers (Guest), 100 brands across 8 zones, and a 3-day
 * rundown (~46 items) so the prototype site is presentable to a client.
 *
 * Idempotent: wipes existing event-scoped data before reseeding so it can
 * be re-run safely. Brand records (global) use firstOrCreate; only the
 * brand_event pivot rows are wiped.
 *
 * Run:
 *   php artisan db:seed --class=GlobalAiExpoDemoSeeder
 */
class GlobalAiExpoDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent rename in case old slug still exists.
        Project::where('username', 'global-ai-expo')->update(['username' => 'globalaiexpo']);

        $project = Project::where('username', 'globalaiexpo')->firstOrFail();

        $event = Event::updateOrCreate(
            [
                'project_id' => $project->id,
                'slug' => 'global-ai-expo-2026',
            ],
            [
                'title' => 'Global AI Expo 2026',
                'edition_number' => 1,
                'description' => 'An AI exhibition, conference, startup pavilion, and business matching platform at Sentul City. Targeting 200 to 500 exhibitors and 20,000 to 50,000 visitors from 30+ countries.',
                'start_date' => '2026-11-20 09:00:00',
                'end_date' => '2026-11-22 21:00:00',
                'location' => 'Sentul City, Bogor, Indonesia',
                'location_link' => 'https://maps.app.goo.gl/sentul-city-placeholder',
                'status' => 'published',
                'visibility' => 'public',
                'is_active' => true,
            ]
        );

        $this->command->info("Project: {$project->username} (id={$project->id})");
        $this->command->info("Event:   {$event->title} (id={$event->id}, slug={$event->slug})");

        // Wipe existing demo content so seeder is idempotent.
        RundownItem::where('event_id', $event->id)->forceDelete();
        Guest::where('event_id', $event->id)->forceDelete();
        BrandEvent::where('event_id', $event->id)->forceDelete();

        $this->seedGuests($event);
        $this->seedBrands($project, $event);
        $this->seedRundown($event);
        $this->attachBrandLogos($event);
    }

    /**
     * Attach brand logo images from storage/app/tmp/brands/ to each matching brand.
     *
     * Filename (without extension) is treated as an Instagram handle. Every brand
     * whose Instagram link ends with that handle gets the image attached. This
     * handles cases where multiple brands share an IG handle (e.g. ChatGPT and
     * OpenAI both use @openai).
     */
    private function attachBrandLogos(Event $event): void
    {
        $dir = storage_path('app/tmp/brands');

        if (! is_dir($dir)) {
            $this->command->warn("attachBrandLogos: directory not found, skipping ({$dir}).");

            return;
        }

        $files = glob($dir.'/*.{jpg,jpeg,png,webp,svg}', GLOB_BRACE) ?: [];

        if (empty($files)) {
            $this->command->warn("attachBrandLogos: no images in {$dir}, skipping.");

            return;
        }

        $brandIds = BrandEvent::where('event_id', $event->id)->pluck('brand_id');

        $attached = 0;
        $unmatched = [];

        foreach ($files as $file) {
            $handle = pathinfo($file, PATHINFO_FILENAME);
            $needle = '%/'.$handle;

            $brands = Brand::whereIn('id', $brandIds)
                ->whereHas('links', function ($q) use ($needle) {
                    $q->where('label', 'Instagram')->where('url', 'ilike', $needle);
                })
                ->get();

            if ($brands->isEmpty()) {
                $unmatched[] = $handle;

                continue;
            }

            foreach ($brands as $brand) {
                $brand->clearMediaCollection('brand_logo');
                $brand->addMedia($file)
                    ->preservingOriginal()
                    ->toMediaCollection('brand_logo');
                $attached++;
            }
        }

        $this->command->info("Attached {$attached} brand logos from ".count($files).' files.');

        if (! empty($unmatched)) {
            $this->command->warn('Unmatched handles: '.implode(', ', $unmatched));
        }
    }

    private function seedGuests(Event $event): void
    {
        $speakers = [
            ['name' => 'Sam Altman', 'title' => 'CEO, OpenAI', 'org' => 'OpenAI', 'bio' => 'Co-founder and CEO of OpenAI, the lab behind ChatGPT and the GPT model family. Previously president of Y Combinator.', 'featured' => true, 'instagram' => 'sama'],
            ['name' => 'Dario Amodei', 'title' => 'CEO & Co-founder, Anthropic', 'org' => 'Anthropic', 'bio' => 'Leads Anthropic, creator of Claude. Former VP of Research at OpenAI. Focused on AI safety and interpretability research.', 'featured' => true, 'instagram' => null],
            ['name' => 'Daniela Amodei', 'title' => 'President & Co-founder, Anthropic', 'org' => 'Anthropic', 'bio' => 'President of Anthropic, leading operations, policy, and go-to-market for the AI safety company behind Claude.', 'featured' => true, 'instagram' => null],
            ['name' => 'Demis Hassabis', 'title' => 'CEO, Google DeepMind', 'org' => 'Google DeepMind', 'bio' => 'Co-founder of DeepMind, Nobel Laureate in Chemistry 2024 for AlphaFold. Leading the team behind Gemini and AlphaGo.', 'featured' => true, 'instagram' => null],
            ['name' => 'Sundar Pichai', 'title' => 'CEO, Google & Alphabet', 'org' => 'Alphabet Inc.', 'bio' => 'Chief Executive of Google and Alphabet. Driving the company\'s AI-first transformation across Search, Workspace, and Cloud.', 'featured' => true, 'instagram' => 'sundarpichai'],
            ['name' => 'Jensen Huang', 'title' => 'Founder & CEO, NVIDIA', 'org' => 'NVIDIA', 'bio' => 'Founded NVIDIA in 1993. Architect of the GPU revolution that powers nearly every modern AI model.', 'featured' => true, 'instagram' => null],
            ['name' => 'Elon Musk', 'title' => 'CEO, xAI & Tesla', 'org' => 'xAI / Tesla', 'bio' => 'Founder of xAI (creator of Grok), CEO of Tesla, and SpaceX. Building AI systems and humanoid robotics at scale.', 'featured' => true, 'instagram' => null],
            ['name' => 'Mark Zuckerberg', 'title' => 'Founder & CEO, Meta', 'org' => 'Meta Platforms', 'bio' => 'Leads Meta and its AI research arm FAIR. Champions open-weight foundation models with the Llama series.', 'featured' => true, 'instagram' => 'zuck'],
            ['name' => 'Yann LeCun', 'title' => 'Chief AI Scientist, Meta', 'org' => 'Meta AI / FAIR', 'bio' => 'Turing Award laureate. Pioneer of convolutional neural networks. Champions self-supervised learning and world models.', 'featured' => false, 'instagram' => null],
            ['name' => 'Satya Nadella', 'title' => 'Chairman & CEO, Microsoft', 'org' => 'Microsoft', 'bio' => 'Chairman and CEO of Microsoft. Architect of Microsoft\'s cloud and AI strategy and the company\'s partnership with OpenAI.', 'featured' => false, 'instagram' => 'satyanadella'],
            ['name' => 'Mustafa Suleyman', 'title' => 'CEO, Microsoft AI', 'org' => 'Microsoft AI', 'bio' => 'Co-founder of DeepMind and Inflection AI. Now leads Microsoft AI, including Copilot consumer products.', 'featured' => false, 'instagram' => null],
            ['name' => 'Andrew Ng', 'title' => 'Founder, DeepLearning.AI', 'org' => 'DeepLearning.AI', 'bio' => 'Founder of DeepLearning.AI and Coursera. Adjunct professor at Stanford. One of the most influential AI educators globally.', 'featured' => false, 'instagram' => null],
            ['name' => 'Fei-Fei Li', 'title' => 'Co-Director, Stanford HAI', 'org' => 'Stanford University', 'bio' => 'Co-director of the Stanford Institute for Human-Centered AI. Created ImageNet, the dataset that catalyzed modern computer vision.', 'featured' => false, 'instagram' => null],
            ['name' => 'Geoffrey Hinton', 'title' => 'Turing Award Laureate', 'org' => 'University of Toronto', 'bio' => 'Often called the "Godfather of AI". Turing Award winner whose work on backpropagation underpins deep learning.', 'featured' => false, 'instagram' => null],
            ['name' => 'Yoshua Bengio', 'title' => 'Founder & Scientific Director, Mila', 'org' => 'Mila - Quebec AI Institute', 'bio' => 'Turing Award laureate. Founder of Mila, one of the world\'s largest academic research groups in deep learning.', 'featured' => false, 'instagram' => null],
            ['name' => 'Liang Wenfeng', 'title' => 'Founder, DeepSeek', 'org' => 'DeepSeek', 'bio' => 'Founder of DeepSeek and High-Flyer Quant. Disrupted the foundation model space with open-weight reasoning models.', 'featured' => false, 'instagram' => null],
            ['name' => 'Jack Ma', 'title' => 'Founder, Alibaba Group', 'org' => 'Alibaba Group', 'bio' => 'Founder of Alibaba and Ant Group. Long-time evangelist for AI as the next great wave of technology in Asia.', 'featured' => false, 'instagram' => null],
            ['name' => 'Aravind Srinivas', 'title' => 'Co-founder & CEO, Perplexity', 'org' => 'Perplexity AI', 'bio' => 'Co-founder of Perplexity, the AI-native answer engine. Former research scientist at OpenAI and Google Brain.', 'featured' => false, 'instagram' => null],
            ['name' => 'Andrej Karpathy', 'title' => 'Founder, Eureka Labs', 'org' => 'Eureka Labs', 'bio' => 'Founder of Eureka Labs. Founding member of OpenAI and former Director of AI at Tesla.', 'featured' => false, 'instagram' => null],
            ['name' => 'Ilya Sutskever', 'title' => 'Co-founder, Safe Superintelligence Inc.', 'org' => 'Safe Superintelligence Inc.', 'bio' => 'Co-founder and Chief Scientist of SSI. Co-founder of OpenAI and one of the most cited researchers in deep learning.', 'featured' => false, 'instagram' => null],
        ];

        $order = 0;
        foreach ($speakers as $row) {
            $order++;
            $guest = new Guest;
            $guest->event_id = $event->id;
            $guest->name = $row['name'];
            $guest->slug = Str::slug($row['name']);
            $guest->title = $row['title'];
            $guest->bio = $row['bio'];
            $guest->organization = $row['org'];
            $guest->status = 'active';
            $guest->visibility = 'public';
            $guest->is_featured = $row['featured'];
            $guest->order_column = $order;
            $guest->save();

            if (! empty($row['instagram'])) {
                $guest->links()->create([
                    'label' => 'Instagram',
                    'url' => 'https://instagram.com/'.$row['instagram'],
                    'order' => 0,
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Inserted '.count($speakers).' guests.');
    }

    private function seedBrands(Project $project, Event $event): void
    {
        $brandsByZone = $this->brandData();

        $totalBrands = 0;
        $totalPivots = 0;

        foreach ($brandsByZone as $zoneCode => $zone) {
            $boothIndex = 0;
            foreach ($zone['brands'] as $row) {
                $boothIndex++;
                $totalBrands++;

                $brand = Brand::firstOrCreate(
                    ['slug' => Str::slug($row['name'])],
                    [
                        'name' => $row['name'],
                        'description' => $row['desc'],
                        'company_name' => $row['company'] ?? $row['name'],
                        'status' => 'active',
                        'visibility' => 'public',
                    ]
                );

                // Always sync category (idempotent across reseeds).
                $brand->syncBusinessCategories([$zone['name']], $project->id);

                // Refresh website + instagram links (delete + recreate, scoped to this brand).
                $brand->links()->delete();

                $linkOrder = 0;
                if (! empty($row['website'])) {
                    $brand->links()->create([
                        'label' => 'Website',
                        'url' => $row['website'],
                        'order' => $linkOrder++,
                        'is_active' => true,
                    ]);
                }
                if (! empty($row['ig'])) {
                    $brand->links()->create([
                        'label' => 'Instagram',
                        'url' => 'https://instagram.com/'.$row['ig'],
                        'order' => $linkOrder++,
                        'is_active' => true,
                    ]);
                }

                // Pivot: BrandEvent.
                $boothNumber = $zoneCode.'-'.str_pad((string) $boothIndex, 2, '0', STR_PAD_LEFT);
                $boothSize = [9, 18, 36][$totalBrands % 3];
                $boothType = match ($totalBrands % 4) {
                    0 => BoothType::StandardShellScheme,
                    1 => BoothType::EnhancedShellScheme,
                    2 => BoothType::RawSpace,
                    3 => BoothType::TableChairOnly,
                };

                BrandEvent::create([
                    'brand_id' => $brand->id,
                    'event_id' => $event->id,
                    'booth_number' => $boothNumber,
                    'booth_size' => $boothSize,
                    'booth_type' => $boothType,
                    'status' => 'active',
                    'fascia_name' => $row['name'],
                    'badge_name' => $row['name'],
                    'promotion_post_limit' => 1,
                ]);
                $totalPivots++;
            }
        }

        $this->command->info("Inserted/synced {$totalBrands} brands and {$totalPivots} brand_event pivots across ".count($brandsByZone).' zones.');
    }

    private function seedRundown(Event $event): void
    {
        $rows = [];

        // ----- Day 1 — Nov 20, 2026 (Fri) — Opening & Foundation Models Day -----
        $d1 = '2026-11-20';
        $rows[] = ['date' => $d1, 'start' => '08:00', 'end' => '09:00', 'title' => 'Registration & Welcome Coffee', 'location' => 'Main Lobby, Sentul Convention Hall'];
        $rows[] = ['date' => $d1, 'start' => '09:00', 'end' => '09:30', 'title' => 'Opening Ceremony — Welcome to Global AI Expo 2026', 'description' => 'National anthem, host welcome, and a short film on the next decade of AI.', 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d1, 'start' => '09:30', 'end' => '10:00', 'title' => 'Keynote: The Next Decade of AI', 'description' => 'Opening keynote on where general-purpose AI is heading and what it means for builders, businesses, and policymakers.', 'speakers' => ['Sam Altman'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d1, 'start' => '10:00', 'end' => '10:30', 'title' => 'Keynote: Constitutional AI & Building Models You Can Trust', 'description' => 'A look at safety, alignment, and constitutional approaches to training frontier models.', 'speakers' => ['Dario Amodei'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d1, 'start' => '10:30', 'end' => '11:00', 'title' => 'Coffee Break & Exhibition Tour', 'location' => 'Zone A — Foundation Models'];
        $rows[] = ['date' => $d1, 'theme' => 'Session I — The Foundation Models Frontier', 'title' => 'Session I'];
        $rows[] = ['date' => $d1, 'start' => '11:00', 'end' => '12:30', 'title' => 'Panel: Frontier Model Roadmaps', 'description' => 'Lab leaders compare research bets, scaling laws, and the path from chat assistants to autonomous agents.', 'moderator' => 'Andrew Ng, Founder, DeepLearning.AI', 'panelists' => ['Sam Altman, CEO, OpenAI', 'Dario Amodei, CEO, Anthropic', 'Demis Hassabis, CEO, Google DeepMind', 'Yann LeCun, Chief AI Scientist, Meta'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d1, 'start' => '12:30', 'end' => '13:30', 'title' => 'Lunch Break', 'location' => 'Garden Foyer'];
        $rows[] = ['date' => $d1, 'start' => '13:30', 'end' => '14:30', 'title' => 'Fireside Chat: Compute, Cloud, and the AI Stack', 'description' => 'Two of the people building the substrate of modern AI talk hardware bottlenecks, energy, and what changes when inference is everywhere.', 'speakers' => ['Jensen Huang', 'Sundar Pichai'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d1, 'start' => '14:30', 'end' => '15:30', 'title' => 'Panel: Open vs. Closed Models', 'description' => 'Where does the open-weight movement go from here? Costs, safety, sovereignty, and ecosystem effects.', 'moderator' => 'Aravind Srinivas, CEO, Perplexity', 'panelists' => ['Liang Wenfeng, Founder, DeepSeek', 'Yann LeCun, Chief AI Scientist, Meta', 'Mustafa Suleyman, CEO, Microsoft AI'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d1, 'start' => '15:30', 'end' => '16:00', 'title' => 'Coffee Break', 'location' => 'Zone A — Foundation Models'];
        $rows[] = ['date' => $d1, 'start' => '16:00', 'end' => '17:00', 'title' => 'Demo Showcase: Foundation Model Pavilion', 'description' => 'Live demos from leading foundation model providers across Zone A.', 'location' => 'Zone A — Foundation Models'];
        $rows[] = ['date' => $d1, 'start' => '17:00', 'end' => '18:30', 'title' => 'Welcome Reception & Networking', 'location' => 'Garden Foyer'];

        // ----- Day 2 — Nov 21, 2026 (Sat) — Enterprise, Hardware & Robotics Day -----
        $d2 = '2026-11-21';
        $rows[] = ['date' => $d2, 'start' => '08:30', 'end' => '09:00', 'title' => 'Registration & Morning Coffee'];
        $rows[] = ['date' => $d2, 'start' => '09:00', 'end' => '09:30', 'title' => 'Keynote: Enterprise AI at Scale', 'description' => 'How enterprises are moving past pilots into production, and what changes when AI becomes the default layer of every workflow.', 'speakers' => ['Satya Nadella'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d2, 'start' => '09:30', 'end' => '10:00', 'title' => 'Keynote: The Coming Decade of AI Hardware', 'description' => 'From training megaclusters to on-device inference, the silicon roadmap that decides what AI can do next.', 'speakers' => ['Jensen Huang'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d2, 'start' => '10:00', 'end' => '10:30', 'title' => 'Keynote: Embodied AI and the Humanoid Decade', 'description' => 'The convergence of foundation models and physical hardware, and what that unlocks across factories, homes, and cities.', 'speakers' => ['Elon Musk'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d2, 'start' => '10:30', 'end' => '11:00', 'title' => 'Coffee Break', 'location' => 'Zone C — Hardware'];
        $rows[] = ['date' => $d2, 'theme' => 'Session II — Hardware, Chips & Compute', 'title' => 'Session II'];
        $rows[] = ['date' => $d2, 'start' => '11:00', 'end' => '12:30', 'title' => 'Panel: Beyond GPUs — The New Inference Stack', 'description' => 'Custom silicon, sparsity-first chips, and the race for cheap, fast inference at the edge.', 'moderator' => 'Fei-Fei Li, Co-Director, Stanford HAI', 'panelists' => ['Andrew Feldman, CEO, Cerebras Systems', 'Jonathan Ross, CEO, Groq', 'Rodrigo Liang, CEO, SambaNova', 'Nigel Toon, CEO, Graphcore'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d2, 'start' => '12:30', 'end' => '13:30', 'title' => 'Lunch Break'];
        $rows[] = ['date' => $d2, 'theme' => 'Session III — Enterprise & Productivity AI', 'title' => 'Session III'];
        $rows[] = ['date' => $d2, 'start' => '13:30', 'end' => '15:00', 'title' => 'Panel: AI in the Enterprise Workflow', 'description' => 'CIOs, founders, and platform leads on what is actually working in production AI deployments.', 'moderator' => 'Mustafa Suleyman, CEO, Microsoft AI', 'panelists' => ['Notion AI Product Lead', 'GitHub Copilot Lead', 'Glean Founding Team', 'Databricks AI Platform Lead'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d2, 'start' => '15:00', 'end' => '16:00', 'title' => 'Workshop: Building Agents That Actually Work', 'description' => 'A hands-on session on agent architectures, tool use, and evaluation patterns.', 'speakers' => ['Andrej Karpathy'], 'location' => 'Workshop Room 1'];
        $rows[] = ['date' => $d2, 'start' => '16:00', 'end' => '16:30', 'title' => 'Coffee Break'];
        $rows[] = ['date' => $d2, 'theme' => 'Session IV — Robotics & Autonomy', 'title' => 'Session IV'];
        $rows[] = ['date' => $d2, 'start' => '16:30', 'end' => '17:30', 'title' => 'Panel: Humanoids, Drones & Self-Driving — What Ships in 2027', 'description' => 'A reality-check panel on what robotics products will actually reach customers in the next 12 to 18 months.', 'moderator' => 'Geoffrey Hinton', 'panelists' => ['Brett Adcock, CEO, Figure AI', 'Bernt Bornich, CEO, 1X Technologies', 'Adam Bry, CEO, Skydio', 'Tekedra Mawakana, Co-CEO, Waymo'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d2, 'start' => '17:30', 'end' => '18:30', 'title' => 'Investor Networking Hour', 'location' => 'VIP Lounge'];

        // ----- Day 3 — Nov 22, 2026 (Sun) — Creative, Startup Pavilion & Closing Day -----
        $d3 = '2026-11-22';
        $rows[] = ['date' => $d3, 'start' => '08:30', 'end' => '09:00', 'title' => 'Registration & Morning Coffee'];
        $rows[] = ['date' => $d3, 'start' => '09:00', 'end' => '09:30', 'title' => 'Keynote: A Human-Centered Future for AI', 'description' => 'On building AI that works with people, not around them.', 'speakers' => ['Fei-Fei Li'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d3, 'start' => '09:30', 'end' => '10:00', 'title' => 'Keynote: What We Got Wrong, and What Comes Next', 'description' => 'Reflections from one of the founding figures of deep learning on the past, present, and the road ahead.', 'speakers' => ['Geoffrey Hinton'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d3, 'start' => '10:00', 'end' => '10:30', 'title' => 'Keynote: From Big Tech to Startups — China\'s AI Surge', 'description' => 'A perspective on how China\'s AI ecosystem evolved from platform giants to model-native startups.', 'speakers' => ['Jack Ma'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d3, 'start' => '10:30', 'end' => '11:00', 'title' => 'Coffee Break'];
        $rows[] = ['date' => $d3, 'theme' => 'Session V — Creative & Generative Media', 'title' => 'Session V'];
        $rows[] = ['date' => $d3, 'start' => '11:00', 'end' => '12:00', 'title' => 'Panel: Generative Media — Image, Video, Voice, Music', 'description' => 'Where generative models are going next, and how creators, studios, and platforms are adapting.', 'moderator' => 'Andrew Ng', 'panelists' => ['Runway Founding Team', 'ElevenLabs Founding Team', 'Suno Founding Team', 'Synthesia Founding Team'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d3, 'theme' => 'Session VI — Safety, Policy & Governance', 'title' => 'Session VI'];
        $rows[] = ['date' => $d3, 'start' => '12:00', 'end' => '13:00', 'title' => 'Panel: AI Safety, Alignment & Global Governance', 'description' => 'Researchers and policy leaders on how to coordinate safety standards across labs, countries, and use cases.', 'moderator' => 'Yoshua Bengio', 'panelists' => ['Daniela Amodei, President, Anthropic', 'Ilya Sutskever, Co-founder, SSI', 'Mira Murati, CEO, Thinking Machines Lab', 'Helen Toner, Director, CSET'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d3, 'start' => '13:00', 'end' => '14:00', 'title' => 'Lunch Break'];
        $rows[] = ['date' => $d3, 'theme' => 'Startup Pavilion — Pitch Day', 'title' => 'Startup Pavilion — Pitch Day'];
        $rows[] = ['date' => $d3, 'start' => '14:00', 'end' => '15:30', 'title' => 'Startup Pavilion: 100+ AI Startup Pitches', 'description' => 'Three parallel pitch tracks across foundation models, vertical AI, and applied AI. Winners advance to the finals later this afternoon.', 'location' => 'Pavilion Hall B / C / D'];
        $rows[] = ['date' => $d3, 'theme' => 'Closing Plenary', 'title' => 'Closing Plenary'];
        $rows[] = ['date' => $d3, 'start' => '15:30', 'end' => '16:15', 'title' => 'Startup Pavilion Finals', 'description' => 'Top 10 startups from the morning pitch tracks present in front of judges and the full audience.', 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d3, 'start' => '16:15', 'end' => '16:45', 'title' => 'Awards Ceremony — Best of Global AI Expo 2026', 'description' => 'Awards for top startup, best demo, and AI for Good winners.', 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d3, 'start' => '16:45', 'end' => '17:15', 'title' => 'Closing Keynote: Toward Beneficial Superintelligence', 'description' => 'A closing reflection on aligning increasingly capable systems with human values.', 'speakers' => ['Ilya Sutskever'], 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d3, 'start' => '17:15', 'end' => '17:30', 'title' => 'Closing Remarks & Farewell', 'location' => 'Plenary Hall A'];
        $rows[] = ['date' => $d3, 'start' => '17:30', 'end' => '19:00', 'title' => 'Farewell Reception', 'location' => 'Garden Foyer'];

        $order = 0;
        foreach ($rows as $row) {
            $order++;
            $item = new RundownItem;
            $item->event_id = $event->id;
            $item->date = $row['date'];
            $item->start_time = $row['start'] ?? null;
            $item->end_time = $row['end'] ?? null;
            $item->is_active = true;
            $item->order_column = $order;

            $item->setTranslations('title', ['en' => $row['title']]);

            if (! empty($row['description'])) {
                $item->setTranslations('description', ['en' => $row['description']]);
            }
            if (! empty($row['theme'])) {
                $item->setTranslations('theme', ['en' => $row['theme']]);
            }
            if (! empty($row['location'])) {
                $item->setTranslations('location', ['en' => $row['location']]);
            }
            if (! empty($row['moderator'])) {
                $item->setTranslations('moderator', ['en' => $row['moderator']]);
            }

            if (! empty($row['speakers'])) {
                $names = array_map(fn ($n) => ['name' => $n], $row['speakers']);
                $item->speakers = ['en' => $names];
            }

            if (! empty($row['panelists'])) {
                $names = array_map(fn ($n) => ['name' => $n], $row['panelists']);
                $item->panelists = ['en' => $names];
            }

            if (! empty($row['theme']) && empty($row['start'])) {
                $item->settings = ['is_group_header' => true];
            }

            $item->save();
        }

        $this->command->info('Inserted '.count($rows).' rundown items across 3 days.');
    }

    /**
     * @return array<string, array{name: string, brands: array<int, array<string, mixed>>}>
     */
    private function brandData(): array
    {
        return [
            'A' => ['name' => 'Foundation Models', 'brands' => [
                ['name' => 'OpenAI', 'company' => 'OpenAI, Inc.', 'website' => 'https://openai.com', 'ig' => 'openai', 'desc' => 'Creator of ChatGPT and the GPT model family. Building safe and beneficial artificial general intelligence.'],
                ['name' => 'Anthropic', 'company' => 'Anthropic PBC', 'website' => 'https://anthropic.com', 'ig' => 'anthropicai', 'desc' => 'Creator of Claude. AI safety company building reliable, interpretable, and steerable AI systems.'],
                ['name' => 'Google DeepMind', 'company' => 'Google DeepMind', 'website' => 'https://deepmind.google', 'ig' => 'googledeepmind', 'desc' => 'Building Gemini and the next generation of AI to advance science and benefit humanity.'],
                ['name' => 'Meta AI', 'company' => 'Meta Platforms, Inc.', 'website' => 'https://ai.meta.com', 'ig' => 'meta', 'desc' => 'Open-weight Llama foundation models and AI research from FAIR.'],
                ['name' => 'Mistral AI', 'company' => 'Mistral AI SAS', 'website' => 'https://mistral.ai', 'ig' => 'mistralai_', 'desc' => 'European AI champion building open and efficient frontier models.'],
                ['name' => 'DeepSeek', 'company' => 'DeepSeek AI', 'website' => 'https://deepseek.com', 'ig' => 'deepseek_ai', 'desc' => 'Open-weight reasoning models from China that have reset cost expectations across the industry.'],
                ['name' => 'xAI', 'company' => 'xAI Corp.', 'website' => 'https://x.ai', 'ig' => 'xai', 'desc' => 'Elon Musk\'s AI lab and the team behind Grok, integrated across the X platform.'],
                ['name' => 'Cohere', 'company' => 'Cohere Inc.', 'website' => 'https://cohere.com', 'ig' => 'cohereai', 'desc' => 'Enterprise-grade foundation models with a focus on retrieval, reasoning, and data privacy.'],
                ['name' => 'Inflection AI', 'company' => 'Inflection AI, Inc.', 'website' => 'https://inflection.ai', 'ig' => 'inflectionai', 'desc' => 'Pioneers of empathetic personal AI, now serving enterprises with the Inflection-3 family.'],
                ['name' => 'AI21 Labs', 'company' => 'AI21 Labs Ltd.', 'website' => 'https://ai21.com', 'ig' => 'ai21labs', 'desc' => 'Israeli AI lab building Jamba and reliable enterprise language models.'],
                ['name' => 'Stability AI', 'company' => 'Stability AI Ltd.', 'website' => 'https://stability.ai', 'ig' => 'stabilityai', 'desc' => 'Open foundation models for image, video, audio, and 3D generation.'],
                ['name' => 'Aleph Alpha', 'company' => 'Aleph Alpha GmbH', 'website' => 'https://aleph-alpha.com', 'ig' => null, 'desc' => 'European sovereign AI focused on transparent, explainable enterprise systems.'],
                ['name' => 'Sakana AI', 'company' => 'Sakana AI K.K.', 'website' => 'https://sakana.ai', 'ig' => null, 'desc' => 'Tokyo-based research lab developing nature-inspired foundation models.'],
                ['name' => 'Reka AI', 'company' => 'Reka, Inc.', 'website' => 'https://reka.ai', 'ig' => null, 'desc' => 'Multimodal foundation models built for builders and enterprises.'],
                ['name' => 'Liquid AI', 'company' => 'Liquid AI, Inc.', 'website' => 'https://liquid.ai', 'ig' => null, 'desc' => 'MIT spinout building efficient liquid neural network foundation models.'],
            ]],
            'B' => ['name' => 'Productivity & Dev Tools', 'brands' => [
                ['name' => 'GitHub Copilot', 'company' => 'GitHub, Inc. (Microsoft)', 'website' => 'https://github.com/features/copilot', 'ig' => 'github', 'desc' => 'Your AI pair programmer, embedded in the editor and across the GitHub developer experience.'],
                ['name' => 'Cursor', 'company' => 'Anysphere Inc.', 'website' => 'https://cursor.com', 'ig' => 'cursor_ai', 'desc' => 'The AI-first code editor designed for pair programming with frontier models.'],
                ['name' => 'Replit', 'company' => 'Replit, Inc.', 'website' => 'https://replit.com', 'ig' => 'replit', 'desc' => 'Build, deploy, and ship apps from anywhere with an AI-powered cloud development platform.'],
                ['name' => 'Vercel', 'company' => 'Vercel Inc.', 'website' => 'https://vercel.com', 'ig' => 'vercel', 'desc' => 'The AI cloud — Next.js, v0, and an AI SDK to ship intelligent web apps end to end.'],
                ['name' => 'Lovable', 'company' => 'Lovable AB', 'website' => 'https://lovable.dev', 'ig' => 'lovable.dev', 'desc' => 'Generate full-stack web apps from a prompt and iterate visually.'],
                ['name' => 'Bolt.new', 'company' => 'StackBlitz, Inc.', 'website' => 'https://bolt.new', 'ig' => 'stackblitz', 'desc' => 'Prompt-to-app full-stack web development running entirely in the browser.'],
                ['name' => 'Windsurf', 'company' => 'Codeium', 'website' => 'https://windsurf.com', 'ig' => null, 'desc' => 'Agentic IDE for fast, autonomous coding with deep codebase context.'],
                ['name' => 'Codeium', 'company' => 'Exafunction, Inc.', 'website' => 'https://codeium.com', 'ig' => null, 'desc' => 'Free, fast, and unlimited AI coding assistance for individuals and enterprises.'],
                ['name' => 'Tabnine', 'company' => 'Tabnine Ltd.', 'website' => 'https://tabnine.com', 'ig' => 'tabnine_official', 'desc' => 'Private and personalized AI assistants for software development teams.'],
                ['name' => 'JetBrains AI', 'company' => 'JetBrains s.r.o.', 'website' => 'https://www.jetbrains.com/ai', 'ig' => 'jetbrains', 'desc' => 'AI assistance built natively into the JetBrains family of developer tools.'],
                ['name' => 'Notion AI', 'company' => 'Notion Labs, Inc.', 'website' => 'https://notion.so/product/ai', 'ig' => 'notionhq', 'desc' => 'Connected AI that searches, writes, and analyzes across your Notion workspace.'],
                ['name' => 'Mem', 'company' => 'Mem Labs, Inc.', 'website' => 'https://mem.ai', 'ig' => 'mem.ai', 'desc' => 'A self-organizing AI workspace that learns from how you think and work.'],
                ['name' => 'Reflect', 'company' => 'Reflect Notes, Inc.', 'website' => 'https://reflect.app', 'ig' => 'reflectnotes', 'desc' => 'A note-taking app that mirrors how your brain works, with built-in AI assistance.'],
                ['name' => 'Granola', 'company' => 'Granola Labs', 'website' => 'https://granola.ai', 'ig' => 'granola.ai', 'desc' => 'AI notepad that turns meeting notes into clean, structured summaries.'],
                ['name' => 'Otter.ai', 'company' => 'AISense, Inc.', 'website' => 'https://otter.ai', 'ig' => 'otter_ai', 'desc' => 'Real-time meeting transcription, summaries, and action items powered by AI.'],
            ]],
            'C' => ['name' => 'AI Hardware & Chips', 'brands' => [
                ['name' => 'NVIDIA', 'company' => 'NVIDIA Corporation', 'website' => 'https://nvidia.com', 'ig' => 'nvidia', 'desc' => 'The accelerated computing platform that powers training and inference for nearly every modern AI model.'],
                ['name' => 'AMD', 'company' => 'Advanced Micro Devices, Inc.', 'website' => 'https://amd.com', 'ig' => 'amd', 'desc' => 'Instinct GPUs and AI accelerators for the data center and the edge.'],
                ['name' => 'Intel', 'company' => 'Intel Corporation', 'website' => 'https://intel.com', 'ig' => 'intel', 'desc' => 'Gaudi accelerators, Xeon CPUs, and an open AI software stack.'],
                ['name' => 'Cerebras Systems', 'company' => 'Cerebras Systems, Inc.', 'website' => 'https://cerebras.ai', 'ig' => 'cerebrassystems', 'desc' => 'The world\'s largest AI chip and wafer-scale systems for fast training and inference.'],
                ['name' => 'Groq', 'company' => 'Groq, Inc.', 'website' => 'https://groq.com', 'ig' => 'groqinc', 'desc' => 'Language Processing Units delivering record-breaking inference speed.'],
                ['name' => 'SambaNova', 'company' => 'SambaNova Systems, Inc.', 'website' => 'https://sambanova.ai', 'ig' => 'sambanovasystems', 'desc' => 'AI platform with reconfigurable dataflow architecture for enterprise foundation models.'],
                ['name' => 'Graphcore', 'company' => 'Graphcore Ltd.', 'website' => 'https://graphcore.ai', 'ig' => 'graphcoreai', 'desc' => 'Intelligence Processing Units (IPUs) designed from the ground up for AI workloads.'],
                ['name' => 'Tenstorrent', 'company' => 'Tenstorrent Inc.', 'website' => 'https://tenstorrent.com', 'ig' => 'tenstorrent', 'desc' => 'High-performance, open-source RISC-V based AI accelerators.'],
                ['name' => 'Etched', 'company' => 'Etched.ai, Inc.', 'website' => 'https://etched.com', 'ig' => null, 'desc' => 'Transformer ASICs for the fastest possible inference of frontier language models.'],
                ['name' => 'Rain AI', 'company' => 'Rain Neuromorphics, Inc.', 'website' => 'https://rain.ai', 'ig' => null, 'desc' => 'Neuromorphic AI compute designed for efficient, real-time intelligence at the edge.'],
            ]],
            'D' => ['name' => 'Cloud & Infrastructure', 'brands' => [
                ['name' => 'AWS', 'company' => 'Amazon Web Services', 'website' => 'https://aws.amazon.com/ai', 'ig' => 'amazonwebservices', 'desc' => 'Bedrock, SageMaker, and Trainium — a full-stack AI platform from the world\'s largest cloud.'],
                ['name' => 'Microsoft Azure', 'company' => 'Microsoft Corporation', 'website' => 'https://azure.microsoft.com/ai', 'ig' => 'microsoft', 'desc' => 'Azure AI Foundry and the Azure OpenAI service for enterprise-grade AI applications.'],
                ['name' => 'Google Cloud', 'company' => 'Google LLC', 'website' => 'https://cloud.google.com/ai', 'ig' => 'googlecloud', 'desc' => 'Vertex AI and Gemini models running on TPUs in Google\'s global infrastructure.'],
                ['name' => 'Oracle Cloud', 'company' => 'Oracle Corporation', 'website' => 'https://oracle.com/cloud/ai', 'ig' => 'oracle', 'desc' => 'OCI Generative AI services and the world\'s largest network of GPU superclusters.'],
                ['name' => 'Lambda Labs', 'company' => 'Lambda Labs, Inc.', 'website' => 'https://lambdalabs.com', 'ig' => 'lambdalabsofficial', 'desc' => 'GPU cloud built for training and inference of foundation models.'],
                ['name' => 'CoreWeave', 'company' => 'CoreWeave, Inc.', 'website' => 'https://coreweave.com', 'ig' => 'coreweave', 'desc' => 'Specialized cloud platform delivering massive GPU capacity for AI workloads.'],
                ['name' => 'Together AI', 'company' => 'Together Computer, Inc.', 'website' => 'https://together.ai', 'ig' => 'together_ai', 'desc' => 'Open-source AI cloud — train, fine-tune, and run open models at scale.'],
                ['name' => 'Fireworks AI', 'company' => 'Fireworks AI, Inc.', 'website' => 'https://fireworks.ai', 'ig' => 'fireworks_ai', 'desc' => 'Production AI platform optimized for fast, reliable inference of open and custom models.'],
                ['name' => 'Replicate', 'company' => 'Replicate, Inc.', 'website' => 'https://replicate.com', 'ig' => 'replicateai', 'desc' => 'Run open-source AI models with a single API call.'],
                ['name' => 'Modal', 'company' => 'Modal Labs, Inc.', 'website' => 'https://modal.com', 'ig' => 'modal_labs', 'desc' => 'Serverless cloud for AI and data — write Python, run anywhere.'],
                ['name' => 'Anyscale', 'company' => 'Anyscale, Inc.', 'website' => 'https://anyscale.com', 'ig' => 'anyscalecompute', 'desc' => 'The Ray-based platform for scalable AI compute and distributed Python.'],
                ['name' => 'RunPod', 'company' => 'RunPod, Inc.', 'website' => 'https://runpod.io', 'ig' => 'runpod_io', 'desc' => 'GPU cloud platform for training, fine-tuning, and serving AI models on demand.'],
            ]],
            'E' => ['name' => 'China AI Pavilion', 'brands' => [
                ['name' => 'Baidu', 'company' => 'Baidu, Inc.', 'website' => 'https://baidu.com', 'ig' => null, 'desc' => 'ERNIE foundation models and a full-stack AI platform serving Chinese consumers and enterprises.'],
                ['name' => 'Alibaba Cloud', 'company' => 'Alibaba Cloud', 'website' => 'https://alibabacloud.com', 'ig' => 'alibabacloud', 'desc' => 'Qwen open-weight models and Tongyi platform powering Alibaba\'s AI ecosystem.'],
                ['name' => 'Tencent', 'company' => 'Tencent Holdings Ltd.', 'website' => 'https://tencent.com', 'ig' => null, 'desc' => 'Hunyuan foundation models integrated across Tencent\'s consumer and enterprise products.'],
                ['name' => 'ByteDance', 'company' => 'ByteDance Ltd.', 'website' => 'https://bytedance.com', 'ig' => null, 'desc' => 'Doubao consumer AI assistant and Volcengine enterprise AI platform.'],
                ['name' => 'Zhipu AI', 'company' => 'Zhipu AI', 'website' => 'https://z.ai', 'ig' => null, 'desc' => 'GLM foundation models from Tsinghua University, serving consumer and enterprise customers.'],
                ['name' => 'Moonshot AI', 'company' => 'Moonshot AI', 'website' => 'https://moonshot.cn', 'ig' => null, 'desc' => 'Kimi assistant and Kimi K2 models, known for long context and reasoning capability.'],
                ['name' => 'MiniMax', 'company' => 'MiniMax AI', 'website' => 'https://minimax.io', 'ig' => null, 'desc' => 'Foundation models, voice, video, and consumer AI products from one of China\'s leading labs.'],
                ['name' => '01.AI', 'company' => '01.AI', 'website' => 'https://01.ai', 'ig' => null, 'desc' => 'Yi family of open foundation models founded by Kai-Fu Lee.'],
                ['name' => 'StepFun', 'company' => 'StepFun', 'website' => 'https://stepfun.com', 'ig' => null, 'desc' => 'Multimodal foundation models including Step series LLMs and video generation models.'],
                ['name' => 'SenseTime', 'company' => 'SenseTime Group', 'website' => 'https://sensetime.com', 'ig' => null, 'desc' => 'Computer vision and generative AI platform serving industries across Asia.'],
                ['name' => 'iFlytek', 'company' => 'iFlytek Co., Ltd.', 'website' => 'https://iflytek.com', 'ig' => null, 'desc' => 'Voice AI and education AI pioneer with the Spark foundation model series.'],
                ['name' => 'Huawei Cloud', 'company' => 'Huawei Technologies Co., Ltd.', 'website' => 'https://huaweicloud.com', 'ig' => null, 'desc' => 'Pangu foundation models and Ascend AI hardware for sovereign and enterprise deployments.'],
            ]],
            'F' => ['name' => 'Robotics & Autonomy', 'brands' => [
                ['name' => 'Boston Dynamics', 'company' => 'Boston Dynamics, Inc.', 'website' => 'https://bostondynamics.com', 'ig' => 'bostondynamics', 'desc' => 'World leader in mobile robotics — Atlas, Spot, and Stretch for industrial and commercial use.'],
                ['name' => 'Figure AI', 'company' => 'Figure AI Inc.', 'website' => 'https://figure.ai', 'ig' => 'figure.robot', 'desc' => 'AI-first humanoid robotics for the home and the workplace.'],
                ['name' => '1X Technologies', 'company' => '1X Technologies AS', 'website' => 'https://1x.tech', 'ig' => '1x_technologies', 'desc' => 'Bipedal humanoid robots designed for safe, useful interaction with people.'],
                ['name' => 'Agility Robotics', 'company' => 'Agility Robotics, Inc.', 'website' => 'https://agilityrobotics.com', 'ig' => 'agilityrobotics', 'desc' => 'Maker of Digit, a humanoid robot designed for warehouse and logistics work.'],
                ['name' => 'Skydio', 'company' => 'Skydio, Inc.', 'website' => 'https://skydio.com', 'ig' => 'skydio', 'desc' => 'Autonomous drones for enterprise, public safety, and defense customers.'],
                ['name' => 'Unitree Robotics', 'company' => 'Unitree Robotics', 'website' => 'https://unitree.com', 'ig' => 'unitreerobotics', 'desc' => 'Quadrupeds and humanoids — accessible, capable robotics platforms from Hangzhou.'],
                ['name' => 'Waymo', 'company' => 'Waymo LLC', 'website' => 'https://waymo.com', 'ig' => 'waymo', 'desc' => 'Autonomous ride-hailing operating across major US cities at full Level 4 autonomy.'],
                ['name' => 'Cruise', 'company' => 'Cruise LLC', 'website' => 'https://getcruise.com', 'ig' => 'getcruise', 'desc' => 'Autonomous vehicle technology for safer, accessible city transportation.'],
                ['name' => 'Pony.ai', 'company' => 'Pony.ai Inc.', 'website' => 'https://pony.ai', 'ig' => null, 'desc' => 'Autonomous driving platform deploying robotaxis and robotrucks across China and the US.'],
                ['name' => 'Wayve', 'company' => 'Wayve Technologies Ltd.', 'website' => 'https://wayve.ai', 'ig' => 'wayveai', 'desc' => 'Embodied AI foundation model for autonomous driving — built in London, deployed worldwide.'],
            ]],
            'G' => ['name' => 'Creative & Media AI', 'brands' => [
                ['name' => 'Midjourney', 'company' => 'Midjourney, Inc.', 'website' => 'https://midjourney.com', 'ig' => 'midjourney', 'desc' => 'Independent research lab building one of the most loved generative image models.'],
                ['name' => 'Runway', 'company' => 'Runway AI, Inc.', 'website' => 'https://runwayml.com', 'ig' => 'runway', 'desc' => 'Frontier video generation, editing, and creative tools for filmmakers and studios.'],
                ['name' => 'Pika Labs', 'company' => 'Pika Labs', 'website' => 'https://pika.art', 'ig' => 'pika_labs', 'desc' => 'Idea-to-video AI for creators, with playful effects and rapid iteration.'],
                ['name' => 'ElevenLabs', 'company' => 'ElevenLabs Ltd.', 'website' => 'https://elevenlabs.io', 'ig' => 'elevenlabsio', 'desc' => 'State-of-the-art voice AI — text to speech, voice cloning, dubbing, and conversational voice agents.'],
                ['name' => 'Suno', 'company' => 'Suno Inc.', 'website' => 'https://suno.com', 'ig' => 'suno_ai_', 'desc' => 'Generate complete songs, including vocals and instruments, from a single prompt.'],
                ['name' => 'Udio', 'company' => 'Uncharted Labs', 'website' => 'https://udio.com', 'ig' => 'udiomusic', 'desc' => 'AI music studio for prompting, editing, and remixing original tracks.'],
                ['name' => 'Synthesia', 'company' => 'Synthesia Ltd.', 'website' => 'https://synthesia.io', 'ig' => 'synthesia.io', 'desc' => 'Create studio-quality AI videos with realistic avatars and 140+ languages.'],
                ['name' => 'HeyGen', 'company' => 'HeyGen', 'website' => 'https://heygen.com', 'ig' => 'heygen.ai', 'desc' => 'AI video generation platform for marketing, sales, and learning teams.'],
                ['name' => 'D-ID', 'company' => 'D-ID Ltd.', 'website' => 'https://d-id.com', 'ig' => 'd_idofficial', 'desc' => 'Photo-realistic talking avatars and creative reality studio for brands and creators.'],
                ['name' => 'Adobe Firefly', 'company' => 'Adobe Inc.', 'website' => 'https://adobe.com/products/firefly', 'ig' => 'adobe', 'desc' => 'Adobe\'s family of generative AI models, embedded across Creative Cloud and Express.'],
                ['name' => 'Canva', 'company' => 'Canva Pty Ltd.', 'website' => 'https://canva.com', 'ig' => 'canva', 'desc' => 'Magic Studio, Magic Write, and an AI-powered visual design platform used by 200M+ people.'],
                ['name' => 'Figma', 'company' => 'Figma, Inc.', 'website' => 'https://figma.com', 'ig' => 'figma', 'desc' => 'Collaborative design platform with AI features for prototyping, code, and creative iteration.'],
            ]],
            'H' => ['name' => 'Search, Data & Enterprise', 'brands' => [
                ['name' => 'Perplexity', 'company' => 'Perplexity AI, Inc.', 'website' => 'https://perplexity.ai', 'ig' => 'perplexity.ai', 'desc' => 'AI-native answer engine combining search, citations, and reasoning.'],
                ['name' => 'You.com', 'company' => 'You.com', 'website' => 'https://you.com', 'ig' => 'you_dotcom', 'desc' => 'Productivity-focused AI search with multimodal answers and customizable agents.'],
                ['name' => 'Brave Search', 'company' => 'Brave Software, Inc.', 'website' => 'https://search.brave.com', 'ig' => 'bravesoftware', 'desc' => 'Independent, privacy-respecting search with built-in AI summaries.'],
                ['name' => 'Hugging Face', 'company' => 'Hugging Face, Inc.', 'website' => 'https://huggingface.co', 'ig' => 'huggingface', 'desc' => 'The community building the future of AI — models, datasets, Spaces, and open-source tooling.'],
                ['name' => 'LangChain', 'company' => 'LangChain, Inc.', 'website' => 'https://langchain.com', 'ig' => null, 'desc' => 'Open-source framework and platform for building, evaluating, and deploying LLM agents.'],
                ['name' => 'LlamaIndex', 'company' => 'LlamaIndex, Inc.', 'website' => 'https://llamaindex.ai', 'ig' => null, 'desc' => 'Data framework for connecting custom data sources to LLMs and AI agents.'],
                ['name' => 'Pinecone', 'company' => 'Pinecone Systems, Inc.', 'website' => 'https://pinecone.io', 'ig' => null, 'desc' => 'Managed vector database powering retrieval for production AI applications.'],
                ['name' => 'Weaviate', 'company' => 'Weaviate B.V.', 'website' => 'https://weaviate.io', 'ig' => null, 'desc' => 'Open-source AI-native vector database with hybrid search and modular deployment.'],
                ['name' => 'Qdrant', 'company' => 'Qdrant', 'website' => 'https://qdrant.tech', 'ig' => null, 'desc' => 'High-performance, open-source vector database written in Rust.'],
                ['name' => 'Chroma', 'company' => 'Chroma, Inc.', 'website' => 'https://trychroma.com', 'ig' => null, 'desc' => 'The AI-native open-source embedding database for fast, simple retrieval.'],
                ['name' => 'Scale AI', 'company' => 'Scale AI, Inc.', 'website' => 'https://scale.com', 'ig' => 'scaleai', 'desc' => 'Data labeling, evaluation, and reinforcement learning infrastructure for the world\'s leading AI labs.'],
                ['name' => 'Databricks', 'company' => 'Databricks, Inc.', 'website' => 'https://databricks.com', 'ig' => 'databricksinc', 'desc' => 'Data Intelligence Platform unifying data, analytics, and AI on one foundation.'],
                ['name' => 'DataRobot', 'company' => 'DataRobot, Inc.', 'website' => 'https://datarobot.com', 'ig' => 'datarobot_inc', 'desc' => 'Enterprise AI platform for building, deploying, and governing predictive and generative AI.'],
                ['name' => 'H2O.ai', 'company' => 'H2O.ai, Inc.', 'website' => 'https://h2o.ai', 'ig' => 'h2o_ai', 'desc' => 'Open-source AI cloud and generative AI platform for enterprise customers.'],
            ]],
            'I' => ['name' => 'AI Assistants & Consumer Apps', 'brands' => [
                ['name' => 'ChatGPT', 'company' => 'OpenAI, Inc.', 'website' => 'https://chatgpt.com', 'ig' => 'openai', 'desc' => 'The world\'s most widely used AI assistant, built on OpenAI\'s GPT model family.'],
                ['name' => 'Claude', 'company' => 'Anthropic PBC', 'website' => 'https://claude.ai', 'ig' => 'anthropicai', 'desc' => 'Anthropic\'s AI assistant, designed to be helpful, harmless, and honest with industry-leading reasoning.'],
                ['name' => 'Google Gemini', 'company' => 'Google LLC', 'website' => 'https://gemini.google.com', 'ig' => 'google', 'desc' => 'Google\'s most capable multimodal AI assistant, integrated across Search, Workspace, and Android.'],
                ['name' => 'Microsoft Copilot', 'company' => 'Microsoft Corporation', 'website' => 'https://copilot.microsoft.com', 'ig' => 'microsoft', 'desc' => 'Microsoft\'s everyday AI companion, integrated across Windows, Microsoft 365, and Edge.'],
                ['name' => 'Apple Intelligence', 'company' => 'Apple Inc.', 'website' => 'https://apple.com/apple-intelligence', 'ig' => 'apple', 'desc' => 'On-device personal AI built into iPhone, iPad, and Mac with privacy at its core.'],
                ['name' => 'Grok', 'company' => 'xAI Corp.', 'website' => 'https://grok.com', 'ig' => 'x', 'desc' => 'xAI\'s AI assistant integrated into the X platform, with real-time knowledge and unfiltered humor.'],
                ['name' => 'Meta Llama', 'company' => 'Meta Platforms, Inc.', 'website' => 'https://llama.com', 'ig' => 'aiatmeta', 'desc' => 'Meta\'s family of open-weight foundation models — the most-downloaded open AI in the world.'],
                ['name' => 'Character.AI', 'company' => 'Character Technologies, Inc.', 'website' => 'https://character.ai', 'ig' => 'character.ai', 'desc' => 'Create and chat with AI characters — entertainment, learning, and roleplay at scale.'],
                ['name' => 'Replika', 'company' => 'Luka, Inc.', 'website' => 'https://replika.com', 'ig' => 'myreplika', 'desc' => 'The AI companion who cares — emotionally intelligent chat, voice, and AR personas.'],
                ['name' => 'Pi', 'company' => 'Inflection AI, Inc.', 'website' => 'https://pi.ai', 'ig' => 'inflectionai', 'desc' => 'Inflection AI\'s personal AI — kind, supportive, and conversational by design.'],
                ['name' => 'Le Chat', 'company' => 'Mistral AI SAS', 'website' => 'https://chat.mistral.ai', 'ig' => 'mistralai_', 'desc' => 'Mistral\'s AI assistant — fast, multilingual, and built on European open-weight models.'],
                ['name' => 'Qwen Chat', 'company' => 'Alibaba Cloud', 'website' => 'https://chat.qwen.ai', 'ig' => 'alibabacloud', 'desc' => 'Alibaba\'s flagship AI assistant powered by the open-weight Qwen foundation model series.'],
            ]],
        ];
    }
}
