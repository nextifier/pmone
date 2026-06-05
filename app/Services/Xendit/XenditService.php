<?php

namespace App\Services\Xendit;

use App\Contracts\Payment\CreatesCheckout;
use App\Contracts\Payment\PaymentProvider;
use App\Contracts\Payment\ProvidesBalance;
use App\Contracts\Payment\ProvidesSettlements;
use App\Contracts\Payment\ProvidesTransactions;
use App\Contracts\Payment\SupportsCheckoutMethods;
use App\DTOs\Payment\BalanceAccount;
use App\DTOs\Payment\BalanceSnapshot;
use App\DTOs\Payment\SettlementBucket;
use App\DTOs\Payment\SettlementSummary;
use App\DTOs\Payment\TransactionEntry;
use App\DTOs\Payment\TransactionPage;
use App\DTOs\Payment\TransactionQuery;
use App\Enums\Payment\CheckoutMethod;
use App\Enums\PaymentCapability;
use App\Exceptions\Payment\PaymentProviderException;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;
use Xendit\Refund\CreateRefund;
use Xendit\Refund\RefundApi;

class XenditService implements CreatesCheckout, PaymentProvider, ProvidesBalance, ProvidesSettlements, ProvidesTransactions, SupportsCheckoutMethods
{
    /**
     * Cache TTL for payment channel listings (24 hours).
     */
    public const PAYMENT_CHANNELS_CACHE_TTL = 86400;

    /**
     * Cache key prefix for payment channels — combine with gateway id (or 'legacy')
     * to derive the full key. Exposed so the observer + artisan command can
     * invalidate the same entries this service writes.
     */
    public const PAYMENT_CHANNELS_CACHE_PREFIX = 'xendit:payment_channels:';

    /**
     * Map Xendit `channel_code` (uppercase) to SVG asset under
     * /public/img/payment-methods/ plus a human-readable label.
     *
     * Channel codes here come from Xendit's `/payment_channels` response;
     * keep keys aligned with the values Xendit returns (PERMATA, BRI, OVO, ...).
     * Codes without an asset entry are skipped at render time.
     *
     * @var array<string, array{file: string, alt: string}>
     */
    public const CHANNEL_LOGO_MAP = [
        // Cards
        'VISA' => ['file' => 'visa.svg', 'alt' => 'Visa'],
        'MASTERCARD' => ['file' => 'mastercard.svg', 'alt' => 'Mastercard'],
        'AMEX' => ['file' => 'amex.svg', 'alt' => 'American Express'],
        'JCB' => ['file' => 'jcb.svg', 'alt' => 'JCB'],
        'CREDIT_CARD' => ['file' => 'visa.svg', 'alt' => 'Credit Card'],
        // Virtual accounts / Banks
        'BCA' => ['file' => 'bca.svg', 'alt' => 'BCA'],
        'BRI' => ['file' => 'bri.svg', 'alt' => 'BRI'],
        'BNI' => ['file' => 'bni.svg', 'alt' => 'BNI'],
        'MANDIRI' => ['file' => 'mandiri.svg', 'alt' => 'Mandiri'],
        'PERMATA' => ['file' => 'permata-bank.svg', 'alt' => 'Permata Bank'],
        'CIMB' => ['file' => 'cimb-niaga.svg', 'alt' => 'CIMB Niaga'],
        'CIMB_NIAGA' => ['file' => 'cimb-niaga.svg', 'alt' => 'CIMB Niaga'],
        'BJB' => ['file' => 'bjb.svg', 'alt' => 'BJB'],
        'BNC' => ['file' => 'neobank.svg', 'alt' => 'Neobank'],
        'NEOBANK' => ['file' => 'neobank.svg', 'alt' => 'Neobank'],
        'BSI' => ['file' => 'bsi.svg', 'alt' => 'BSI'],
        'BSS' => ['file' => 'bss.svg', 'alt' => 'Bank Sahabat Sampoerna'],
        'SAHABAT_SAMPOERNA' => ['file' => 'bss.svg', 'alt' => 'Bank Sahabat Sampoerna'],
        'MUAMALAT' => ['file' => 'bank-muamalat.svg', 'alt' => 'Bank Muamalat'],
        // Direct debit
        'DD_BRI' => ['file' => 'dd-bri.svg', 'alt' => 'Direct Debit BRI'],
        'BRI_DIRECT_DEBIT' => ['file' => 'dd-bri.svg', 'alt' => 'Direct Debit BRI'],
        // E-wallets
        'OVO' => ['file' => 'ovo.svg', 'alt' => 'OVO'],
        'SHOPEEPAY' => ['file' => 'shopeepay.svg', 'alt' => 'ShopeePay'],
        'NEXCASH' => ['file' => 'nexcash.svg', 'alt' => 'NexCash'],
        'DANA' => ['file' => 'dana.svg', 'alt' => 'DANA'],
        'ASTRAPAY' => ['file' => 'astrapay.svg', 'alt' => 'AstraPay'],
        'LINKAJA' => ['file' => 'link-aja.svg', 'alt' => 'LinkAja'],
        'JENIUSPAY' => ['file' => 'jeniuspay.svg', 'alt' => 'JeniusPay'],
        'GOPAY' => ['file' => 'gopay.svg', 'alt' => 'GoPay'],
        // QR
        'QRIS' => ['file' => 'qris.svg', 'alt' => 'QRIS'],
    ];

    /**
     * Fallback channels rendered when Xendit `/payment_channels` is unreachable.
     * Keeps the invoice PDF renderable rather than blank.
     *
     * @var array<int, string>
     */
    protected const FALLBACK_CHANNEL_CODES = ['VISA', 'MASTERCARD', 'BCA', 'BRI', 'BNI', 'MANDIRI', 'QRIS'];

