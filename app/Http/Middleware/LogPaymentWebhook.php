<?php

namespace App\Http\Middleware;

use App\Models\PaymentWebhookEvent;
use App\Models\Reservation;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every inbound payment-provider webhook into payment_webhook_events
 * for auditing and debugging. Runs AFTER the controller so it captures the
 * final HTTP status and result message.
 *
 * Logging is strictly best-effort: any failure here is swallowed so it can
 * never break actual webhook processing.
 */
class LogPaymentWebhook
{
    public function handle(Request $request, Closure $next, string $provider = 'xendit'): Response
    {
        $response = $next($request);

        try {
            $this->record($request, $response, $provider);
        } catch (\Throwable $e) {
            Log::warning('Failed to log payment webhook event', [
                'provider' => $provider,
                'message' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    protected function record(Request $request, Response $response, string $provider): void
    {
        $payload = $request->all();
        if (! is_array($payload)) {
            $payload = [];
        }

        $httpStatus = $response->getStatusCode();
        $message = $this->extractMessage($response);
        $externalId = $this->extractExternalId($payload);

        PaymentWebhookEvent::create([
            'provider' => $provider,
            'project_id' => $this->resolveProjectId($externalId),
            'event_type' => $this->extractEventType($payload),
            'external_id' => $externalId,
            'status' => $this->deriveStatus($httpStatus, $message),
            'http_status' => $httpStatus,
            'message' => $message,
            'payload' => $payload,
            'ip_address' => $request->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function extractEventType(array $payload): ?string
    {
        $event = $payload['event'] ?? null;
        if (is_string($event) && $event !== '') {
            return strtolower($event);
        }

        $txStatus = $payload['transaction_status'] ?? null;
        if (is_string($txStatus) && $txStatus !== '') {
            return 'midtrans.'.strtolower($txStatus);
        }

        $status = $payload['status'] ?? null;
        if (is_string($status) && $status !== '') {
            return 'invoice.'.strtolower($status);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function extractExternalId(array $payload): ?string
    {
        $candidate = $payload['external_id']
            ?? $payload['order_id']
            ?? $payload['invoice_id']
            ?? $payload['data']['invoice_id']
            ?? $payload['data']['reference_id']
            ?? $payload['data']['qrpy_id']
            ?? $payload['data']['id']
            ?? null;

        return is_string($candidate) && $candidate !== '' ? $candidate : null;
    }

    protected function resolveProjectId(?string $externalId): ?int
    {
        if ($externalId === null) {
            return null;
        }

        $reservation = Reservation::query()
            ->where('reservation_number', $externalId)
            ->orWhere('xendit_invoice_id', $externalId)
            ->orWhere('xendit_payment_id', $externalId)
            ->orWhere('xendit_refund_id', $externalId)
            ->first();

        return $reservation?->event?->project_id;
    }

    protected function extractMessage(Response $response): ?string
    {
        $data = $response instanceof JsonResponse
            ? $response->getData(true)
            : json_decode((string) $response->getContent(), true);

        if (is_array($data) && isset($data['message']) && is_string($data['message'])) {
            return mb_substr($data['message'], 0, 255);
        }

        return null;
    }

    /**
     * Classify the webhook outcome from the response so the admin log can be
     * filtered without re-parsing payloads.
     */
    protected function deriveStatus(int $httpStatus, ?string $message): string
    {
        if ($httpStatus === 401 || $httpStatus === 403) {
            return 'rejected';
        }

        if ($httpStatus >= 400) {
            return 'error';
        }

        $lower = strtolower((string) $message);
        foreach (['not found', 'no action', 'acknowledged', 'already', 'not eligible', 'ignored'] as $needle) {
            if (str_contains($lower, $needle)) {
                return 'ignored';
            }
        }

        return 'processed';
    }
}
