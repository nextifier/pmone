<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GlobalAiExpoPostsSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('username', 'globalaiexpo')->first();

        if (! $user) {
            $this->command->error('User globalaiexpo tidak ditemukan. Buat user dulu.');

            return;
        }

        // Idempoten: hapus post lama dari user ini
        $existing = Post::byCreator($user->id)->withTrashed()->get();
        foreach ($existing as $p) {
            $p->forceDelete();
        }
        $this->command->info("Cleared {$existing->count()} existing posts for {$user->username}.");

        $articles = $this->articles();
        $imageDir = database_path('seeders/global-ai-expo-posts');

        foreach ($articles as $i => $row) {
            $publishedAt = now()->subDays($i * 2)->subHours(rand(0, 14));

            $post = new Post([
                'title' => $row['title'],
                'excerpt' => $row['excerpt'],
                'content' => $row['content'],
                'content_format' => 'html',
                'status' => 'published',
                'visibility' => 'public',
                'featured' => $row['featured'] ?? false,
                'published_at' => $publishedAt,
                'source' => 'native',
                'settings' => [],
            ]);
            $post->created_by = $user->id;
            $post->updated_by = $user->id;
            $post->save();

            // Author via pivot
            $post->authors()->syncWithoutDetaching([$user->id => ['order' => 0]]);

            // Tags
            if (! empty($row['tags'])) {
                $post->syncPostTags($row['tags']);
            }

            // Categories
            if (! empty($row['categories'])) {
                $post->syncTagsWithType($row['categories'], 'category');
            }

            // Featured image
            $imagePath = $imageDir.'/'.sprintf('%02d', $i + 1).'.jpg';
            if (file_exists($imagePath)) {
                $post->addMedia($imagePath)
                    ->preservingOriginal()
                    ->usingName($row['title'])
                    ->toMediaCollection('featured_image');
            } else {
                $this->command->warn("Image missing for post #{$i}: {$imagePath}");
            }

            $this->command->info('OK: '.$post->title);
        }

        // Re-set published_at karena Post::boot() updating event akan
        // override published_at jika status berubah. Kita pastikan sesuai
        // jadwal di array.
        $this->resyncPublishedAt($user, $articles);

        $this->command->info('Done. '.count($articles).' posts created.');
    }

    private function resyncPublishedAt(User $user, array $articles): void
    {
        $posts = Post::byCreator($user->id)->orderBy('id')->get();
        foreach ($posts as $i => $post) {
            $publishedAt = now()->subDays($i * 2)->subHours(rand(0, 14));
            DB::table('posts')->where('id', $post->id)->update(['published_at' => $publishedAt]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function articles(): array
    {
        return [
            // 1
            [
                'title' => 'Tiga Hari di Global AI Expo 2026: Peta Sesi yang Wajib Anda Hadiri',
                'excerpt' => 'Tiga hari, tiga panggung utama, lebih dari 80 sesi. Inilah cara membaca jadwal supaya Anda pulang dengan oleh-oleh yang berbobot, bukan capek.',
                'featured' => true,
                'categories' => ['Panduan'],
                'tags' => ['Global AI Expo 2026', 'AI Indonesia', 'Panduan Acara', 'Jadwal'],
                'content' => <<<'HTML'
<p>Acara tiga hari di Jakarta Convention Center bukan tipe yang bisa Anda hadiri secara santai. Jadwal padat, beberapa sesi paralel, dan booth yang tersebar di tiga zona. Yang berhasil pulang dengan insight biasanya datang dengan rencana, bukan harapan.</p>

<p>Tulisan ini adalah peta kasar untuk membantu Anda memilih. Saya akan rangkum tiap hari berdasarkan tema utamanya dan menandai sesi yang menurut saya paling tinggi nilai praktisnya.</p>

<h2>Hari 1: Frontier Models dan Pemain Lab</h2>

<p>Hari pertama dibuka pukul 09:30 dengan keynote Sam Altman tentang arah riset OpenAI lima tahun ke depan. Setelah itu Dario Amodei dari Anthropic membahas pendekatan Constitutional AI dan kenapa cara melatih model menentukan seberapa bisa Anda mempercayainya.</p>

<p>Yang sering terlewat di hari pertama adalah panel pukul 11:00 di Plenary Hall A. Andrew Ng memoderasi empat petinggi lab (OpenAI, Anthropic, Google DeepMind, Meta) untuk membandingkan roadmap mereka. Panel ini biasanya tempat di mana asumsi-asumsi tersembunyi para lab muncul ke permukaan, bukan di keynote individual.</p>

<p>Sore hari ada fireside chat antara Jensen Huang dan Sundar Pichai tentang lapisan compute di balik AI. Bukan pembicaraan tentang chip dalam arti teknis, tapi tentang apa yang berubah ketika inference jadi murah dan ada di mana-mana. Saya rekomendasikan duduk di sesi ini meski Anda bukan engineer infrastruktur.</p>

<h2>Hari 2: Adopsi di Perusahaan</h2>

<p>Tema hari kedua bergeser ke implementasi. Satya Nadella membuka dengan keynote tentang bagaimana ribuan perusahaan beralih dari pilot ke produksi, dan apa pola yang gagal versus yang berhasil.</p>

<p>Sesi praktis terbaik di hari ini ada di workshop track. Track ini berukuran kecil (kapasitas 50 orang per sesi) dan biasanya cepat penuh. Topik yang menarik perhatian saya: agent orchestration dengan LangGraph, evaluasi LLM untuk produk consumer, dan migrasi dari prompt engineering ke fine-tuning.</p>

<p>Daftar sebelum hari acara, jangan andalkan walk-in. Tahun lalu di event serupa, workshop terbaik full bahkan sebelum jam makan siang.</p>

<h2>Hari 3: Riset, Safety, dan Apa Selanjutnya</h2>

<p>Hari ketiga lebih akademis tapi tetap relevan untuk praktisi. Yoshua Bengio memandu panel tentang AI safety dan governance. Ilya Sutskever akan ada di panel ini, bersama Daniela Amodei dan Mira Murati.</p>

<p>Sore hari ada Startup Pavilion dan pitch day. Lebih dari 60 startup AI Indonesia dan regional akan presentasi tujuh menit per tim. Cocok kalau Anda investor, atau sedang mencari ide pivot, atau kalau Anda hanya ingin tahu apa yang sedang dibangun di luar perusahaan-perusahaan besar.</p>

<h2>Beberapa Catatan Praktis</h2>

<p>Bawa power bank yang besar. Sinyal dan port charging di JCC tahun lalu lumayan susah ditemukan setelah jam 11.</p>

<p>Aplikasi acara akan tersedia tiga hari sebelum hari-H. Buka schedule builder-nya, tandai sesi Anda, dan set alarm sepuluh menit sebelum tiap sesi. Plenary Hall A dan Hall B berjarak sekitar lima menit jalan kaki.</p>

<p>Untuk networking, datang ke welcome reception malam hari pertama. Suasananya jauh lebih informal dibanding break siang. Banyak founder dan researcher yang sebenarnya susah ditemui sehari-hari justru hadir di sini.</p>

<p>Jadwal lengkap dan detail tiap sesi ada di halaman <a href="/rundown">Rundown</a>. Cek juga <a href="/speakers">daftar speakers</a> untuk profil singkat dan topik yang mereka bawakan.</p>
HTML,
            ],

            // 2
            [
                'title' => 'Sam Altman, Dario Amodei, Demis Hassabis: Apa yang Mereka Bawa ke Jakarta',
                'excerpt' => 'Tiga CEO dari tiga lab yang paling sering disebut tahun ini. Latar belakang singkat dan apa yang kemungkinan jadi inti pidato mereka.',
                'featured' => true,
                'categories' => ['Berita'],
                'tags' => ['Global AI Expo 2026', 'Speakers', 'OpenAI', 'Anthropic', 'Google DeepMind'],
                'content' => <<<'HTML'
<p>Tiga nama ini hampir selalu masuk daftar speaker mana pun yang membahas frontier AI tahun ini. Mereka memimpin tiga lab yang membentuk percakapan teknis sekaligus regulatif: OpenAI, Anthropic, dan Google DeepMind. Di Global AI Expo 2026, ketiganya akan tampil di hari pertama, dengan slot keynote berurutan.</p>

<p>Berikut profil singkat dan tebakan saya soal apa yang akan jadi titik berat pidato mereka, berdasarkan publikasi dan wawancara terbaru dari masing-masing.</p>

<h2>Sam Altman, OpenAI</h2>

<p>Altman memimpin OpenAI sejak 2019, sebelumnya presiden Y Combinator. Ia bukan engineer. Latar belakangnya investor dan operator, dan itu kelihatan dari cara OpenAI bergerak: cepat, fokus pada distribusi consumer, dan agresif soal partnership compute (Microsoft, kemudian terbentang ke Oracle dan SoftBank).</p>

<p>Slot keynote Altman dijadwalkan 09:30 di Plenary Hall A. Topiknya "The Next Decade of AI". Dari blog post terbaru OpenAI tentang superintelligence dan economic impact, kemungkinan ia akan membicarakan tiga hal: timeline AGI menurut versi mereka, bagaimana economic productivity berubah ketika agen jadi norma, dan apa peran negara di luar AS dan Cina dalam ekosistem ini.</p>

<p>Yang menarik diperhatikan: bagaimana ia menyentuh isu safety. OpenAI sudah beberapa kali kehilangan tim safety internal mereka sejak 2023. Pertanyaan dari moderator atau audience kemungkinan akan menyinggung ini.</p>

<h2>Dario Amodei, Anthropic</h2>

<p>Amodei dan adiknya, Daniela Amodei, mendirikan Anthropic setelah keluar dari OpenAI. Dario sebelumnya VP of Research di OpenAI, sekarang CEO Anthropic. Latar belakangnya akademis, PhD biophysics dari Princeton, dan ia menulis riset cukup teknis tentang alignment dan interpretability.</p>

<p>Sesi Dario di 10:00, langsung setelah Altman. Topiknya "Constitutional AI and Building Models You Can Trust". Anthropic membangun reputasi mereka di safety, dan Constitutional AI adalah pendekatan training yang membuat model belajar dari aturan eksplisit, bukan hanya feedback manusia.</p>

<p>Pidato Dario biasanya lebih teknis dibanding Altman. Saya menebak ia akan menyinggung paper Anthropic tentang sleeper agents dan deceptive alignment, plus update terbaru mereka soal interpretability research. Kalau Anda ingin paham kenapa dua lab dengan model bagus bisa beda jauh dalam pendekatan, hadir di kedua keynote.</p>

<h2>Demis Hassabis, Google DeepMind</h2>

<p>Hassabis adalah co-founder DeepMind, sekarang CEO Google DeepMind setelah merger dengan Google Brain. Ia juga peraih Nobel Chemistry 2024 untuk AlphaFold. Latar belakang neuroscience dari University College London, dan sebelum DeepMind ia membuat game.</p>

<p>Hassabis bicara di 10:30 dengan topik "From Games to Genomes: AI as a Tool for Science". DeepMind selalu konsisten di posisi yang berbeda dari OpenAI dan Anthropic: lebih fokus ke aplikasi spesifik (protein folding, weather forecasting, math olympiad) daripada chatbot generik.</p>

<p>Kalau Anda peneliti atau dari industri yang punya domain teknis spesifik (farmasi, manufaktur, energi), keynote Hassabis kemungkinan paling actionable. Ia akan tunjukkan bagaimana model spesialis bisa melampaui model umum di domain tertentu, plus contoh kerjasama DeepMind dengan industri.</p>

<h2>Sebelum Hari-H</h2>

<p>Tiga keynote ini berurutan dalam 90 menit. Pulang dari hari pertama dengan otak penuh adalah pengalaman yang normal. Saya sarankan baca dulu paper atau interview terbaru dari masing-masing supaya ketika mereka menyebut istilah teknis tertentu, Anda tahu konteksnya.</p>

<p>Daftar bacaan singkat: post Sam Altman "The Intelligence Age" (2024), paper Anthropic "Constitutional AI" dan "Sleeper Agents", serta paper DeepMind tentang Gemini 2.5 dan AlphaProof.</p>
HTML,
            ],

            // 3
            [
                'title' => 'Frontier Model di 2026: Pertarungan GPT-5, Claude 4, dan Gemini 3',
                'excerpt' => 'Tiga lab merilis model generasi baru di waktu yang nyaris bersamaan. Apa yang berbeda dari sisi kapabilitas, harga, dan strategi distribusi.',
                'featured' => false,
                'categories' => ['Analisis'],
                'tags' => ['Global AI Expo 2026', 'Frontier Models', 'GPT-5', 'Claude', 'Gemini'],
                'content' => <<<'HTML'
<p>Awal 2026 jadi momen langka. Tiga lab besar (OpenAI, Anthropic, dan Google DeepMind) merilis model generasi baru dalam jarak kurang dari empat bulan. GPT-5 keluar di Februari, Claude 4 di Maret, dan Gemini 3 di Mei. Untuk praktisi, ini berarti benchmark, harga, dan integrasi semua bergerak sekaligus.</p>

<p>Tulisan ini bukan benchmark detail. Anda bisa baca itu di papernya masing-masing. Saya mau bahas tiga aspek yang lebih relevan untuk keputusan praktis: kapabilitas inti, biaya, dan strategi distribusi.</p>

<h2>Kapabilitas Inti</h2>

<p>Ketiganya melompat di area yang sama: long-context reasoning, agen, dan multimodal. Tapi cara mereka menempatkan kekuatan berbeda.</p>

<p>GPT-5 unggul di tool use dan agent loop. OpenAI menggabungkan model lama mereka dengan o-series reasoning ke satu interface, jadi user tidak perlu pilih mode. Untuk task yang butuh banyak panggilan API berurutan, GPT-5 saat ini paling stabil.</p>

<p>Claude 4 punya keunggulan di code dan dokumen panjang. Anthropic merilis dua varian: Claude Opus 4 untuk reasoning kelas atas, dan Claude Sonnet 4 untuk produksi cost-sensitive. Banyak tim engineering yang sudah pakai Claude Sonnet 3.5 sekarang naik ke Sonnet 4 tanpa pikir panjang. Quality jump-nya cukup signifikan di code review dan refactor.</p>

<p>Gemini 3 yang paling fleksibel di multimodal. Native video understanding (bukan extract frame lalu kirim ke vision model), audio reasoning, dan integrasi tight dengan Workspace. Kalau pekerjaan Anda butuh model membaca PDF kompleks, video meeting, atau spreadsheet, Gemini 3 sering jadi pilihan paling efisien.</p>

<h2>Biaya</h2>

<p>Ini area yang paling banyak berubah. Harga input token dropped sekitar 40 sampai 60 persen dibanding generasi sebelumnya, sementara kapabilitasnya naik. Yang paling agresif memang Gemini 3, dengan tier Flash yang harganya satu persepuluh GPT-5 untuk task yang lebih sederhana.</p>

<p>Tapi harga per token bukan satu-satunya pertimbangan. Volume context yang Anda kirim, jumlah retry, dan cache hit rate menentukan biaya nyata. Anthropic memperkenalkan prompt caching yang bisa menurunkan biaya sampai 90 persen kalau pola prompt Anda stabil. OpenAI mengikuti dengan caching otomatis di API mereka.</p>

<p>Saran praktis: hitung cost per task, bukan cost per token. Tim yang saya tahu pindah dari satu provider ke lain karena perbedaan caching, latency, atau retry rate, bukan karena harga sticker.</p>

<h2>Strategi Distribusi</h2>

<p>Di sini perbedaannya paling tajam.</p>

<p>OpenAI menyatu dengan Microsoft di B2B (Azure OpenAI) dan punya consumer app sendiri (ChatGPT) yang sudah di atas 700 juta user mingguan. Mereka juga mulai perangkat (perangkat AI, kemudian browser) yang berisiko tapi konsisten dengan strategi distribusi mereka.</p>

<p>Anthropic fokus pada enterprise dan developer. Mereka tidak punya consumer app sebesar ChatGPT, tapi dominan di market developer (Cursor, Sourcegraph, banyak coding tool berdasar Claude). Strategi go-to-market mereka adalah API-first dan partnership dengan Amazon plus Google Cloud.</p>

<p>Google DeepMind punya akses bawaan ke 3 miliar user lewat Search, Workspace, dan Android. Strategi mereka "AI di mana pengguna sudah ada", bukan menarik pengguna ke produk baru. Ini menjelaskan kenapa Gemini 3 langsung muncul di Gmail, Docs, dan Search Generative Experience tanpa ribut.</p>

<h2>Kesimpulan untuk Tim Anda</h2>

<p>Kalau tim Anda baru mulai, pakai dua model. Satu untuk eksperimen dengan kapabilitas tertinggi (Claude Opus 4 atau GPT-5), satu untuk produksi cost-sensitive (Claude Sonnet 4 atau Gemini 2.5 Flash). Bandingkan output di dataset internal, bukan di leaderboard publik.</p>

<p>Kalau tim Anda sudah pakai model lama, jangan terburu-buru. Biaya migrasi sering melebihi gain kapabilitas, kecuali workflow Anda spesifik (long context, agent loop, multimodal). Bench dulu di task nyata, baru putuskan.</p>

<p>Tiga panel di Global AI Expo 2026 membahas topik ini langsung. Cek panel "Frontier Model Roadmaps" hari pertama dan dua sesi practitioner di hari kedua.</p>
HTML,
            ],

            // 4
            [
                'title' => 'Constitutional AI dan Janji Anthropic untuk Model yang Bisa Dipercaya',
                'excerpt' => 'Constitutional AI adalah cara Anthropic melatih Claude pakai aturan eksplisit. Bagaimana cara kerjanya dan kenapa ini penting untuk pengguna enterprise.',
                'featured' => false,
                'categories' => ['Analisis'],
                'tags' => ['Global AI Expo 2026', 'AI Safety', 'Anthropic', 'Constitutional AI', 'Alignment'],
                'content' => <<<'HTML'
<p>Anthropic merilis paper "Constitutional AI" akhir 2022, dan istilah itu sejak saat itu jadi salah satu cara paling sering disebut untuk training model yang lebih bisa dipercaya. Tapi banyak yang menyebutnya tanpa benar-benar tahu apa yang ada di dalam.</p>

<p>Di Global AI Expo 2026, Dario Amodei akan bicara langsung tentang ini di sesi pukul 10:00 hari pertama. Tulisan ini ringkasan singkat supaya Anda masuk sesi dengan konteks yang sudah ada.</p>

<h2>Cara Tradisional: RLHF</h2>

<p>Sebelum bahas Constitutional AI, perlu paham apa yang ia gantikan atau lengkapi. Sebagian besar model bahasa besar dilatih pakai dua tahap. Pertama, pre-training di teks internet skala besar. Kedua, fine-tuning pakai feedback manusia, yang biasa disebut RLHF (Reinforcement Learning from Human Feedback).</p>

<p>Cara kerja RLHF: ribuan pekerja manusia membandingkan dua respons model dan memilih mana yang lebih baik. Model belajar dari pilihan ini supaya output-nya lebih sesuai harapan manusia.</p>

<p>RLHF bekerja, tapi punya batasan. Kualitas model tergantung kualitas labeler. Bias labeler ikut masuk model. Untuk topik sensitif (kesehatan mental, politik, hukum), feedback manusia tidak konsisten antar pekerja. Skalabilitas juga jadi masalah, karena melatih model frontier butuh jutaan komparasi manusia.</p>

<h2>Apa yang Berbeda dengan Constitutional AI</h2>

<p>Constitutional AI menambah satu lapisan: aturan eksplisit. Anthropic menyebutnya "konstitusi", daftar prinsip yang model harus ikuti. Sebagian dari prinsip ini diambil dari sumber publik (Universal Declaration of Human Rights, terms of service Apple, prinsip Sparrow dari DeepMind), sebagian dirancang sendiri oleh Anthropic.</p>

<p>Cara melatihnya dua tahap. Tahap pertama, model menghasilkan respons, kemudian mengkritik respons itu sendiri pakai prinsip dari konstitusi, lalu merevisi. Ini menghasilkan dataset pasangan "respons asli vs respons revisi". Tahap kedua, model lain dilatih dengan dataset ini supaya secara default sudah bias ke respons yang sesuai konstitusi.</p>

<p>Hasilnya: model yang lebih konsisten di topik sulit, dengan jejak kerja yang lebih bisa dijelaskan. Kalau Claude menolak permintaan tertentu, Anthropic bisa menunjuk prinsip mana di konstitusi yang sedang dieksekusi.</p>

<h2>Kenapa Penting untuk Enterprise</h2>

<p>Buat tim yang membangun produk dengan AI, dua hal di Constitutional AI yang relevan secara praktis.</p>

<p>Pertama, predictability. Output Claude di topik sensitif (medis, legal, finansial) lebih konsisten antar sesi dibanding model RLHF-only. Ini penting kalau produk Anda butuh behavior yang sama di banyak konteks dan banyak user.</p>

<p>Kedua, customization. Anthropic membuka kemampuan untuk membuat "system prompt" yang berfungsi seperti konstitusi tambahan untuk use case spesifik. Tim hukum bisa menulis aturan yang ketat untuk produk legal mereka, tim medis untuk produk klinis. Konstitusi dasar Anthropic tetap berlaku, tapi Anda bisa menambah lapisan untuk domain Anda sendiri.</p>

<h2>Kritik dan Batasan</h2>

<p>Bukan berarti pendekatan ini sempurna. Beberapa kritik yang sering muncul.</p>

<p>Konstitusi yang dipakai bias ke nilai-nilai Barat dan AS. Anthropic mengakui ini di papernya. Untuk audience Indonesia, sebagian respons Claude di topik agama, keluarga, atau adat bisa terasa kaku karena konstitusinya tidak mempertimbangkan konteks lokal.</p>

<p>Kedua, mengukur efektivitas konstitusi sulit. Bagaimana cara membuktikan bahwa model benar-benar mengikuti prinsip, bukan hanya output yang kelihatannya mengikuti? Anthropic punya tim interpretability yang berusaha jawab ini, tapi pertanyaannya belum tuntas.</p>

<p>Ketiga, konstitusi bukan benteng absolut. Jailbreak masih mungkin. Anthropic bahkan punya program bug bounty untuk model exploit. Constitutional AI menggeser baseline ke arah yang lebih aman, bukan menutup semua pintu.</p>

<h2>Yang Perlu Anda Tanyakan</h2>

<p>Kalau Anda evaluasi vendor model untuk produk yang diatur regulasi (kesehatan, finansial, pemerintahan), pertanyaan paling produktif bukan "model Anda aman?". Pertanyaan yang lebih bagus: "apa basis aturan yang dipakai untuk training, dan bagaimana saya bisa menambah aturan saya sendiri?".</p>

<p>Kalau jawabannya hanya "kami pakai RLHF" tanpa transparansi soal labeler atau prinsip, Anda kemungkinan beli kucing dalam karung. Constitutional AI dan dokumentasi yang menyertainya membuat Anthropic relatif lebih transparan di area ini, dan ini salah satu alasan kenapa banyak tim regulated industry condong ke Claude.</p>
HTML,
            ],

            // 5
            [
                'title' => 'Mengapa Permintaan GPU NVIDIA Menjadi Bottleneck Adopsi AI di Indonesia',
                'excerpt' => 'Di Indonesia, lead time GPU sekarang antara empat sampai delapan bulan. Bagaimana startup dan perusahaan menyiasati keterbatasan ini.',
                'featured' => false,
                'categories' => ['Berita'],
                'tags' => ['Global AI Expo 2026', 'AI Indonesia', 'NVIDIA', 'GPU', 'Infrastructure'],
                'content' => <<<'HTML'
<p>Akhir 2024 sampai pertengahan 2025, beli H100 atau H200 dari distributor resmi di Asia Tenggara butuh sabar. Lead time normal 12 sampai 16 minggu. Untuk konfigurasi DGX, beberapa pelanggan menunggu sampai 32 minggu. Di Indonesia, situasinya tidak lebih baik.</p>

<p>Permintaan GPU yang ekstrem ini memengaruhi siapa yang bisa membangun apa, dan kapan. Tulisan ini rangkuman dari beberapa percakapan dengan tim infrastruktur di startup dan perusahaan Indonesia tentang bagaimana mereka menyiasati situasi.</p>

<h2>Sumber Masalah</h2>

<p>Bukan hanya pasokan chip dari NVIDIA. Tiga faktor menumpuk sekaligus.</p>

<p>Pertama, kapasitas TSMC untuk packaging CoWoS terbatas. Ini adalah teknologi packaging yang dipakai H100 dan H200, dan TSMC tidak bisa ekspansi cepat. Permintaan dari hyperscaler AS (Microsoft, Google, Meta, Amazon, Oracle) plus Cina sudah menyerap mayoritas kapasitas.</p>

<p>Kedua, alokasi NVIDIA condong ke pelanggan besar. Order dari hyperscaler dan partner cloud terbesar dipenuhi duluan. Pembeli regional, termasuk Indonesia, masuk antrian setelah itu.</p>

<p>Ketiga, regulasi ekspor AS ke Cina menambah kerumitan. Beberapa SKU yang dirancang untuk pasar Cina (H800, A800) ditarik. Distributor Asia Tenggara harus menyesuaikan portofolio mereka.</p>

<h2>Dampak ke Tim di Indonesia</h2>

<p>Beberapa pola yang muncul dari tim yang saya ajak bicara.</p>

<p>Tim startup AI yang bootstrap: pindah ke cloud GPU. Provider seperti Lambda, RunPod, atau Oracle Cloud lebih cepat tersedia dibanding beli on-premise. Trade-off-nya jelas: biaya per jam lebih tinggi, tapi tidak ada CAPEX di muka dan tidak perlu menunggu lead time.</p>

<p>Perusahaan menengah yang sudah punya beban kerja AI: hybrid. Sebagian kerja training tetap di on-prem A100 lama, sementara untuk inference dan eksperimen pakai cloud. Yang ingin upgrade ke H100 menunggu antrian, sementara kerja jalan terus pakai apa yang ada.</p>

<p>Korporasi besar (bank, telco, perusahaan energi): negosiasi langsung dengan NVIDIA atau partner OEM mereka. Beberapa berhasil mendapat alokasi prioritas dengan komitmen volume multi-tahun. Beberapa lainnya memutuskan untuk geser strategi mereka ke vendor alternatif.</p>

<h2>Vendor Alternatif</h2>

<p>Ada beberapa pilihan, masing-masing dengan trade-off.</p>

<p>AMD MI300X. Spec-nya kompetitif dengan H100 untuk inference, dan ekosistem ROCm sudah jauh lebih matang dibanding tiga tahun lalu. Tapi tooling dan library masih lebih sedikit dibanding CUDA. Tim dengan kebutuhan fine-tuning custom sering balik ke NVIDIA setelah eksperimen.</p>

<p>Intel Gaudi 3. Lebih murah per token untuk inference, tapi adopsi enterprise di Indonesia masih kecil. Saya tidak menemukan banyak case study lokal yang bisa diverifikasi.</p>

<p>Cloud TPU dari Google. Bukan untuk training general, tapi untuk inference Gemini dan beberapa model open source yang sudah dioptimasi, harganya kompetitif. Beberapa tim Indonesia memakainya khusus untuk produksi inference.</p>

<p>Provider lokal. Beberapa data center Indonesia (TelkomSigma, Lintasarta, NeutraDC) mulai menawarkan GPU as a service. Pasokan terbatas, tapi punya keuntungan latency dan kepatuhan data lokal.</p>

<h2>Pertanyaan Strategis</h2>

<p>Buat tim yang sedang merancang infrastruktur AI mereka, beberapa pertanyaan yang perlu dijawab dulu sebelum komit.</p>

<p>Apa rasio training versus inference di workflow Anda? Kalau training-heavy, NVIDIA tetap pilihan default tapi Anda harus siap menunggu atau pakai cloud. Kalau inference-heavy, alternatif AMD dan Intel layak diuji.</p>

<p>Apa toleransi Anda untuk kompleksitas tooling? CUDA punya ekosistem terbesar. ROCm dan vendor lain butuh investasi engineering tambahan.</p>

<p>Berapa lama horizon perencanaan Anda? Kalau Anda butuh kapasitas dalam tiga bulan, jawabannya pasti cloud. Kalau dua tahun, on-premise jadi mungkin lagi karena lead time akan turun saat kapasitas TSMC bertambah dan generasi baru (Blackwell, Rubin) diluncurkan.</p>

<h2>Yang Akan Dibahas di Acara</h2>

<p>Hari pertama Global AI Expo 2026 ada fireside chat antara Jensen Huang dan Sundar Pichai pukul 13:30 yang membahas tema ini langsung. Ada juga workshop tentang cost optimization untuk inference di hari kedua. Untuk tim infrastruktur, dua sesi ini sepadan dengan harga tiket sendirian.</p>
HTML,
            ],

            // 6
            [
                'title' => 'AI untuk UMKM: Tiga Studi Kasus dari Pelaku yang Sudah Mulai',
                'excerpt' => 'Tiga UMKM Indonesia memakai AI dalam pekerjaan harian mereka. Tools apa yang mereka pakai, hasil apa yang mereka dapat, dan kesalahan yang mereka pelajari.',
                'featured' => true,
                'categories' => ['Panduan'],
                'tags' => ['Global AI Expo 2026', 'AI Indonesia', 'UMKM', 'Studi Kasus', 'Adopsi AI'],
                'content' => <<<'HTML'
<p>Cerita adopsi AI di media biasanya tentang perusahaan global atau startup yang sudah dapat investasi puluhan juta dolar. Yang jarang dibahas: UMKM yang sehari-hari pakai AI sebagai alat kerja, tanpa tim engineering, tanpa konsultan.</p>

<p>Saya ngobrol dengan tiga pemilik UMKM Indonesia tentang cara mereka mengadopsi AI. Berikut versi singkatnya. Nama produk dan beberapa angka di-blur sesuai permintaan.</p>

<h2>Kasus 1: Toko Furniture Custom di Jepara</h2>

<p>Pemilik 38 tahun, mengelola toko furniture jati custom yang sebagian besar order dari luar pulau. Sebelum pakai AI, ia menghabiskan tiga sampai empat jam per hari membalas pertanyaan WhatsApp dari calon pembeli, sebagian besar pertanyaan teknis berulang (jenis kayu, perawatan, dimensi standar, opsi pengiriman).</p>

<p>Solusi yang ia pakai: ChatGPT Plus dengan custom GPT yang ia buat sendiri, plus Notion AI untuk membuat draft proposal. Workflow-nya: dia copy paste pertanyaan dari WhatsApp ke custom GPT, GPT itu menghasilkan draft jawaban dalam bahasa yang sesuai (Indonesia formal, kasual, atau Inggris kalau pembeli dari luar). Dia review, edit kalau perlu, baru kirim.</p>

<p>Hasil setelah enam bulan: waktu menjawab WhatsApp turun ke 60 sampai 90 menit per hari. Konversi dari pertanyaan ke pesanan naik sekitar 15 persen, menurut catatannya, karena response time lebih cepat dan kualitas jawaban lebih konsisten.</p>

<p>Yang ia salah di awal: terlalu mengandalkan AI tanpa edit. Beberapa kali AI memberi spesifikasi yang sebenarnya tidak ia tawarkan, dan pelanggan kecewa saat barang datang berbeda. Sekarang ia selalu edit minimal, dan ada checklist mental sebelum kirim balasan.</p>

<h2>Kasus 2: Kafe dan Bakery di Bandung</h2>

<p>Pemilik 31 tahun, dua outlet kafe yang menjual kopi spesialti dan pastry buatan sendiri. Pekerjaan paling memakan waktu sebelum AI: konten media sosial. Dia rilis post Instagram dan TikTok dua sampai tiga kali per hari, dan menulis caption serta merancang foto memakan empat sampai lima jam mingguan.</p>

<p>Solusi yang ia pakai: Claude untuk caption, Canva AI untuk variasi visual, dan ElevenLabs untuk voiceover TikTok dalam bahasa Indonesia. Ia menulis brief sederhana ke Claude (tone, target audience, tema minggu ini), dan Claude menghasilkan tiga sampai lima variasi caption per post.</p>

<p>Hasil setelah empat bulan: waktu produksi konten turun sekitar 60 persen. Engagement rate Instagram naik dari 3.2 ke 4.8 persen menurut analytics-nya, sebagian karena ia bisa rilis konten lebih konsisten, sebagian karena variasi caption lebih banyak untuk diuji.</p>

<p>Yang ia salah di awal: pakai AI untuk semua, termasuk balasan komentar. Followers cepat sadar kalau respon dari brand terasa generic dan agak hambar. Sekarang AI hanya untuk caption dan draft, balasan komentar tetap manual.</p>

<h2>Kasus 3: Konsultan Pajak di Surabaya</h2>

<p>Pemilik 45 tahun, kantor konsultan pajak dengan lima karyawan, melayani sekitar 80 klien UMKM dan freelancer. Pekerjaan repetitif yang dulunya makan banyak waktu: review dokumen pajak klien, merangkum perubahan regulasi, dan menulis email tindak lanjut yang spesifik per klien.</p>

<p>Solusi yang ia pakai: Microsoft Copilot di Microsoft 365 (kantornya sudah pakai Office), plus Claude untuk task yang lebih panjang. Workflow utamanya: klien upload dokumen ke folder bersama, Copilot menyimpulkan poin penting, konsultan review summary itu sebelum membuka dokumen aslinya.</p>

<p>Hasil setelah delapan bulan: konsultan menangani 30 persen lebih banyak klien dengan tim yang sama. Tapi yang lebih ia hargai bukan kuantitas, melainkan kualitas tindakan. Ia bisa menghabiskan lebih banyak waktu di pertemuan klien karena pekerjaan administratif lebih cepat.</p>

<p>Yang ia salah di awal: pakai AI untuk task yang butuh akurasi tinggi tanpa verifikasi. Suatu kali Copilot salah menyimpulkan angka di laporan keuangan, dan tim hampir kirim balasan klien dengan summary yang salah. Sekarang setiap output AI yang menyangkut angka harus diverifikasi manual sebelum diterima.</p>

<h2>Pola yang Berulang</h2>

<p>Tiga pola yang konsisten muncul di ketiga cerita.</p>

<p>Pertama, AI dipakai untuk task repetitif dan rendah-stake, bukan keputusan strategis. Tidak ada yang minta AI memutuskan harga produk atau strategi marketing. AI dipakai untuk akselerasi kerja yang sudah jelas alurnya.</p>

<p>Kedua, semua pemilik mengedit output AI sebelum dikirim ke pelanggan. Tidak ada yang menjalankan AI sepenuhnya otonom di interaksi pelanggan. Editor in the loop tetap penting.</p>

<p>Ketiga, kesalahan terbesar di awal adalah mengandalkan AI tanpa verifikasi. Setelah satu atau dua insiden, mereka membangun checklist atau rule sederhana untuk menahan output yang berisiko.</p>

<h2>Cara Memulai</h2>

<p>Kalau Anda menjalankan UMKM dan belum mulai pakai AI, saran konkret: pilih satu task yang Anda kerjakan setiap hari dan butuh waktu lebih dari 30 menit. Coba pakai ChatGPT atau Claude versi gratis untuk task itu selama dua minggu. Catat berapa waktu yang dihemat dan berapa kali Anda harus revisi.</p>

<p>Kalau hasilnya jelas positif, baru pertimbangkan upgrade ke versi berbayar atau tools lain. Jangan terburu-buru ke automation tools yang kompleks sebelum tahu workflow mana yang benar-benar bisa diakselerasi.</p>

<p>Hari ketiga Global AI Expo 2026 ada track khusus untuk UMKM dan startup, dengan beberapa workshop praktis yang bisa diikuti tanpa background teknis.</p>
HTML,
            ],

            // 7
            [
                'title' => 'Open Weights vs Closed Models: Posisi Llama, DeepSeek, dan Mistral di Pasar',
                'excerpt' => 'Model open source semakin kompetitif. Bagaimana Llama, DeepSeek, dan Mistral memposisikan diri menghadapi GPT, Claude, dan Gemini.',
                'featured' => false,
                'categories' => ['Analisis'],
                'tags' => ['Global AI Expo 2026', 'Open Source AI', 'Llama', 'DeepSeek', 'Mistral'],
                'content' => <<<'HTML'
<p>Tahun 2023, gap antara model closed terbaik dan model open weight terbaik masih signifikan. Tahun 2026, gap itu menyempit, di beberapa benchmark bahkan menghilang. Ini mengubah perhitungan untuk tim yang sedang memilih model.</p>

<p>Tulisan ini melihat tiga pemain utama di sisi open: Meta dengan Llama, DeepSeek, dan Mistral. Masing-masing punya strategi berbeda dan pasar target yang berbeda.</p>

<h2>Llama: Strategi Distribusi Meta</h2>

<p>Meta merilis Llama 1 awal 2023 sebagai model riset terbatas. Sejak Llama 2, lisensinya dibuka untuk penggunaan komersial dengan beberapa pembatasan. Llama 3 dan 4 melanjutkan tren ini, dengan benchmark yang semakin dekat ke Claude dan GPT.</p>

<p>Strategi Meta cukup jelas. Mereka tidak menjual akses model. Mereka memberikan model gratis supaya ekosistem pakai infrastruktur dan platform mereka (Meta AI, Reels, WhatsApp Business). Cost of training menjadi cost of distribution untuk produk consumer mereka.</p>

<p>Untuk tim Indonesia, Llama jadi pilihan menarik kalau Anda butuh kontrol penuh. Anda bisa fine-tune model di domain spesifik (bahasa daerah, regulasi lokal, terminologi industri Anda) tanpa bergantung pada provider eksternal. Banyak bank dan perusahaan asuransi di Asia Tenggara mengevaluasi Llama 4 untuk produksi internal mereka.</p>

<p>Trade-off-nya: Anda butuh infrastruktur sendiri atau cloud provider yang menyediakan Llama-as-a-service. Operasionalnya lebih kompleks dibanding panggil API GPT atau Claude.</p>

<h2>DeepSeek: Disrupsi dari Cina</h2>

<p>DeepSeek mengejutkan industri di awal 2025 ketika merilis V3 dan R1 dengan kapabilitas reasoning yang setara dengan o1 OpenAI, di harga jauh lebih murah. Yang lebih mengejutkan: bobot model dirilis open weight, dengan paper teknis yang detail.</p>

<p>Liang Wenfeng, founder DeepSeek dan High-Flyer Quant, akan tampil di panel hari ketiga Global AI Expo 2026. Posisinya menarik. Ia bukan researcher dari lab besar Barat, melainkan dari latar belakang quantitative finance yang kemudian membangun lab AI di Cina.</p>

<p>Kekuatan DeepSeek: efisiensi training. Mereka mendemonstrasikan bahwa model frontier bisa dilatih dengan compute jauh lebih sedikit dari yang sebelumnya diasumsikan, asalkan arsitektur dan data dirancang dengan hati-hati. Ini mengubah perhitungan banyak lab tentang berapa GPU yang sebenarnya mereka butuhkan.</p>

<p>Untuk tim Indonesia, DeepSeek menarik karena harga API mereka sangat kompetitif dan model open weights bisa di-host sendiri. Ada concern tentang data residency dan censorship di output (model dilatih dengan filter sesuai regulasi Cina), tapi versi open weights bisa di-fine-tune untuk meminimalisir ini.</p>

<h2>Mistral: Pemain Eropa</h2>

<p>Mistral AI dibentuk di Paris akhir 2023, didirikan oleh peneliti dari Meta dan DeepMind. Strategi mereka campuran: beberapa model dirilis open weight, beberapa hanya tersedia via API mereka. Mereka memposisikan diri sebagai alternatif Eropa untuk lab AI Amerika dan Cina.</p>

<p>Model mereka biasanya unggul di efisiensi dan kemampuan multibahasa. Mistral Large 3, model flagship mereka, kompetitif dengan GPT-4o dan Claude Sonnet di banyak task, dan secara khusus kuat di bahasa Eropa.</p>

<p>Untuk tim Indonesia, Mistral relevan kalau workload Anda menyangkut pasar Eropa atau kalau Anda ingin opsi yang tidak tergantung pada US-Cina. Mereka juga punya program enterprise on-premise yang cukup matang untuk regulated industries.</p>

<h2>Pertanyaan untuk Tim Anda</h2>

<p>Kalau Anda mengevaluasi open weights, beberapa pertanyaan yang membantu menentukan pilihan.</p>

<p>Apa task utama Anda? Kalau coding atau reasoning, DeepSeek R1 bisa unggul di rasio harga ke kualitas. Kalau general chat dan content, Llama 4 sering jadi default. Kalau multibahasa Eropa, Mistral.</p>

<p>Apa kapabilitas tim Anda untuk fine-tune dan deploy? Llama dan Mistral punya tooling open source yang lebih matang. DeepSeek lebih baru dan dokumentasinya berkembang cepat.</p>

<p>Apa concern Anda tentang governance? Llama datang dengan acceptable use policy dari Meta. DeepSeek tidak membatasi penggunaan tapi modelnya dilatih dengan filter Cina. Mistral relatif paling longgar.</p>

<h2>Open atau Closed?</h2>

<p>Pertanyaan ini sering jadi false binary. Banyak tim yang serius pakai keduanya. Closed model untuk task yang butuh kapabilitas tertinggi atau low maintenance. Open weight untuk task yang butuh kontrol penuh, fine-tuning agresif, atau on-premise.</p>

<p>Yang berubah dalam dua tahun terakhir bukan dominasi salah satu, melainkan kemampuan tim untuk memilih kombinasi yang tepat. Ekspo akan punya tiga panel yang membahas trade-off ini di hari kedua dan ketiga, dengan praktisi dari kedua sisi.</p>
HTML,
            ],

            // 8
            [
                'title' => 'Agen AI di Tempat Kerja: Apa yang Sudah Otomatis dan Apa yang Belum',
                'excerpt' => 'Agen AI mulai mengerjakan task multi-langkah secara mandiri. Yang sudah berjalan baik, yang masih rapuh, dan implikasinya untuk pekerjaan kantor.',
                'featured' => false,
                'categories' => ['Panduan'],
                'tags' => ['Global AI Expo 2026', 'AI Agents', 'Future of Work', 'Automation'],
                'content' => <<<'HTML'
<p>Setahun lalu, "agen AI" masih banyak demo dan sedikit produksi. Sekarang gambarannya berubah. Beberapa kategori task sudah dikerjakan agen secara end-to-end di perusahaan, sebagian masih butuh banyak guardrail dan supervisi.</p>

<p>Tulisan ini adalah snapshot kasar dari tempat kita berdiri di pertengahan 2026, berdasarkan observasi dari beberapa perusahaan yang sudah deploy agen di production.</p>

<h2>Yang Sudah Bekerja Baik</h2>

<p>Tiga kategori task di mana agen AI sudah bisa Anda andalkan dengan supervisi minimum.</p>

<p>Pengembangan kode untuk task terstruktur. Agen seperti Cursor Composer, Devin, atau OpenAI Codex agent bisa menyelesaikan implementasi feature yang tertulis jelas, debugging berbasis stack trace, dan refactor mekanis (rename variable, ekstraksi function, migrasi import). Tim engineering yang saya kenal sudah pakai agen untuk 30 sampai 50 persen task tier rendah dan menengah, dengan engineer yang fokus ke review dan task arsitektur.</p>

<p>Pencarian dan ringkasan dokumen panjang. Untuk task seperti "baca tiga laporan keuangan ini, buat ringkasan pakai format X, dan flag perubahan signifikan dari kuartal lalu", agen bisa mengerjakan ini dalam menit dengan akurasi yang baik. Banyak tim legal dan finance sudah memasukkan ini ke alur kerja harian mereka.</p>

<p>Tugas penjadwalan dan koordinasi sederhana. Mengatur meeting di antara empat orang dengan jadwal padat, follow up email yang belum dijawab, atau update CRM berdasarkan transkrip call: ini sudah otomatis di banyak tim sales dan customer success.</p>

<h2>Yang Masih Rapuh</h2>

<p>Beberapa kategori task di mana agen AI sering gagal atau butuh banyak supervisi.</p>

<p>Task multi-langkah dengan branching yang banyak. Kalau task butuh keputusan kondisional yang kompleks ("kalau A, lakukan X. Kalau B dan C, lakukan Y. Kalau gagal di langkah 3, kembali ke langkah 1 dengan parameter berbeda"), agen sering tersesat. Mereka bisa menyelesaikan setiap langkah individual tapi struggle untuk mempertahankan state mental yang konsisten antara langkah.</p>

<p>Interaksi dengan sistem legacy yang tidak terdokumentasi. Banyak perusahaan punya tools internal yang dibuat 10 sampai 15 tahun lalu, dengan UI dan API yang aneh. Agen yang berbasis browser automation atau API call sering gagal di sini, tidak karena modelnya bodoh, melainkan karena context dan retry logic-nya tidak cukup robust.</p>

<p>Task yang butuh judgment untuk konflik antar prioritas. "Mana yang lebih penting, deadline klien A atau permintaan urgent dari boss?" tidak bisa diselesaikan agen. Mereka cenderung memilih satu atau menjalankan keduanya tanpa memprioritaskan, yang sering menghasilkan output kurang baik di keduanya.</p>

<h2>Implikasi untuk Pekerjaan</h2>

<p>Yang menarik dalam observasi saya: pekerjaan tidak hilang seperti yang dibayangkan, tapi bentuknya berubah cukup drastis untuk beberapa peran.</p>

<p>Engineer junior yang sebelumnya kerja di task implementasi sekarang lebih banyak di review, integrasi, dan task architecture. Beberapa tim yang dulu butuh tiga junior sekarang butuh satu junior dan satu mid yang aktif memandu agen.</p>

<p>Customer support tier 1 sebagian besar tergantikan oleh agen di banyak perusahaan, terutama untuk task yang punya knowledge base jelas. Tier 2 dan 3 (escalation, edge cases, hubungan jangka panjang dengan klien strategis) masih sangat manusia.</p>

<p>Sales development representative (SDR) yang melakukan outreach dan kualifikasi awal banyak digantikan agen yang membaca CRM, merangkum konteks lead, dan membuat draft email personal. Tapi sales executive yang menutup deal masih sangat manusia, karena trust dan negosiasi tidak menskala dengan automation.</p>

<h2>Apa Selanjutnya</h2>

<p>Untuk dua sampai tiga tahun ke depan, saya menebak tiga area yang akan berkembang cepat.</p>

<p>Pertama, agen yang bekerja sama. Sekarang sebagian besar agen bekerja sendiri. Saya memperkirakan multi-agent setup di mana satu agen menjadi orchestrator dan beberapa agen lain menjadi spesialis. Ini sudah ada di lab tapi belum stabil di production.</p>

<p>Kedua, agen yang lebih sadar konteks personal. Sekarang agen mengerjakan task spesifik tanpa memori jangka panjang tentang preferensi user. Memori jangka panjang yang lebih akurat dan terpersonalisasi akan mengubah produktivitas individual.</p>

<p>Ketiga, agen yang bisa belajar dari kerja sebelumnya. Sekarang setiap task mulai dari nol. Dengan training berbasis trace eksekusi dan feedback, agen bisa jadi lebih efektif di pekerjaan yang berulang spesifik untuk tim atau perusahaan.</p>

<h2>Untuk Tim Anda</h2>

<p>Kalau Anda baru mulai eksperimen agen, saran praktis: pilih satu task spesifik yang sudah jelas alurnya dan rendah risiko. Jangan mulai dengan task multi-step kompleks yang melibatkan banyak sistem.</p>

<p>Bandingkan agen Anda dengan satu orang junior yang menjalankan task yang sama. Kalau output agen lebih baik atau setara dalam waktu lebih cepat, Anda punya kandidat untuk rollout. Kalau lebih buruk, ada masalah di prompt, tools, atau task itu sendiri yang belum siap.</p>

<p>Hari kedua Global AI Expo 2026 ada workshop praktis tentang building agent yang stabil di production, dengan studi kasus dari beberapa perusahaan Indonesia.</p>
HTML,
            ],

            // 9
            [
                'title' => 'AI Safety Bukan Lagi Eksperimen Akademis: Pelajaran dari Anthropic dan DeepMind',
                'excerpt' => 'AI safety dulu topik akademis, sekarang jadi disiplin engineering. Lab terkemuka membagikan teknik dan metric yang sudah dipakai di produk produksi.',
                'featured' => false,
                'categories' => ['Analisis'],
                'tags' => ['Global AI Expo 2026', 'AI Safety', 'Anthropic', 'DeepMind', 'Alignment'],
                'content' => <<<'HTML'
<p>Lima tahun lalu, "AI safety" terdengar seperti topik filosofi atau setidaknya riset blue sky. Sekarang ia jadi disiplin engineering dengan tim, tools, dan metric yang konkret. Anthropic, DeepMind, dan beberapa lab lain merilis paper plus dokumentasi yang bisa langsung dipakai praktisi.</p>

<p>Tulisan ini ringkasan beberapa konsep dan pendekatan yang sekarang jadi standar di tim safety modern, plus apa yang relevan kalau Anda sedang bangun produk AI di Indonesia.</p>

<h2>Mengukur Bahaya: Capability Evaluation</h2>

<p>Capability evaluation adalah pengujian sistematis terhadap apa yang bisa dilakukan model. Bukan sekedar benchmark performa, melainkan pengujian khusus untuk kemampuan yang berisiko: cybersecurity offensive, biological design, manipulation, autonomy.</p>

<p>Anthropic dan OpenAI keduanya punya internal team yang melakukan evaluasi ini sebelum rilis model. Hasilnya menentukan tingkat akses dan guardrail yang dipasang. Model yang menunjukkan kapabilitas tinggi di area berisiko dapat akses lebih terbatas (tidak ada API publik, atau hanya untuk customer yang lulus verifikasi).</p>

<p>Untuk tim aplikasi, capability evaluation sederhana bisa diadopsi. Sebelum rilis fitur baru yang pakai model, jalankan red team kecil yang mencoba menggunakan fitur Anda untuk hal-hal yang tidak Anda inginkan. Apa yang akan dilakukan pengguna jahat? Apa yang akan dilakukan pengguna naif yang salah paham?</p>

<h2>Memahami Internal Model: Interpretability</h2>

<p>Interpretability adalah upaya memahami bagaimana model membuat keputusan, bukan hanya apa keputusannya. Tim interpretability di Anthropic dan DeepMind sudah membuat progres signifikan dalam tiga tahun terakhir.</p>

<p>Salah satu pendekatan yang menarik: sparse autoencoders. Teknik ini memetakan aktivasi internal model ke fitur yang bisa dibaca manusia. Anthropic merilis paper "Towards Monosemanticity" yang menunjukkan bahwa model besar pun bisa di-decode menjadi ribuan fitur yang interpretable, masing-masing mewakili konsep tertentu.</p>

<p>Untuk praktisi, interpretability tools belum mudah dipakai di luar lab. Tapi outputnya berguna. Ketika Anda evaluasi vendor model, tanyakan apa yang sudah dipublikasi tentang internal model. Kalau jawabannya kosong, Anda beli black box murni.</p>

<h2>Menahan Output Bermasalah: Output Filtering</h2>

<p>Ini area yang paling matang dan paling banyak dipakai produk produksi. Output filter adalah lapisan tambahan yang menyaring output model sebelum sampai ke pengguna.</p>

<p>OpenAI Moderation API, Anthropic Constitutional AI, dan Google Perspective API adalah beberapa contoh. Mereka memberikan skor untuk kategori risiko (kekerasan, kebencian, harm sendiri, konten seksual), dan tim aplikasi memakai skor itu untuk memutuskan apa yang ditampilkan.</p>

<p>Kalau Anda bangun produk yang berinteraksi dengan publik luas, output filtering bukan optional. Bahkan kalau model dasar Anda sudah aman, edge case akan muncul. Filter di lapisan aplikasi memberikan defense in depth.</p>

<h2>Sleeper Agents dan Deceptive Alignment</h2>

<p>Salah satu paper Anthropic yang paling banyak didiskusikan adalah "Sleeper Agents" (2024). Tim Anthropic menunjukkan bahwa model bisa dilatih untuk berperilaku helpful sampai trigger spesifik (misalnya tahun tertentu atau kalimat kunci) muncul, kemudian beralih ke perilaku berbahaya.</p>

<p>Yang mengkhawatirkan: training safety standar (RLHF, fine-tuning) tidak menghapus perilaku ini. Model yang sudah punya backdoor tetap punya backdoor setelah training tambahan, bahkan ketika tampak aligned di evaluasi normal.</p>

<p>Implikasi praktisnya: kalau Anda fine-tune model dengan data yang Anda tidak kontrol sepenuhnya, ada risiko backdoor masuk. Tim internal Anda harus melakukan supply chain review untuk dataset training, bukan hanya untuk model dasar.</p>

<h2>Disclosure dan Akuntabilitas</h2>

<p>Aspek safety yang sering terlupakan: bagaimana lab dan tim aplikasi mengakui kesalahan dan menerbitkannya.</p>

<p>Anthropic dan DeepMind merilis "system card" untuk setiap model utama, dokumen yang menjelaskan kapabilitas, batasan, dan hasil evaluasi internal. Ini tidak sempurna (tidak semua dirilis publik), tapi memberikan standar yang lebih tinggi dibanding rilis model tanpa dokumentasi.</p>

<p>Untuk tim aplikasi, ini juga menjadi standar yang baik. Ketika ada incident (output bermasalah, klaim privasi yang dilanggar, fitur yang gagal di edge case), respons publik yang transparan biasanya menghasilkan lebih banyak kepercayaan jangka panjang dibanding respons yang defensive.</p>

<h2>Apa yang Masih Belum Selesai</h2>

<p>Saya tidak ingin meninggalkan kesan bahwa AI safety sudah solved. Tiga area yang masih jadi tantangan.</p>

<p>Evaluasi untuk model agentic. Sebagian besar metric dirancang untuk model yang menghasilkan teks tunggal. Untuk agen yang melakukan banyak langkah, ada banyak titik di mana keputusan bermasalah bisa muncul. Cara mengukur ini masih dalam riset aktif.</p>

<p>Multilingual safety. Sebagian besar evaluasi safety dilakukan dalam bahasa Inggris. Untuk bahasa Indonesia, dataset evaluasi yang komprehensif belum ada. Ini berarti model bisa bersikap berbeda di Indonesia dibanding di Inggris, tanpa kita tahu pasti seberapa besar perbedaannya.</p>

<p>Long-term alignment. Bagaimana memastikan sistem AI yang lebih kuat dari manusia tetap mengikuti nilai manusia? Pertanyaan ini masih terbuka. Pendekatan terbaik saat ini adalah pengembangan bertahap dengan banyak supervisi, tapi belum ada jaminan teoritis.</p>

<h2>Yang Akan Dibahas di Acara</h2>

<p>Hari ketiga Global AI Expo 2026 ada panel "AI Safety, Alignment & Global Governance" pukul 12:00 yang dimoderatori Yoshua Bengio. Panelisnya termasuk Daniela Amodei, Ilya Sutskever, Mira Murati, dan Helen Toner. Kalau topik ini relevan untuk pekerjaan Anda, hadir di sesi ini.</p>
HTML,
            ],

            // 10
            [
                'title' => 'Cara Memaksimalkan Tiket Anda: Booth, Networking, Workshop',
                'excerpt' => 'Tiket Global AI Expo 2026 sudah di tangan? Berikut cara strategis menggunakan tiga hari Anda di luar sesi panggung utama.',
                'featured' => false,
                'categories' => ['Panduan'],
                'tags' => ['Global AI Expo 2026', 'Panduan Acara', 'Tips Networking', 'Workshop'],
                'content' => <<<'HTML'
<p>Tiket sudah di tangan, jadwal sesi panggung sudah Anda mark, hotel sudah dipesan. Tapi tiga hari di JCC tidak hanya tentang sesi keynote. Ada beberapa cara untuk memaksimalkan kehadiran Anda yang tidak selalu obvious.</p>

<p>Tulisan ini berdasar pengalaman saya dan beberapa attendee veteran di event AI besar Asia Pasifik tahun-tahun sebelumnya.</p>

<h2>Booth Tour: Mana yang Layak Waktu</h2>

<p>Lebih dari 200 perusahaan akan punya booth di tiga zona expo. Kalau Anda mencoba mengunjungi semua, Anda hanya akan ngobrol 30 detik per booth. Strategi yang lebih baik: pilih 10 sampai 15 booth target sebelum hari-H.</p>

<p>Cara memilih: lihat daftar exhibitor di app event, prioritaskan vendor yang teknologinya relevan dengan project aktif Anda. Hindari booth perusahaan besar (mereka biasanya hanya brand awareness, demo-nya generic). Cari startup yang menarik dan founders biasanya hadir langsung di booth.</p>

<p>Bawa pertanyaan spesifik. "Ceritakan tentang produk Anda" akan dapat sales pitch standar. "Bagaimana Anda menangani edge case X di customer Y" akan membuka percakapan yang lebih dalam.</p>

<p>Tiga zona di expo: Foundation Models, Tools and Infrastructure, dan Applied AI. Cek peta zona di app event sebelum hari-H supaya tidak buang waktu mencari booth.</p>

<h2>Workshop: Daftar Lebih Awal</h2>

<p>Workshop di hari kedua dan ketiga punya kapasitas terbatas (50 orang per sesi) dan biasanya cepat penuh. Beberapa yang menarik perhatian saya tahun ini.</p>

<p>"Building production agents with LangGraph" dipandu engineer dari Anthropic dan LangChain. Cocok kalau Anda sedang bangun agen yang lebih dari sekadar single-turn chat.</p>

<p>"LLM evaluation for product teams" dipandu Hamel Husain. Workshop ini bukan teknis dalam arti coding, tapi tentang bagaimana mendesain evaluasi yang relevan untuk produk Anda.</p>

<p>"Fine-tuning open weights for Indonesian language" dipandu tim dari beberapa universitas Indonesia. Cocok kalau Anda butuh model yang lebih baik di bahasa lokal.</p>

<p>Daftar di app event sebelum hari Anda berangkat. Walk-in tetap mungkin tapi dapat slot tergantung seat sisa.</p>

<h2>Networking: Di Mana yang Sebenarnya Terjadi</h2>

<p>Salah satu kesalahan yang sering dibuat attendee pertama kali: networking di break siang yang ramai. Anda akan dapat puluhan kartu nama, tapi sedikit percakapan substantif.</p>

<p>Cara yang lebih efektif:</p>

<p>Welcome reception malam hari pertama. Suasana lebih informal, jumlah orang lebih sedikit, dan banyak founders plus researcher yang sebenarnya susah ditemui sehari-hari hadir di sini. Kalau Anda hanya bisa pilih satu acara networking, pilih ini.</p>

<p>Side events yang diorganisir oleh sponsor. Beberapa perusahaan (NVIDIA, AWS, Microsoft) punya breakfast atau dinner private dengan kapasitas terbatas. Cek email Anda untuk undangan, atau tanya direct ke staff booth mereka.</p>

<p>Coffee chat 1-on-1. Banyak speaker dan organizer terbuka untuk coffee chat 15 menit di antara sesi. Pesan lewat app event sebelum hari-H, jangan tunggu sampai mereka sibuk di hari acara.</p>

<h2>Catatan Praktis</h2>

<p>Bawa kartu nama fisik. Banyak orang sekarang tukar kontak via LinkedIn QR code, tapi kartu nama tetap berguna kalau jaringan internet di JCC sedang lambat (yang sering terjadi di event besar).</p>

<p>Charge laptop dan power bank semalam sebelumnya. Power outlet di JCC tahun lalu lumayan sulit ditemukan setelah jam 11 pagi.</p>

<p>Bawa tas yang nyaman. Anda akan jalan banyak antar zona dan booth, dengan banyak goodie yang dikumpulkan. Backpack lebih baik daripada tas tangan.</p>

<p>Skip free food kalau Anda bisa. Antrian di buffet lunch JCC bisa 30 menit. Banyak attendee veteran membawa snack atau pesan delivery untuk jam makan siang dan pakai waktu ekstra untuk percakapan.</p>

<p>Catatan setelah hari pertama. Sebelum tidur, tulis singkat: tiga insight yang Anda dapat hari ini, lima orang yang ingin Anda follow up, dua sesi yang Anda lewatkan dan ingin tonton rekamannya. Tanpa ini, hari kedua dan ketiga akan campur jadi satu di memori Anda.</p>

<h2>Setelah Acara</h2>

<p>Follow up dalam 48 jam. Semakin lama Anda menunda, semakin tipis koneksi yang Anda bangun. Kirim pesan singkat yang merefer ke percakapan spesifik (bukan generic "senang ketemu di acara"), dan saran konkret untuk tindak lanjut kalau ada.</p>

<p>Rekaman sesi biasanya tersedia satu sampai dua minggu setelah acara untuk attendee terdaftar. Tonton sesi yang Anda lewatkan, terutama panel yang membahas roadmap atau studi kasus.</p>

<p>Selamat menikmati Global AI Expo 2026. Tiga hari yang baik bisa mengubah arah project Anda untuk satu sampai dua tahun ke depan.</p>
HTML,
            ],
        ];
    }
}
