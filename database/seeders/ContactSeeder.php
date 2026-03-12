<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContactSeeder extends Seeder
{
    private const TOTAL = 200;

    private array $companies = [
        'PT Mega Konstruksi Indonesia', 'CV Bumi Perkasa', 'PT Sinar Abadi Jaya', 'PT Karya Mandiri Sejahtera',
        'PT Indo Beton Prima', 'CV Mitra Teknik Utama', 'PT Bangun Cipta Nusantara', 'PT Graha Sarana Duta',
        'PT Wijaya Karya Bangunan', 'CV Sukses Makmur', 'PT Delta Murni Textile', 'PT Anugerah Perkasa',
        'PT Trisula International', 'CV Cipta Kreasi', 'PT Maju Bersama Utama', 'PT Bahana Wirya',
        'PT Fajar Surya Wisesa', 'PT Arwana Citramulia', 'PT Holcim Indonesia', 'PT Semen Gresik',
        'PT Indocement Tunggal Prakarsa', 'CV Bintang Timur', 'PT Mulia Industrindo', 'PT Asahimas Flat Glass',
        'PT Surya Toto Indonesia', 'PT Cakra Compact Aluminium', 'PT Krakatau Steel', 'CV Jaya Mandiri',
        'PT Alumindo Light Metal', 'PT Japfa Comfeed', 'PT Astra Otoparts', 'PT Komatsu Indonesia',
        'PT United Tractors', 'PT Caterpillar Indonesia', 'CV Karya Baja', 'PT Ciputra Development',
        'PT Summarecon Agung', 'PT Pakuwon Jati', 'PT Lippo Karawaci', 'PT Intiland Development',
        'PT Agung Podomoro Land', 'PT Bumi Serpong Damai', 'PT Metropolitan Land', 'CV Global Teknik',
        'PT Modernland Realty', 'PT Jababeka', 'PT Megapolitan Developments', 'PT Duta Pertiwi',
        'PT Sentul City', 'PT Alam Sutera Realty', 'CV Kreasi Bangunan', 'PT Schneider Electric Indonesia',
        'PT Siemens Indonesia', 'PT ABB Indonesia', 'PT Legrand Indonesia', 'PT Panasonic Gobel Indonesia',
        'PT Philips Indonesia', 'PT Osram Indonesia', 'CV Cahaya Elektrik', 'PT Daikin Airconditioning Indonesia',
        'PT Toshiba Consumer Products', 'PT Sharp Electronics Indonesia', 'PT LG Electronics Indonesia',
        'PT Samsung Electronics Indonesia', 'CV Tekno Solusi', 'PT Mitsubishi Electric Indonesia',
        'PT Hitachi Astemo Indonesia', 'PT Denso Indonesia', 'PT Toyota Motor Manufacturing',
        'PT Honda Prospect Motor', 'CV Prima Jasa', 'PT Suzuki Indomobil Motor', 'PT Yamaha Motor Indonesia',
        'PT Kawasaki Motor Indonesia', 'PT Pindad', 'PT Dirgantara Indonesia',
    ];

    private array $jobTitles = [
        'CEO', 'Managing Director', 'General Manager', 'Marketing Director', 'Sales Director',
        'Marketing Manager', 'Sales Manager', 'Business Development Manager', 'Project Manager',
        'Product Manager', 'Brand Manager', 'Account Executive', 'Senior Account Executive',
        'Public Relations Manager', 'Communications Director', 'Event Manager', 'Exhibition Manager',
        'Operations Manager', 'Procurement Manager', 'Purchasing Manager', 'Supply Chain Manager',
        'Technical Director', 'Chief Technology Officer', 'IT Manager', 'Head of Digital',
        'Creative Director', 'Art Director', 'Media Planner', 'Content Strategist',
        'Human Resources Manager', 'Finance Manager', 'Chief Financial Officer',
        'Administration Manager', 'Office Manager', 'Corporate Secretary',
        'Regional Sales Manager', 'Area Manager', 'Territory Manager', 'Key Account Manager',
        'Export Manager', 'Import Manager', 'Logistics Manager', 'Warehouse Manager',
        'Quality Control Manager', 'R&D Manager', 'Production Manager', 'Plant Manager',
        'Head of Partnerships', 'Community Manager', 'Sponsorship Manager',
    ];

    private array $businessCategories = [
        'Building Materials', 'Construction Equipment', 'Interior Design', 'Architecture',
        'Electrical & Lighting', 'HVAC & Plumbing', 'Safety & Security', 'Smart Home',
        'Green Building', 'Steel & Metal', 'Glass & Ceramics', 'Wood & Timber',
        'Paint & Coatings', 'Doors & Windows', 'Roofing', 'Flooring',
        'Kitchen & Bath', 'Furniture', 'Landscaping', 'Heavy Equipment',
        'Tools & Hardware', 'Concrete & Cement', 'Waterproofing', 'Fire Protection',
        'Renewable Energy', 'Water Treatment', 'Waste Management', 'Elevator & Escalator',
    ];

    private array $tags = [
        'vip', 'priority', 'follow-up', 'hot-lead', 'repeat-client', 'new-contact',
        'needs-callback', 'interested', 'decision-maker', 'influencer', 'budget-approved',
        'negotiating', 'long-term', 'local', 'international', 'government',
        'private-sector', 'sme', 'corporate', 'startup', 'association',
        'returning-exhibitor', 'first-timer', 'premium-booth', 'standard-booth',
        'media', 'press', 'blogger', 'key-opinion-leader', 'trade-buyer',
    ];

    private array $provinces = [
        'DKI Jakarta', 'Jawa Barat', 'Jawa Tengah', 'Jawa Timur', 'Banten',
        'DI Yogyakarta', 'Bali', 'Sumatera Utara', 'Sumatera Barat', 'Sumatera Selatan',
        'Riau', 'Kepulauan Riau', 'Kalimantan Selatan', 'Kalimantan Timur', 'Sulawesi Selatan',
        'Sulawesi Utara', 'Lampung', 'Nusa Tenggara Barat', 'Papua', 'Maluku',
    ];

    private array $cities = [
        'Jakarta Selatan', 'Jakarta Pusat', 'Jakarta Barat', 'Jakarta Utara', 'Jakarta Timur',
        'Bandung', 'Surabaya', 'Semarang', 'Yogyakarta', 'Medan', 'Makassar', 'Palembang',
        'Denpasar', 'Tangerang', 'Tangerang Selatan', 'Bekasi', 'Depok', 'Bogor',
        'Malang', 'Solo', 'Balikpapan', 'Samarinda', 'Manado', 'Padang', 'Pekanbaru',
        'Batam', 'Banjarmasin', 'Pontianak', 'Mataram', 'Kupang', 'Ambon', 'Jayapura',
    ];

    private array $streets = [
        'Jl. Sudirman No.', 'Jl. Thamrin No.', 'Jl. Gatot Subroto No.', 'Jl. HR Rasuna Said No.',
        'Jl. Kuningan No.', 'Jl. MT Haryono No.', 'Jl. Casablanca No.', 'Jl. Tendean No.',
        'Jl. Raya Darmo No.', 'Jl. Diponegoro No.', 'Jl. Ahmad Yani No.', 'Jl. Pemuda No.',
        'Jl. Pahlawan No.', 'Jl. Veteran No.', 'Jl. Merdeka No.', 'Jl. Asia Afrika No.',
        'Jl. Braga No.', 'Jl. Dago No.', 'Jl. Raya Pajajaran No.', 'Jl. Soekarno Hatta No.',
        'Jl. Raya Bogor Km.', 'Jl. TB Simatupang No.', 'Jl. Letjen S. Parman No.',
        'Jl. Prof. Dr. Satrio No.', 'Jl. Mega Kuningan No.', 'Jl. Raya Serpong No.',
        'Jl. BSD Boulevard No.', 'Jl. Raya Kelapa Gading No.', 'Jl. Pantai Indah Kapuk No.',
    ];

    private array $emailDomains = [
        'gmail.com', 'yahoo.co.id', 'outlook.com', 'hotmail.com',
    ];

    private array $contactTypes = [
        'exhibitor', 'media-partner', 'sponsor', 'speaker', 'vendor', 'visitor', 'other',
    ];

    public function run(): void
    {
        $this->command->info('Creating contacts...');

        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Contacts will be created without creator tracking.');
        }

        $projects = Project::all();

        $bar = $this->command->getOutput()->createProgressBar(self::TOTAL);

        for ($i = 0; $i < self::TOTAL; $i++) {
            $this->createContact($users, $projects);
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info('✅ Successfully created '.self::TOTAL.' contacts!');
    }

    private function createContact($users, $projects): void
    {
        $faker = fake('id_ID');
        $creator = $users->isNotEmpty() ? $users->random() : null;

        $name = $faker->name();
        $slug = Str::slug($name);
        $company = $faker->randomElement($this->companies);
        $companySlug = Str::slug(Str::before($company, ' '));

        // Build varied email patterns
        $emails = $this->generateEmails($slug, $companySlug, $faker);

        // Build varied phone patterns
        $phones = $this->generatePhones($faker);

        // Status distribution: 75% active, 15% inactive, 10% archived
        $status = $faker->randomElement(array_merge(
            array_fill(0, 15, 'active'),
            array_fill(0, 3, 'inactive'),
            array_fill(0, 2, 'archived'),
        ));

        // Source distribution
        $source = $faker->optional(0.8)->randomElement(['event', 'event', 'event', 'referral', 'referral', 'website', 'import', 'manual']);

        // Address: 70% have address
        $address = null;
        if ($faker->boolean(70)) {
            $city = $faker->randomElement($this->cities);
            $address = [
                'street' => $faker->randomElement($this->streets).' '.$faker->numberBetween(1, 200),
                'city' => $city,
                'province' => $faker->randomElement($this->provinces),
                'postal_code' => (string) $faker->numberBetween(10110, 99999),
                'country' => 'Indonesia',
            ];
        }

        // Website: 60% have website
        $website = null;
        if ($faker->boolean(60)) {
            $website = 'https://www.'.Str::slug($company, '').'.co.id';
        }

        // Notes: 30% have notes
        $notes = null;
        if ($faker->boolean(30)) {
            $notesPool = [
                'Met at Megabuild Indonesia 2025. Very interested in booth space for next year.',
                'Key decision maker for sponsorship budget. Follow up in Q2.',
                'Referred by '.$faker->name().'. Looking for exhibition opportunities.',
                'Attended webinar on construction technology. Requested product catalog.',
                'Long-term partner since 2020. Always books premium booth.',
                'New contact from website inquiry. Interested in media partnership.',
                'VIP guest at last event. Provided excellent feedback.',
                'Government procurement officer. Requires formal proposal.',
                'Representing trade association. Can bring group of exhibitors.',
                'International buyer from Singapore. Visits Jakarta twice a year.',
                'Potential sponsor for main stage. Budget discussion pending.',
                'Media partner for online coverage. Has 500K+ followers.',
                'Speaker at industry conference. Expert in green building.',
                'Vendor for event services - AV equipment and staging.',
                'Regular visitor. Has attended all events since 2022.',
                'Interested in co-hosting a workshop session.',
                'Requires bilingual materials (English and Indonesian).',
                'Prefer communication via WhatsApp. Responds quickly.',
                'Has connections with multiple exhibitors in HVAC sector.',
                'Budget holder. Can approve sponsorship up to IDR 500M.',
            ];
            $notes = $faker->randomElement($notesPool);
        }

        $contact = Contact::create([
            'ulid' => (string) Str::ulid(),
            'name' => $name,
            'job_title' => $faker->optional(0.75)->randomElement($this->jobTitles),
            'emails' => $emails,
            'phones' => $phones,
            'company_name' => $faker->optional(0.85)->passthrough($company),
            'website' => $website,
            'address' => $address,
            'notes' => $notes,
            'source' => $source,
            'status' => $status,
            'created_by' => $creator?->id,
            'updated_by' => $creator?->id,
        ]);

        // Assign contact type (single)
        $contactType = $faker->randomElement($this->contactTypes);
        $contact->syncContactTypes([$contactType]);

        // Business categories: 50% have 1-3 categories
        if ($faker->boolean(50)) {
            $categories = $faker->randomElements(
                $this->businessCategories,
                $faker->numberBetween(1, 3)
            );
            $contact->syncBusinessCategories($categories);
        }

        // Tags: 40% have 1-4 tags
        if ($faker->boolean(40)) {
            $contactTags = $faker->randomElements(
                $this->tags,
                $faker->numberBetween(1, 4)
            );
            $contact->syncContactTags($contactTags);
        }

        // Project association: 30% are associated with 1-2 projects
        if ($projects->isNotEmpty() && $faker->boolean(30)) {
            $projectIds = $projects->random(min($faker->numberBetween(1, 2), $projects->count()))->pluck('id');
            $contact->projects()->attach($projectIds);
        }
    }

    /**
     * @return array<string>
     */
    private function generateEmails(string $slug, string $companySlug, \Faker\Generator $faker): array
    {
        $emails = [];

        // Primary email: 50% corporate, 50% personal
        if ($faker->boolean(50)) {
            $emails[] = $slug.'@'.$companySlug.'.co.id';
        } else {
            $emails[] = $slug.'@'.$faker->randomElement($this->emailDomains);
        }

        // 25% have a second email
        if ($faker->boolean(25)) {
            $emails[] = $slug.'.'.$faker->randomElement(['work', 'personal', 'office']).'@'.$faker->randomElement($this->emailDomains);
        }

        return $emails;
    }

    /**
     * @return array<string>
     */
    private function generatePhones(\Faker\Generator $faker): array
    {
        $phones = [];

        // Primary phone: Indonesian mobile
        $prefixes = ['0811', '0812', '0813', '0814', '0815', '0816', '0817', '0818', '0819',
            '0821', '0822', '0823', '0852', '0853', '0856', '0857', '0858',
            '0877', '0878', '0895', '0896', '0897', '0898', '0899'];
        $phones[] = $faker->randomElement($prefixes).$faker->numerify('########');

        // 20% have a second phone (office/landline)
        if ($faker->boolean(20)) {
            $areaCodes = ['021', '022', '031', '024', '061', '0411', '0274'];
            $phones[] = $faker->randomElement($areaCodes).'-'.$faker->numerify('#######');
        }

        return $phones;
    }
}
