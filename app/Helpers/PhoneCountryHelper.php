<?php

namespace App\Helpers;

class PhoneCountryHelper
{
    /**
     * Phone prefix to country code and name mapping.
     * Sorted by prefix length (longest first) for accurate matching.
     *
     * @var array<string, array{code: string, name: string}>
     */
    private static array $prefixMap = [
        '+998' => ['code' => 'UZ', 'name' => 'Uzbekistan'],
        '+996' => ['code' => 'KG', 'name' => 'Kyrgyzstan'],
        '+995' => ['code' => 'GE', 'name' => 'Georgia'],
        '+994' => ['code' => 'AZ', 'name' => 'Azerbaijan'],
        '+993' => ['code' => 'TM', 'name' => 'Turkmenistan'],
        '+992' => ['code' => 'TJ', 'name' => 'Tajikistan'],
        '+977' => ['code' => 'NP', 'name' => 'Nepal'],
        '+976' => ['code' => 'MN', 'name' => 'Mongolia'],
        '+975' => ['code' => 'BT', 'name' => 'Bhutan'],
        '+974' => ['code' => 'QA', 'name' => 'Qatar'],
        '+973' => ['code' => 'BH', 'name' => 'Bahrain'],
        '+972' => ['code' => 'IL', 'name' => 'Israel'],
        '+971' => ['code' => 'AE', 'name' => 'United Arab Emirates'],
        '+970' => ['code' => 'PS', 'name' => 'Palestine'],
        '+968' => ['code' => 'OM', 'name' => 'Oman'],
        '+967' => ['code' => 'YE', 'name' => 'Yemen'],
        '+966' => ['code' => 'SA', 'name' => 'Saudi Arabia'],
        '+965' => ['code' => 'KW', 'name' => 'Kuwait'],
        '+964' => ['code' => 'IQ', 'name' => 'Iraq'],
        '+963' => ['code' => 'SY', 'name' => 'Syria'],
        '+962' => ['code' => 'JO', 'name' => 'Jordan'],
        '+961' => ['code' => 'LB', 'name' => 'Lebanon'],
        '+960' => ['code' => 'MV', 'name' => 'Maldives'],
        '+886' => ['code' => 'TW', 'name' => 'Taiwan'],
        '+880' => ['code' => 'BD', 'name' => 'Bangladesh'],
        '+856' => ['code' => 'LA', 'name' => 'Laos'],
        '+855' => ['code' => 'KH', 'name' => 'Cambodia'],
        '+853' => ['code' => 'MO', 'name' => 'Macao'],
        '+852' => ['code' => 'HK', 'name' => 'Hong Kong'],
        '+850' => ['code' => 'KP', 'name' => 'North Korea'],
        '+692' => ['code' => 'MH', 'name' => 'Marshall Islands'],
        '+691' => ['code' => 'FM', 'name' => 'Micronesia'],
        '+688' => ['code' => 'TV', 'name' => 'Tuvalu'],
        '+686' => ['code' => 'KI', 'name' => 'Kiribati'],
        '+685' => ['code' => 'WS', 'name' => 'Samoa'],
        '+680' => ['code' => 'PW', 'name' => 'Palau'],
        '+679' => ['code' => 'FJ', 'name' => 'Fiji'],
        '+678' => ['code' => 'VU', 'name' => 'Vanuatu'],
        '+677' => ['code' => 'SB', 'name' => 'Solomon Islands'],
        '+676' => ['code' => 'TO', 'name' => 'Tonga'],
        '+675' => ['code' => 'PG', 'name' => 'Papua New Guinea'],
        '+674' => ['code' => 'NR', 'name' => 'Nauru'],
        '+673' => ['code' => 'BN', 'name' => 'Brunei'],
        '+670' => ['code' => 'TL', 'name' => 'Timor-Leste'],
        '+598' => ['code' => 'UY', 'name' => 'Uruguay'],
        '+597' => ['code' => 'SR', 'name' => 'Suriname'],
        '+595' => ['code' => 'PY', 'name' => 'Paraguay'],
        '+593' => ['code' => 'EC', 'name' => 'Ecuador'],
        '+592' => ['code' => 'GY', 'name' => 'Guyana'],
        '+591' => ['code' => 'BO', 'name' => 'Bolivia'],
        '+509' => ['code' => 'HT', 'name' => 'Haiti'],
        '+507' => ['code' => 'PA', 'name' => 'Panama'],
        '+506' => ['code' => 'CR', 'name' => 'Costa Rica'],
        '+505' => ['code' => 'NI', 'name' => 'Nicaragua'],
        '+504' => ['code' => 'HN', 'name' => 'Honduras'],
        '+503' => ['code' => 'SV', 'name' => 'El Salvador'],
        '+502' => ['code' => 'GT', 'name' => 'Guatemala'],
        '+501' => ['code' => 'BZ', 'name' => 'Belize'],
        '+423' => ['code' => 'LI', 'name' => 'Liechtenstein'],
        '+421' => ['code' => 'SK', 'name' => 'Slovakia'],
        '+420' => ['code' => 'CZ', 'name' => 'Czech Republic'],
        '+389' => ['code' => 'MK', 'name' => 'North Macedonia'],
        '+387' => ['code' => 'BA', 'name' => 'Bosnia and Herzegovina'],
        '+386' => ['code' => 'SI', 'name' => 'Slovenia'],
        '+385' => ['code' => 'HR', 'name' => 'Croatia'],
        '+382' => ['code' => 'ME', 'name' => 'Montenegro'],
        '+381' => ['code' => 'RS', 'name' => 'Serbia'],
        '+380' => ['code' => 'UA', 'name' => 'Ukraine'],
        '+379' => ['code' => 'VA', 'name' => 'Vatican City'],
        '+378' => ['code' => 'SM', 'name' => 'San Marino'],
        '+377' => ['code' => 'MC', 'name' => 'Monaco'],
        '+376' => ['code' => 'AD', 'name' => 'Andorra'],
        '+375' => ['code' => 'BY', 'name' => 'Belarus'],
        '+374' => ['code' => 'AM', 'name' => 'Armenia'],
        '+373' => ['code' => 'MD', 'name' => 'Moldova'],
        '+372' => ['code' => 'EE', 'name' => 'Estonia'],
        '+371' => ['code' => 'LV', 'name' => 'Latvia'],
        '+370' => ['code' => 'LT', 'name' => 'Lithuania'],
        '+359' => ['code' => 'BG', 'name' => 'Bulgaria'],
        '+358' => ['code' => 'FI', 'name' => 'Finland'],
        '+357' => ['code' => 'CY', 'name' => 'Cyprus'],
        '+356' => ['code' => 'MT', 'name' => 'Malta'],
        '+355' => ['code' => 'AL', 'name' => 'Albania'],
        '+354' => ['code' => 'IS', 'name' => 'Iceland'],
        '+353' => ['code' => 'IE', 'name' => 'Ireland'],
        '+352' => ['code' => 'LU', 'name' => 'Luxembourg'],
        '+351' => ['code' => 'PT', 'name' => 'Portugal'],
        '+297' => ['code' => 'AW', 'name' => 'Aruba'],
        '+291' => ['code' => 'ER', 'name' => 'Eritrea'],
        '+269' => ['code' => 'KM', 'name' => 'Comoros'],
        '+268' => ['code' => 'SZ', 'name' => 'Eswatini'],
        '+267' => ['code' => 'BW', 'name' => 'Botswana'],
        '+266' => ['code' => 'LS', 'name' => 'Lesotho'],
        '+265' => ['code' => 'MW', 'name' => 'Malawi'],
        '+264' => ['code' => 'NA', 'name' => 'Namibia'],
        '+263' => ['code' => 'ZW', 'name' => 'Zimbabwe'],
        '+261' => ['code' => 'MG', 'name' => 'Madagascar'],
        '+260' => ['code' => 'ZM', 'name' => 'Zambia'],
        '+258' => ['code' => 'MZ', 'name' => 'Mozambique'],
        '+256' => ['code' => 'UG', 'name' => 'Uganda'],
        '+255' => ['code' => 'TZ', 'name' => 'Tanzania'],
        '+254' => ['code' => 'KE', 'name' => 'Kenya'],
        '+253' => ['code' => 'DJ', 'name' => 'Djibouti'],
        '+252' => ['code' => 'SO', 'name' => 'Somalia'],
        '+251' => ['code' => 'ET', 'name' => 'Ethiopia'],
        '+250' => ['code' => 'RW', 'name' => 'Rwanda'],
        '+249' => ['code' => 'SD', 'name' => 'Sudan'],
        '+248' => ['code' => 'SC', 'name' => 'Seychelles'],
        '+245' => ['code' => 'GW', 'name' => 'Guinea-Bissau'],
        '+244' => ['code' => 'AO', 'name' => 'Angola'],
        '+243' => ['code' => 'CD', 'name' => 'DR Congo'],
        '+242' => ['code' => 'CG', 'name' => 'Congo'],
        '+241' => ['code' => 'GA', 'name' => 'Gabon'],
        '+240' => ['code' => 'GQ', 'name' => 'Equatorial Guinea'],
        '+239' => ['code' => 'ST', 'name' => 'São Tomé and Príncipe'],
        '+238' => ['code' => 'CV', 'name' => 'Cape Verde'],
        '+237' => ['code' => 'CM', 'name' => 'Cameroon'],
        '+236' => ['code' => 'CF', 'name' => 'Central African Republic'],
        '+235' => ['code' => 'TD', 'name' => 'Chad'],
        '+234' => ['code' => 'NG', 'name' => 'Nigeria'],
        '+233' => ['code' => 'GH', 'name' => 'Ghana'],
        '+232' => ['code' => 'SL', 'name' => 'Sierra Leone'],
        '+231' => ['code' => 'LR', 'name' => 'Liberia'],
        '+230' => ['code' => 'MU', 'name' => 'Mauritius'],
        '+229' => ['code' => 'BJ', 'name' => 'Benin'],
        '+228' => ['code' => 'TG', 'name' => 'Togo'],
        '+227' => ['code' => 'NE', 'name' => 'Niger'],
        '+226' => ['code' => 'BF', 'name' => 'Burkina Faso'],
        '+225' => ['code' => 'CI', 'name' => "Côte d'Ivoire"],
        '+224' => ['code' => 'GN', 'name' => 'Guinea'],
        '+223' => ['code' => 'ML', 'name' => 'Mali'],
        '+222' => ['code' => 'MR', 'name' => 'Mauritania'],
        '+221' => ['code' => 'SN', 'name' => 'Senegal'],
        '+220' => ['code' => 'GM', 'name' => 'Gambia'],
        '+218' => ['code' => 'LY', 'name' => 'Libya'],
        '+216' => ['code' => 'TN', 'name' => 'Tunisia'],
        '+213' => ['code' => 'DZ', 'name' => 'Algeria'],
        '+212' => ['code' => 'MA', 'name' => 'Morocco'],
        '+211' => ['code' => 'SS', 'name' => 'South Sudan'],
        '+98' => ['code' => 'IR', 'name' => 'Iran'],
        '+95' => ['code' => 'MM', 'name' => 'Myanmar'],
        '+94' => ['code' => 'LK', 'name' => 'Sri Lanka'],
        '+93' => ['code' => 'AF', 'name' => 'Afghanistan'],
        '+92' => ['code' => 'PK', 'name' => 'Pakistan'],
        '+91' => ['code' => 'IN', 'name' => 'India'],
        '+90' => ['code' => 'TR', 'name' => 'Turkey'],
        '+86' => ['code' => 'CN', 'name' => 'China'],
        '+84' => ['code' => 'VN', 'name' => 'Vietnam'],
        '+82' => ['code' => 'KR', 'name' => 'South Korea'],
        '+81' => ['code' => 'JP', 'name' => 'Japan'],
        '+66' => ['code' => 'TH', 'name' => 'Thailand'],
        '+65' => ['code' => 'SG', 'name' => 'Singapore'],
        '+64' => ['code' => 'NZ', 'name' => 'New Zealand'],
        '+63' => ['code' => 'PH', 'name' => 'Philippines'],
        '+62' => ['code' => 'ID', 'name' => 'Indonesia'],
        '+61' => ['code' => 'AU', 'name' => 'Australia'],
        '+60' => ['code' => 'MY', 'name' => 'Malaysia'],
        '+58' => ['code' => 'VE', 'name' => 'Venezuela'],
        '+57' => ['code' => 'CO', 'name' => 'Colombia'],
        '+56' => ['code' => 'CL', 'name' => 'Chile'],
        '+55' => ['code' => 'BR', 'name' => 'Brazil'],
        '+54' => ['code' => 'AR', 'name' => 'Argentina'],
        '+53' => ['code' => 'CU', 'name' => 'Cuba'],
        '+52' => ['code' => 'MX', 'name' => 'Mexico'],
        '+51' => ['code' => 'PE', 'name' => 'Peru'],
        '+49' => ['code' => 'DE', 'name' => 'Germany'],
        '+48' => ['code' => 'PL', 'name' => 'Poland'],
        '+47' => ['code' => 'NO', 'name' => 'Norway'],
        '+46' => ['code' => 'SE', 'name' => 'Sweden'],
        '+45' => ['code' => 'DK', 'name' => 'Denmark'],
        '+44' => ['code' => 'GB', 'name' => 'United Kingdom'],
        '+43' => ['code' => 'AT', 'name' => 'Austria'],
        '+41' => ['code' => 'CH', 'name' => 'Switzerland'],
        '+40' => ['code' => 'RO', 'name' => 'Romania'],
        '+39' => ['code' => 'IT', 'name' => 'Italy'],
        '+36' => ['code' => 'HU', 'name' => 'Hungary'],
        '+34' => ['code' => 'ES', 'name' => 'Spain'],
        '+33' => ['code' => 'FR', 'name' => 'France'],
        '+32' => ['code' => 'BE', 'name' => 'Belgium'],
        '+31' => ['code' => 'NL', 'name' => 'Netherlands'],
        '+30' => ['code' => 'GR', 'name' => 'Greece'],
        '+27' => ['code' => 'ZA', 'name' => 'South Africa'],
        '+20' => ['code' => 'EG', 'name' => 'Egypt'],
        '+7' => ['code' => 'RU', 'name' => 'Russia'],
        '+1' => ['code' => 'US', 'name' => 'United States'],
    ];

