/**
 * Phone to Country Composable
 *
 * A utility composable for converting phone number prefixes to country information.
 * Useful for displaying country flags based on phone numbers.
 *
 * @example
 * ```typescript
 * const { getCountryFromPhone } = usePhoneCountry();
 *
 * const country = getCountryFromPhone('+6281234567890');
 * // { code: 'ID', name: 'Indonesia' }
 * ```
 */

export interface CountryInfo {
  code: string;
  name: string;
}

// Phone prefix to ISO2 country code and name mapping
const phoneToCountryMap: Record<string, CountryInfo> = {
  "+93": { code: "AF", name: "Afghanistan" },
  "+355": { code: "AL", name: "Albania" },
  "+213": { code: "DZ", name: "Algeria" },
  "+376": { code: "AD", name: "Andorra" },
  "+244": { code: "AO", name: "Angola" },
  "+54": { code: "AR", name: "Argentina" },
  "+374": { code: "AM", name: "Armenia" },
  "+297": { code: "AW", name: "Aruba" },
  "+61": { code: "AU", name: "Australia" },
  "+43": { code: "AT", name: "Austria" },
  "+994": { code: "AZ", name: "Azerbaijan" },
  "+973": { code: "BH", name: "Bahrain" },
  "+880": { code: "BD", name: "Bangladesh" },
  "+375": { code: "BY", name: "Belarus" },
  "+32": { code: "BE", name: "Belgium" },
  "+501": { code: "BZ", name: "Belize" },
  "+229": { code: "BJ", name: "Benin" },
  "+975": { code: "BT", name: "Bhutan" },
  "+591": { code: "BO", name: "Bolivia" },
  "+387": { code: "BA", name: "Bosnia and Herzegovina" },
  "+267": { code: "BW", name: "Botswana" },
  "+55": { code: "BR", name: "Brazil" },
  "+673": { code: "BN", name: "Brunei" },
  "+359": { code: "BG", name: "Bulgaria" },
  "+226": { code: "BF", name: "Burkina Faso" },
  "+257": { code: "BI", name: "Burundi" },
  "+855": { code: "KH", name: "Cambodia" },
  "+237": { code: "CM", name: "Cameroon" },
  "+1": { code: "US", name: "United States" },
  "+238": { code: "CV", name: "Cape Verde" },
  "+236": { code: "CF", name: "Central African Republic" },
  "+235": { code: "TD", name: "Chad" },
  "+56": { code: "CL", name: "Chile" },
  "+86": { code: "CN", name: "China" },
  "+57": { code: "CO", name: "Colombia" },
  "+269": { code: "KM", name: "Comoros" },
  "+242": { code: "CG", name: "Congo" },
  "+243": { code: "CD", name: "DR Congo" },
  "+506": { code: "CR", name: "Costa Rica" },
  "+225": { code: "CI", name: "Côte d'Ivoire" },
  "+385": { code: "HR", name: "Croatia" },
  "+53": { code: "CU", name: "Cuba" },
  "+357": { code: "CY", name: "Cyprus" },
  "+420": { code: "CZ", name: "Czech Republic" },
  "+45": { code: "DK", name: "Denmark" },
  "+253": { code: "DJ", name: "Djibouti" },
  "+593": { code: "EC", name: "Ecuador" },
  "+20": { code: "EG", name: "Egypt" },
  "+503": { code: "SV", name: "El Salvador" },
  "+240": { code: "GQ", name: "Equatorial Guinea" },
  "+291": { code: "ER", name: "Eritrea" },
  "+372": { code: "EE", name: "Estonia" },
  "+251": { code: "ET", name: "Ethiopia" },
  "+679": { code: "FJ", name: "Fiji" },
  "+358": { code: "FI", name: "Finland" },
  "+33": { code: "FR", name: "France" },
  "+241": { code: "GA", name: "Gabon" },
  "+220": { code: "GM", name: "Gambia" },
  "+995": { code: "GE", name: "Georgia" },
  "+49": { code: "DE", name: "Germany" },
  "+233": { code: "GH", name: "Ghana" },
  "+30": { code: "GR", name: "Greece" },
  "+502": { code: "GT", name: "Guatemala" },
  "+224": { code: "GN", name: "Guinea" },
  "+245": { code: "GW", name: "Guinea-Bissau" },
  "+592": { code: "GY", name: "Guyana" },
  "+509": { code: "HT", name: "Haiti" },
  "+504": { code: "HN", name: "Honduras" },
  "+852": { code: "HK", name: "Hong Kong" },
  "+36": { code: "HU", name: "Hungary" },
  "+354": { code: "IS", name: "Iceland" },
  "+91": { code: "IN", name: "India" },
  "+62": { code: "ID", name: "Indonesia" },
  "+98": { code: "IR", name: "Iran" },
  "+964": { code: "IQ", name: "Iraq" },
  "+353": { code: "IE", name: "Ireland" },
  "+972": { code: "IL", name: "Israel" },
  "+39": { code: "IT", name: "Italy" },
  "+81": { code: "JP", name: "Japan" },
  "+962": { code: "JO", name: "Jordan" },
  "+7": { code: "RU", name: "Russia" },
  "+254": { code: "KE", name: "Kenya" },
  "+686": { code: "KI", name: "Kiribati" },
  "+850": { code: "KP", name: "North Korea" },
  "+82": { code: "KR", name: "South Korea" },
  "+965": { code: "KW", name: "Kuwait" },
  "+996": { code: "KG", name: "Kyrgyzstan" },
  "+856": { code: "LA", name: "Laos" },
  "+371": { code: "LV", name: "Latvia" },
  "+961": { code: "LB", name: "Lebanon" },
  "+266": { code: "LS", name: "Lesotho" },
  "+231": { code: "LR", name: "Liberia" },
  "+218": { code: "LY", name: "Libya" },
  "+423": { code: "LI", name: "Liechtenstein" },
  "+370": { code: "LT", name: "Lithuania" },
  "+352": { code: "LU", name: "Luxembourg" },
  "+853": { code: "MO", name: "Macao" },
  "+389": { code: "MK", name: "North Macedonia" },
  "+261": { code: "MG", name: "Madagascar" },
  "+265": { code: "MW", name: "Malawi" },
  "+60": { code: "MY", name: "Malaysia" },
  "+960": { code: "MV", name: "Maldives" },
  "+223": { code: "ML", name: "Mali" },
  "+356": { code: "MT", name: "Malta" },
  "+692": { code: "MH", name: "Marshall Islands" },
  "+222": { code: "MR", name: "Mauritania" },
  "+230": { code: "MU", name: "Mauritius" },
  "+52": { code: "MX", name: "Mexico" },
  "+691": { code: "FM", name: "Micronesia" },
  "+373": { code: "MD", name: "Moldova" },
  "+377": { code: "MC", name: "Monaco" },
  "+976": { code: "MN", name: "Mongolia" },
  "+382": { code: "ME", name: "Montenegro" },
  "+212": { code: "MA", name: "Morocco" },
  "+258": { code: "MZ", name: "Mozambique" },
  "+95": { code: "MM", name: "Myanmar" },
  "+264": { code: "NA", name: "Namibia" },
  "+674": { code: "NR", name: "Nauru" },
  "+977": { code: "NP", name: "Nepal" },
  "+31": { code: "NL", name: "Netherlands" },
  "+64": { code: "NZ", name: "New Zealand" },
  "+505": { code: "NI", name: "Nicaragua" },
  "+227": { code: "NE", name: "Niger" },
  "+234": { code: "NG", name: "Nigeria" },
  "+47": { code: "NO", name: "Norway" },
  "+968": { code: "OM", name: "Oman" },
  "+92": { code: "PK", name: "Pakistan" },
  "+680": { code: "PW", name: "Palau" },
  "+970": { code: "PS", name: "Palestine" },
  "+507": { code: "PA", name: "Panama" },
  "+675": { code: "PG", name: "Papua New Guinea" },
  "+595": { code: "PY", name: "Paraguay" },
  "+51": { code: "PE", name: "Peru" },
  "+63": { code: "PH", name: "Philippines" },
  "+48": { code: "PL", name: "Poland" },
  "+351": { code: "PT", name: "Portugal" },
  "+974": { code: "QA", name: "Qatar" },
  "+40": { code: "RO", name: "Romania" },
  "+250": { code: "RW", name: "Rwanda" },
  "+685": { code: "WS", name: "Samoa" },
  "+378": { code: "SM", name: "San Marino" },
  "+239": { code: "ST", name: "São Tomé and Príncipe" },
  "+966": { code: "SA", name: "Saudi Arabia" },
  "+221": { code: "SN", name: "Senegal" },
  "+381": { code: "RS", name: "Serbia" },
  "+248": { code: "SC", name: "Seychelles" },
  "+232": { code: "SL", name: "Sierra Leone" },
  "+65": { code: "SG", name: "Singapore" },
  "+421": { code: "SK", name: "Slovakia" },
  "+386": { code: "SI", name: "Slovenia" },
  "+677": { code: "SB", name: "Solomon Islands" },
  "+252": { code: "SO", name: "Somalia" },
  "+27": { code: "ZA", name: "South Africa" },
  "+211": { code: "SS", name: "South Sudan" },
  "+34": { code: "ES", name: "Spain" },
  "+94": { code: "LK", name: "Sri Lanka" },
  "+249": { code: "SD", name: "Sudan" },
  "+597": { code: "SR", name: "Suriname" },
  "+268": { code: "SZ", name: "Eswatini" },
  "+46": { code: "SE", name: "Sweden" },
  "+41": { code: "CH", name: "Switzerland" },
  "+963": { code: "SY", name: "Syria" },
  "+886": { code: "TW", name: "Taiwan" },
  "+992": { code: "TJ", name: "Tajikistan" },
  "+255": { code: "TZ", name: "Tanzania" },
  "+66": { code: "TH", name: "Thailand" },
  "+670": { code: "TL", name: "Timor-Leste" },
  "+228": { code: "TG", name: "Togo" },
  "+676": { code: "TO", name: "Tonga" },
  "+216": { code: "TN", name: "Tunisia" },
  "+90": { code: "TR", name: "Turkey" },
  "+993": { code: "TM", name: "Turkmenistan" },
  "+688": { code: "TV", name: "Tuvalu" },
  "+256": { code: "UG", name: "Uganda" },
  "+380": { code: "UA", name: "Ukraine" },
  "+971": { code: "AE", name: "United Arab Emirates" },
  "+44": { code: "GB", name: "United Kingdom" },
  "+598": { code: "UY", name: "Uruguay" },
  "+998": { code: "UZ", name: "Uzbekistan" },
  "+678": { code: "VU", name: "Vanuatu" },
  "+379": { code: "VA", name: "Vatican City" },
  "+58": { code: "VE", name: "Venezuela" },
  "+84": { code: "VN", name: "Vietnam" },
  "+967": { code: "YE", name: "Yemen" },
  "+260": { code: "ZM", name: "Zambia" },
  "+263": { code: "ZW", name: "Zimbabwe" },
};

// Pre-sorted prefixes by length (longest first) for efficient matching
const sortedPrefixes = Object.keys(phoneToCountryMap).sort((a, b) => b.length - a.length);

export const usePhoneCountry = () => {
  /**
   * Get country information from a phone number
   * @param phone - Phone number with country prefix (e.g., '+6281234567890')
   * @returns CountryInfo object or null if no match found
   */
  const getCountryFromPhone = (phone: string | null | undefined): CountryInfo | null => {
    if (!phone) return null;

    for (const prefix of sortedPrefixes) {
      if (phone.startsWith(prefix)) {
        return phoneToCountryMap[prefix];
      }
    }

    return null;
  };

  /**
   * Get country code (ISO2) from a phone number
   * @param phone - Phone number with country prefix
   * @returns ISO2 country code or null
   */
  const getCountryCode = (phone: string | null | undefined): string | null => {
    return getCountryFromPhone(phone)?.code || null;
  };

  /**
   * Get country name from a phone number
   * @param phone - Phone number with country prefix
   * @returns Country name or null
   */
  const getCountryName = (phone: string | null | undefined): string | null => {
    return getCountryFromPhone(phone)?.name || null;
  };

  return {
    getCountryFromPhone,
    getCountryCode,
    getCountryName,
  };
};
