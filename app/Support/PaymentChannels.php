<?php

namespace App\Support;

use App\Services\Xendit\XenditService;

/**
 * Canonical payment-channel catalog + per-provider code normalisation.
 *
 * Canonical codes are the uppercase keys we already use across the system
 * (webhook channel resolution, invoice PDF logos, the event-website
 * `payment-method-logos.ts`). They mirror Xendit's `/payment_channels`
 * `channel_code` values. Card brands collapse to a single CREDIT_CARD entry
 * because both Xendit and the customer treat "cards" as one channel.
 *
 * The mappers translate the stored canonical list into the exact codes each
 * provider/checkout-mode expects when restricting available channels:
 *  - Xendit Sessions API  -> `allowed_payment_channels` (cards => "CARDS")
 *  - Xendit Invoice (v2)  -> `payment_methods`          (cards => "CREDIT_CARD")
 */
final class PaymentChannels
{
    /**
     * Raw codes that all mean "the card channel". Normalised to CREDIT_CARD
     * before mapping so a stray VISA/MASTERCARD never leaks through.
     *
     * @var array<int, string>
     */
    private const CARD_ALIASES = ['CREDIT_CARD', 'VISA', 'MASTERCARD', 'AMEX', 'JCB', 'CARDS'];

    /**
     * Selectable canonical channels shown to admins - one entry per real
     * channel. `aliases` are the raw `/payment_channels` codes that indicate
     * this channel is activated on the account (used to intersect with the
     * live enabled list). Label + logo are sourced from
     * {@see XenditService::CHANNEL_LOGO_MAP} to avoid duplicating asset data.
     *
     * @var array<int, array{code: string, group: string, aliases: array<int, string>}>
     */
    private const CATALOG = [
        ['code' => 'CREDIT_CARD', 'group' => 'card', 'aliases' => ['CREDIT_CARD', 'VISA', 'MASTERCARD', 'AMEX', 'JCB', 'CARDS']],
        ['code' => 'BCA', 'group' => 'bank', 'aliases' => ['BCA']],
        ['code' => 'BNI', 'group' => 'bank', 'aliases' => ['BNI']],
        ['code' => 'BRI', 'group' => 'bank', 'aliases' => ['BRI']],
        ['code' => 'MANDIRI', 'group' => 'bank', 'aliases' => ['MANDIRI']],
        ['code' => 'PERMATA', 'group' => 'bank', 'aliases' => ['PERMATA']],
        ['code' => 'CIMB', 'group' => 'bank', 'aliases' => ['CIMB', 'CIMB_NIAGA']],
        ['code' => 'BJB', 'group' => 'bank', 'aliases' => ['BJB']],
        ['code' => 'BSI', 'group' => 'bank', 'aliases' => ['BSI']],
        ['code' => 'NEOBANK', 'group' => 'bank', 'aliases' => ['NEOBANK', 'BNC']],
        ['code' => 'BSS', 'group' => 'bank', 'aliases' => ['BSS', 'SAHABAT_SAMPOERNA']],
        ['code' => 'MUAMALAT', 'group' => 'bank', 'aliases' => ['MUAMALAT']],
        ['code' => 'DD_BRI', 'group' => 'bank', 'aliases' => ['DD_BRI', 'BRI_DIRECT_DEBIT']],
        ['code' => 'OVO', 'group' => 'ewallet', 'aliases' => ['OVO']],
        ['code' => 'DANA', 'group' => 'ewallet', 'aliases' => ['DANA']],
        ['code' => 'SHOPEEPAY', 'group' => 'ewallet', 'aliases' => ['SHOPEEPAY']],
        ['code' => 'LINKAJA', 'group' => 'ewallet', 'aliases' => ['LINKAJA']],
        ['code' => 'GOPAY', 'group' => 'ewallet', 'aliases' => ['GOPAY']],
        ['code' => 'ASTRAPAY', 'group' => 'ewallet', 'aliases' => ['ASTRAPAY']],
        ['code' => 'JENIUSPAY', 'group' => 'ewallet', 'aliases' => ['JENIUSPAY']],
        ['code' => 'NEXCASH', 'group' => 'ewallet', 'aliases' => ['NEXCASH']],
        ['code' => 'QRIS', 'group' => 'qr', 'aliases' => ['QRIS']],
    ];

