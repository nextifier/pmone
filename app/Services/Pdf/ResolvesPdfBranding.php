<?php

namespace App\Services\Pdf;

use App\Models\AppSetting;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Services\Midtrans\MidtransService;
use App\Services\Xendit\XenditService;

/**
 * Shared branding + payment-channel resolution for invoice/receipt PDFs.
 *
 * Both hotel reservations and ticket orders render branded documents from the
 * same project-level branding (logo, company details) and the same payment
 * gateway logos, so this logic lives in one place rather than being duplicated
 * per feature. Consumers pass the resolved Project / ProjectPaymentGateway.
 */
trait ResolvesPdfBranding
{
    /**
     * Brand color map for known Indonesian payment channels.
     *
     * Used to render a recognizable colored badge per channel on the receipt.
     * Channel keys match Xendit's `payment_channel` field (uppercase).
     *
     * @var array<string, array{color: string, label?: string, text?: string}>
     */
    protected const CHANNEL_BRAND = [
        // Banks
        'BCA' => ['color' => '#0060AF'],
        'BNI' => ['color' => '#F58220'],
        'BRI' => ['color' => '#00529C'],
        'MANDIRI' => ['color' => '#003D7B'],
        'PERMATA' => ['color' => '#008752'],
        'BSI' => ['color' => '#00A972'],
        'CIMB' => ['color' => '#C8102E', 'label' => 'CIMB'],
        'CIMB_NIAGA' => ['color' => '#C8102E', 'label' => 'CIMB'],
        'DANAMON' => ['color' => '#FF6B00'],
        'BTN' => ['color' => '#0F4C8F'],
        'MAYBANK' => ['color' => '#F0AB00', 'text' => '#231F20'],
        'BJB' => ['color' => '#112D4E'],
        'BNC' => ['color' => '#00355F'],
        // E-wallets
        'OVO' => ['color' => '#4C2A86'],
        'DANA' => ['color' => '#118EEA'],
        'GOPAY' => ['color' => '#00AED6'],
        'SHOPEEPAY' => ['color' => '#EE4D2D'],
        'LINKAJA' => ['color' => '#ED1C24'],
        'ASTRAPAY' => ['color' => '#0E72BE'],
        'JENIUSPAY' => ['color' => '#00A19A'],
        // QR
        'QRIS' => ['color' => '#ED1C24'],
        // Cards
        'VISA' => ['color' => '#1A1F71'],
        'MASTERCARD' => ['color' => '#EB001B'],
        'JCB' => ['color' => '#0E4DA4'],
        'AMEX' => ['color' => '#2671B8', 'label' => 'AMEX'],
        'CREDIT_CARD' => ['color' => '#3F3F46', 'label' => 'CARD'],
        // Retail outlets
        'ALFAMART' => ['color' => '#E60012'],
        'INDOMARET' => ['color' => '#1F4A93'],
        // PayLater
        'KREDIVO' => ['color' => '#0DB14B'],
        'AKULAKU' => ['color' => '#E63946'],
        'INDODANA' => ['color' => '#1A75BB'],
    ];

    /**
     * Merge global + project branding and resolve the logo to a data URI.
     *
     * @return array<string, mixed>
     */
    public function brandingFor(?Project $project): array
    {
        $global = AppSetting::get('branding') ?? [];
        $projectOverride = $project?->branding ?? [];

        $merged = array_merge($global, $projectOverride ?: []);

        // Browsershot launches a headless Chrome that may not be able to reach the
        // app's `http://localhost:8000/storage/...` URL (e.g. on Forge the Node
        // process resolves the host differently than the user's browser does).
        // Resolve the logo to a data URI from the underlying media file so the
        // PDF rendering pipeline never has to make an outbound HTTP call.
        $merged['logo_url'] = $this->resolveBrandingLogoFor($project, $merged['logo_url'] ?? null);

        return $merged;
    }

    /**
     * Resolve the branding logo to a base64-encoded data URI when the file is
     * locally accessible. Falls back to the original URL when the media is not
     * resolvable (e.g. external CDN). Returns null if no logo is configured.
     */
    public function resolveBrandingLogoFor(?Project $project, ?string $fallbackUrl): ?string
    {
        $candidates = [];

        // Project override media takes precedence — that's what BrandingForm uploads to.
        if ($project && $project->getFirstMedia('branding_logo')) {
            $candidates[] = $project->getFirstMedia('branding_logo');
        }

        // Global branding fallback — only used when the project has no custom logo.
        if (! $candidates) {
            $appSetting = AppSetting::query()->where('key', 'branding')->first();
            if ($appSetting && $appSetting->getFirstMedia('branding_logo')) {
                $candidates[] = $appSetting->getFirstMedia('branding_logo');
            }
        }

        foreach ($candidates as $media) {
            $path = $media->getPath();
            if (is_file($path) && is_readable($path)) {
                $mime = $media->mime_type ?: mime_content_type($path) ?: 'image/png';
                $data = base64_encode(file_get_contents($path));

                return "data:{$mime};base64,{$data}";
            }
        }

        return $fallbackUrl ?: null;
    }

