<?php

namespace Database\Seeders;

use App\Models\CustomField;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\User;
use App\Support\FormFieldTypes;
use App\Support\FormTemplates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Production-seedable example forms showcasing every form builder field type,
 * each with realistic sample responses so the Responses and Analytics tabs
 * have meaningful demo data. Definitions live in FormTemplates (shared with
 * the create-from-template endpoint). Idempotent: safe to run multiple times.
 *
 * Responses are built around a coherent persona per submission (name drives a
 * matching email, phone, company, and links) and curated free-text answers,
 * so the demo data reads like real submissions instead of lorem ipsum.
 *
 * Run manually: php artisan db:seed --class=ExampleFormsSeeder
 */
class ExampleFormsSeeder extends Seeder
{
    private const RESPONSE_COUNTS = [
        'event-registration' => 30,
        'customer-feedback-survey' => 28,
        'job-application' => 18,
        'vendor-application' => 15,
        'field-showcase' => 22,
    ];

    /** @var array<int, array{0: string, 1: string}> first + last name */
    private const NAMES = [
        ['Budi', 'Santoso'], ['Siti', 'Rahmawati'], ['Andi', 'Wijaya'], ['Putri', 'Anggraini'],
        ['Dimas', 'Pratama'], ['Rina', 'Kartika'], ['Aji', 'Nugroho'], ['Maya', 'Lestari'],
        ['Bayu', 'Saputra'], ['Dewi', 'Anjani'], ['Fajar', 'Ramadhan'], ['Indah', 'Permata'],
        ['Rizki', 'Hidayat'], ['Nadia', 'Safitri'], ['Yoga', 'Kurniawan'], ['Tari', 'Wulandari'],
        ['Hendra', 'Gunawan'], ['Sari', 'Melati'], ['Eko', 'Prasetyo'], ['Lia', 'Hapsari'],
        ['Arif', 'Setiawan'], ['Citra', 'Maharani'], ['Galih', 'Pamungkas'], ['Vina', 'Oktaviani'],
        ['Reza', 'Firmansyah'], ['Mega', 'Puspita'], ['Iqbal', 'Maulana'], ['Tika', 'Ananda'],
        ['Surya', 'Darmawan'], ['Wulan', 'Cahyani'], ['Bagus', 'Wibowo'], ['Ayu', 'Pertiwi'],
        ['Kevin', 'Tan'], ['Aisha', 'Rahman'], ['Marcus', 'Lim'], ['Grace', 'Wong'],
        ['Daniel', 'Lee'], ['Olivia', 'Chen'],
    ];

    /** @var array<int, string> */
    private const COMPANIES = [
        'Nusantara Foods', 'Garda Tekno', 'Batik Lestari', 'Mitra Logistik', 'Kreasi Digital',
        'Sinar Kreatif', 'Boga Rasa', 'Anugerah Pangan', 'Jaya Tekstil', 'Cahaya Studio',
        'Sentra Niaga', 'Karya Mandiri', 'Daya Cipta', 'Rumah Kopi Nusantara', 'Selaras Group',
        'Maju Bersama', 'Inti Karya', 'Bumi Hijau Organik', 'Visi Media', 'Tunas Kreasi',
    ];

    /** @var array<int, string> */
    private const SKILLS = [
        'design', 'figma', 'marketing', 'copywriting', 'laravel', 'vue', 'seo',
        'photography', 'sales', 'public speaking', 'data analysis', 'project management',
    ];