    /**
     * Canonical code -> Xendit Payment Sessions (v3) channel code for
     * `allowed_payment_channels`. CRITICAL: the v3 Sessions API uses DIFFERENT
     * codes from the legacy Invoice API / `/payment_channels` listing - virtual
     * accounts carry a `_VIRTUAL_ACCOUNT` suffix, cards collapse to `CARDS`.
     * Sending a bare `BCA` here makes Xendit reject the whole session (which is
     * why a BCA-only restriction produced no payment link). This is an explicit
     * whitelist: canonical codes without a v3 equivalent (e.g. NEXCASH) are
     * dropped rather than sent as-is.
     *
     * @var array<string, string>
     */
    private const XENDIT_SESSIONS_MAP = [
        'CREDIT_CARD' => 'CARDS',
        'BCA' => 'BCA_VIRTUAL_ACCOUNT',
        'BNI' => 'BNI_VIRTUAL_ACCOUNT',
        'BRI' => 'BRI_VIRTUAL_ACCOUNT',
        'MANDIRI' => 'MANDIRI_VIRTUAL_ACCOUNT',
        'PERMATA' => 'PERMATA_VIRTUAL_ACCOUNT',
        'CIMB' => 'CIMB_VIRTUAL_ACCOUNT',
        'BJB' => 'BJB_VIRTUAL_ACCOUNT',
        'BSI' => 'BSI_VIRTUAL_ACCOUNT',
        'NEOBANK' => 'BNC_VIRTUAL_ACCOUNT',
        'BSS' => 'BSS_VIRTUAL_ACCOUNT',
        'MUAMALAT' => 'MUAMALAT_VIRTUAL_ACCOUNT',
        'DD_BRI' => 'BRI_DIRECT_DEBIT',
        'OVO' => 'OVO',
        'DANA' => 'DANA',
        'SHOPEEPAY' => 'SHOPEEPAY',
        'LINKAJA' => 'LINKAJA',
        'GOPAY' => 'GOPAY',
        'ASTRAPAY' => 'ASTRAPAY',
        'JENIUSPAY' => 'JENIUSPAY',
        'QRIS' => 'QRIS',
    ];

    /**
     * Canonical codes whose legacy Invoice `payment_methods` value differs from
     * the canonical code. Bank Sahabat Sampoerna's legacy code is
     * `SAHABAT_SAMPOERNA` (the v3 code is `BSS_VIRTUAL_ACCOUNT`). All other
     * common banks/e-wallets use the canonical code verbatim in legacy.
     *
     * @var array<string, string>
     */
    private const XENDIT_INVOICE_OVERRIDES = [
        'BSS' => 'SAHABAT_SAMPOERNA',
    ];

    /**
     * Canonical code -> Midtrans Snap `enabled_payments` code. Midtrans supports
     * a SUBSET of our catalog; canonical codes absent here (OVO, DANA, LinkAja,
     * BJB, BSI, BSS, Muamalat, AstraPay, NexCash, JeniusPay, DD_BRI) have no Snap
     * equivalent and are dropped.
     *
     * @var array<string, string>
     */
    private const MIDTRANS_MAP = [
        'CREDIT_CARD' => 'credit_card',
        'BCA' => 'bca_va',
        'BNI' => 'bni_va',
        'BRI' => 'bri_va',
        'MANDIRI' => 'echannel',
        'PERMATA' => 'permata_va',
        'CIMB' => 'cimb_va',
        'GOPAY' => 'gopay',
        'SHOPEEPAY' => 'shopeepay',
        'QRIS' => 'qris',
    ];

    /**
     * Full selectable catalog enriched with label + logo URL.
     *
     * @return array<int, array{code: string, label: string, group: string, logo_url: string}>
     */
    public static function catalog(): array
    {
        return array_map(static function (array $entry): array {
            $logo = XenditService::CHANNEL_LOGO_MAP[$entry['code']] ?? ['file' => '', 'alt' => $entry['code']];

            return [
                'code' => $entry['code'],
                'label' => $logo['alt'],
                'group' => $entry['group'],
                'logo_url' => $logo['file'] !== '' ? '/img/payment-methods/'.$logo['file'] : '',
            ];
        }, self::CATALOG);
    }

    /**
     * Canonical catalog codes only (what we store + validate).
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_column(self::CATALOG, 'code');
    }

    /**
     * True when $code is a storable canonical catalog code.
     */
    public static function isValid(string $code): bool
    {
        return in_array(strtoupper($code), self::codes(), true);
    }

