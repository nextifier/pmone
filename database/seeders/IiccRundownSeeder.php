<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\RundownItem;
use Illuminate\Database\Seeder;

class IiccRundownSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::where('username', 'askindo')->firstOrFail();
        $event = $project->events()->where('slug', 'iicc-2026')->firstOrFail();

        // Wipe existing rundown items for clean re-seed
        RundownItem::where('event_id', $event->id)->forceDelete();

        // Speakers / moderators / panelists translations (mirror i18n keys)
        $speakers = [
            'jeffreyHaribowo' => ['en' => 'Jeffrey Haribowo, Chairman of INCA (ASKINDO)', 'id' => 'Jeffrey Haribowo, Ketua INCA (ASKINDO)'],
            'michelArrion' => ['en' => 'Michel Arrion, Executive Director, ICCO', 'id' => 'Michel Arrion, Executive Director, ICCO'],
            'zulkifliHasan' => ['en' => 'H.E. Zulkifli Hasan, Coordinating Minister for Food Affairs', 'id' => 'H.E. Zulkifli Hasan, Menko Pangan'],
            'gibranRakabuming' => ['en' => 'H.E. Gibran Rakabuming, Vice President', 'id' => 'H.E. Gibran Rakabuming, Wakil Presiden RI'],
            'widiastuti' => ['en' => 'Widiastuti, Deputy for Food and Agriculture Business Coordination, Coordinating Ministry for Food Affairs', 'id' => 'Widiastuti, Deputi Koordinasi Pangan & Pertanian, Kemenko Pangan'],
            'mohammadAlfansyah' => ['en' => 'Mohammad Alfansyah, Director of Downstream Fund Disbursement, National Plantation Fund Development Agency (BPDP)', 'id' => 'Mohammad Alfansyah, Direktur Penyaluran Dana Hilir, Badan Pengelola Dana Perkebunan (BPDP)'],
            'andiAmranSulaiman' => ['en' => 'H.E. Andi Amran Sulaiman, Minister of Agriculture', 'id' => 'H.E. Andi Amran Sulaiman, Menteri Pertanian'],
            'budiSantoso' => ['en' => 'H.E. Budi Santoso, Minister of Trade, Republic of Indonesia', 'id' => 'H.E. Budi Santoso, Menteri Perdagangan RI'],
            'kashanRashid' => ['en' => 'Kashan Rashid, Managing Director, Cargill Southeast Asia, Australia and New Zealand', 'id' => 'Kashan Rashid, Managing Director, Cargill Asia Tenggara, Australia dan Selandia Baru'],
            'agusGumiwang' => ['en' => 'H.E. Agus Gumiwang Kartasasmita, Minister of Industry, Republic of Indonesia', 'id' => 'H.E. Agus Gumiwang Kartasasmita, Menteri Perindustrian RI'],
            'sikstusGusli' => ['en' => 'Prof. Sikstus Gusli, Hasanuddin University', 'id' => 'Prof. Sikstus Gusli, Universitas Hasanuddin'],
            'djatmikoBris' => ['en' => 'Djatmiko Bris Witjaksono, Director General for International Trade Negotiation, Ministry of Trade', 'id' => 'Djatmiko Bris Witjaksono, Dirjen Perundingan Perdagangan Internasional, Kemendag'],
        ];

        $moderators = [
            's1' => ['en' => 'Fay Fay Choo, Mars, Incorporated', 'id' => 'Fay Fay Choo, Mars, Incorporated'],
            's2' => ['en' => 'Chandra Panjiwibowo, Senior Director for Asia Pacific, Rainforest Alliance', 'id' => 'Chandra Panjiwibowo, Senior Director Asia Pasifik, Rainforest Alliance'],
            's3' => ['en' => 'Dini Astika Sari, Head of Indonesian Coffee and Cocoa Research Institute', 'id' => 'Dini Astika Sari, Kepala Puslitkoka Indonesia'],
            's4' => ['en' => 'Abi Ismarrahman, Sustainable Commodities Adviser, FCDO', 'id' => 'Abi Ismarrahman, Sustainable Commodities Adviser, FCDO'],
            's5' => ['en' => 'Ida Bagus Namarupa, Co-founder, PT Bali Coklat (Junglegold)', 'id' => 'Ida Bagus Namarupa, Co-founder, PT Bali Coklat (Junglegold)'],
        ];

        $panelists = [
            's1' => [
                'en' => ['Putu Juli Ardika, Director General Agro Industry, Ministry of Industry, Republic of Indonesia', 'Rama G. Notowidigdo, Cocoa and Coffee Task Force, Ministry of National Development Planning (BAPPENAS)', 'Cedric van Cutsem, Senior Director Cocoa Life, Mondelez International'],
                'id' => ['Putu Juli Ardika, Direktur Jenderal Industri Agro, Kementerian Perindustrian RI', 'Rama G. Notowidigdo, Satgas Kakao dan Kopi, Kementerian PPN/BAPPENAS', 'Cedric van Cutsem, Senior Director Cocoa Life, Mondelez International'],
            ],
            's2' => [
                'en' => ['Joko Tri Haryanto, President Director, Indonesian Environment Fund (BPDLH)', 'Franka Braun, World Bank', 'Ismet Khaeruddin, Advisor for Biodiversity and Management of Protected Areas, GIZ (GrowHer: Kakao)', 'Saskia Tjokro, Director, Angel Investor Network (ANGIN) Advisory'],
                'id' => ['Joko Tri Haryanto, Direktur Utama, Badan Pengelola Dana Lingkungan Hidup (BPDLH)', 'Franka Braun, World Bank', 'Ismet Khaeruddin, Advisor Biodiversitas & Pengelolaan Kawasan Lindung, GIZ (GrowHer: Kakao)', 'Saskia Tjokro, Director, Angel Investor Network (ANGIN) Advisory'],
            ],
            's3' => [
                'en' => ['Leonardo Teguh, Deputy of Food and Agriculture, National Development Planning Agency (BAPPENAS)', 'Meine van Noordwijk, Wageningen University', 'Andrew Ward, Regional Research Senior Manager, Mars, Incorporated', 'Puji Lestari, Chairman of Research of Organization for Agriculture and Food, Indonesia National Research & Innovation Agency (BRIN)'],
                'id' => ['Leonardo Teguh, Deputi Bidang Pangan dan Pertanian, BAPPENAS', 'Meine van Noordwijk, Wageningen University', 'Andrew Ward, Regional Research Senior Manager, Mars, Incorporated', 'Puji Lestari, Ketua OR Pertanian dan Pangan, Badan Riset dan Inovasi Nasional (BRIN)'],
            ],
            's4' => [
                'en' => ['Brook Chang, Director Sustainability Asia Pacific, Cargill', 'Harm Haverkort, Acorn Partnerships Lead Asia, Acorn Project, Rabobank', 'Imam Suharto, Head of Cocoa Sustainability, ofi', 'Tim McCoy, Director, Cocoa Partnerships, Hershey Company'],
                'id' => ['Brook Chang, Director Sustainability Asia Pasifik, Cargill', 'Harm Haverkort, Acorn Partnerships Lead Asia, Acorn Project, Rabobank', 'Imam Suharto, Head of Cocoa Sustainability, ofi', 'Tim McCoy, Director, Cocoa Partnerships, Hershey Company'],
            ],
            's5' => [
                'en' => ['Masahide Wada, Global Accelerator, Raw Materials Sourcing & Supply Chain Excellence Global Value Chain for Sustainability, Glico Global', 'Fiona Hor, Director Trading & Risk Management Cocoa and Chocolate, Cargill Southeast Asia, Australia and New Zealand', 'Baldwin Jehanno, CEO, Jika Chocolat', 'Vijay Kumar Yadav, Vice President, Transgraph Consulting', 'Catherine Entzminger, Director General, European Cocoa Association'],
                'id' => ['Masahide Wada, Global Accelerator, Raw Materials Sourcing & Supply Chain Excellence Global Value Chain for Sustainability, Glico Global', 'Fiona Hor, Director Trading & Risk Management Cocoa and Chocolate, Cargill Asia Tenggara, Australia dan Selandia Baru', 'Baldwin Jehanno, CEO, Jika Chocolat', 'Vijay Kumar Yadav, Vice President, Transgraph Consulting', 'Catherine Entzminger, Director General, European Cocoa Association'],
            ],
        ];

        $events = [
            'registration' => ['en' => 'Registration', 'id' => 'Registrasi'],
            'nationalAnthem' => ['en' => 'National Anthem: Indonesia Raya', 'id' => 'Lagu Kebangsaan Indonesia Raya'],
            'welcomeDance' => ['en' => 'Welcome Dance', 'id' => 'Tarian Penyambutan'],
            'openingRemarks' => ['en' => 'Opening & Welcome', 'id' => 'Sambutan & Pembukaan'],
            'welcomeSpeech' => ['en' => 'Welcome Speech', 'id' => 'Welcome Speech'],
            'keynoteAddress' => ['en' => 'Keynote Speech', 'id' => 'Keynote Speech'],
            'panelDiscussion' => ['en' => 'Panel Discussion', 'id' => 'Diskusi Panel'],
            'sessionRemark' => ['en' => 'Session Remark', 'id' => 'Pengantar Sesi'],
            'lunchBreak' => ['en' => 'Lunch', 'id' => 'Makan Siang'],
            'cocoaBreak' => ['en' => 'Cocoa Break', 'id' => 'Cocoa Break'],
            'vipTour' => ['en' => 'VIP Exhibition Area Tour & Press Conference', 'id' => 'Tur Pameran VIP & Press Conference'],
            'welcomeCocktail' => ['en' => 'Welcome Cocktail at Tentrem Hotel', 'id' => 'Welcome Cocktail di Hotel Tentrem'],
            'welcomeSpeechOfi' => ['en' => 'Welcome Speech by: ofi', 'id' => 'Welcome Speech oleh: ofi'],
            'openingEntertainment' => ['en' => 'Opening Entertainment', 'id' => 'Hiburan Pembuka'],
            'wrapUp' => ['en' => 'Wrap Up and Summary', 'id' => 'Rangkuman & Penutupan'],
            'announcement' => ['en' => 'Announcement by ASKINDO', 'id' => 'Pengumuman ASKINDO'],
            'grindRelease' => ['en' => 'Indonesia Q2 Grind Release', 'id' => 'Rilis Data Giling Indonesia Q2'],
            'vvipDialogue' => ['en' => 'VVIP Dialogue', 'id' => 'Dialog VVIP'],
            'preDinnerDelegates' => ['en' => 'Pre-Dinner Cocktail for Delegates', 'id' => 'Pre-dinner Cocktail untuk delegasi'],
            'travelToDinner' => ['en' => 'Travel from Tentrem Hotel to Ndalem Ngabean Resto', 'id' => 'Perjalanan ke Ndalem Ngabean Resto'],
            'preDinnerCocktail' => ['en' => 'Pre-dinner Cocktail', 'id' => 'Pre-dinner Cocktail'],
            'galaDinner' => ['en' => 'Royal Dinner at nDalem Ngabean', 'id' => 'Royal Dinner di nDalem Ngabean'],
            'hostedBy' => ['en' => 'Hosted by: His Majesty Sri Sultan Hamengkubuwono X', 'id' => 'Tuan Rumah: Sri Sultan Hamengkubuwono X'],
            'panelS1' => ['en' => "Synergy and Strategic Collaboration of Public and Private Sector in Advancing Indonesia's Cocoa Production", 'id' => 'Sinergi & Kolaborasi Strategis Publik-Swasta untuk Produksi Kakao Indonesia'],
            'panelDescS1' => ['en' => "Indonesian cocoa production experienced a peak about 12 years ago, ranking as the world's third- or even second-largest producer. However, due to various challenges, Indonesia now ranks seventh in the world and imports approximately 200,000 tons of cocoa beans annually. To boost production again, synergy and collaboration are needed across all parties: government, private companies, farmers, research institutions, associations, NGOs, and universities.", 'id' => 'Produksi kakao Indonesia pernah mencapai puncak sekitar 12 tahun lalu, saat itu kita produsen terbesar ketiga bahkan kedua dunia. Sekarang posisi Indonesia turun ke peringkat tujuh dan mengimpor sekitar 200.000 ton biji kakao per tahun. Untuk mendongkrak produksi lagi, sinergi semua pihak jadi kunci: pemerintah, swasta, petani, lembaga riset, asosiasi, NGO, dan universitas.'],
            'panelS2' => ['en' => 'Urgency of Investing in Cocoa and Advancing the Role of Women & Youth in Delivering Modern Innovation', 'id' => 'Urgensi Investasi Kakao & Peran Perempuan dan Pemuda dalam Inovasi'],
            'panelDescS2' => ['en' => "Increasing Indonesian cocoa production requires modern innovations, considering that existing problems are not only technical but also encompass economic, social, and environmental aspects. Government policies must be truly responsive to these challenges and consider farmers' financial strength. The involvement of women and young people is crucial, given that women are the co-pilots in farming households, while young people are crucial players in the sustainability of farming businesses.", 'id' => 'Meningkatkan produksi kakao Indonesia butuh inovasi modern. Masalah yang ada bukan cuma teknis, tapi juga ekonomi, sosial, dan lingkungan. Kebijakan pemerintah harus responsif dan mempertimbangkan kondisi finansial petani. Peran perempuan dan anak muda sangat penting - perempuan adalah tulang punggung rumah tangga petani, sementara anak muda jadi kunci keberlanjutan usaha tani.'],
            'panelS3' => ['en' => 'Road towards Indonesia 2045 Cocoa Golden Age', 'id' => 'Menuju Era Keemasan Kakao Indonesia 2045'],
            'panelDescS3' => ['en' => 'To achieve Golden Indonesia by 2045, research is needed to support the production of cocoa bean raw materials that can meet domestic industry needs, meet global market standards, and benefit all business actors. Research priorities must be based on the needs of all actors in the value chain, and results must be transformed into guidelines for field implementation, particularly for farmers.', 'id' => 'Menuju Indonesia Emas 2045, riset perlu mendukung produksi bahan baku kakao yang memenuhi kebutuhan industri domestik, standar pasar global, dan menguntungkan semua pelaku usaha. Prioritas riset harus berbasis kebutuhan seluruh aktor di value chain, dan hasilnya bisa diterapkan langsung di lapangan, terutama untuk petani.'],
            'panelS4' => ['en' => 'Sustainable Cocoa as Way Forward for Environmental Conservation and Global Carbon Footprint Reduction', 'id' => 'Kakao Berkelanjutan untuk Konservasi Lingkungan & Pengurangan Jejak Karbon'],
            'panelDescS4' => ['en' => 'Sustainable cocoa businesses can only be achieved if all activities, from on-farm to off-farm and distribution, consistently consider environmental conservation and carbon emission reduction. The concept of carbon trading and innovations aimed at environmental conservation must be truly implemented down to the farmer level.', 'id' => 'Bisnis kakao berkelanjutan hanya tercapai kalau semua aktivitas - dari on-farm, off-farm, sampai distribusi - konsisten memperhatikan kelestarian lingkungan dan pengurangan emisi karbon. Konsep carbon trading dan inovasi konservasi lingkungan harus benar-benar diimplementasikan sampai ke level petani.'],
            'panelS5' => ['en' => 'Navigating the Shifts of Cocoa Supply & Demand and Advancing Global Market Access for Cocoa & Chocolate', 'id' => 'Dinamika Supply & Demand Kakao serta Akses Pasar Global'],
            'panelDescS5' => ['en' => 'Global demand for cocoa and chocolate is constantly changing, driven by consumer trends. Cocoa and chocolate producing countries must be prepared to adapt their supply to meet demand. Continuous monitoring by producers is essential to ensure their products remain popular with consumers.', 'id' => 'Permintaan global untuk kakao dan cokelat terus berubah mengikuti tren konsumen. Negara produsen harus siap menyesuaikan supply untuk memenuhi demand. Monitoring berkelanjutan oleh produsen kakao dan cokelat jadi kunci agar produk tetap relevan di pasar.'],
        ];

        $sessions = [
            's1' => ['title' => ['en' => 'Session I', 'id' => 'Sesi I'], 'theme' => ['en' => "Recipe of Success for Indonesia's Cocoa: Reward of Public and Private Sector Synergies", 'id' => 'Resep Sukses Kakao Indonesia: Sinergi Sektor Publik & Swasta']],
            's2' => ['title' => ['en' => 'Session II', 'id' => 'Sesi II'], 'theme' => ['en' => 'Empowering Cocoa: Adaptive Policies, Funding & Financing, Women & Youth', 'id' => 'Memberdayakan Kakao: Kebijakan, Pendanaan, Perempuan & Pemuda']],
            's3' => ['title' => ['en' => 'Session III', 'id' => 'Sesi III'], 'theme' => ['en' => 'Legacy to Come: Future Research and Building Momentum for the Long-Term', 'id' => 'Warisan Masa Depan: Riset & Momentum Jangka Panjang']],
            's4' => ['title' => ['en' => 'Session IV', 'id' => 'Sesi IV'], 'theme' => ['en' => "Building Cocoa's Resiliency: Sustainability, Carbon Markets, and Innovation Driven by Cocoa Sector", 'id' => 'Ketahanan Kakao: Keberlanjutan, Pasar Karbon & Inovasi']],
            's5' => ['title' => ['en' => 'Session V', 'id' => 'Sesi V'], 'theme' => ['en' => "Global Cocoa Market: Delivering Cocoa & Chocolate to the World's Consumers", 'id' => 'Pasar Kakao Global: Kakao & Cokelat untuk Konsumen Dunia']],
            'closing' => ['title' => ['en' => 'Closing Ceremony', 'id' => 'Penutupan']],
        ];

        $rows = [];

        // Day 1 - 2026-07-22
        $d1 = '2026-07-22';
        $rows = array_merge($rows, [
            ['date' => $d1, 'start' => '07:30', 'end' => '08:30', 'title' => $events['registration']],
            ['date' => $d1, 'start' => '08:30', 'end' => '08:45', 'title' => $events['nationalAnthem']],
            ['date' => $d1, 'start' => '08:45', 'end' => '08:55', 'title' => $events['welcomeDance']],
            ['date' => $d1, 'start' => '08:55', 'end' => '09:05', 'title' => $events['openingRemarks'], 'speaker' => $speakers['jeffreyHaribowo']],
            ['date' => $d1, 'start' => '09:05', 'end' => '09:15', 'title' => $events['welcomeSpeech'], 'speaker' => $speakers['michelArrion']],
            ['date' => $d1, 'start' => '09:15', 'end' => '09:30', 'title' => $events['keynoteAddress'], 'speaker' => $speakers['zulkifliHasan']],
            ['date' => $d1, 'start' => '09:30', 'end' => '09:45', 'title' => $events['keynoteAddress'], 'speaker' => $speakers['gibranRakabuming']],
            ['date' => $d1, 'start' => '09:45', 'end' => '10:00', 'title' => $events['cocoaBreak'], 'subtitle' => $events['vipTour']],
            ['date' => $d1, 'title' => $sessions['s1']['title'], 'theme' => $sessions['s1']['theme']],
            ['date' => $d1, 'start' => '10:00', 'end' => '10:15', 'title' => $events['keynoteAddress'], 'speaker' => $speakers['widiastuti']],
            ['date' => $d1, 'start' => '10:15', 'end' => '12:00', 'title' => $events['panelDiscussion'], 'subtitle' => $events['panelS1'], 'description' => $events['panelDescS1'], 'moderator' => $moderators['s1'], 'panelistKey' => 's1'],
            ['date' => $d1, 'start' => '12:00', 'end' => '13:00', 'title' => $events['lunchBreak']],
            ['date' => $d1, 'title' => $sessions['s2']['title'], 'theme' => $sessions['s2']['theme']],
            ['date' => $d1, 'start' => '13:00', 'end' => '13:15', 'title' => $events['sessionRemark'], 'speaker' => $speakers['mohammadAlfansyah']],
            ['date' => $d1, 'start' => '13:15', 'end' => '15:00', 'title' => $events['panelDiscussion'], 'subtitle' => $events['panelS2'], 'description' => $events['panelDescS2'], 'moderator' => $moderators['s2'], 'panelistKey' => 's2'],
            ['date' => $d1, 'title' => $sessions['s3']['title'], 'theme' => $sessions['s3']['theme']],
            ['date' => $d1, 'start' => '15:00', 'end' => '15:15', 'title' => $events['sessionRemark'], 'speaker' => $speakers['andiAmranSulaiman']],
            ['date' => $d1, 'start' => '15:15', 'end' => '17:00', 'title' => $events['panelDiscussion'], 'subtitle' => $events['panelS3'], 'description' => $events['panelDescS3'], 'moderator' => $moderators['s3'], 'panelistKey' => 's3'],
            ['date' => $d1, 'start' => '17:30', 'end' => '18:30', 'title' => $events['welcomeCocktail'], 'subtitle' => $events['welcomeSpeechOfi']],
        ]);

        // Day 2 - 2026-07-23
        $d2 = '2026-07-23';
        $rows = array_merge($rows, [
            ['date' => $d2, 'start' => '08:00', 'end' => '09:45', 'title' => $events['registration']],
            ['date' => $d2, 'start' => '09:45', 'end' => '10:00', 'title' => $events['openingEntertainment']],
            ['date' => $d2, 'start' => '10:00', 'end' => '10:15', 'title' => $events['keynoteAddress'], 'speaker' => $speakers['budiSantoso']],
            ['date' => $d2, 'start' => '10:15', 'end' => '10:30', 'title' => $events['keynoteAddress'], 'speaker' => $speakers['kashanRashid']],
            ['date' => $d2, 'title' => $sessions['s4']['title'], 'theme' => $sessions['s4']['theme']],
            ['date' => $d2, 'start' => '10:30', 'end' => '12:00', 'title' => $events['panelDiscussion'], 'subtitle' => $events['panelS4'], 'description' => $events['panelDescS4'], 'moderator' => $moderators['s4'], 'panelistKey' => 's4'],
            ['date' => $d2, 'start' => '12:00', 'end' => '13:00', 'title' => $events['lunchBreak']],
            ['date' => $d2, 'title' => $sessions['s5']['title'], 'theme' => $sessions['s5']['theme']],
            ['date' => $d2, 'start' => '13:00', 'end' => '13:15', 'title' => $events['sessionRemark'], 'speaker' => $speakers['djatmikoBris']],
            ['date' => $d2, 'start' => '13:15', 'end' => '15:00', 'title' => $events['panelDiscussion'], 'subtitle' => $events['panelS5'], 'description' => $events['panelDescS5'], 'moderator' => $moderators['s5'], 'panelistKey' => 's5'],
            ['date' => $d2, 'title' => $sessions['closing']['title']],
            ['date' => $d2, 'start' => '15:00', 'end' => '15:30', 'title' => $events['wrapUp'], 'speaker' => $speakers['sikstusGusli']],
            ['date' => $d2, 'start' => '15:30', 'end' => '16:00', 'title' => $events['announcement']],
            ['date' => $d2, 'start' => '16:00', 'end' => '16:15', 'title' => $events['grindRelease'], 'speaker' => $speakers['agusGumiwang']],
            ['date' => $d2, 'start' => '16:15', 'end' => '17:15', 'title' => $events['vvipDialogue'], 'subtitle' => $events['preDinnerDelegates']],
            ['date' => $d2, 'start' => '17:15', 'end' => '18:00', 'title' => $events['travelToDinner']],
            ['date' => $d2, 'start' => '18:00', 'end' => '19:00', 'title' => $events['preDinnerCocktail']],
            ['date' => $d2, 'start' => '19:00', 'end' => null, 'title' => $events['galaDinner'], 'subtitle' => $events['hostedBy']],
        ]);

        // Day 3 - 2026-07-24 - Field trip
        $d3 = '2026-07-24';
        $rows[] = [
            'date' => $d3,
            'start' => '08:00',
            'end' => null,
            'title' => ['en' => 'Cocoa Field Trip', 'id' => 'Cocoa Field Trip'],
            'description' => ['en' => 'Meet at Tentrem Ballroom Lobby for Field Trip to Chocolate Artisan Maker in Yogyakarta.', 'id' => 'Kumpul di Lobby Ballroom Tentrem untuk Field Trip ke pembuat cokelat artisan di Yogyakarta.'],
        ];

        $order = 0;
        foreach ($rows as $row) {
            $order++;

            $speakersJson = null;
            if (isset($row['speaker'])) {
                $speakersJson = [
                    'en' => [['name' => $row['speaker']['en']]],
                    'id' => [['name' => $row['speaker']['id']]],
                ];
            }

            $panelistsJson = null;
            if (isset($row['panelistKey'])) {
                $key = $row['panelistKey'];
                $panelistsJson = [
                    'en' => array_map(fn ($n) => ['name' => $n], $panelists[$key]['en']),
                    'id' => array_map(fn ($n) => ['name' => $n], $panelists[$key]['id']),
                ];
            }

            $item = new RundownItem;
            $item->event_id = $event->id;
            $item->date = $row['date'];
            $item->start_time = $row['start'] ?? null;
            $item->end_time = $row['end'] ?? null;
            $item->is_active = true;
            $item->order_column = $order;

            $item->setTranslations('title', $row['title']);
            if (isset($row['subtitle'])) {
                $item->setTranslations('subtitle', $row['subtitle']);
            }
            if (isset($row['description'])) {
                $item->setTranslations('description', $row['description']);
            }
            if (isset($row['theme'])) {
                $item->setTranslations('theme', $row['theme']);
            }
            if (isset($row['moderator'])) {
                $item->setTranslations('moderator', $row['moderator']);
            }

            if ($speakersJson) {
                $item->speakers = $speakersJson;
            }
            if ($panelistsJson) {
                $item->panelists = $panelistsJson;
            }

            $item->save();
        }

        $this->command->info('Inserted '.count($rows).' rundown items for IICC 2026.');
    }
}