    /**
     * Curated free-text answers keyed by exact field label. Falls back to the
     * generic pools below when a label is not mapped.
     *
     * @var array<string, array<int, string>>
     */
    private const FREE_TEXT = [
        'Suggestions' => [
            'Overall a great event. The only downside was the long queue at registration in the morning.',
            'Loved the talks, but the venue got too crowded in the afternoon. Maybe a bigger hall next year.',
            'Please add more food stalls. The lines were really long during lunch.',
            'The WiFi kept dropping during the workshop. Other than that, a fantastic experience.',
            'More charging stations would help. Great speakers though!',
            'Signage was a bit confusing finding the breakout rooms. The content itself was top notch.',
            'Would love a clearer schedule app next time. Everything else was well organised.',
            'The networking night was the highlight for me. Please keep it for next year.',
            'Ticket pricing felt fair for the value. Maybe offer a student discount.',
            'Parking was difficult. Consider partnering with a nearby lot.',
            'Sessions were great but started a little late. Tighter time management would help.',
            'The AC in hall B was too cold. Small thing, but worth noting.',
            'Honestly nothing major. Smooth event and friendly staff.',
            'Add live translation for the international speakers please.',
        ],
        'Anything else we should know?' => [
            'We can bring our own display fixtures, but would appreciate a power outlet near the booth.',
            'Our team will arrive a day early for setup. Please share the load-in schedule.',
            'We are happy to sponsor a coffee break in exchange for additional signage.',
            'We hold halal certification and can provide the documents on request.',
            'Looking forward to it. We attended last year and sales were strong.',
            'We would prefer a corner booth if available, near the main entrance.',
            'Our products are fragile, so we may need extra time for setup and teardown.',
            'Can you confirm whether electricity is included or billed separately?',
            'We can provide our own banner stands and tablecloths.',
            'Interested in a speaking slot if any are still open.',
            'Please send the floor plan so we can plan our layout.',
            'No special requirements. Just excited to be part of the event.',
        ],
        'Cover Letter' => [
            '<p>I have spent the last four years building products for early-stage startups, and I am drawn to your team because of the focus on craft and user experience.</p><p>In my current role I lead a small design team and ship weekly. I would love to bring that pace and attention to detail here.</p>',
            '<p>I am a frontend engineer with a strong eye for design. I have shipped several Vue and Laravel projects end to end, and I enjoy the part where engineering meets product thinking.</p><p>What excites me most about this role is the chance to own features from idea to launch.</p>',
            '<p>After six years in marketing, I have learned that the best campaigns start with listening to customers. I run experiments, read the data, and iterate quickly.</p><p>I would be thrilled to help grow your community.</p>',
            '<p>I am applying because I genuinely use your product every week. I have a few ideas for the onboarding flow that I would love to discuss.</p><p>My background spans support and product, so I understand users from both sides.</p>',
            '<p>I am a recent graduate eager to learn. I built two side projects last year, one of which reached a few thousand users.</p><p>I am not afraid of hard problems and I learn fast.</p>',
            '<p>With a decade in operations, I am good at turning chaos into clear processes. I have managed vendors, budgets, and cross-functional teams.</p><p>I would love to bring that structure to a fast-growing company.</p>',
            '<p>I write code and care about the people who use it. Most recently I rebuilt a checkout flow that lifted conversion by a meaningful margin.</p><p>I value clear communication and shipping things that matter.</p>',
            '<p>I have run events for the past three years, from small meetups to a 2,000-person conference. Logistics, sponsors, and last-minute fixes are my comfort zone.</p><p>Your mission resonates with me.</p>',
            '<p>I am a data analyst who likes telling stories with numbers. I have built dashboards that leadership actually uses to make decisions.</p><p>I am excited by the scale of data your team works with.</p>',
            '<p>I switched into tech two years ago and have not looked back. I am self-taught, persistent, and collaborative.</p><p>I would welcome the chance to prove myself and keep growing here.</p>',
        ],
        'Short Text' => [
            'Looks clean and easy to use', 'Great first impression', 'Jakarta', 'Marketing team',
            'Found it through a friend', 'Product manager', 'Bandung', 'Really smooth so far',
            'Design lead', 'Quick and simple', 'Surabaya', 'Happy with it',
        ],
        'Long Text' => [
            'I tried filling this out on my phone and it worked well. The inputs are big enough and the layout did not break.',
            'We are considering this for our customer onboarding. The mix of field types covers almost everything we need.',
            'Setup was straightforward. I appreciated that the required fields were clearly marked before I hit submit.',
            'The rating and slider inputs feel natural. My only note is that a longer help text would be useful on a few fields.',
            'I like that it keeps my answers as I scroll. The whole thing took less than two minutes to complete.',
            'Used this to collect feedback after our last workshop and got a good response rate. People found it painless.',
            'Clean design overall. The dropdowns open smoothly and the date picker is easy to navigate.',
            'I filled it out to test the file upload. The drag and drop worked on the first try, which is rare.',
            'Honestly one of the nicer forms I have used recently. Nothing felt cluttered or confusing.',
            'We plan to embed this on our landing page. The fact that it supports so many field types is a big plus.',
            'The flow makes sense from top to bottom. I never had to guess what a field wanted from me.',
            'Tried it in dark mode and it looked just as good. A small detail, but it matters to me.',
        ],
        'Rich Text' => [
            '<p>This is a quick test of the <strong>rich text</strong> field. It handles <em>bold</em> and <em>italics</em> nicely.</p>',
            '<p>A few things I liked:</p><ul><li>Clean toolbar</li><li>Lists work well</li><li>Links are easy to add</li></ul>',
            '<p>Writing a longer note here to see how it wraps. So far the editor feels responsive and the formatting sticks after submit.</p>',
            '<p>Our team would use this for <strong>internal updates</strong>. Formatting makes the responses much easier to read.</p>',
            '<p>Testing a link: <a href="https://pmone.id">pmone.id</a>. It rendered correctly in the response view.</p>',
            '<p>Short and sweet. The rich text option is a nice touch for open-ended answers.</p>',
            '<p>I pasted a paragraph from a doc and the formatting carried over cleanly. No weird spacing issues.</p>',
            '<p>Great for collecting <em>detailed</em> feedback where a plain textarea would feel limiting.</p>',
        ],
    ];