    /**
     * Intersect the canonical catalog with the raw channel codes a gateway
     * reports as enabled. Returns catalog entries whose aliases overlap the
     * enabled set (deduped, catalog order preserved).
     *
     * @param  array<int, string>  $enabledCodes  raw uppercase codes (e.g. from `/payment_channels`)
     * @return array<int, array{code: string, label: string, group: string, logo_url: string}>
     */
    public static function catalogForEnabled(array $enabledCodes): array
    {
        $enabled = array_flip(array_map('strtoupper', $enabledCodes));

        $matched = [];
        foreach (self::CATALOG as $entry) {
            foreach ($entry['aliases'] as $alias) {
                if (isset($enabled[$alias])) {
                    $matched[$entry['code']] = true;
                    break;
                }
            }
        }

        return array_values(array_filter(
            self::catalog(),
            static fn (array $c): bool => isset($matched[$c['code']]),
        ));
    }

    /**
     * Catalog entries restricted to the given canonical codes (catalog order
     * preserved). Used to scope the admin picker to a provider's supported set.
     *
     * @param  array<int, string>  $codes  canonical codes
     * @return array<int, array{code: string, label: string, group: string, logo_url: string}>
     */
    public static function catalogForCodes(array $codes): array
    {
        $wanted = array_flip(array_map('strtoupper', $codes));

        return array_values(array_filter(
            self::catalog(),
            static fn (array $c): bool => isset($wanted[$c['code']]),
        ));
    }

    /**
     * Map canonical codes to Xendit Sessions (v3) `allowed_payment_channels`.
     * Uses an explicit whitelist of v3 codes; canonical codes without a v3
     * equivalent are dropped so an unknown code can never break the session.
     *
     * @param  array<int, string>  $canonical
     * @return array<int, string>
     */
    public static function toXenditSessionsCodes(array $canonical): array
    {
        $out = [];
        foreach ($canonical as $raw) {
            $code = strtoupper((string) $raw);
            if (in_array($code, self::CARD_ALIASES, true)) {
                $code = 'CREDIT_CARD';
            }
            $mapped = self::XENDIT_SESSIONS_MAP[$code] ?? null;
            if ($mapped !== null) {
                $out[$mapped] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * Map canonical codes to Xendit legacy Invoice `payment_methods` (bare bank
     * codes, cards as CREDIT_CARD). Only BSS differs (-> SAHABAT_SAMPOERNA).
     *
     * @param  array<int, string>  $canonical
     * @return array<int, string>
     */
    public static function toXenditInvoiceCodes(array $canonical): array
    {
        return self::mapCodes($canonical, self::XENDIT_INVOICE_OVERRIDES);
    }

    /**
     * Map canonical codes to Midtrans Snap `enabled_payments`. Codes Midtrans
     * does not support are dropped (so a list of only-unsupported codes yields
     * [] -> caller treats it as "no restriction" rather than blocking everyone).
     *
     * @param  array<int, string>  $canonical
     * @return array<int, string>
     */
    public static function toMidtransEnabledPayments(array $canonical): array
    {
        $out = [];
        foreach ($canonical as $raw) {
            $code = strtoupper((string) $raw);
            if (in_array($code, self::CARD_ALIASES, true)) {
                $code = 'CREDIT_CARD';
            }
            $mapped = self::MIDTRANS_MAP[$code] ?? null;
            if ($mapped !== null) {
                $out[$mapped] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * Canonical codes Midtrans can restrict to - used to build the admin channel
     * picker for projects whose active gateway is Midtrans.
     *
     * @return array<int, string>
     */
    public static function midtransSupportedCodes(): array
    {
        return array_keys(self::MIDTRANS_MAP);
    }

    /**
     * Normalise (card aliases -> CREDIT_CARD), drop unknowns, apply provider
     * overrides, and dedupe while preserving first-seen order.
     *
     * @param  array<int, string>  $canonical
     * @param  array<string, string>  $overrides
     * @return array<int, string>
     */
    private static function mapCodes(array $canonical, array $overrides): array
    {
        $out = [];
        foreach ($canonical as $raw) {
            $code = strtoupper((string) $raw);
            if (in_array($code, self::CARD_ALIASES, true)) {
                $code = 'CREDIT_CARD';
            }
            if (! self::isValid($code)) {
                continue;
            }
            $mapped = $overrides[$code] ?? $code;
            $out[$mapped] = true;
        }

        return array_keys($out);
    }
}
