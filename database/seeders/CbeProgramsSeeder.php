<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Program;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Seeds the "Main Programs" cards for the Cafe & Brasserie Expo (cbe) 9th edition
 * event. These power the frontend MainPrograms section (/api/.../programs). The
 * 9th-edition event had no programs of its own, so the public endpoint was
 * borrowing the 8th edition's list via its fallback; this makes the 9th explicit.
 *
 * title/description are translatable (Spatie HasTranslations) and stored as
 * per-locale JSON. icon is a hugeicons name (frontend MainProgramCard icon mode).
 * order_column is assigned automatically by SortableTrait in creation order.
 *
 * Idempotent: skips the event if it already has any (non-trashed) program.
 * Run manually (local + production): php artisan db:seed --class=CbeProgramsSeeder
 */
class CbeProgramsSeeder extends Seeder
{
    private const PROJECT_USERNAME = 'cbe';

    private const EVENT_SLUG = 'cafe-brasserie-expo-9th';

    private const EVENT_EDITION = 9;

    /**
     * Program cards in display order. Each `title`/`description` is keyed by locale
     * (en/id/ja/ko/zh): copy written natively per language, not translated.
     *
     * @return array<int, array{icon: string, title: array<string, string>, description: array<string, string>}>
     */
    private function programs(): array
    {
        return [
            [
                'icon' => 'hugeicons:champion',
                'title' => [
                    'en' => 'Live Competitions & Awards',
                    'id' => 'Kompetisi Live & Awards',
                    'ja' => 'ライブ・コンペティション＆表彰',
                    'ko' => '라이브 경연 & 시상식',
                    'zh' => '现场比赛与颁奖',
                ],
                'description' => [
                    'en' => 'Baristas and pastry chefs go head to head for the title. Watch it live, then catch the winners on the Main Stage.',
                    'id' => 'Barista dan pastry chef adu skill rebutan gelar juara. Tonton langsung, terus lihat para pemenang di Main Stage.',
                    'ja' => 'バリスタやパティシエが、タイトルをかけて真剣勝負。その様子をライブで見届けて、メインステージで優勝者に会いに行きましょう。',
                    'ko' => '바리스타와 파티시에가 타이틀을 걸고 맞붙습니다. 현장에서 생생하게 지켜보고, 수상자는 Main Stage에서 만나 보세요.',
                    'zh' => '咖啡师和甜点师同台较劲，争夺冠军。看完比拼，再到 Main Stage 见证获奖名单。',
                ],
            ],
            [
                'icon' => 'hugeicons:coffee-01',
                'title' => [
                    'en' => 'Taste Bars & Live Tastings',
                    'id' => 'Taste Bar & Live Tasting',
                    'ja' => 'テイストバー＆ライブテイスティング',
                    'ko' => '테이스트 바 & 라이브 테이스팅',
                    'zh' => '品鉴吧台与现场试饮',
                ],
                'description' => [
                    'en' => 'Trial stations sit across the floor, so you can sip and taste your way through hundreds of products before you commit to a supplier.',
                    'id' => 'Booth trial tersebar di seluruh area, jadi kamu bisa nyicip ratusan produk dulu sebelum mutusin pilih supplier.',
                    'ja' => '試飲・試食のステーションが会場のあちこちに。仕入れ先を決める前に、何百もの商品をじっくり飲み比べ、食べ比べできます。',
                    'ko' => '전시장 곳곳에 시음 스테이션이 있어, 공급업체를 정하기 전에 수백 가지 제품을 직접 맛보고 비교할 수 있어요.',
                    'zh' => '试饮台遍布全场，几百款产品先尝个遍，再决定跟哪家供应商合作。',
                ],
            ],
            [
                'icon' => 'hugeicons:coffee-beans',
                'title' => [
                    'en' => 'Hands-On Workshops',
                    'id' => 'Workshop Praktik',
                    'ja' => '実践ワークショップ',
                    'ko' => '핸즈온 워크숍',
                    'zh' => '动手工作坊',
                ],
                'description' => [
                    'en' => 'Short, practical classes on brewing, latte art, and running the bar. Bring questions, leave with something you can use on Monday.',
                    'id' => 'Kelas singkat dan praktis soal brewing, latte art, dan cara ngurus bar. Bawa pertanyaan, pulang bawa ilmu yang langsung bisa dipakai besok.',
                    'ja' => '抽出やラテアート、バーの回し方まで、短くて実践的なクラス。疑問を持って参加すれば、明日からすぐ使えるコツを持ち帰れます。',
                    'ko' => '브루잉과 라테 아트, 바 운영까지 짧고 실용적인 클래스로 배웁니다. 궁금한 걸 들고 와서, 당장 내일부터 매장에서 써먹을 수 있는 걸 챙겨 가세요.',
                    'zh' => '关于冲泡、拉花和吧台运营的短课，讲的都是能用得上的。带着问题来，走的时候学到周一就能上手的东西。',
                ],
            ],
            [
                'icon' => 'hugeicons:chocolate',
                'title' => [
                    'en' => 'Bean-to-Bar & Pastry Demos',
                    'id' => 'Demo Bean-to-Bar & Pastry',
                    'ja' => 'Bean to Bar＆パティスリー実演',
                    'ko' => '빈투바 & 페이스트리 데모',
                    'zh' => '从可可豆到成品：巧克力与甜点演示',
                ],
                'description' => [
                    'en' => 'Watch chocolatiers and pastry chefs work up close, from tempering to the finished plate, and pick up a few tricks along the way.',
                    'id' => 'Lihat chocolatier dan pastry chef kerja dari dekat, mulai dari tempering sampai plating akhir, sambil nyuri-nyuri trik mereka.',
                    'ja' => 'ショコラティエやパティシエの手元を間近で。テンパリングから盛り付けまで、プロの技をそばで見ながらコツをつかめます。',
                    'ko' => '쇼콜라티에와 파티시에의 작업을 가까이에서 지켜보세요. 템퍼링부터 완성된 플레이팅까지, 소소한 노하우도 함께 얻어 갑니다.',
                    'zh' => '近距离看巧克力师和甜点师怎么做，从调温到摆盘走一遍，顺手偷学几招。',
                ],
            ],
            [
                'icon' => 'hugeicons:mic-01',
                'title' => [
                    'en' => 'Taste Talks & Industry Panels',
                    'id' => 'Taste Talk & Panel Industri',
                    'ja' => 'テイストトーク＆業界パネル',
                    'ko' => '테이스트 토크 & 산업 패널',
                    'zh' => '行业分享与圆桌对谈',
                ],
                'description' => [
                    'en' => 'Founders and chefs share what actually worked for them: opening, scaling, and getting people through the door.',
                    'id' => 'Founder dan chef cerita apa yang beneran berhasil buat mereka: buka usaha, scaling, dan bikin orang mau datang.',
                    'ja' => '創業者やシェフが、うまくいったことを本音で語ります。開業のこと、店を大きくすること、お客さんに足を運んでもらう工夫まで。',
                    'ko' => '창업자와 셰프가 실제로 통했던 이야기를 들려줍니다. 매장을 열고, 규모를 키우고, 손님을 불러 모은 경험까지요.',
                    'zh' => '创始人和主厨聊真正管用的经验：怎么开店、怎么做大、怎么把人吸引进门。',
                ],
            ],
            [
                'icon' => 'hugeicons:user-group',
                'title' => [
                    'en' => 'Business Matching & Networking',
                    'id' => 'Business Matching & Networking',
                    'ja' => 'ビジネスマッチング＆ネットワーキング',
                    'ko' => '비즈니스 매칭 & 네트워킹',
                    'zh' => '商务对接与交流',
                ],
                'description' => [
                    'en' => 'We match buyers with suppliers ahead of time, so the meetings you have are ones worth having.',
                    'id' => 'Kami pasangin buyer sama supplier dari jauh-jauh hari, jadi tiap meeting yang kamu jalani memang sepadan.',
                    'ja' => 'バイヤーと仕入れ先を事前にマッチング。当日の商談が、時間をかける価値のあるものになります。',
                    'ko' => '바이어와 공급업체를 미리 매칭해 드려요. 그래서 현장에서 갖는 미팅은 시간이 아깝지 않은 만남이 됩니다.',
                    'zh' => '我们提前帮买家和供应商配对，让你谈的每一场都值得谈。',
                ],
            ],
            [
                'icon' => 'hugeicons:store-01',
                'title' => [
                    'en' => 'Brand Activations & Pop-up Market',
                    'id' => 'Brand Activation & Pop-up Market',
                    'ja' => 'ブランド体験＆ポップアップマーケット',
                    'ko' => '브랜드 액티베이션 & 팝업 마켓',
                    'zh' => '品牌体验与快闪市集',
                ],
                'description' => [
                    'en' => 'Hands-on brand spaces and a pop-up market where you can try new launches, buy on the spot, and take something home.',
                    'id' => 'Ruang brand yang interaktif plus pop-up market, tempat kamu bisa coba produk baru, beli di tempat, dan bawa pulang sesuatu.',
                    'ja' => '実際に触れて楽しめるブランド空間と、ポップアップマーケット。新商品を試して、気に入ったらその場で買って、持ち帰れます。',
                    'ko' => '직접 체험하는 브랜드 공간과 팝업 마켓이 열립니다. 새로 나온 제품을 써 보고, 마음에 들면 바로 구매해 집에 가져가세요.',
                    'zh' => '能上手体验的品牌空间，还有一个快闪市集：新品当场试、当场买，拎点东西回家。',
                ],
            ],
        ];
    }

    public function run(): void
    {
        $project = Project::where('username', self::PROJECT_USERNAME)->first();

        if (! $project) {
            $this->command->warn("Project '".self::PROJECT_USERNAME."' not found, skipping.");

            return;
        }

        /** @var Event|null $event */
        $event = $project->events()->where('slug', self::EVENT_SLUG)->first()
            ?? $project->events()->where('edition_number', self::EVENT_EDITION)->first();

        if (! $event) {
            $this->command->warn("cbe 9th edition event not found (slug '".self::EVENT_SLUG."'), skipping.");

            return;
        }

        if ($event->programs()->exists()) {
            $this->command->info("cbe 9th ('{$event->slug}'): already has programs, skipping.");

            return;
        }

        foreach ($this->programs() as $data) {
            $event->programs()->create([
                'title' => $data['title'],
                'description' => $data['description'],
                'icon' => $data['icon'],
                'is_active' => true,
            ]);
        }

        $this->command->info("cbe 9th ('{$event->slug}'): ".count($this->programs()).' programs created.');
    }
}