    /**
     * Payment channel codes (uppercase, matching Xendit's payment_channel value)
     * that can be refunded automatically. Everything not in this list — notably
     * Virtual Accounts and retail outlets — requires the admin to refund
     * manually (bank transfer to guest, etc.).
     *
     * Two API routes are involved, picked by {@see ProcessXenditRefundJob}:
     *  - Cards, e-wallets and direct debit go through the unified Refund API
     *    ({@see refundInvoice}), keyed by invoice id.
     *  - QRIS does NOT support the unified Refund API (it returns
     *    REFUND_NOT_SUPPORTED). It must use the dedicated QR Code Refund
     *    endpoint ({@see refundQrPayment}), keyed by the qrpy_ payment id, and
     *    only succeeds for the supported QRIS issuers (DANA, OVO, ShopeePay,
     *    LinkAja, Mandiri, Permata, CIMB, Jenius/BTPN, BSI).
     *
     * @var array<int, string>
     */
    public const REFUNDABLE_CHANNELS = [
        // Cards (fully refundable via the unified Refund API)
        'CREDIT_CARD',
        'VISA',
        'MASTERCARD',
        'AMEX',
        'JCB',
        // E-wallets (most support refunds via the unified Refund API)
        'OVO',
        'DANA',
        'SHOPEEPAY',
        'LINKAJA',
        'GOPAY',
        'ASTRAPAY',
        'JENIUSPAY',
        'NEXCASH',
        // QRIS — refunded via the dedicated QR Code Refund endpoint, not /refunds
        'QRIS',
        // Direct debit
        'DD_BRI',
        'BRI_DIRECT_DEBIT',
    ];

    /**
     * Whether the given payment channel can be refunded automatically via Xendit.
     * Virtual Accounts (BCA, BNI, BRI, MANDIRI, ...) and retail outlets all
     * return false — those require manual handling by the admin.
     */
    public static function channelSupportsRefund(?string $channel): bool
    {
        if ($channel === null || $channel === '') {
            return false;
        }

        return in_array(strtoupper($channel), self::REFUNDABLE_CHANNELS, true);
    }

    protected ?string $secretKey = null;

    protected ?string $webhookToken = null;

    protected ?ProjectPaymentGateway $gateway = null;

    public function __construct(?ProjectPaymentGateway $gateway = null)
    {
        if ($gateway !== null) {
            $this->bindGateway($gateway);
        }
        // No env fallback. An unbound service has no credentials; callers must
        // attach a ProjectPaymentGateway via forGateway()/attachGateway() before
        // making any API call. Operations on an unbound instance throw.
    }

    /**
     * Throw if the service is not bound to a project gateway. Called by every
     * operation that needs Xendit credentials so we fail loudly instead of
     * silently leaking a request to the wrong account.
     */
    protected function requireGateway(): void
    {
        if ($this->gateway === null || $this->secretKey === null) {
            throw new \RuntimeException(
                'Xendit service is not bound to a project payment gateway. '.
                'Use XenditService::forGateway($gateway) before calling this method.'
            );
        }
    }

    /**
     * Build a service instance bound to a specific project gateway.
     * Resolves through the container so test code can swap the implementation
     * via $this->app->instance(XenditService::class, $mock). Mocked instances
     * are detected by class identity and not rebound (they manage their own state).
     */
    public static function forGateway(ProjectPaymentGateway $gateway): self
    {
        /** @var self $instance */
        $instance = app(self::class);

        if ($instance::class === self::class) {
            $instance->attachGateway($gateway);
        }

        return $instance;
    }

    public function attachGateway(ProjectPaymentGateway $gateway): self
    {
        $this->bindGateway($gateway);

        return $this;
    }

