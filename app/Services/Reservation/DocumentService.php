<?php

namespace App\Services\Reservation;

use App\Models\AppSetting;
use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class DocumentService
{
    public function renderInvoicePdf(Reservation $reservation): Response
    {
        $reservation->loadMissing(['hotel', 'event', 'items.roomType', 'transfers']);

        $branding = $this->getBranding($reservation);

        $pdf = Pdf::loadView('pdf.reservation.invoice', [
            'r' => $reservation,
            'branding' => $branding,
            'invoiceNumber' => $this->buildInvoiceNumber($reservation),
        ]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->buildInvoiceNumber($reservation).'.pdf"',
        ]);
    }

    public function renderReceiptPdf(Reservation $reservation): Response
    {
        $reservation->loadMissing(['hotel', 'event', 'items.roomType', 'transfers']);

        $branding = $this->getBranding($reservation);

        $pdf = Pdf::loadView('pdf.reservation.receipt', [
            'r' => $reservation,
            'branding' => $branding,
            'receiptNumber' => $this->buildReceiptNumber($reservation),
        ]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->buildReceiptNumber($reservation).'.pdf"',
        ]);
    }

    public function getBranding(?Reservation $reservation = null): array
    {
        $global = AppSetting::get('branding') ?? [];
        $eventOverride = $reservation?->event?->branding ?? [];

        return array_merge($global, $eventOverride ?: []);
    }

    public function buildInvoiceNumber(Reservation $reservation): string
    {
        // Convert HTL-20260417-XXXX to INV/HTL/20260417/XXXX
        $parts = explode('-', $reservation->reservation_number);

        return 'INV/HTL/'.($parts[1] ?? now()->format('Ymd')).'/'.($parts[2] ?? '0001');
    }

    public function buildReceiptNumber(Reservation $reservation): string
    {
        $parts = explode('-', $reservation->reservation_number);

        return 'RCP/HTL/'.($parts[1] ?? now()->format('Ymd')).'/'.($parts[2] ?? '0001');
    }
}
