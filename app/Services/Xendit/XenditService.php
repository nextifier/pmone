<?php

namespace App\Services\Xendit;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;
use Xendit\Refund\CreateRefund;
use Xendit\Refund\RefundApi;

class XenditService
{
    public function __construct()
    {
        $secretKey = config('xendit.secret_key');

        if ($secretKey) {
            Configuration::setXenditKey($secretKey);
        }
    }

    /**
     * Create Xendit invoice for a reservation.
     *
     * Per-invoice $successUrl/$failureUrl override the env defaults so each module
     * (hotel, ticket, etc.) can land users on its own context-specific page.
     *
     * @return array{invoice_id: string, invoice_url: string}
     */
    public function createInvoice(
        Reservation $reservation,
        ?string $successUrl = null,
        ?string $failureUrl = null,
    ): array {
        $api = new InvoiceApi;

        $success = $successUrl ?? (config('xendit.success_redirect_url').'?ref='.$reservation->reservation_number);
        $failure = $failureUrl ?? (config('xendit.failure_redirect_url').'?ref='.$reservation->reservation_number);

        $payload = new CreateInvoiceRequest([
            'external_id' => $reservation->reservation_number,
            'amount' => (float) $reservation->total_amount,
            'description' => "Hotel reservation {$reservation->reservation_number} - {$reservation->hotel?->name}",
            'invoice_duration' => config('xendit.invoice_duration'),
            'currency' => config('xendit.currency', 'IDR'),
            'customer' => [
                'given_names' => $reservation->guest_name,
                'email' => $reservation->guest_email,
                'mobile_number' => $reservation->guest_phone,
            ],
            'success_redirect_url' => $success,
            'failure_redirect_url' => $failure,
        ]);

        $invoice = $api->createInvoice($payload);

        return [
            'invoice_id' => $invoice->getId(),
            'invoice_url' => $invoice->getInvoiceUrl(),
        ];
    }

    /**
     * Refund a Xendit invoice (partial or full).
     */
    public function refundInvoice(string $invoiceId, float $amount, string $reason = 'CANCELLATION'): string
    {
        $api = new RefundApi;

        $payload = new CreateRefund([
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'reason' => $reason,
        ]);

        $refund = $api->createRefund(null, $payload);

        return $refund->getId();
    }

    /**
     * Verify Xendit webhook signature using configured token.
     */
    public function verifyWebhookToken(Request $request): bool
    {
        $token = config('xendit.webhook_token');

        if (! $token) {
            return false;
        }

        return hash_equals($token, (string) $request->header('x-callback-token'));
    }
}
