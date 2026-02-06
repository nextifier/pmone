<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FetchExchangeRates;
use App\Models\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    /**
     * Currency metadata (ISO 4217 codes with names and country codes for flags)
     *
     * @var array<string, array{name: string, country: string}>
     */
    private const CURRENCY_META = [
        'USD' => ['name' => 'United States Dollar', 'country' => 'us'],
        'EUR' => ['name' => 'Euro', 'country' => 'eu'],
        'GBP' => ['name' => 'British Pound Sterling', 'country' => 'gb'],
        'JPY' => ['name' => 'Japanese Yen', 'country' => 'jp'],
        'AUD' => ['name' => 'Australian Dollar', 'country' => 'au'],
        'CAD' => ['name' => 'Canadian Dollar', 'country' => 'ca'],
        'CHF' => ['name' => 'Swiss Franc', 'country' => 'ch'],
        'CNY' => ['name' => 'Chinese Yuan', 'country' => 'cn'],
        'HKD' => ['name' => 'Hong Kong Dollar', 'country' => 'hk'],
        'NZD' => ['name' => 'New Zealand Dollar', 'country' => 'nz'],
        'SEK' => ['name' => 'Swedish Krona', 'country' => 'se'],
        'KRW' => ['name' => 'South Korean Won', 'country' => 'kr'],
        'SGD' => ['name' => 'Singapore Dollar', 'country' => 'sg'],
        'NOK' => ['name' => 'Norwegian Krone', 'country' => 'no'],
        'MXN' => ['name' => 'Mexican Peso', 'country' => 'mx'],
        'INR' => ['name' => 'Indian Rupee', 'country' => 'in'],
        'RUB' => ['name' => 'Russian Ruble', 'country' => 'ru'],
        'ZAR' => ['name' => 'South African Rand', 'country' => 'za'],
        'TRY' => ['name' => 'Turkish Lira', 'country' => 'tr'],
        'BRL' => ['name' => 'Brazilian Real', 'country' => 'br'],
        'TWD' => ['name' => 'New Taiwan Dollar', 'country' => 'tw'],
        'DKK' => ['name' => 'Danish Krone', 'country' => 'dk'],
        'PLN' => ['name' => 'Polish Zloty', 'country' => 'pl'],
        'THB' => ['name' => 'Thai Baht', 'country' => 'th'],
        'IDR' => ['name' => 'Indonesian Rupiah', 'country' => 'id'],
        'HUF' => ['name' => 'Hungarian Forint', 'country' => 'hu'],
        'CZK' => ['name' => 'Czech Koruna', 'country' => 'cz'],
        'ILS' => ['name' => 'Israeli New Shekel', 'country' => 'il'],
        'CLP' => ['name' => 'Chilean Peso', 'country' => 'cl'],
        'PHP' => ['name' => 'Philippine Peso', 'country' => 'ph'],
        'AED' => ['name' => 'UAE Dirham', 'country' => 'ae'],
        'COP' => ['name' => 'Colombian Peso', 'country' => 'co'],
        'SAR' => ['name' => 'Saudi Riyal', 'country' => 'sa'],
        'MYR' => ['name' => 'Malaysian Ringgit', 'country' => 'my'],
        'RON' => ['name' => 'Romanian Leu', 'country' => 'ro'],
        'VND' => ['name' => 'Vietnamese Dong', 'country' => 'vn'],
        'BGN' => ['name' => 'Bulgarian Lev', 'country' => 'bg'],
        'ARS' => ['name' => 'Argentine Peso', 'country' => 'ar'],
        'NGN' => ['name' => 'Nigerian Naira', 'country' => 'ng'],
        'EGP' => ['name' => 'Egyptian Pound', 'country' => 'eg'],
        'PKR' => ['name' => 'Pakistani Rupee', 'country' => 'pk'],
        'BDT' => ['name' => 'Bangladeshi Taka', 'country' => 'bd'],
        'UAH' => ['name' => 'Ukrainian Hryvnia', 'country' => 'ua'],
        'PEN' => ['name' => 'Peruvian Sol', 'country' => 'pe'],
        'KES' => ['name' => 'Kenyan Shilling', 'country' => 'ke'],
        'GHS' => ['name' => 'Ghanaian Cedi', 'country' => 'gh'],
        'MAD' => ['name' => 'Moroccan Dirham', 'country' => 'ma'],
        'QAR' => ['name' => 'Qatari Riyal', 'country' => 'qa'],
        'KWD' => ['name' => 'Kuwaiti Dinar', 'country' => 'kw'],
        'BHD' => ['name' => 'Bahraini Dinar', 'country' => 'bh'],
        'OMR' => ['name' => 'Omani Rial', 'country' => 'om'],
        'JOD' => ['name' => 'Jordanian Dinar', 'country' => 'jo'],
        'LKR' => ['name' => 'Sri Lankan Rupee', 'country' => 'lk'],
        'NPR' => ['name' => 'Nepalese Rupee', 'country' => 'np'],
        'MMK' => ['name' => 'Myanmar Kyat', 'country' => 'mm'],
        'KHR' => ['name' => 'Cambodian Riel', 'country' => 'kh'],
        'LAK' => ['name' => 'Lao Kip', 'country' => 'la'],
        'BND' => ['name' => 'Brunei Dollar', 'country' => 'bn'],
        'ISK' => ['name' => 'Icelandic Krona', 'country' => 'is'],
        'HRK' => ['name' => 'Croatian Kuna', 'country' => 'hr'],
        'RSD' => ['name' => 'Serbian Dinar', 'country' => 'rs'],
        'DZD' => ['name' => 'Algerian Dinar', 'country' => 'dz'],
        'TND' => ['name' => 'Tunisian Dinar', 'country' => 'tn'],
        'LBP' => ['name' => 'Lebanese Pound', 'country' => 'lb'],
        'IQD' => ['name' => 'Iraqi Dinar', 'country' => 'iq'],
        'IRR' => ['name' => 'Iranian Rial', 'country' => 'ir'],
        'AFN' => ['name' => 'Afghan Afghani', 'country' => 'af'],
        'ALL' => ['name' => 'Albanian Lek', 'country' => 'al'],
        'AMD' => ['name' => 'Armenian Dram', 'country' => 'am'],
        'AOA' => ['name' => 'Angolan Kwanza', 'country' => 'ao'],
        'AWG' => ['name' => 'Aruban Florin', 'country' => 'aw'],
        'AZN' => ['name' => 'Azerbaijani Manat', 'country' => 'az'],
        'BAM' => ['name' => 'Bosnia-Herzegovina Convertible Mark', 'country' => 'ba'],
        'BBD' => ['name' => 'Barbadian Dollar', 'country' => 'bb'],
        'BMD' => ['name' => 'Bermudan Dollar', 'country' => 'bm'],
        'BOB' => ['name' => 'Bolivian Boliviano', 'country' => 'bo'],
        'BSD' => ['name' => 'Bahamian Dollar', 'country' => 'bs'],
        'BTN' => ['name' => 'Bhutanese Ngultrum', 'country' => 'bt'],
        'BWP' => ['name' => 'Botswanan Pula', 'country' => 'bw'],
        'BYN' => ['name' => 'Belarusian Ruble', 'country' => 'by'],
        'BZD' => ['name' => 'Belize Dollar', 'country' => 'bz'],
        'CDF' => ['name' => 'Congolese Franc', 'country' => 'cd'],
        'CRC' => ['name' => 'Costa Rican Colon', 'country' => 'cr'],
        'CUP' => ['name' => 'Cuban Peso', 'country' => 'cu'],
        'CVE' => ['name' => 'Cape Verdean Escudo', 'country' => 'cv'],
        'DJF' => ['name' => 'Djiboutian Franc', 'country' => 'dj'],
        'DOP' => ['name' => 'Dominican Peso', 'country' => 'do'],
        'ERN' => ['name' => 'Eritrean Nakfa', 'country' => 'er'],
        'ETB' => ['name' => 'Ethiopian Birr', 'country' => 'et'],
        'FJD' => ['name' => 'Fijian Dollar', 'country' => 'fj'],
        'FKP' => ['name' => 'Falkland Islands Pound', 'country' => 'fk'],
        'GEL' => ['name' => 'Georgian Lari', 'country' => 'ge'],
        'GIP' => ['name' => 'Gibraltar Pound', 'country' => 'gi'],
        'GMD' => ['name' => 'Gambian Dalasi', 'country' => 'gm'],
        'GNF' => ['name' => 'Guinean Franc', 'country' => 'gn'],
        'GTQ' => ['name' => 'Guatemalan Quetzal', 'country' => 'gt'],
        'GYD' => ['name' => 'Guyanaese Dollar', 'country' => 'gy'],
        'HNL' => ['name' => 'Honduran Lempira', 'country' => 'hn'],
        'HTG' => ['name' => 'Haitian Gourde', 'country' => 'ht'],
        'JMD' => ['name' => 'Jamaican Dollar', 'country' => 'jm'],
        'KGS' => ['name' => 'Kyrgystani Som', 'country' => 'kg'],
        'KMF' => ['name' => 'Comorian Franc', 'country' => 'km'],
        'KPW' => ['name' => 'North Korean Won', 'country' => 'kp'],
        'KYD' => ['name' => 'Cayman Islands Dollar', 'country' => 'ky'],
        'KZT' => ['name' => 'Kazakhstani Tenge', 'country' => 'kz'],
        'LRD' => ['name' => 'Liberian Dollar', 'country' => 'lr'],
        'LSL' => ['name' => 'Lesotho Loti', 'country' => 'ls'],
        'LYD' => ['name' => 'Libyan Dinar', 'country' => 'ly'],
        'MDL' => ['name' => 'Moldovan Leu', 'country' => 'md'],
        'MGA' => ['name' => 'Malagasy Ariary', 'country' => 'mg'],
        'MKD' => ['name' => 'Macedonian Denar', 'country' => 'mk'],
        'MNT' => ['name' => 'Mongolian Tugrik', 'country' => 'mn'],
        'MOP' => ['name' => 'Macanese Pataca', 'country' => 'mo'],
        'MRU' => ['name' => 'Mauritanian Ouguiya', 'country' => 'mr'],
        'MUR' => ['name' => 'Mauritian Rupee', 'country' => 'mu'],
        'MVR' => ['name' => 'Maldivian Rufiyaa', 'country' => 'mv'],
        'MWK' => ['name' => 'Malawian Kwacha', 'country' => 'mw'],
        'MZN' => ['name' => 'Mozambican Metical', 'country' => 'mz'],
        'NAD' => ['name' => 'Namibian Dollar', 'country' => 'na'],
        'NIO' => ['name' => 'Nicaraguan Cordoba', 'country' => 'ni'],
        'PAB' => ['name' => 'Panamanian Balboa', 'country' => 'pa'],
        'PGK' => ['name' => 'Papua New Guinean Kina', 'country' => 'pg'],
        'PYG' => ['name' => 'Paraguayan Guarani', 'country' => 'py'],
        'RWF' => ['name' => 'Rwandan Franc', 'country' => 'rw'],
        'SBD' => ['name' => 'Solomon Islands Dollar', 'country' => 'sb'],
        'SCR' => ['name' => 'Seychellois Rupee', 'country' => 'sc'],
        'SDG' => ['name' => 'Sudanese Pound', 'country' => 'sd'],
        'SHP' => ['name' => 'Saint Helena Pound', 'country' => 'sh'],
        'SLE' => ['name' => 'Sierra Leonean Leone', 'country' => 'sl'],
        'SOS' => ['name' => 'Somali Shilling', 'country' => 'so'],
        'SRD' => ['name' => 'Surinamese Dollar', 'country' => 'sr'],
        'SSP' => ['name' => 'South Sudanese Pound', 'country' => 'ss'],
        'STN' => ['name' => 'Sao Tome and Principe Dobra', 'country' => 'st'],
        'SVC' => ['name' => 'Salvadoran Colon', 'country' => 'sv'],
        'SYP' => ['name' => 'Syrian Pound', 'country' => 'sy'],
        'SZL' => ['name' => 'Swazi Lilangeni', 'country' => 'sz'],
        'TJS' => ['name' => 'Tajikistani Somoni', 'country' => 'tj'],
        'TMT' => ['name' => 'Turkmenistani Manat', 'country' => 'tm'],
        'TOP' => ['name' => 'Tongan Paʻanga', 'country' => 'to'],
        'TTD' => ['name' => 'Trinidad and Tobago Dollar', 'country' => 'tt'],
        'TZS' => ['name' => 'Tanzanian Shilling', 'country' => 'tz'],
        'UGX' => ['name' => 'Ugandan Shilling', 'country' => 'ug'],
        'UYU' => ['name' => 'Uruguayan Peso', 'country' => 'uy'],
        'UZS' => ['name' => 'Uzbekistan Som', 'country' => 'uz'],
        'VES' => ['name' => 'Venezuelan Bolivar', 'country' => 've'],
        'VUV' => ['name' => 'Vanuatu Vatu', 'country' => 'vu'],
        'WST' => ['name' => 'Samoan Tala', 'country' => 'ws'],
        'XAF' => ['name' => 'CFA Franc BEAC', 'country' => 'cm'],
        'XCD' => ['name' => 'East Caribbean Dollar', 'country' => 'ag'],
        'XOF' => ['name' => 'CFA Franc BCEAO', 'country' => 'sn'],
        'XPF' => ['name' => 'CFP Franc', 'country' => 'pf'],
        'YER' => ['name' => 'Yemeni Rial', 'country' => 'ye'],
        'ZMW' => ['name' => 'Zambian Kwacha', 'country' => 'zm'],
        'ZWL' => ['name' => 'Zimbabwean Dollar', 'country' => 'zw'],
    ];

    /**
     * Popular currencies for frontend display
     *
     * @var array<string>
     */
    private const POPULAR_CURRENCIES = [
        'USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD', 'CHF', 'CNY',
        'HKD', 'NZD', 'SGD', 'KRW', 'INR', 'IDR', 'MYR', 'THB',
    ];

    /**
     * Get all exchange rates with metadata
     */
    public function index(Request $request): JsonResponse
    {
        $exchangeRate = ExchangeRate::getLatest();

        if (! $exchangeRate) {
            // Trigger fetch if no data exists
            FetchExchangeRates::dispatchSync();
            $exchangeRate = ExchangeRate::getLatest();
        }

        if (! $exchangeRate) {
            return response()->json([
                'message' => 'Exchange rate data not available',
            ], 503);
        }

        // Determine base currency (default: USD from DB)
        $requestedBase = strtoupper($request->input('base', $exchangeRate->base_currency));
        $rates = $exchangeRate->rates;

        // Re-base rates if a different base currency is requested
        if ($requestedBase !== $exchangeRate->base_currency && isset($rates[$requestedBase])) {
            $baseRate = $rates[$requestedBase];
            $rebasedRates = [];
            foreach ($rates as $code => $rate) {
                $rebasedRates[$code] = $rate / $baseRate;
            }
            $rates = $rebasedRates;
        }

        // Build rates with metadata
        $ratesWithMeta = $this->buildRatesWithMetadata($rates);

        // Filter by search term if provided
        $search = $request->input('search');
        if ($search) {
            $search = strtolower($search);
            $ratesWithMeta = array_filter($ratesWithMeta, function ($rate) use ($search) {
                return str_contains(strtolower($rate['code']), $search)
                    || str_contains(strtolower($rate['name']), $search);
            });
            $ratesWithMeta = array_values($ratesWithMeta);
        }

        // Sort: popular currencies first, then alphabetically
        usort($ratesWithMeta, function ($a, $b) {
            $aPopular = in_array($a['code'], self::POPULAR_CURRENCIES);
            $bPopular = in_array($b['code'], self::POPULAR_CURRENCIES);

            if ($aPopular && ! $bPopular) {
                return -1;
            }
            if (! $aPopular && $bPopular) {
                return 1;
            }

            // If both are popular, sort by popularity order
            if ($aPopular && $bPopular) {
                return array_search($a['code'], self::POPULAR_CURRENCIES) - array_search($b['code'], self::POPULAR_CURRENCIES);
            }

            return strcmp($a['code'], $b['code']);
        });

        return response()->json([
            'data' => [
                'base_currency' => $requestedBase,
                'rates' => $ratesWithMeta,
                'rates_count' => count($ratesWithMeta),
            ],
            'meta' => [
                'api_updated_at' => $exchangeRate->api_updated_at?->toIso8601String(),
                'fetched_at' => $exchangeRate->fetched_at->toIso8601String(),
                'is_stale' => $exchangeRate->isStale(),
            ],
        ]);
    }

    /**
     * Get specific currency rate
     */
    public function show(string $currency): JsonResponse
    {
        $currency = strtoupper($currency);
        $exchangeRate = ExchangeRate::getLatest();

        if (! $exchangeRate) {
            return response()->json([
                'message' => 'Exchange rate data not available',
            ], 503);
        }

        $rate = $exchangeRate->getRate($currency);

        if ($rate === null) {
            return response()->json([
                'message' => 'Currency not found',
            ], 404);
        }

        $meta = self::CURRENCY_META[$currency] ?? [
            'name' => $currency,
            'country' => strtolower(substr($currency, 0, 2)),
        ];

        return response()->json([
            'data' => [
                'code' => $currency,
                'name' => $meta['name'],
                'country' => $meta['country'],
                'rate' => $rate,
                'base_currency' => $exchangeRate->base_currency,
            ],
            'meta' => [
                'api_updated_at' => $exchangeRate->api_updated_at?->toIso8601String(),
                'fetched_at' => $exchangeRate->fetched_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Convert amount between currencies
     */
    public function convert(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
        ]);

        $amount = (float) $request->input('amount');
        $from = strtoupper($request->input('from'));
        $to = strtoupper($request->input('to'));

        $exchangeRate = ExchangeRate::getLatest();

        if (! $exchangeRate) {
            return response()->json([
                'message' => 'Exchange rate data not available',
            ], 503);
        }

        $result = $exchangeRate->convertBetween($amount, $from, $to);

        if ($result === null) {
            return response()->json([
                'message' => 'One or both currencies not found',
            ], 400);
        }

        $fromMeta = self::CURRENCY_META[$from] ?? ['name' => $from, 'country' => strtolower(substr($from, 0, 2))];
        $toMeta = self::CURRENCY_META[$to] ?? ['name' => $to, 'country' => strtolower(substr($to, 0, 2))];

        return response()->json([
            'data' => [
                'amount' => $amount,
                'from' => [
                    'code' => $from,
                    'name' => $fromMeta['name'],
                    'country' => $fromMeta['country'],
                    'rate' => $exchangeRate->getRate($from),
                ],
                'to' => [
                    'code' => $to,
                    'name' => $toMeta['name'],
                    'country' => $toMeta['country'],
                    'rate' => $exchangeRate->getRate($to),
                ],
                'result' => round($result, 6),
                'result_formatted' => number_format($result, 2),
            ],
            'meta' => [
                'api_updated_at' => $exchangeRate->api_updated_at?->toIso8601String(),
                'fetched_at' => $exchangeRate->fetched_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get list of available currencies
     */
    public function currencies(): JsonResponse
    {
        $exchangeRate = ExchangeRate::getLatest();

        if (! $exchangeRate) {
            return response()->json([
                'message' => 'Exchange rate data not available',
            ], 503);
        }

        $currencies = [];
        foreach (array_keys($exchangeRate->rates) as $code) {
            $meta = self::CURRENCY_META[$code] ?? [
                'name' => $code,
                'country' => strtolower(substr($code, 0, 2)),
            ];

            $currencies[] = [
                'code' => $code,
                'name' => $meta['name'],
                'country' => $meta['country'],
                'is_popular' => in_array($code, self::POPULAR_CURRENCIES),
            ];
        }

        // Sort: popular first, then alphabetically
        usort($currencies, function ($a, $b) {
            if ($a['is_popular'] && ! $b['is_popular']) {
                return -1;
            }
            if (! $a['is_popular'] && $b['is_popular']) {
                return 1;
            }
            if ($a['is_popular'] && $b['is_popular']) {
                return array_search($a['code'], self::POPULAR_CURRENCIES) - array_search($b['code'], self::POPULAR_CURRENCIES);
            }

            return strcmp($a['code'], $b['code']);
        });

        return response()->json([
            'data' => $currencies,
            'meta' => [
                'count' => count($currencies),
            ],
        ]);
    }

    /**
     * Get popular currencies only
     */
    public function popular(): JsonResponse
    {
        $exchangeRate = ExchangeRate::getLatest();

        if (! $exchangeRate) {
            return response()->json([
                'message' => 'Exchange rate data not available',
            ], 503);
        }

        $rates = [];
        foreach (self::POPULAR_CURRENCIES as $code) {
            $rate = $exchangeRate->getRate($code);
            if ($rate !== null) {
                $meta = self::CURRENCY_META[$code] ?? [
                    'name' => $code,
                    'country' => strtolower(substr($code, 0, 2)),
                ];

                $rates[] = [
                    'code' => $code,
                    'name' => $meta['name'],
                    'country' => $meta['country'],
                    'rate' => $rate,
                ];
            }
        }

        return response()->json([
            'data' => [
                'base_currency' => $exchangeRate->base_currency,
                'rates' => $rates,
            ],
            'meta' => [
                'api_updated_at' => $exchangeRate->api_updated_at?->toIso8601String(),
                'fetched_at' => $exchangeRate->fetched_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Build rates array with metadata
     *
     * @param  array<string, float>  $rates
     * @return array<int, array{code: string, name: string, country: string, rate: float, is_popular: bool}>
     */
    private function buildRatesWithMetadata(array $rates): array
    {
        $result = [];

        foreach ($rates as $code => $rate) {
            $meta = self::CURRENCY_META[$code] ?? [
                'name' => $code,
                'country' => strtolower(substr($code, 0, 2)),
            ];

            $result[] = [
                'code' => $code,
                'name' => $meta['name'],
                'country' => $meta['country'],
                'rate' => $rate,
                'is_popular' => in_array($code, self::POPULAR_CURRENCIES),
            ];
        }

        return $result;
    }
}
