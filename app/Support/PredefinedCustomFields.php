<?php

namespace App\Support;

use App\Models\CustomField;

class PredefinedCustomFields
{
    /**
     * Library of common fields staff can toggle on per event. Toggling ON
     * instantiates a normal custom_fields row with these labels/options copied
     * in (staff may edit them afterwards); `system_key` marks provenance and
     * keeps the toggle idempotent. Option labels are {locale: string} maps
     * resolved by FormFieldTypes::optionLabelFrom and the shared renderer.
     */
    private const INDUSTRY_OPTIONS = [
        ['value' => 'technology', 'label' => ['en' => 'Technology', 'id' => 'Teknologi', 'ja' => 'テクノロジー', 'ko' => '기술', 'zh' => '科技']],
        ['value' => 'manufacturing', 'label' => ['en' => 'Manufacturing', 'id' => 'Manufaktur', 'ja' => '製造業', 'ko' => '제조업', 'zh' => '制造业']],
        ['value' => 'retail_ecommerce', 'label' => ['en' => 'Retail & E-commerce', 'id' => 'Ritel & E-commerce', 'ja' => '小売・Eコマース', 'ko' => '소매 및 이커머스', 'zh' => '零售与电商']],
        ['value' => 'food_beverage', 'label' => ['en' => 'Food & Beverage', 'id' => 'Makanan & Minuman', 'ja' => '飲食', 'ko' => '식음료', 'zh' => '食品饮料']],
        ['value' => 'healthcare', 'label' => ['en' => 'Healthcare', 'id' => 'Kesehatan', 'ja' => 'ヘルスケア', 'ko' => '헬스케어', 'zh' => '医疗健康']],
        ['value' => 'finance', 'label' => ['en' => 'Finance', 'id' => 'Keuangan', 'ja' => '金融', 'ko' => '금융', 'zh' => '金融']],
        ['value' => 'education', 'label' => ['en' => 'Education', 'id' => 'Pendidikan', 'ja' => '教育', 'ko' => '교육', 'zh' => '教育']],
        ['value' => 'government', 'label' => ['en' => 'Government', 'id' => 'Pemerintahan', 'ja' => '行政', 'ko' => '정부', 'zh' => '政府']],
        ['value' => 'media_creative', 'label' => ['en' => 'Media & Creative', 'id' => 'Media & Kreatif', 'ja' => 'メディア・クリエイティブ', 'ko' => '미디어 및 크리에이티브', 'zh' => '媒体与创意']],
        ['value' => 'construction_property', 'label' => ['en' => 'Construction & Property', 'id' => 'Konstruksi & Properti', 'ja' => '建設・不動産', 'ko' => '건설 및 부동산', 'zh' => '建筑与房地产']],
        ['value' => 'logistics', 'label' => ['en' => 'Logistics', 'id' => 'Logistik', 'ja' => '物流', 'ko' => '물류', 'zh' => '物流']],
        ['value' => 'hospitality_tourism', 'label' => ['en' => 'Hospitality & Tourism', 'id' => 'Perhotelan & Pariwisata', 'ja' => 'ホスピタリティ・観光', 'ko' => '호텔 및 관광', 'zh' => '酒店与旅游']],
        ['value' => 'energy', 'label' => ['en' => 'Energy', 'id' => 'Energi', 'ja' => 'エネルギー', 'ko' => '에너지', 'zh' => '能源']],
        ['value' => 'agriculture', 'label' => ['en' => 'Agriculture', 'id' => 'Pertanian', 'ja' => '農業', 'ko' => '농업', 'zh' => '农业']],
        ['value' => 'professional_services', 'label' => ['en' => 'Professional Services', 'id' => 'Jasa Profesional', 'ja' => '専門サービス', 'ko' => '전문 서비스', 'zh' => '专业服务']],
        ['value' => 'other', 'label' => ['en' => 'Other', 'id' => 'Lainnya', 'ja' => 'その他', 'ko' => '기타', 'zh' => '其他']],
    ];

    private const COUNTRY_FIELD = [
        'type' => CustomField::TYPE_COUNTRY,
        'label' => ['en' => 'Country', 'id' => 'Negara', 'ja' => '国', 'ko' => '국가', 'zh' => '国家'],
        'options' => null,
        'validation' => ['required' => false],
        'settings' => null,
    ];