    /** @var array<int, string> */
    private const FALLBACK_SHORT = [
        'Looks great', 'Easy to follow', 'Very intuitive', 'Nice and clean',
        'Works well for us', 'Simple to complete', 'Helpful', 'No complaints here',
    ];

    /** @var array<int, string> */
    private const FALLBACK_LONG = [
        'Filling this out was quick and the questions were clear. I did not get stuck anywhere along the way.',
        'A well-built form overall. The spacing makes it easy to read and the controls respond instantly.',
        'I completed this on a laptop and everything lined up nicely. Submitting was fast with no errors.',
        'The questions felt relevant and none of them were confusing. I would happily fill this out again.',
    ];

    /** @var array<int, string> */
    private const FALLBACK_RICH = [
        '<p>A short note to test formatting. <strong>Bold</strong> and <em>italics</em> both work as expected.</p>',
        '<p>This editor is comfortable to type in. The response looks the same as what I wrote.</p>',
    ];

    public function run(): void
    {
        $owner = User::role('master')->first() ?? User::first();

        if (! $owner) {
            $this->command?->warn('ExampleFormsSeeder skipped: no users found.');

            return;
        }

        foreach ($this->forms() as $definition) {
            $form = Form::withTrashed()->firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'title' => $definition['title'],
                    'description' => $definition['description'],
                    'settings' => $definition['settings'],
                    'status' => Form::STATUS_PUBLISHED,
                    'is_active' => true,
                    'user_id' => $owner->id,
                    'created_by' => $owner->id,
                ]
            );

            if ($form->wasRecentlyCreated) {
                foreach (array_values($definition['fields']) as $index => $field) {
                    $form->fields()->create($field + [
                        'context' => CustomField::CONTEXT_FORM,
                        'order_column' => $index + 1,
                    ]);
                }
            }

            if ($form->responses()->doesntExist()) {
                $this->seedResponses($form->load('fields'), $definition['response_count']);
            }