    /**
     * Resolve display data for a payment channel logo.
     *
     * Returns brand color + label that the default SVG template uses to render
     * a recognizable colored badge. Unknown channels fall back to neutral gray.
     *
     * @return array{channel: string, color: string, textColor: string, label: string}|null
     */
    public function channelBadge(?string $channel): ?array
    {
        if (! $channel) {
            return null;
        }

        $key = strtoupper($channel);
        $brand = self::CHANNEL_BRAND[$key] ?? ['color' => '#52525B'];

        return [
            'channel' => $key,
            'color' => $brand['color'],
            'textColor' => $brand['text'] ?? '#FFFFFF',
            'label' => $brand['label'] ?? $key,
        ];
    }

    /**
     * Resolve the list of payment-method logos to render in the invoice footer,
     * scoped to the gateway tied to this document. Midtrans and Xendit each
     * surface their own enabled set, so the footer never advertises methods the
     * customer's gateway can't accept. Returns the static Xendit fallback when no
     * gateway is attached (rare — legacy/manually-created records).
     *
     * @return array<int, array{file: string, alt: string}>
     */
    public function enabledPaymentLogosFor(?ProjectPaymentGateway $gateway): array
    {
        if ($gateway?->provider === 'midtrans') {
            return MidtransService::forGateway($gateway)->getEnabledPaymentChannels();
        }

        if ($gateway) {
            return XenditService::forGateway($gateway)->getEnabledPaymentChannels();
        }

        return (new XenditService)->getEnabledPaymentChannels();
    }

    /**
     * The payment-gateway brandmark for the "Secure checkout powered by …" line
     * on the invoice/receipt. Provider-aware so a Midtrans document never
     * advertises Xendit. Defaults to Xendit for records with no gateway
     * (legacy/manual), matching the prior hard-coded behavior.
     *
     * @return array{file: string, name: string}
     */
    public function paymentProviderBadgeFor(?ProjectPaymentGateway $gateway): array
    {
        return match ($gateway?->provider) {
            'midtrans' => ['file' => 'midtrans.svg', 'name' => 'Midtrans'],
            default => ['file' => 'xendit.svg', 'name' => 'Xendit'],
        };
    }

    /**
     * Map a payment channel code to its logo filename in /public/img/payment-methods/.
     *
     * Returns null if no logo asset is available for the given channel.
     */
    public function channelLogoFile(?string $channel): ?string
    {
        if (! $channel) {
            return null;
        }

        $map = [
            'BCA' => 'bca.svg',
            'BNI' => 'bni.svg',
            'BRI' => 'bri.svg',
            'MANDIRI' => 'mandiri.svg',
            'PERMATA' => 'permata-bank.svg',
            'BSI' => 'bsi.svg',
            'BSS' => 'bss.svg',
            'CIMB' => 'cimb-niaga.svg',
            'CIMB_NIAGA' => 'cimb-niaga.svg',
            'BJB' => 'bjb.svg',
            'BNC' => 'neobank.svg',
            'NEOBANK' => 'neobank.svg',
            'MUAMALAT' => 'bank-muamalat.svg',
            'GOPAY' => 'gopay.svg',
            'OVO' => 'ovo.svg',
            'DANA' => 'dana.svg',
            'SHOPEEPAY' => 'shopeepay.svg',
            'LINKAJA' => 'link-aja.svg',
            'JENIUSPAY' => 'jeniuspay.svg',
            'NEXCASH' => 'nexcash.svg',
            'ASTRAPAY' => 'astrapay.svg',
            'QRIS' => 'qris.svg',
            'VISA' => 'visa.svg',
            'MASTERCARD' => 'mastercard.svg',
            'AMEX' => 'amex.svg',
            'JCB' => 'jcb.svg',
            'DD_BRI' => 'dd-bri.svg',
            'BRI_DIRECT_DEBIT' => 'dd-bri.svg',
        ];

        return $map[strtoupper($channel)] ?? null;
    }
}
