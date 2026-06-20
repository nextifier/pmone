<?php

use App\Http\Controllers\Api\Public\PublicTicketOrderController;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Services\Ticket\TicketDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create([
        'branding' => [
            'company_name' => 'Test Brand Inc.',
            'footer_note' => 'Thank you for your purchase.',
        ],
    ]);
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
});

function paidOrderWithItem(Event $event): TicketOrder
{
    $order = TicketOrder::factory()->confirmed()->create([
        'event_id' => $event->id,
        'buyer_name' => 'Jane Buyer',
        'buyer_email' => 'jane@example.com',
        'payment_channel' => 'BCA',
        'paid_at' => now(),
        'subtotal' => 60000,
        'total' => 60000,
    ]);
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);
    $order->items()->create([
        'ticket_id' => $ticket->id,
        'quantity' => 1,
        'unit_price' => 60000,
        'subtotal' => 60000,
        'phase_label' => 'Normal',
    ]);

    return $order->loadMissing(['items.ticket', 'items.selectedEventDay', 'items.ticketSession', 'event.project', 'paymentGateway']);
}

/**
 * Build the view payload the same way TicketDocumentService does, so the view
 * tests exercise the shared branding/payment resolution (mirrors PdfBrandingTest).
 *
 * @return array<string, mixed>
 */
function ticketDocViewData(TicketOrder $order, string $number, string $kind): array
{
    $service = app(TicketDocumentService::class);

    $base = [
        'order' => $order,
        'number' => $number,
        'branding' => $service->brandingFor($order->event?->project),
        'paymentProvider' => $service->paymentProviderBadgeFor($order->paymentGateway),
    ];

    if ($kind === 'invoice') {
        return $base + ['enabledPaymentLogos' => []];
    }

    return $base + [
        'channelBadge' => $service->channelBadge($order->payment_channel),
        'channelLogo' => $service->channelLogoFile($order->payment_channel),
    ];
}

it('renders the receipt PDF view with order details and branding', function () {
    $order = paidOrderWithItem($this->event);

    $html = View::make('pdf.ticket.receipt', ticketDocViewData($order, 'RCP/TIX/X', 'receipt'))->render();

    expect($html)->toContain($order->order_number)
        ->toContain('Jane Buyer')
        ->toContain('PAID')
        ->toContain('60.000')
        ->toContain('Test Brand Inc.') // branding company name (no logo set)
        ->toContain('bca.svg');        // payment channel logo
});

it('renders the invoice PDF view with branding and footer note', function () {
    $order = paidOrderWithItem($this->event);

    $html = View::make('pdf.ticket.invoice', ticketDocViewData($order, 'INV/TIX/X', 'invoice'))->render();

    expect($html)->toContain('INVOICE')
        ->toContain($order->order_number)
        ->toContain('60.000')
        ->toContain('Test Brand Inc.')
        ->toContain('Thank you for your purchase.');
});

it('refuses a receipt before payment', function () {
    $order = TicketOrder::factory()->create(['event_id' => $this->event->id]); // pending, unpaid
    $token = TicketOrder::magicLinkTokenFor($order->order_number);

    expect(fn () => app(PublicTicketOrderController::class)
        ->receiptPdfByMagicLink($token, app(TicketDocumentService::class)))
        ->toThrow(HttpException::class);
});

it('404s an invalid magic token', function () {
    expect(fn () => app(PublicTicketOrderController::class)
        ->invoicePdfByMagicLink('not-a-real-token', app(TicketDocumentService::class)))
        ->toThrow(HttpException::class);
});
