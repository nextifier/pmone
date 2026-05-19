<?php

namespace App\Services\Xendit;

use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;
use Xendit\Refund\CreateRefund;
use Xendit\Refund\RefundApi;

class XenditService
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
     * /public/images/payment-methods/ plus a human-readable label.
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
     * Refund a Xendit invoice (partial or full).
     */
    public function refundInvoice(string $invoiceId, float $amount, string $reason = 'CANCELLATION'): string
    {
        $this->requireGateway();
        $api = new RefundApi($this->httpClient());

        $normalizedReason = strtoupper($reason);
        if (! in_array($normalizedReason, self::REFUND_REASONS, true)) {
            $normalizedReason = 'CANCELLATION';
        }

        $payload = new CreateRefund([
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'reason' => $normalizedReason,
        ]);

        $refund = $api->createRefund(null, null, $payload);

        $this->touchGatewayUsage();

        return $refund->getId();
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

    protected function resolveCurrency(): string
    {
        return $this->gateway?->config['currency']
            ?? config('xendit.currency', 'IDR');
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

        $cached = Cache::remember(
            $cacheKey,
            self::PAYMENT_CHANNELS_CACHE_TTL,
            fn () => $this->fetchEnabledChannelCodes(),
        );

        if ($cached === null) {
            Cache::forget($cacheKey);

            return $this->fallbackLogos();
        }

        return $this->mapCodesToLogos($cached);
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

            $channels = $response->json();
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