    private const CITY_FIELD = [
        'type' => CustomField::TYPE_TEXT,
        'label' => ['en' => 'City', 'id' => 'Kota', 'ja' => '都市', 'ko' => '도시', 'zh' => '城市'],
        'options' => null,
        'validation' => ['required' => false],
        'settings' => null,
    ];

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    private static function definitions(): array
    {
        return [
            CustomField::CONTEXT_TICKET_REGISTRATION => [
                'gender' => [
                    'type' => CustomField::TYPE_SELECT,
                    'label' => ['en' => 'Gender', 'id' => 'Jenis kelamin', 'ja' => '性別', 'ko' => '성별', 'zh' => '性别'],
                    'options' => [
                        ['value' => 'male', 'label' => ['en' => 'Male', 'id' => 'Laki-laki', 'ja' => '男性', 'ko' => '남성', 'zh' => '男']],
                        ['value' => 'female', 'label' => ['en' => 'Female', 'id' => 'Perempuan', 'ja' => '女性', 'ko' => '여성', 'zh' => '女']],
                        ['value' => 'prefer_not_to_say', 'label' => ['en' => 'Prefer not to say', 'id' => 'Memilih tidak menjawab', 'ja' => '回答しない', 'ko' => '답변하지 않음', 'zh' => '不愿透露']],
                    ],
                    'validation' => ['required' => false],
                    'settings' => null,
                ],
                'birth_year' => [
                    'type' => CustomField::TYPE_SELECT,
                    'label' => ['en' => 'Birth year', 'id' => 'Tahun lahir', 'ja' => '生まれ年', 'ko' => '출생 연도', 'zh' => '出生年份'],
                    'options' => null,
                    'validation' => ['required' => false],
                    'settings' => ['options_preset' => 'years'],
                ],
                'country' => self::COUNTRY_FIELD,
                'city' => self::CITY_FIELD,
                'company' => [
                    'type' => CustomField::TYPE_TEXT,
                    'label' => ['en' => 'Company', 'id' => 'Perusahaan', 'ja' => '会社名', 'ko' => '회사', 'zh' => '公司'],
                    'options' => null,
                    'validation' => ['required' => false],
                    'settings' => null,
                ],
                'job_title' => [
                    'type' => CustomField::TYPE_TEXT,
                    'label' => ['en' => 'Job title', 'id' => 'Jabatan', 'ja' => '役職', 'ko' => '직함', 'zh' => '职位'],
                    'options' => null,
                    'validation' => ['required' => false],
                    'settings' => null,
                ],
                'industry' => [
                    'type' => CustomField::TYPE_SELECT,
                    'label' => ['en' => 'Industry', 'id' => 'Industri', 'ja' => '業界', 'ko' => '산업', 'zh' => '行业'],
                    'options' => self::INDUSTRY_OPTIONS,
                    'validation' => ['required' => false],
                    'settings' => null,
                ],
                'how_did_you_hear' => [
                    'type' => CustomField::TYPE_SELECT,
                    'label' => ['en' => 'How did you hear about this event?', 'id' => 'Dari mana Anda mengetahui acara ini?', 'ja' => 'このイベントをどこで知りましたか？', 'ko' => '이 행사를 어떻게 알게 되셨나요?', 'zh' => '您是如何得知本次活动的？'],
                    'options' => [
                        ['value' => 'social_media', 'label' => ['en' => 'Social media', 'id' => 'Media sosial', 'ja' => 'SNS', 'ko' => '소셜 미디어', 'zh' => '社交媒体']],
                        ['value' => 'search_engine', 'label' => ['en' => 'Search engine', 'id' => 'Mesin pencari', 'ja' => '検索エンジン', 'ko' => '검색 엔진', 'zh' => '搜索引擎']],
                        ['value' => 'friend_colleague', 'label' => ['en' => 'Friend or colleague', 'id' => 'Teman atau kolega', 'ja' => '友人・同僚', 'ko' => '친구 또는 동료', 'zh' => '朋友或同事']],
                        ['value' => 'invitation', 'label' => ['en' => 'Invitation', 'id' => 'Undangan', 'ja' => '招待', 'ko' => '초대', 'zh' => '邀请']],
                        ['value' => 'email_newsletter', 'label' => ['en' => 'Email newsletter', 'id' => 'Buletin email', 'ja' => 'メールマガジン', 'ko' => '이메일 뉴스레터', 'zh' => '电子邮件通讯']],
                        ['value' => 'advertisement', 'label' => ['en' => 'Advertisement', 'id' => 'Iklan', 'ja' => '広告', 'ko' => '광고', 'zh' => '广告']],
                        ['value' => 'previous_edition', 'label' => ['en' => 'Previous edition', 'id' => 'Edisi sebelumnya', 'ja' => '過去の開催', 'ko' => '이전 행사', 'zh' => '往届活动']],
                        ['value' => 'other', 'label' => ['en' => 'Other', 'id' => 'Lainnya', 'ja' => 'その他', 'ko' => '기타', 'zh' => '其他']],
                    ],
                    'validation' => ['required' => false],
                    'settings' => null,
                ],
            ],
            CustomField::CONTEXT_BUSINESS_MATCHING => [
                'country' => self::COUNTRY_FIELD,
                'city' => self::CITY_FIELD,
                'business_interests' => [
                    'type' => CustomField::TYPE_MULTI_SELECT,
                    'label' => ['en' => 'Business interests', 'id' => 'Minat bisnis', 'ja' => 'ビジネスの関心分野', 'ko' => '비즈니스 관심 분야', 'zh' => '业务兴趣'],
                    'options' => self::INDUSTRY_OPTIONS,
                    'validation' => ['required' => false],
                    'settings' => null,
                ],
                'industry' => [
                    'type' => CustomField::TYPE_SELECT,
                    'label' => ['en' => 'Industry', 'id' => 'Industri', 'ja' => '業界', 'ko' => '산업', 'zh' => '行业'],
                    'options' => self::INDUSTRY_OPTIONS,
                    'validation' => ['required' => false],
                    'settings' => null,
                ],
                'company_size' => [
                    'type' => CustomField::TYPE_SELECT,
                    'label' => ['en' => 'Company size', 'id' => 'Ukuran perusahaan', 'ja' => '会社規模', 'ko' => '회사 규모', 'zh' => '公司规模'],
                    'options' => [
                        ['value' => '1-10', 'label' => ['en' => '1-10 employees', 'id' => '1-10 karyawan', 'ja' => '1〜10人', 'ko' => '1-10명', 'zh' => '1-10人']],
                        ['value' => '11-50', 'label' => ['en' => '11-50 employees', 'id' => '11-50 karyawan', 'ja' => '11〜50人', 'ko' => '11-50명', 'zh' => '11-50人']],
                        ['value' => '51-200', 'label' => ['en' => '51-200 employees', 'id' => '51-200 karyawan', 'ja' => '51〜200人', 'ko' => '51-200명', 'zh' => '51-200人']],
                        ['value' => '201-1000', 'label' => ['en' => '201-1,000 employees', 'id' => '201-1.000 karyawan', 'ja' => '201〜1,000人', 'ko' => '201-1,000명', 'zh' => '201-1000人']],
                        ['value' => '1000+', 'label' => ['en' => 'More than 1,000 employees', 'id' => 'Lebih dari 1.000 karyawan', 'ja' => '1,000人以上', 'ko' => '1,000명 이상', 'zh' => '1000人以上']],
                    ],
                    'validation' => ['required' => false],
                    'settings' => null,
                ],
                'objectives' => [
                    'type' => CustomField::TYPE_MULTI_SELECT,
                    'label' => ['en' => 'What are you looking for?', 'id' => 'Apa yang Anda cari?', 'ja' => 'お探しの内容', 'ko' => '찾고 있는 것', 'zh' => '您在寻找什么？'],
                    'options' => [
                        ['value' => 'find_suppliers', 'label' => ['en' => 'Find suppliers', 'id' => 'Mencari pemasok', 'ja' => 'サプライヤー探し', 'ko' => '공급업체 찾기', 'zh' => '寻找供应商']],
                        ['value' => 'find_distributors', 'label' => ['en' => 'Find distributors', 'id' => 'Mencari distributor', 'ja' => '販売代理店探し', 'ko' => '유통업체 찾기', 'zh' => '寻找经销商']],
                        ['value' => 'networking', 'label' => ['en' => 'Networking', 'id' => 'Membangun relasi', 'ja' => 'ネットワーキング', 'ko' => '네트워킹', 'zh' => '拓展人脉']],
                        ['value' => 'investment', 'label' => ['en' => 'Investment opportunities', 'id' => 'Peluang investasi', 'ja' => '投資機会', 'ko' => '투자 기회', 'zh' => '投资机会']],
                        ['value' => 'franchise', 'label' => ['en' => 'Franchise opportunities', 'id' => 'Peluang waralaba', 'ja' => 'フランチャイズ機会', 'ko' => '프랜차이즈 기회', 'zh' => '特许经营机会']],
                        ['value' => 'market_research', 'label' => ['en' => 'Market research', 'id' => 'Riset pasar', 'ja' => '市場調査', 'ko' => '시장 조사', 'zh' => '市场调研']],
                        ['value' => 'partnerships', 'label' => ['en' => 'Partnerships', 'id' => 'Kemitraan', 'ja' => 'パートナーシップ', 'ko' => '파트너십', 'zh' => '合作伙伴']],
                        ['value' => 'sourcing_products', 'label' => ['en' => 'Sourcing products', 'id' => 'Mencari produk', 'ja' => '商品調達', 'ko' => '제품 소싱', 'zh' => '采购产品']],
                    ],
                    'validation' => ['required' => false],
                    'settings' => null,
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function catalog(string $context): array
    {
        return self::definitions()[$context] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $context, string $systemKey): ?array
    {
        return self::catalog($context)[$systemKey] ?? null;
    }

    /**
     * Ready-to-fill CustomField attribute payload for instantiating a library
     * field on an owner. Labels/options are copied so staff can edit them on
     * the instantiated row without affecting the catalog.
     *
     * @return array<string, mixed>
     */
    public static function attributesFor(string $context, string $systemKey): array
    {
        $definition = self::get($context, $systemKey);

        if ($definition === null) {
            return [];
        }

        return [
            'context' => $context,
            'type' => $definition['type'],
            'label' => $definition['label'],
            'options' => $definition['options'],
            'validation' => $definition['validation'],
            'settings' => $definition['settings'],
            'system_key' => $systemKey,
            'is_active' => true,
        ];
    }
}
