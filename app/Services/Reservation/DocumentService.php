<?php

namespace App\Services\Reservation;

use App\Models\Reservation;
use App\Services\Pdf\ResolvesPdfBranding;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\Response;

class DocumentService
{
    use ResolvesPdfBranding;

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
            'paymentProvider' => $this->paymentProviderBadge($reservation),
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
            'paymentProvider' => $this->paymentProviderBadge($reservation),
        ])
            ->format('a4')
            ->name($receiptNumber.'.pdf')
            ->withBrowsershot(fn ($bs) => $bs->timeout(120)->waitUntilNetworkIdle(false))
            ->toResponse(request());
    }

    /**
     * @return array<string, mixed>
     */
    public function getBranding(?Reservation $reservation = null): array
    {
        return $this->brandingFor($reservation?->event?->project);
    }

    /**
     * @return array<int, array{file: string, alt: string}>
     */
    public function resolveEnabledPaymentLogos(Reservation $reservation): array
    {
        return $this->enabledPaymentLogosFor($reservation->paymentGateway);
    }

    /**
     * @return array{file: string, name: string}
     */
    public function paymentProviderBadge(Reservation $reservation): array
    {
        return $this->paymentProviderBadgeFor($reservation->paymentGateway);
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
