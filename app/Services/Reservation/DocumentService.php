<?php

namespace App\Services\Reservation;

use App\Models\AppSetting;
use App\Models\Reservation;
use App\Services\Xendit\XenditService;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\Response;

class DocumentService
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

    public function renderInvoicePdf(Reservation $reservation): Response
    {
        // Browsershot subprocess (Node.js + Chrome) can take 5-20s on cold start.
        // Default PHP-FPM max_execution_time of 30s is borderline — bump it so the
        // request finishes instead of being killed mid-render.
        set_time_limit(120);

        $reservation->loadMissing(['hotel', 'event', 'items.roomType', 'transfers', 'paymentGateway', 'adjustments']);

        $invoiceNumber = $this->buildInvoiceNumber($reservation);

        return Pdf::view('pdf.reservation.invoice', [
            'r' => $reservation,
            'branding' => $this->getBranding($reservation),
            'invoiceNumber' => $invoiceNumber,
            'enabledPaymentLogos' => $this->resolveEnabledPaymentLogos($reservation),
        ])
            ->format('a4')
            ->name($invoiceNumber.'.pdf')
            ->withBrowsershot(fn ($bs) => $bs->timeout(120)->waitUntilNetworkIdle(false))
            ->toResponse(request());
    }

    public function renderReceiptPdf(Reservation $reservation): Response
    {
        set_time_limit(120);

        $reservation->loadMissing(['hotel', 'event', 'items.roomType', 'transfers', 'adjustments']);

        $receiptNumber = $this->buildReceiptNumber($reservation);

        return Pdf::view('pdf.reservation.receipt', [
            'r' => $reservation,
            'branding' => $this->getBranding($reservation),
            'receiptNumber' => $receiptNumber,
            'channelBadge' => $this->channelBadge($reservation->payment_channel),
            'channelLogo' => $this->channelLogoFile($reservation->payment_channel),
        ])
            ->format('a4')
            ->name($receiptNumber.'.pdf')
            ->withBrowsershot(fn ($bs) => $bs->timeout(120)->waitUntilNetworkIdle(false))
            ->toResponse(request());
    }

    public function getBranding(?Reservation $reservation = null): array
    {
        $global = AppSetting::get('branding') ?? [];
        $eventOverride = $reservation?->event?->branding ?? [];

        $merged = array_merge($global, $eventOverride ?: []);

        // Browsershot launches a headless Chrome that may not be able to reach the
        // app's `http://localhost:8000/storage/...` URL (e.g. on Forge the Node
        // process resolves the host differently than the user's browser does).
        // Resolve the logo to a data URI from the underlying media file so the
        // PDF rendering pipeline never has to make an outbound HTTP call.
        $merged['logo_url'] = $this->resolveBrandingLogo($reservation, $merged['logo_url'] ?? null);

        return $merged;
    }

    /**
     * Resolve the branding logo to a base64-encoded data URI when the file is
     * locally accessible. Falls back to the original URL when the media is not
     * resolvable (e.g. external CDN). Returns null if no logo is configured.
     */
    private function resolveBrandingLogo(?Reservation $reservation, ?string $fallbackUrl): ?string
    {
        $candidates = [];

        // Event override media takes precedence — that's what BrandingForm uploads to.
        if ($reservation?->event && $reservation->event->getFirstMedia('branding_logo')) {
            $candidates[] = $reservation->event->getFirstMedia('branding_logo');
        }

        // Global branding fallback — only used when the event has no custom logo.
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
     * scoped to the Xendit account tied to this reservation. Returns the static
     * fallback list when the reservation has no gateway attached (rare — happens
     * only for legacy/manually-created reservations without a payment_gateway_id).
     *
     * @return array<int, array{file: string, alt: string}>
     */
    public function resolveEnabledPaymentLogos(Reservation $reservation): array
    {
        if (! $reservation->paymentGateway) {
            return (new XenditService)->getEnabledPaymentChannels();
        }

        return XenditService::forGateway($reservation->paymentGateway)->getEnabledPaymentChannels();
    }

    /**
     * Map a payment channel code to its logo filename in /public/images/payment-methods/.
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

    public function buildInvoiceNumber(Reservation $reservation): string
    {
        $parts = explode('-', $reservation->reservation_number);

        return 'INV/HTL/'.($parts[1] ?? now()->format('Ymd')).'/'.($parts[2] ?? '0001');
    }

    public function buildReceiptNumber(Reservation $reservation): string
    {
        $parts = explode('-', $reservation->reservation_number);

        return 'RCP/HTL/'.($parts[1] ?? now()->format('Ymd')).'/'.($parts[2] ?? '0001');
    }
}