    /**
     * Get country information from a phone number.
     *
     * @return array{code: string, name: string}|null
     */
    public static function getCountryFromPhone(string $phone): ?array
    {
        $phone = self::normalizePhoneNumber($phone);

        foreach (self::$prefixMap as $prefix => $country) {
            if (str_starts_with($phone, $prefix)) {
                return $country;
            }
        }

        return null;
    }

    /**
     * Get country name from a phone number.
     */
    public static function getCountryName(string $phone): ?string
    {
        return self::getCountryFromPhone($phone)['name'] ?? null;
    }

    /**
     * Normalize a phone number to international format.
     *
     * - Removes separators (spaces, dashes, dots, parentheses)
     * - Already has '+' -> return as-is
     * - Starts with '62' and digits >= 10 -> prepend '+'
     * - Starts with '0' (local Indonesian format) -> replace '0' with '+62'
     * - Other patterns -> try to match known prefix, prepend '+' if match
     * - No match -> return as-is
     */
    public static function normalizePhoneNumber(string $phone): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return $phone;
        }

        // Remove separators (spaces, dashes, dots, parentheses)
        $cleaned = preg_replace('/[\s\-\.\(\)]+/', '', $phone);

        // Already has '+' prefix -> return cleaned
        if (str_starts_with($cleaned, '+')) {
            return $cleaned;
        }

        // Starts with '62' and has enough digits (Indonesian international without +)
        if (str_starts_with($cleaned, '62') && strlen($cleaned) >= 10) {
            return '+'.$cleaned;
        }

        // Starts with '0' (local Indonesian format)
        if (str_starts_with($cleaned, '0')) {
            return '+62'.substr($cleaned, 1);
        }

        // Try to match against known prefixes (without +)
        foreach (array_keys(self::$prefixMap) as $prefix) {
            $prefixDigits = substr($prefix, 1); // Remove '+'
            if (str_starts_with($cleaned, $prefixDigits) && strlen($cleaned) >= strlen($prefixDigits) + 5) {
                return '+'.$cleaned;
            }
        }

        return $cleaned;
    }
}