    protected function bindGateway(ProjectPaymentGateway $gateway): void
    {
        if ($gateway->provider !== 'xendit') {
            throw new \InvalidArgumentException("Gateway provider mismatch: expected xendit, got {$gateway->provider}");
        }

        $this->gateway = $gateway;
        $this->secretKey = $gateway->secret_key;
        $this->webhookToken = $gateway->webhook_token;

        if ($this->secretKey) {
            Configuration::setXenditKey($this->secretKey);
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
    /**
     * Guzzle HTTP client used for Xendit SDK calls. Caps total time at 15s and
     * connection time at 5s so misconfigured firewalls (IP allowlist, DNS) fail
     * fast instead of timing out the whole PHP request.
     */
    protected function httpClient(): GuzzleClient
    {
        return new GuzzleClient([
            'timeout' => 15.0,
            'connect_timeout' => 5.0,
        ]);
    }

    /**
     * Fetch an existing invoice by Xendit invoice id. Used to backfill
     * `payment_channel` / `payment_method` after the fact, e.g. when the
     * webhook arrived without the channel field or when callers want to
     * reconcile a previously-paid reservation. Returns null on any failure
     * so callers can degrade gracefully without raising 5xx to end users.
     *
     * @return array<string, mixed>|null
     */
    public function fetchInvoiceDetail(string $invoiceId): ?array
    {
        $this->requireGateway();

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->acceptJson()
                ->timeout(10)
                ->connectTimeout(5)
                ->get('https://api.xendit.co/v2/invoices/'.urlencode($invoiceId));

            if (! $response->successful()) {
                Log::info('Xendit fetchInvoiceDetail non-2xx', [
                    'status' => $response->status(),
                    'invoice_id' => $invoiceId,
                ]);

                return null;
            }

            $payload = $response->json();

            return is_array($payload) ? $payload : null;
        } catch (\Throwable $e) {
            Log::warning('Xendit fetchInvoiceDetail failed', [
                'message' => $e->getMessage(),
                'invoice_id' => $invoiceId,
            ]);

            return null;
        }
    }

    /**
     * Resolve the card network (VISA / MASTERCARD / AMEX / JCB) behind a paid
     * credit-card invoice. Xendit reports every card payment on the single
     * `CREDIT_CARD` channel; the actual brand lives on the credit card charge,
     * referenced by the invoice's `credit_card_charge_id`.
     *
     * Returns null when the charge can't be fetched or the brand is unknown,
     * so callers keep the generic `CREDIT_CARD` value.
     */
    public function fetchCardBrand(string $creditCardChargeId): ?string
    {
        $this->requireGateway();

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->acceptJson()
                ->timeout(10)
                ->connectTimeout(5)
                ->get('https://api.xendit.co/credit_card_charges/'.urlencode($creditCardChargeId));

            if (! $response->successful()) {
                Log::info('Xendit fetchCardBrand non-2xx', [
                    'status' => $response->status(),
                    'charge_id' => $creditCardChargeId,
                ]);

                return null;
            }

            $brand = strtoupper(trim((string) $response->json('card_brand')));

            return match ($brand) {
                'VISA' => 'VISA',
                'MASTERCARD' => 'MASTERCARD',
                'JCB' => 'JCB',
                'AMEX', 'AMERICAN EXPRESS' => 'AMEX',
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('Xendit fetchCardBrand failed', [
                'message' => $e->getMessage(),
                'charge_id' => $creditCardChargeId,
            ]);

            return null;
        }
    }

    public function createInvoice(
        Reservation $reservation,
        ?string $successUrl = null,
        ?string $failureUrl = null,
    ): array {
        $this->requireGateway();
        $api = new InvoiceApi($this->httpClient());

        $success = $successUrl ?? (config('xendit.success_redirect_url').'?ref='.$reservation->reservation_number);
        $failure = $failureUrl ?? (config('xendit.failure_redirect_url').'?ref='.$reservation->reservation_number);

        $payload = new CreateInvoiceRequest([
            'external_id' => $reservation->reservation_number,
            'amount' => (float) $reservation->total_amount,
            'description' => "Hotel reservation {$reservation->reservation_number} - {$reservation->hotel?->name}",
            'invoice_duration' => config('xendit.invoice_duration'),
            'currency' => $this->resolveCurrency(),
            'customer' => [
                'given_names' => $reservation->guest_name,
                'email' => $reservation->guest_email,
                'mobile_number' => $reservation->guest_phone,
            ],
            'success_redirect_url' => $success,
            'failure_redirect_url' => $failure,
        ]);

        $invoice = $api->createInvoice($payload);

        $this->touchGatewayUsage();

        return [
            'invoice_id' => $invoice->getId(),
            'invoice_url' => $invoice->getInvoiceUrl(),
        ];
    }

    /**
     * Create a payment for a reservation using whichever checkout method the
     * bound gateway is configured for (see ProjectPaymentGateway::$checkout_method).
     *
     * Returns a method-agnostic shape so callers (ReservationService) never need
     * to know whether the Sessions API or the legacy Invoices API ran.
     *
     * @return array{reference: string, payment_url: string, checkout_method: string}
     */
    public function createCheckout(
        Reservation $reservation,
        ?string $successUrl = null,
        ?string $failureUrl = null,
    ): array {
        $this->requireGateway();

        $method = $this->gateway->checkout_method ?? CheckoutMethod::PaymentLinkLegacy;

        if ($method === CheckoutMethod::PaymentLinkSessions) {
            $session = $this->createSession($reservation, $successUrl, $failureUrl);

            return [
                'reference' => $session['session_id'],
                'payment_url' => $session['payment_url'],
                'checkout_method' => $method->value,
            ];
        }

        // CheckoutMethod::PaymentLinkLegacy — the legacy Invoices API.
        $invoice = $this->createInvoice($reservation, $successUrl, $failureUrl);

        return [
            'reference' => $invoice['invoice_id'],
            'payment_url' => $invoice['invoice_url'],
            'checkout_method' => $method->value,
        ];
    }

    /**
     * Create a Xendit Payment Session (PAYMENT_LINK mode) for a reservation
     * and return the hosted checkout URL.
     *
     * The installed xendit/xendit-php SDK has no Sessions client, so this calls
     * the REST endpoint directly with the same Basic-auth pattern as
     * getBalance().
     *
     * @return array{session_id: string, payment_url: string, status: string}
     */
    public function createSession(
        Reservation $reservation,
        ?string $successUrl = null,
        ?string $failureUrl = null,
    ): array {
        $this->requireGateway();

        $success = $successUrl ?? (config('xendit.success_redirect_url').'?ref='.$reservation->reservation_number);
        $cancel = $failureUrl ?? (config('xendit.failure_redirect_url').'?ref='.$reservation->reservation_number);

        $customer = [
            'reference_id' => $reservation->reservation_number.'-cust',
            'type' => 'INDIVIDUAL',
            'individual_detail' => [
                'given_names' => $reservation->guest_name ?: 'Guest',
            ],
        ];

        if (filled($reservation->guest_email)) {
            $customer['email'] = $reservation->guest_email;
        }

        // Sessions validates mobile_number strictly as E.164. PM One guest
        // phones are free-form, so only forward a conforming value; otherwise
        // omit it rather than have Xendit reject the whole request.
        $mobile = $this->toE164($reservation->guest_phone);
        if ($mobile !== null) {
            $customer['mobile_number'] = $mobile;
        }

        $payload = [
            'reference_id' => $reservation->reservation_number,
            'session_type' => 'PAY',
            'mode' => 'PAYMENT_LINK',
            'currency' => $this->resolveCurrency(),
            // IDR has no minor units — Xendit expects a whole-rupiah integer.
            'amount' => (int) round((float) $reservation->total_amount),
            'country' => 'ID',
            'locale' => 'en',
            'customer' => $customer,
            'description' => "Hotel reservation {$reservation->reservation_number} - {$reservation->hotel?->name}",
            // Sessions default to a 30-minute expiry; align it with the
            // app-wide payment window so the hosted page does not die early.
            'expires_at' => now()->addSeconds((int) config('xendit.invoice_duration', 86400))->toIso8601String(),
            'success_return_url' => $success,
            'cancel_return_url' => $cancel,
            // Suppress the "Installment Plan" dropdown on the hosted Sessions
            // Payment Link page. Hotel bookings are one-off and we have no
            // installment programs to offer — the dropdown would otherwise
            // render a useless single "Pay in Full" option (or worse, an empty
            // combobox if the merchant account hasn't configured any programs).
            // Undocumented in the Sessions API spec but verified empirically:
            // Xendit reads this field at the session level and excludes the
            // installment selector from the Card payment form when the array
            // is empty.
            'channel_properties' => [
                'cards' => [
                    'allowed_installment_program_ids' => [],
                ],
            ],
        ];

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->acceptJson()
                ->timeout(15)
                ->connectTimeout(5)
                ->post('https://api.xendit.co/sessions', $payload);
        } catch (\Throwable $e) {
            $mapped = XenditErrorMapper::map($e);

            throw new PaymentProviderException(
                $mapped['message'],
                $mapped['error_code'],
                $mapped['http_status'],
                $e,
            );
        }

        if (! $response->successful()) {
            throw $this->mapProviderApiError($response, 'sessions');
        }

        $this->touchGatewayUsage();

        // The create response is the session object. Xendit has used both
        // `payment_session_id` and the bare `id` for it across API revisions —
        // accept either so a field rename upstream does not break checkout.
        $sessionId = (string) ($response->json('payment_session_id') ?? $response->json('id') ?? '');
        $paymentUrl = (string) ($response->json('payment_link_url') ?? '');

        if ($paymentUrl === '') {
            throw new PaymentProviderException(
                'Xendit created the session but returned no payment link URL.',
                'PAYMENT_GATEWAY_UNAVAILABLE',
                502,
            );
        }

        return [
            'session_id' => $sessionId,
            'payment_url' => $paymentUrl,
            'status' => (string) ($response->json('status') ?? ''),
        ];
    }

