<?php

namespace App\Services\Ticket;

use App\Models\TicketOrder;
use App\Services\Pdf\ResolvesPdfBranding;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\Response;

class TicketDocumentService
{
    use ResolvesPdfBranding;

    /**
     * Render the order invoice (the bill) as an on-the-fly PDF. Nothing is stored.
     */
    public function renderInvoicePdf(TicketOrder $order): Response
    {
        // Browsershot (Node.js + Chrome) can take several seconds on a cold start;
        // give the request room so PHP-FPM doesn't kill it mid-render.
        set_time_limit(120);

        $order->loadMissing(['items.ticket', 'items.selectedEventDay', 'items.ticketSession', 'event.project', 'paymentGateway']);
        $number = $this->invoiceNumber($order);

        return Pdf::view('pdf.ticket.invoice', [
            'order' => $order,
            'number' => $number,
            'branding' => $this->brandingFor($order->event?->project),
            'enabledPaymentLogos' => $this->enabledPaymentLogosFor($order->paymentGateway),
            'paymentProvider' => $this->paymentProviderBadgeFor($order->paymentGateway),
        ])
            ->format('a4')
            ->name($number.'.pdf')
            ->withBrowsershot(fn ($bs) => $bs->timeout(120)->waitUntilNetworkIdle(false))
            ->toResponse(request());
    }

    /**
     * Render the payment receipt (proof of payment) as an on-the-fly PDF.
     */
    public function renderReceiptPdf(TicketOrder $order): Response
    {
        set_time_limit(120);

        $order->loadMissing(['items.ticket', 'items.selectedEventDay', 'items.ticketSession', 'event.project', 'paymentGateway']);
        $number = $this->receiptNumber($order);

        return Pdf::view('pdf.ticket.receipt', [
            'order' => $order,
            'number' => $number,
            'branding' => $this->brandingFor($order->event?->project),
            'channelBadge' => $this->channelBadge($order->payment_channel),
            'channelLogo' => $this->channelLogoFile($order->payment_channel),
            'paymentProvider' => $this->paymentProviderBadgeFor($order->paymentGateway),
        ])
            ->format('a4')
            ->name($number.'.pdf')
            ->withBrowsershot(fn ($bs) => $bs->timeout(120)->waitUntilNetworkIdle(false))
            ->toResponse(request());
    }

    protected function invoiceNumber(TicketOrder $order): string
    {
        return 'INV/TIX/'.str_replace('TIX-', '', $order->order_number);
    }

    protected function receiptNumber(TicketOrder $order): string
    {
        return 'RCP/TIX/'.str_replace('TIX-', '', $order->order_number);
    }
}