            $this->command?->info("Seeded form: {$form->title}");
        }
    }

    private function forms(): array
    {
        return collect(FormTemplates::all())
            ->map(fn (array $template, string $key) => [
                'slug' => 'example-'.$key,
                'title' => 'Example - '.$template['title'],
                'description' => $template['description'],
                'settings' => $template['settings'],
                'fields' => $template['fields'],
                'response_count' => self::RESPONSE_COUNTS[$key] ?? 20,
            ])
            ->values()
            ->all();
    }

    private function seedResponses(Form $form, int $count): void
    {
        $requireEmail = ! empty($form->settings['require_email']);

        for ($i = 0; $i < $count; $i++) {
            $persona = $this->makePersona();
            $data = [];

            foreach ($form->fields as $field) {
                if ($field->type === CustomField::TYPE_SECTION) {
                    continue;
                }

                $required = ! empty($field->validation['required']);

                if (! $required && mt_rand(1, 100) > 75) {
                    continue;
                }

                $value = $this->makeValue($field, $persona);

                if ($value !== null) {
                    $data[$field->ulid] = $value;
                }
            }

            $submittedAt = $this->randomSubmittedAt();

            $response = FormResponse::create([
                'form_id' => $form->id,
                'response_data' => $data,
                'respondent_email' => $requireEmail || mt_rand(0, 1) ? $persona['email'] : null,
                'browser_fingerprint' => fake()->sha1(),
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'status' => $this->randomStatus(),
                'submitted_at' => $submittedAt,
            ]);

            $response->forceFill([
                'created_at' => $submittedAt,
                'updated_at' => $submittedAt,
            ])->saveQuietly();
        }
    }

    /**
     * Build a coherent fake identity so a response's name, email, phone, company,
     * and links all belong to the same believable person.
     *
     * @return array<string, string>
     */
    private function makePersona(): array
    {
        [$first, $last] = self::NAMES[array_rand(self::NAMES)];
        $sf = $this->asciiLower($first);
        $sl = $this->asciiLower($last);

        $provider = (string) $this->weightedPick([
            'gmail.com' => 60, 'outlook.com' => 12, 'yahoo.com' => 10, 'icloud.com' => 6, 'proton.me' => 4,
        ]);

        $local = $this->pickFrom(["$sf.$sl", "$sf$sl", $sf.substr($sl, 0, 1), "$sf.$sl".mt_rand(7, 99)]);

        $company = self::COMPANIES[array_rand(self::COMPANIES)];
        $companySlug = preg_replace('/[^a-z]/', '', strtolower($company));
        $tld = $this->pickFrom(['co.id', 'com', 'id']);

        return [
            'first' => $sf,
            'last' => $sl,
            'name' => "$first $last",
            'email' => "$local@$provider",
            'company_email' => "$sf@$companySlug.co.id",
            'phone' => '+628'.fake()->numerify('#########'),
            'country' => (string) $this->weightedPick([
                'Indonesia' => 52, 'Singapore' => 11, 'Malaysia' => 9, 'Japan' => 6,
                'Australia' => 5, 'United States' => 5, 'Thailand' => 4, 'Vietnam' => 4,
                'India' => 2, 'South Korea' => 2,
            ]),
            'company' => $company,
            'website' => "https://www.$companySlug.$tld",
            'linkedin' => "https://www.linkedin.com/in/$sf-$sl",
        ];
    }

    /**
     * @param  array<string, string>  $persona
     */
    private function makeValue(CustomField $field, array $persona): mixed
    {
        $validation = $field->validation ?? [];
        $optionValues = FormFieldTypes::optionValues($field);
        $label = strtolower($field->label);

        return match ($field->type) {
            CustomField::TYPE_TEXT => $this->makeText($field, $persona),
            CustomField::TYPE_TEXTAREA => $this->pickFrom(
                self::FREE_TEXT[$field->label] ?? self::FALLBACK_LONG
            ),
            CustomField::TYPE_RICH_TEXT => $this->pickFrom(
                self::FREE_TEXT[$field->label] ?? self::FALLBACK_RICH
            ),
            CustomField::TYPE_EMAIL => str_contains($label, 'work') ? $persona['company_email'] : $persona['email'],
            CustomField::TYPE_PHONE => $persona['phone'],
            CustomField::TYPE_URL => $this->makeUrl($field, $persona),
            CustomField::TYPE_DATE => now()->addDays(mt_rand(3, 60))->format('Y-m-d'),
            CustomField::TYPE_TIME => sprintf('%02d:%02d', mt_rand(8, 20), [0, 15, 30, 45][mt_rand(0, 3)]),
            CustomField::TYPE_DATETIME => now()->addDays(mt_rand(3, 30))->setTime(mt_rand(9, 17), [0, 30][mt_rand(0, 1)])->format('Y-m-d H:i'),
            CustomField::TYPE_DATE_RANGE => $this->makeDateRange(),
            CustomField::TYPE_SELECT, CustomField::TYPE_RADIO => $optionValues
                ? $optionValues[$this->weightedIndex(count($optionValues))]
                : null,
            CustomField::TYPE_MULTI_SELECT, CustomField::TYPE_CHECKBOX_GROUP => $this->pickMany(
                $optionValues,
                min((int) ($validation['max_selections'] ?? 3), 3)
            ),
            CustomField::TYPE_TAGS => $this->pickMany(
                self::SKILLS,
                min((int) ($validation['max_selections'] ?? 4), 5)
            ),
            CustomField::TYPE_CHECKBOX => mt_rand(1, 100) <= 85,
            CustomField::TYPE_SWITCH => mt_rand(1, 100) <= 60,
            CustomField::TYPE_FILE => null,
            CustomField::TYPE_RATING => $this->weightedPick([1 => 2, 2 => 4, 3 => 12, 4 => 36, 5 => 46]),
            CustomField::TYPE_LINEAR_SCALE => $this->makeScaleValue(
                (int) ($validation['min'] ?? 1),
                (int) ($validation['max'] ?? 5)
            ),
            CustomField::TYPE_SLIDER => $this->makeSliderValue($field),
            CustomField::TYPE_NUMBER => mt_rand((int) ($validation['min'] ?? 1), (int) ($validation['max'] ?? 100)),
            CustomField::TYPE_COLOR => fake()->randomElement([
                '#2563eb', '#16a34a', '#db2777', '#f59e0b', '#7c3aed', '#0f172a', '#dc2626',
            ]),
            CustomField::TYPE_COUNTRY => $persona['country'],
            default => null,
        };
    }

    /**
     * @param  array<string, string>  $persona
     */
    private function makeText(CustomField $field, array $persona): string
    {
        $label = strtolower($field->label);

        if (str_contains($label, 'company')) {
            return $persona['company'];
        }

        if (str_contains($label, 'name')) {
            return $persona['name'];
        }

        return $this->pickFrom(self::FREE_TEXT[$field->label] ?? self::FALLBACK_SHORT);
    }

    /**
     * @param  array<string, string>  $persona
     */
    private function makeUrl(CustomField $field, array $persona): string
    {
        $label = strtolower($field->label);

        if (str_contains($label, 'website')) {
            return $persona['website'];
        }

        if (str_contains($label, 'portfolio') || str_contains($label, 'linkedin')) {
            return $this->pickFrom([
                $persona['linkedin'],
                'https://github.com/'.$persona['first'].$persona['last'],
                'https://www.behance.net/'.$persona['first'].$persona['last'],
                'https://'.$persona['first'].$persona['last'].'.framer.website',
            ]);
        }

        return $persona['website'];
    }

    private function asciiLower(string $value): string
    {
        return preg_replace('/[^a-z]/', '', strtolower($value)) ?: 'user';
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $pool
     * @return T
     */
    private function pickFrom(array $pool): mixed
    {
        return $pool[array_rand($pool)];
    }

    private function makeDateRange(): array
    {
        $start = now()->addDays(mt_rand(7, 45));

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $start->copy()->addDays(mt_rand(1, 5))->format('Y-m-d'),
        ];
    }

    private function makeScaleValue(int $min, int $max): int
    {
        $skewed = $min + (int) round(($max - $min) * sqrt(mt_rand(0, 100) / 100));

        return max($min, min($max, $skewed));
    }

    private function makeSliderValue(CustomField $field): int
    {
        $min = (int) ($field->validation['min'] ?? 0);
        $max = (int) ($field->validation['max'] ?? 100);
        $step = max(1, (int) ($field->settings['step'] ?? 1));
        $steps = intdiv($max - $min, $step);

        return $min + ($step * mt_rand(0, $steps));
    }

    /**
     * @return array<int, string>
     */
    private function pickMany(array $values, int $max): array
    {
        if (! $values) {
            return [];
        }

        $count = mt_rand(1, max(1, min($max, count($values))));
        $keys = (array) array_rand($values, min($count, count($values)));

        return array_values(array_map(fn ($key) => $values[$key], $keys));
    }

    /**
     * Pick the first option more often than the last, with some randomness.
     */
    private function weightedIndex(int $count): int
    {
        $weights = [];
        for ($i = 0; $i < $count; $i++) {
            $weights[$i] = (($count - $i) ** 2) + mt_rand(0, 2);
        }

        return (int) $this->weightedPick($weights);
    }

    /**
     * @param  array<int|string, int>  $weightedMap  value => weight
     */
    private function weightedPick(array $weightedMap): int|string|null
    {
        if (! $weightedMap) {
            return null;
        }

        $roll = mt_rand(1, max(1, array_sum($weightedMap)));

        foreach ($weightedMap as $value => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $value;
            }
        }

        return array_key_first($weightedMap);
    }

    private function randomSubmittedAt(): Carbon
    {
        $daysAgo = (int) floor(28 * pow(mt_rand(0, 100) / 100, 1.7));

        return now()->subDays($daysAgo)->setTime(mt_rand(8, 21), mt_rand(0, 59));
    }

    private function randomStatus(): string
    {
        return (string) $this->weightedPick([
            FormResponse::STATUS_READ => 60,
            FormResponse::STATUS_NEW => 30,
            FormResponse::STATUS_STARRED => 7,
            FormResponse::STATUS_SPAM => 3,
        ]);
    }
}