    /**
     * Fetch a Xendit Payment Session by id. Used by the webhook handler to
     * backfill the payment channel when the session.completed payload omits it.
     * Returns null on any failure so callers degrade gracefully.
     *
     * @return array<string, mixed>|null
     */
    public function fetchSessionDetail(string $sessionId): ?array
    {
        $this->requireGateway();

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->acceptJson()
                ->timeout(10)
                ->connectTimeout(5)
                ->get('https://api.xendit.co/sessions/'.urlencode($sessionId));

            if (! $response->successful()) {
                Log::info('Xendit fetchSessionDetail non-2xx', [
                    'status' => $response->status(),
                    'session_id' => $sessionId,
                ]);

                return null;
            }

            $payload = $response->json();

            return is_array($payload) ? $payload : null;
        } catch (\Throwable $e) {
            Log::warning('Xendit fetchSessionDetail failed', [
                'message' => $e->getMessage(),
                'session_id' => $sessionId,
            ]);

            return null;
        }
    }

    /**
     * Fetch a Xendit v3 Payment Request by id. A completed Payment Session
     * exposes the underlying `payment_request_id`; the payment request is the
     * only place the channel actually used (QRIS, OVO, a specific VA bank, ...)
     * is recorded. Returns null on any failure so callers degrade gracefully.
     *
     * @return array<string, mixed>|null
     */
    public function fetchPaymentRequestDetail(string $paymentRequestId): ?array
    {
        $this->requireGateway();

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->withHeaders(['api-version' => '2024-11-11'])
                ->acceptJson()
                ->timeout(10)
                ->connectTimeout(5)
                ->get('https://api.xendit.co/v3/payment_requests/'.urlencode($paymentRequestId));

            if (! $response->successful()) {
                Log::info('Xendit fetchPaymentRequestDetail non-2xx', [
                    'status' => $response->status(),
                    'payment_request_id' => $paymentRequestId,
                ]);

                return null;
            }

            $payload = $response->json();

            return is_array($payload) ? $payload : null;
        } catch (\Throwable $e) {
            Log::warning('Xendit fetchPaymentRequestDetail failed', [
                'message' => $e->getMessage(),
                'payment_request_id' => $paymentRequestId,
            ]);

            return null;
        }
    }

    /**
     * Return the phone number if it is already valid E.164 (`+` then 7-15
     * digits), otherwise null. The Sessions API rejects malformed mobile
     * numbers, so a non-conforming value is dropped rather than risking the
     * whole request.
     */
    protected function toE164(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        return preg_match('/^\+[1-9]\d{6,14}$/', $phone) === 1 ? $phone : null;
    }

    /**
     * Xendit refund `reason` accepts only a fixed set of enum values. Anything
     * outside this list gets rejected by Xendit with "Failed to validate the
     * request". We preserve the user-supplied free text on the Reservation
     * model itself (`refund_reason` column) and only forward an allowed enum
     * here.
     */
    public const REFUND_REASONS = [
        'FRAUDULENT',
        'DUPLICATE',
        'REQUESTED_BY_CUSTOMER',
        'CANCELLATION',
        'OTHERS',
    ];

    /**
     * Coerce a free-text cancellation reason into one of Xendit's accepted
     * refund enum values, defaulting to CANCELLATION. The human-readable reason
     * is preserved separately on the Reservation model (`refund_reason`).
     */
    private function normalizeRefundReason(string $reason): string
    {
        $normalized = strtoupper($reason);

        return in_array($normalized, self::REFUND_REASONS, true) ? $normalized : 'CANCELLATION';
    }

    /**
     * Refund a Xendit invoice (partial or full) through the unified Refund API.
     *
     * Works for cards, e-wallets and direct debit. Does NOT work for QRIS —
     * Xendit rejects QRIS invoices here with REFUND_NOT_SUPPORTED; use
     * {@see refundQrPayment} for those.
     */
    public function refundInvoice(string $invoiceId, float $amount, string $reason = 'CANCELLATION'): string
    {
        $this->requireGateway();
        $api = new RefundApi($this->httpClient());

        $payload = new CreateRefund([
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'reason' => $this->normalizeRefundReason($reason),
        ]);

        $refund = $api->createRefund(null, null, $payload);

        $this->touchGatewayUsage();

        return $refund->getId();
    }

    /**
     * Refund a QRIS payment through Xendit's dedicated QR Code Refund endpoint.
     *
     * QRIS cannot be refunded via the unified Refund API ({@see refundInvoice}).
     * This endpoint is keyed by the QR payment id (`qrpy_...`) — Xendit reports
     * it as `payment_id` on the paid invoice, and PM One stores it on the
     * reservation as `xendit_payment_id`.
     *
     * The call returns immediately with status PENDING; Xendit then sends a
     * `qr.refund` webhook once the refund settles. Refunds only succeed for the
     * supported QRIS issuers — unsupported ones (e.g. GoPay) return a 4xx and
     * must be refunded manually.
     *
     * @return array{id: string, status: string, refund_amount: int|null, channel_code: string|null}
     *
     * @throws PaymentProviderException on any non-2xx response (4xx = permanent)
     */
    public function refundQrPayment(string $qrPaymentId, float $amount, string $reason = 'CANCELLATION'): array
    {
        $this->requireGateway();

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->connectTimeout(5)
                ->post('https://api.xendit.co/qr_codes/payments/'.urlencode($qrPaymentId).'/refunds', [
                    'amount' => (int) round($amount),
                    'reason' => $this->normalizeRefundReason($reason),
                ]);
        } catch (\Throwable $e) {
            // Network/timeout — transient, let the caller retry.
            throw new PaymentProviderException(
                'Xendit QR refund request failed: '.$e->getMessage(),
                'QR_REFUND_REQUEST_FAILED',
                503,
                $e,
            );
        }

        if (! $response->successful()) {
            $body = $response->json();
            $errorCode = is_array($body) ? (string) ($body['error_code'] ?? 'QR_REFUND_FAILED') : 'QR_REFUND_FAILED';
            $message = is_array($body)
                ? (string) ($body['message'] ?? 'Xendit QR refund failed')
                : 'Xendit QR refund failed';

            throw new PaymentProviderException($message, $errorCode, $response->status());
        }

        $this->touchGatewayUsage();

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        return [
            'id' => (string) ($body['id'] ?? ''),
            'status' => strtoupper((string) ($body['status'] ?? 'PENDING')),
            'refund_amount' => isset($body['refund_amount']) ? (int) $body['refund_amount'] : null,
            'channel_code' => isset($body['channel_code']) ? (string) $body['channel_code'] : null,
        ];
    }

    /**
     * Verify Xendit webhook signature against the bound gateway token. Returns
     * false when no gateway is bound (no credentials = signature can't match).
     */
    public function verifyWebhookToken(Request $request): bool
    {
        if (! $this->webhookToken) {
            return false;
        }

        return hash_equals($this->webhookToken, (string) $request->header('x-callback-token'));
    }

    public function gateway(): ?ProjectPaymentGateway
    {
        return $this->gateway;
    }

    public function provider(): string
    {
        return 'xendit';
    }

    /**
     * Capabilities this provider exposes. Payouts is added here when that
     * phase lands.
     *
     * @return array<int, PaymentCapability>
     */
    public function capabilities(): array
    {
        return [
            PaymentCapability::Invoicing,
            PaymentCapability::Refunds,
            PaymentCapability::Balance,
            PaymentCapability::Transactions,
            PaymentCapability::Settlement,
        ];
    }

    public function supports(PaymentCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    /**
     * Checkout integrations Xendit exposes, in display order. Includes methods
     * that are not implemented yet (CheckoutMethod::available() === false) so
     * the admin UI can render them disabled.
     *
     * @return array<int, CheckoutMethod>
     */
    public function checkoutMethods(): array
    {
        return [
            CheckoutMethod::PaymentLinkSessions,
            CheckoutMethod::PaymentLinkLegacy,
        ];
    }

    public function supportsCheckoutMethod(CheckoutMethod $method): bool
    {
        return in_array($method, $this->checkoutMethods(), true) && $method->available();
    }

    /**
     * Fetch the bound account's balance from Xendit.
     *
     * CASH is the primary spendable balance and a failure there is fatal.
     * HOLDING is supplementary — skipped silently if the account is not
     * available on this Xendit plan or the call fails.
     */
    public function getBalance(): BalanceSnapshot
    {
        $this->requireGateway();

        $currency = $this->resolveCurrency();
        $accounts = [
            new BalanceAccount('CASH', $this->fetchAccountBalance('CASH'), $currency),
        ];

        try {
            $accounts[] = new BalanceAccount('HOLDING', $this->fetchAccountBalance('HOLDING'), $currency);
        } catch (PaymentProviderException $e) {
            Log::info('Xendit HOLDING balance unavailable, skipped', [
                'gateway_id' => $this->gateway?->id,
                'message' => $e->getMessage(),
            ]);
        }

        $this->touchGatewayUsage();

        return new BalanceSnapshot(
            available: $accounts[0]->balance,
            currency: $currency,
            accounts: $accounts,
            fetchedAt: now(),
        );
    }

    /**
     * Call Xendit `GET /balance` for one account type. Throws a
     * PaymentProviderException already mapped to a user-safe code on any
     * non-2xx response or transport failure.
     */
    protected function fetchAccountBalance(string $accountType): float
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->acceptJson()
                ->timeout(10)
                ->connectTimeout(5)
                ->get('https://api.xendit.co/balance', ['account_type' => $accountType]);
        } catch (\Throwable $e) {
            $mapped = XenditErrorMapper::map($e);

            throw new PaymentProviderException(
                $mapped['message'],
                $mapped['error_code'],
                $mapped['http_status'],
                $e,
            );
        }

        if ($response->successful()) {
            return (float) ($response->json('balance') ?? 0);
        }

        throw $this->mapProviderApiError($response, 'balance:'.$accountType);
    }

    /**
     * List transactions on the bound Xendit account, one cursor page at a time.
     */
    public function listTransactions(TransactionQuery $query): TransactionPage
    {
        $this->requireGateway();

        $params = ['limit' => $query->limit];

        if ($query->afterId !== null) {
            $params['after_id'] = $query->afterId;
        }
        if ($query->type !== null) {
            $params['types'] = strtoupper($query->type);
        }
        if ($query->status !== null) {
            $params['statuses'] = strtoupper($query->status);
        }
        if ($query->dateFrom !== null) {
            $params['created']['gte'] = Carbon::parse($query->dateFrom)->startOfDay()->toIso8601String();
        }
        if ($query->dateTo !== null) {
            $params['created']['lte'] = Carbon::parse($query->dateTo)->endOfDay()->toIso8601String();
        }

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->acceptJson()
                ->timeout(15)
                ->connectTimeout(5)
                ->get('https://api.xendit.co/transactions', $params);
        } catch (\Throwable $e) {
            $mapped = XenditErrorMapper::map($e);

            throw new PaymentProviderException(
                $mapped['message'],
                $mapped['error_code'],
                $mapped['http_status'],
                $e,
            );
        }

        if (! $response->successful()) {
            throw $this->mapProviderApiError($response, 'transactions');
        }

        $this->touchGatewayUsage();

        return $this->mapTransactionsResponse($response->json());
    }

    /**
     * Map Xendit's `/transactions` payload to a normalized TransactionPage.
     * Xendit uses cursor pagination: the cursor for the next page is the id of
     * the last row, surfaced only when `has_more` is true.
     */
    protected function mapTransactionsResponse(mixed $payload): TransactionPage
    {
        $rows = (is_array($payload) && is_array($payload['data'] ?? null)) ? $payload['data'] : [];
        $hasMore = is_array($payload) && ($payload['has_more'] ?? false) === true;

        $entries = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $entries[] = new TransactionEntry(
                id: (string) ($row['id'] ?? ''),
                type: (string) ($row['type'] ?? ''),
                status: (string) ($row['status'] ?? ''),
                channelCode: $this->normalizeChannelCode($row['channel_code'] ?? null),
                channelCategory: isset($row['channel_category']) ? (string) $row['channel_category'] : null,
                amount: (float) ($row['amount'] ?? 0),
                netAmount: isset($row['net_amount']) ? (float) $row['net_amount'] : null,
                currency: (string) ($row['currency'] ?? $this->resolveCurrency()),
                reference: isset($row['reference_id']) ? (string) $row['reference_id'] : null,
                createdAt: isset($row['created']) ? Carbon::parse($row['created']) : null,
                settlementStatus: isset($row['settlement_status']) ? (string) $row['settlement_status'] : null,
                estimatedSettlementTime: isset($row['estimated_settlement_time'])
                    ? Carbon::parse($row['estimated_settlement_time'])
                    : null,
            );
        }

        $nextCursor = ($hasMore && $entries !== []) ? end($entries)->id : null;

        return new TransactionPage($entries, $hasMore, $nextCursor);
    }

    /**
     * Xendit transaction channel codes carry a country prefix on e-wallets
     * (ID_OVO, ID_DANA, ID_SHOPEEPAY, ...). Strip it so the code matches the
     * shared payment-method logo map used across the app.
     */
    protected function normalizeChannelCode(mixed $code): ?string
    {
        if (! is_string($code) || $code === '') {
            return null;
        }

        return preg_replace('/^ID_/', '', $code);
    }

    /**
     * Summarize settlement progress by walking successful payments and reading
     * each transaction's `settlement_status` / `estimated_settlement_time`.
     * Pending amounts are bucketed by their estimated settlement date.
     */
    public function getSettlementSummary(string $dateFrom, string $dateTo): SettlementSummary
    {
        $this->requireGateway();

        $pendingAmount = 0.0;
        $pendingCount = 0;
        $settledAmount = 0.0;
        $settledCount = 0;
        /** @var array<string, array{amount: float, count: int}> $buckets */
        $buckets = [];

        $cursor = null;
        $truncated = true;
        $maxPages = 40;

        for ($i = 0; $i < $maxPages; $i++) {
            $page = $this->listTransactions(new TransactionQuery(
                limit: 50,
                afterId: $cursor,
                type: 'payment',
                status: 'success',
                dateFrom: $dateFrom,
                dateTo: $dateTo,
            ));

            foreach ($page->entries as $txn) {
                $status = strtoupper((string) $txn->settlementStatus);

                if ($status === 'PENDING') {
                    $pendingAmount += $txn->amount;
                    $pendingCount++;
                    $key = $txn->estimatedSettlementTime?->toDateString() ?? '';
                    $buckets[$key]['amount'] = ($buckets[$key]['amount'] ?? 0) + $txn->amount;
                    $buckets[$key]['count'] = ($buckets[$key]['count'] ?? 0) + 1;
                } elseif ($status === 'SETTLED' || $status === 'EARLY_SETTLED') {
                    $settledAmount += $txn->amount;
                    $settledCount++;
                }
            }

            if (! $page->hasMore || $page->nextCursor === null) {
                $truncated = false;
                break;
            }
            $cursor = $page->nextCursor;
        }

        ksort($buckets);
        $upcoming = [];
        $unscheduled = null;
        foreach ($buckets as $date => $data) {
            $bucket = new SettlementBucket(
                date: $date !== '' ? $date : null,
                amount: $data['amount'],
                count: $data['count'],
            );
            if ($date === '') {
                $unscheduled = $bucket;
            } else {
                $upcoming[] = $bucket;
            }
        }
        if ($unscheduled !== null) {
            $upcoming[] = $unscheduled;
        }

        return new SettlementSummary(
            pendingAmount: $pendingAmount,
            pendingCount: $pendingCount,
            settledAmount: $settledAmount,
            settledCount: $settledCount,
            currency: $this->resolveCurrency(),
            upcoming: $upcoming,
            truncated: $truncated,
        );
    }

    /**
     * Translate a non-2xx Xendit response into a PaymentProviderException with
     * a stable error code. Mirrors the status taxonomy used by
     * testCredentials() so the admin UI surfaces consistent messages.
     */
    protected function mapProviderApiError(Response $response, string $context): PaymentProviderException
    {
        $body = $response->json();
        $providerCode = is_array($body) ? (string) ($body['error_code'] ?? '') : '';
        $providerMsg = is_array($body) ? (string) ($body['message'] ?? '') : '';
        $status = $response->status();

        Log::warning('Xendit API returned non-2xx', [
            'status' => $status,
            'context' => $context,
            'gateway_id' => $this->gateway?->id,
            'error_code' => $providerCode,
        ]);

        if ($providerCode === 'IP_NOT_ALLOWED' || str_contains(strtolower($providerMsg), 'ip allowlist')) {
            return new PaymentProviderException(
                'Xendit blocked this request. Add the server IP to the Xendit IP allowlist (Settings → Developers → IP Allowlist).',
                'PAYMENT_GATEWAY_IP_NOT_ALLOWED',
                502,
            );
        }

        if ($providerCode === 'INVALID_URL') {
            return new PaymentProviderException(
                'Xendit rejected a checkout URL. The Sessions API requires publicly reachable success/cancel URLs — localhost URLs are not accepted. Set FRONTEND_URL to a public domain.',
                'PAYMENT_GATEWAY_MISCONFIGURED',
                502,
            );
        }

        if ($status === 401 || $providerCode === 'INVALID_API_KEY') {
            return new PaymentProviderException(
                'Xendit rejected the secret key for this gateway. Check the credentials in payment gateway settings.',
                'PAYMENT_GATEWAY_MISCONFIGURED',
                502,
            );
        }

        if ($status === 429) {
            return new PaymentProviderException(
                'Too many requests to Xendit. Wait a minute and try again.',
                'PAYMENT_GATEWAY_RATE_LIMITED',
                429,
            );
        }

        if ($status === 403) {
            return new PaymentProviderException(
                'Xendit refused the request. The account may be suspended or the key may lack permission.',
                'PAYMENT_GATEWAY_FORBIDDEN',
                502,
            );
        }

        if ($status >= 500) {
            return new PaymentProviderException(
                'Xendit reported a server error. Try again in a few minutes.',
                'PAYMENT_GATEWAY_SERVER_ERROR',
                502,
            );
        }

        return new PaymentProviderException(
            'Xendit could not complete the request (status '.$status.').',
            'PAYMENT_GATEWAY_UNAVAILABLE',
            502,
        );
    }

    /**
     * Xendit invoice currency. PM One prices everything in IDR (room rates,
     * taxes, transfers - no FX conversion anywhere), so this is fixed at the
     * app level and intentionally not configurable per gateway.
     */
    protected function resolveCurrency(): string
    {
        return config('xendit.currency', 'IDR');
    }

    protected function touchGatewayUsage(): void
    {
        if ($this->gateway) {
            $this->gateway->forceFill(['last_used_at' => now()])->saveQuietly();
        }
    }

    /**
     * Build the cache key used to store the enabled-channels list for the
     * currently bound gateway. Returns null when no gateway is bound (caller
     * should use fallback logos instead of caching).
     */
    public function paymentChannelsCacheKey(): ?string
    {
        if ($this->gateway?->id === null) {
            return null;
        }

        return self::PAYMENT_CHANNELS_CACHE_PREFIX."gateway:{$this->gateway->id}";
    }

    /**
     * Fetch the list of payment channels enabled on the bound Xendit account,
     * cached for 24 hours. Filters out channels with `is_activated: false`
     * and keeps only those we have a logo asset for (see CHANNEL_LOGO_MAP).
     *
     * On network/API failure, falls back to a minimal hardcoded list so the
     * invoice PDF still renders. The fallback is NOT cached so the next render
     * retries the upstream call.
     *
     * @return array<int, array{file: string, alt: string}>
     */
    public function getEnabledPaymentChannels(): array
    {
        $cacheKey = $this->paymentChannelsCacheKey();
        if ($cacheKey === null || ! $this->secretKey) {
            return $this->fallbackLogos();
        }

        // Cache the raw channel codes (`[]` on failure) so a degraded Xendit
        // response doesn't make every invoice render re-hit the API.
        $codes = Cache::remember(
            $cacheKey,
            self::PAYMENT_CHANNELS_CACHE_TTL,
            fn () => $this->fetchEnabledChannelCodes() ?? [],
        );

        $logos = $this->mapCodesToLogos(is_array($codes) ? $codes : []);

        // An invoice must never render with zero payment logos: fall back to a
        // sensible static set when the live list is unavailable, empty, or none
        // of the returned codes have a known logo asset.
        return $logos !== [] ? $logos : $this->fallbackLogos();
    }

    /**
     * Probe a Xendit secret key by calling `GET /payment_channels` (the
     * cheapest read-only authenticated endpoint Xendit exposes). Used by the
     * admin "Test Connection" flow BEFORE persisting credentials to the
     * database, so staff get fast feedback on typos, account mismatches, and
     * IP allowlist gaps instead of finding out at a customer's first booking.
     *
     * Does NOT require the service instance to be bound — `$secretKey` is
     * passed in directly. Webhook tokens cannot be verified end-to-end here
     * (the verification flows the other direction at runtime), so the caller
     * is responsible for any structural checks on `$webhookToken`.
     *
     * @return array{
     *     success: bool,
     *     error_code?: string,
     *     message: string,
     *     channels_count?: int,
     *     active_channels?: array<int, string>,
     * }
     */
    public function testCredentials(string $secretKey, ?string $webhookToken = null): array
    {
        if (trim($secretKey) === '') {
            return [
                'success' => false,
                'error_code' => 'PAYMENT_GATEWAY_MISCONFIGURED',
                'message' => 'Secret key is empty.',
            ];
        }

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->acceptJson()
                ->timeout(10)
                ->connectTimeout(5)
                ->get('https://api.xendit.co/payment_channels');
        } catch (\Throwable $e) {
            $mapped = XenditErrorMapper::map($e);

            return [
                'success' => false,
                'error_code' => $mapped['error_code'],
                'message' => $mapped['message'],
            ];
        }

        if ($response->successful()) {
            $payload = $response->json();
            $channels = is_array($payload) ? $payload : [];
            $active = [];
            foreach ($channels as $channel) {
                if (! is_array($channel) || ($channel['is_activated'] ?? false) !== true) {
                    continue;
                }
                $code = $channel['channel_code'] ?? null;
                if (is_string($code) && $code !== '') {
                    $active[] = strtoupper($code);
                }
            }

            return [
                'success' => true,
                'message' => 'Credentials verified. Connection to Xendit OK.',
                'channels_count' => count($active),
                'active_channels' => array_values(array_unique($active)),
            ];
        }

        // 401/403: credential issue. 429: rate limit. 5xx: provider outage.
        // The response payload typically includes Xendit's own `error_code`
        // and `message`, which we map to our public-facing taxonomy.
        $body = $response->json();
        $providerCode = is_array($body) ? (string) ($body['error_code'] ?? '') : '';
        $providerMsg = is_array($body) ? (string) ($body['message'] ?? '') : '';

        if ($providerCode === 'IP_NOT_ALLOWED' || str_contains(strtolower($providerMsg), 'ip allowlist')) {
            return [
                'success' => false,
                'error_code' => 'PAYMENT_GATEWAY_IP_NOT_ALLOWED',
                'message' => 'Xendit blocked this request. Add your current server IP to the Xendit IP allowlist (Settings → Developers → IP Allowlist) and try again.',
            ];
        }

        if ($response->status() === 401 || $providerCode === 'INVALID_API_KEY') {
            return [
                'success' => false,
                'error_code' => 'PAYMENT_GATEWAY_MISCONFIGURED',
                'message' => 'Xendit rejected the secret key. Double-check that you copied the correct value from the right account and mode (live/test).',
            ];
        }

        if ($response->status() === 429) {
            return [
                'success' => false,
                'error_code' => 'PAYMENT_GATEWAY_RATE_LIMITED',
                'message' => 'Too many requests to Xendit in a short window. Wait a minute and try again.',
            ];
        }

        if ($response->status() === 403) {
            return [
                'success' => false,
                'error_code' => 'PAYMENT_GATEWAY_FORBIDDEN',
                'message' => 'Xendit refused the request. The account may be suspended or this key may lack the required permissions.',
            ];
        }

        if ($response->status() >= 500) {
            return [
                'success' => false,
                'error_code' => 'PAYMENT_GATEWAY_SERVER_ERROR',
                'message' => 'Xendit reported a server error. Try again in a few minutes.',
            ];
        }

        return [
            'success' => false,
            'error_code' => 'PAYMENT_GATEWAY_UNAVAILABLE',
            'message' => 'Could not verify credentials. Xendit returned status '.$response->status().'.',
        ];
    }

    /**
     * Call Xendit `GET /payment_channels` and return the list of activated
     * channel codes (uppercase). Returns null on failure so callers can decide
     * whether to fall back or skip caching.
     *
     * @return array<int, string>|null
     */
    protected function fetchEnabledChannelCodes(): ?array
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->acceptJson()
                ->timeout(10)
                ->get('https://api.xendit.co/payment_channels');

            if (! $response->successful()) {
                Log::warning('Xendit /payment_channels returned non-2xx', [
                    'status' => $response->status(),
                    'gateway_id' => $this->gateway?->id,
                ]);

                return null;
            }

            $payload = $response->json();
            // Xendit has returned both a bare array and a `{ data: [...] }`
            // envelope for this endpoint across API versions - accept either.
            $channels = (is_array($payload) && is_array($payload['data'] ?? null))
                ? $payload['data']
                : $payload;
            if (! is_array($channels)) {
                return null;
            }

            $codes = [];
            foreach ($channels as $channel) {
                if (! is_array($channel) || ($channel['is_activated'] ?? false) !== true) {
                    continue;
                }
                $code = $channel['channel_code'] ?? null;
                if (is_string($code) && $code !== '') {
                    $codes[] = strtoupper($code);
                }
            }

            return array_values(array_unique($codes));
        } catch (\Throwable $e) {
            Log::warning('Xendit /payment_channels request failed', [
                'message' => $e->getMessage(),
                'gateway_id' => $this->gateway?->id,
            ]);

            return null;
        }
    }

    /**
     * Map raw channel codes to renderable logo entries, deduped by filename
     * so aliases like CIMB/CIMB_NIAGA do not render twice. Order follows
     * CHANNEL_LOGO_MAP (cards → banks → e-wallets → QR) for visual consistency.
     *
     * @param  array<int, string>  $codes
     * @return array<int, array{file: string, alt: string}>
     */
    protected function mapCodesToLogos(array $codes): array
    {
        $upper = array_flip(array_map('strtoupper', $codes));
        $logos = [];
        $seenFiles = [];

        foreach (self::CHANNEL_LOGO_MAP as $code => $logo) {
            if (! isset($upper[$code])) {
                continue;
            }
            if (isset($seenFiles[$logo['file']])) {
                continue;
            }
            $seenFiles[$logo['file']] = true;
            $logos[] = $logo;
        }

        return $logos;
    }

    /**
     * @return array<int, array{file: string, alt: string}>
     */
    protected function fallbackLogos(): array
    {
        return $this->mapCodesToLogos(self::FALLBACK_CHANNEL_CODES);
    }
}
