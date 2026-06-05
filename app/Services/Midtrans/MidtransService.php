<?php

namespace App\Services\Midtrans;

use App\Contracts\Payment\CreatesCheckout;
use App\Contracts\Payment\PaymentProvider;
use App\Contracts\Payment\ProvidesSettlements;
use App\Contracts\Payment\ProvidesTransactions;
use App\DTOs\Payment\SettlementSummary;
use App\DTOs\Payment\TransactionEntry;
use App\DTOs\Payment\TransactionPage;
use App\DTOs\Payment\TransactionQuery;
use App\Enums\PaymentCapability;
use App\Enums\ReservationStatus;
use App\Exceptions\Payment\PaymentProviderException;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Midtrans payment provider (Snap checkout + Core API status/refund).
 *
 * Mirrors XenditService's gateway-binding pattern: an instance is bound to a
 * ProjectPaymentGateway and reads its encrypted Server Key. Snap is the only
 * checkout integration, so this provider does NOT implement SupportsCheckoutMethods.
 *
 * Credentials live encrypted in project_payment_gateways:
 *   - secret_key  → Midtrans Server Key (API auth + SHA512 webhook signature)
 *   - public_key  → Midtrans Client Key (Snap.js; unused for the redirect flow)
 *
 * API hosts are chosen by the gateway mode, not the key prefix:
 *   - test → app.sandbox.midtrans.com (Snap) / api.sandbox.midtrans.com (Core)
 *   - live → app.midtrans.com / api.midtrans.com
 */
class MidtransService implements CreatesCheckout, PaymentProvider, ProvidesSettlements, ProvidesTransactions
{
    protected ?string $serverKey = null;

    protected ?string $clientKey = null;

    protected ?ProjectPaymentGateway $gateway = null;

    public function __construct(?ProjectPaymentGateway $gateway = null)
    {
        if ($gateway !== null) {
            $this->bindGateway($gateway);
        }
    }

    /**
     * Build a service instance bound to a specific project gateway. Resolves
     * through the container so test code can swap the implementation via
     * $this->app->instance(MidtransService::class, $mock); mocked instances are
     * detected by class identity and not rebound.
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
        if ($gateway->provider !== 'midtrans') {
            throw new \InvalidArgumentException("Gateway provider mismatch: expected midtrans, got {$gateway->provider}");
        }

        $this->gateway = $gateway;
        $this->serverKey = $gateway->secret_key;
        $this->clientKey = $gateway->public_key;
    }

    /**
     * Throw if the service is not bound to a project gateway. Called by every
     * operation that needs Midtrans credentials so we fail loudly instead of
     * silently leaking a request to the wrong account.
     */
    protected function requireGateway(): void
    {
        if ($this->gateway === null || $this->serverKey === null) {
            throw new \RuntimeException(
                'Midtrans service is not bound to a project payment gateway. '.
                'Use MidtransService::forGateway($gateway) before calling this method.'
            );
        }
    }

    public function gateway(): ?ProjectPaymentGateway
    {
        return $this->gateway;
    }

    public function provider(): string
    {
        return 'midtrans';
    }

    /**
     * Capabilities this provider exposes. Only Invoicing (Snap checkout) is live
     * today; Refunds, Transactions and Settlement are advertised as their
     * implementations land so the admin UI never surfaces a panel that 422s.
     * Balance is intentionally absent — Midtrans has no acquiring-balance REST
     * API (only the dashboard "Saldo" and the separate Iris payout product).
     *
     * @return array<int, PaymentCapability>
     */
    public function capabilities(): array
    {
        return [
            PaymentCapability::Invoicing,
            PaymentCapability::Refunds,
            PaymentCapability::Transactions,
            PaymentCapability::Settlement,
        ];
    }

    public function supports(PaymentCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    /**
     * Create a Midtrans Snap transaction for a reservation and return the hosted
     * checkout redirect URL.
     *
     * `order_id` is the reservation number so the notification webhook can
     * resolve the reservation by it. `gross_amount` must be a whole-rupiah
     * integer (IDR has no minor units). `item_details` is intentionally omitted:
     * Midtrans requires the item sum to equal gross_amount, and the Xendit
     * invoice flow does not itemise either.
     *
     * @return array{reference: string, payment_url: string, checkout_method: string}
     */
    public function createCheckout(
        Reservation $reservation,
        ?string $successUrl = null,
        ?string $failureUrl = null,
    ): array {
        $this->requireGateway();

        $payload = [
            'transaction_details' => [
                'order_id' => $reservation->reservation_number,
                'gross_amount' => (int) round((float) $reservation->total_amount),
            ],
            'customer_details' => array_filter([
                'first_name' => $reservation->guest_name ?: 'Guest',
                'email' => $reservation->guest_email ?: null,
                'phone' => $reservation->guest_phone ?: null,
            ]),
            'credit_card' => ['secure' => true],
            'expiry' => [
                'unit' => 'second',
                'duration' => (int) config('midtrans.expiry_duration', 86400),
            ],
        ];

        // Snap redirects here after the customer finishes (or abandons) payment.
        if ($successUrl !== null) {
            $payload['callbacks'] = ['finish' => $successUrl];
        }

        // Per-transaction notification routing. When one Midtrans account is
        // shared across systems/projects, the account-wide dashboard URL cannot
        // serve all of them — X-Override-Notification routes THIS transaction's
        // HTTP notification to the gateway's own URL without touching the shared
        // dashboard config. Set per gateway via config.notification_override_url.
        $headers = [];
        $override = $this->gateway->config['notification_override_url'] ?? null;
        if (is_string($override) && $override !== '') {
            $headers['X-Override-Notification'] = $override;
        }

        try {
            $response = Http::withBasicAuth((string) $this->serverKey, '')
                ->withHeaders($headers)
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->connectTimeout(5)
                ->post($this->baseSnapUrl().'/snap/v1/transactions', $payload);
        } catch (\Throwable $e) {
            Log::warning('Midtrans Snap request failed (transport)', [
                'gateway_id' => $this->gateway?->id,
                'message' => $e->getMessage(),
            ]);

            throw new PaymentProviderException(
                'Could not reach Midtrans to create the payment. Try again in a moment.',
                'PAYMENT_GATEWAY_UNAVAILABLE',
                503,
                $e,
            );
        }

        if (! $response->successful()) {
            throw $this->mapApiError($response, 'snap');
        }

        $this->touchGatewayUsage();

        $token = (string) ($response->json('token') ?? '');
        $redirectUrl = (string) ($response->json('redirect_url') ?? '');

        if ($token === '' || $redirectUrl === '') {
            throw new PaymentProviderException(
                'Midtrans created the transaction but returned no Snap redirect URL.',
                'PAYMENT_GATEWAY_UNAVAILABLE',
                502,
            );
        }

        return [
            'reference' => $token,
            'payment_url' => $redirectUrl,
            'checkout_method' => 'snap',
        ];
    }

    /**
     * Fetch a transaction's current status from the Core API by order id (the
     * reservation number). Used to verify/backfill state and for reconciliation.
     * Returns null on any failure so callers can degrade gracefully.
     *
     * @return array<string, mixed>|null
     */
    public function fetchTransactionStatus(string $orderId): ?array
    {
        $this->requireGateway();

        try {
            $response = Http::withBasicAuth((string) $this->serverKey, '')
                ->acceptJson()
                ->timeout(10)
                ->connectTimeout(5)
                ->get($this->baseApiUrl().'/v2/'.urlencode($orderId).'/status');

            if (! $response->successful()) {
                Log::info('Midtrans fetchTransactionStatus non-2xx', [
                    'status' => $response->status(),
                    'order_id' => $orderId,
                ]);

                return null;
            }

            $payload = $response->json();

            return is_array($payload) ? $payload : null;
        } catch (\Throwable $e) {
            Log::warning('Midtrans fetchTransactionStatus failed', [
                'message' => $e->getMessage(),
                'order_id' => $orderId,
            ]);

            return null;
        }
    }

    /**
     * Refund a settled transaction via the Core API (online/direct). Returns the
     * Midtrans refund key for storage/idempotency.
     *
     * Online refund only works for channels that support it (GoPay, QRIS,
     * ShopeePay, credit card). Most bank-transfer VAs cannot be refunded via API
     * (funds must be returned to the customer's bank manually); Midtrans then
     * responds with a 4xx body status_code, surfaced here as an unrecoverable
     * PaymentProviderException (httpStatus 422) so the caller routes to a manual
     * refund instead of retrying.
     */
    public function refund(string $orderId, float $amount, string $reason): string
    {
        $this->requireGateway();

        // Deterministic refund_key = idempotency: re-running never double-refunds.
        $refundKey = 'pmone-rfd-'.$orderId;

        try {
            $response = Http::withBasicAuth((string) $this->serverKey, '')
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->connectTimeout(5)
                ->post($this->baseApiUrl().'/v2/'.urlencode($orderId).'/refund/online/direct', [
                    'refund_key' => $refundKey,
                    'amount' => (int) round($amount),
                    'reason' => $reason,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Midtrans refund request failed (transport)', [
                'gateway_id' => $this->gateway?->id,
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);

            throw new PaymentProviderException(
                'Could not reach Midtrans to process the refund.',
                'PAYMENT_GATEWAY_UNAVAILABLE',
                503,
                $e,
            );
        }

        $body = is_array($response->json()) ? $response->json() : [];
        // Midtrans returns HTTP 200 with the real outcome in body.status_code.
        $statusCode = (string) ($body['status_code'] ?? $response->status());

        if (in_array($statusCode, ['200', '201'], true)) {
            $this->touchGatewayUsage();

            return (string) ($body['refund_key'] ?? $refundKey);
        }

        // 4xx (412 channel/balance, 414 amount, ...) = refund cannot be done
        // online → unrecoverable; map to 422 so the job falls back to manual.
        $message = (string) ($body['status_message'] ?? 'Midtrans refund failed.');
        $unrecoverable = str_starts_with($statusCode, '4');

        throw new PaymentProviderException(
            'Midtrans refund failed ('.$statusCode.'): '.$message,
            $unrecoverable ? 'PAYMENT_GATEWAY_REFUND_UNSUPPORTED' : 'PAYMENT_GATEWAY_UNAVAILABLE',
            $unrecoverable ? 422 : 502,
        );
    }

    /**
     * Verify a Midtrans HTTP notification signature.
     *
     * Midtrans signs every notification with
     *   sha512(order_id + status_code + gross_amount + ServerKey)
     * and sends it as `signature_key`. The `gross_amount` MUST be taken verbatim
     * from the payload (e.g. the string "10000.00") — casting/reformatting it
     * breaks the hash for every notification.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifySignature(array $payload): bool
    {
        // Fail closed when unbound: a signature check without a Server Key can
        // never legitimately pass, and throwing here would turn a spoofed
        // webhook into a 500 instead of a clean rejection.
        if (! $this->serverKey) {
            return false;
        }

        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signature = (string) ($payload['signature_key'] ?? '');

        if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signature === '') {
            return false;
        }

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$this->serverKey);

        return hash_equals($expected, $signature);
    }

    /**
     * Verify provider credentials before persisting them. Issues a single
     * authenticated read-only call: GET /v2/{dummy-order}/status. A valid Server
     * Key with a non-existent order returns 404 (auth passed); a bad key returns
     * 401. Returns the same {success, error_code?, message, ...} shape as
     * XenditService::testCredentials so the admin UI can render it identically.
     *
     * @return array{success: bool, error_code?: string, message: string}
     */
    public function testCredentials(string $serverKey, string $mode = 'test'): array
    {
        if (trim($serverKey) === '') {
            return [
                'success' => false,
                'error_code' => 'PAYMENT_GATEWAY_MISCONFIGURED',
                'message' => 'Server key is empty.',
            ];
        }

        $apiBase = $mode === 'live'
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->acceptJson()
                ->timeout(10)
                ->connectTimeout(5)
                ->get($apiBase.'/v2/pmone-ping-'.Str::random(12).'/status');
        } catch (\Throwable $e) {
            Log::warning('Midtrans testCredentials request failed (transport)', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_code' => 'PAYMENT_GATEWAY_UNAVAILABLE',
                'message' => 'Could not reach Midtrans to verify the key. Check your network and try again.',
            ];
        }

        // 404 "Transaction doesn't exist" = auth succeeded, order simply not
        // found → the key is valid. 200 would also mean valid.
        if ($response->status() === 404 || $response->successful()) {
            return [
                'success' => true,
                'message' => 'Credentials verified. Connection to Midtrans OK.',
            ];
        }

        if ($response->status() === 401) {
            return [
                'success' => false,
                'error_code' => 'PAYMENT_GATEWAY_MISCONFIGURED',
                'message' => 'Midtrans rejected the Server Key. Double-check that you copied the correct value for the right environment (Sandbox vs Production).',
            ];
        }

        if ($response->status() === 429) {
            return [
                'success' => false,
                'error_code' => 'PAYMENT_GATEWAY_RATE_LIMITED',
                'message' => 'Too many requests to Midtrans in a short window. Wait a minute and try again.',
            ];
        }

        if ($response->status() >= 500) {
            return [
                'success' => false,
                'error_code' => 'PAYMENT_GATEWAY_SERVER_ERROR',
                'message' => 'Midtrans reported a server error. Try again in a few minutes.',
            ];
        }

        return [
            'success' => false,
            'error_code' => 'PAYMENT_GATEWAY_UNAVAILABLE',
            'message' => 'Could not verify credentials. Midtrans returned status '.$response->status().'.',
        ];
    }

    /**
     * Normalise a Midtrans notification into the payment-channel vocabulary the
     * rest of the app keys on (receipt logos, reconciliation). Returns null when
     * the channel cannot be resolved, so receipts degrade to a generic label
     * rather than failing.
     *
     * @param  array<string, mixed>  $payload
     */
    public function resolveChannel(array $payload): ?string
    {
        $type = strtolower((string) ($payload['payment_type'] ?? ''));

        return match ($type) {
            'bank_transfer' => $this->resolveBankTransferChannel($payload),
            'echannel' => 'MANDIRI',
            'qris' => 'QRIS',
            'gopay' => 'GOPAY',
            'shopeepay' => 'SHOPEEPAY',
            'credit_card' => $this->resolveCardNetwork($payload),
            'cstore' => strtoupper((string) ($payload['store'] ?? 'CSTORE')) ?: null,
            default => $type !== '' ? strtoupper($type) : null,
        };
    }

    /**
     * Bank/VA number a guest should transfer to, when present in the payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function resolveDestination(array $payload): ?string
    {
        $vaNumbers = $payload['va_numbers'] ?? null;
        if (is_array($vaNumbers) && isset($vaNumbers[0]['va_number'])) {
            return (string) $vaNumbers[0]['va_number'];
        }

        if (! empty($payload['permata_va_number'])) {
            return (string) $payload['permata_va_number'];
        }

        if (! empty($payload['bill_key'])) {
            return (string) $payload['bill_key'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveBankTransferChannel(array $payload): ?string
    {
        $vaNumbers = $payload['va_numbers'] ?? null;
        if (is_array($vaNumbers) && isset($vaNumbers[0]['bank'])) {
            return strtoupper((string) $vaNumbers[0]['bank']);
        }

        if (! empty($payload['permata_va_number'])) {
            return 'PERMATA';
        }

        return 'BANK_TRANSFER';
    }

    /**
     * Map a credit-card transaction to its card network via the masked PAN's
     * BIN (Midtrans sends `masked_card` like "481111-1114"). Lets the receipt
     * render the Visa/Mastercard/JCB/Amex logo instead of a generic card badge,
     * matching how Xendit reports the network. Falls back to a generic card
     * code when the network can't be determined.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveCardNetwork(array $payload): string
    {
        $masked = preg_replace('/\D/', '', (string) ($payload['masked_card'] ?? '')) ?? '';

        return match (true) {
            str_starts_with($masked, '34'), str_starts_with($masked, '37') => 'AMEX',
            str_starts_with($masked, '35') => 'JCB',
            str_starts_with($masked, '4') => 'VISA',
            str_starts_with($masked, '5'), str_starts_with($masked, '2') => 'MASTERCARD',
            default => 'CREDIT_CARD',
        };
    }

    /**
     * Payment-method logos for the invoice footer's "we accept" strip.
     *
     * Midtrans exposes no "list enabled channels" endpoint the way Xendit does
     * (the active set lives in the merchant's Snap preferences), so this is a
     * curated list of the common Midtrans Snap methods, mirroring the
     * {file, alt} shape XenditService::getEnabledPaymentChannels() returns.
     *
     * @return array<int, array{file: string, alt: string}>
     */
    public function getEnabledPaymentChannels(): array
    {
        return [
            ['file' => 'bca.svg', 'alt' => 'BCA'],
            ['file' => 'bni.svg', 'alt' => 'BNI'],
            ['file' => 'bri.svg', 'alt' => 'BRI'],
            ['file' => 'mandiri.svg', 'alt' => 'Mandiri'],
            ['file' => 'permata-bank.svg', 'alt' => 'Permata'],
            ['file' => 'cimb-niaga.svg', 'alt' => 'CIMB Niaga'],
            ['file' => 'qris.svg', 'alt' => 'QRIS'],
            ['file' => 'gopay.svg', 'alt' => 'GoPay'],
            ['file' => 'shopeepay.svg', 'alt' => 'ShopeePay'],
            ['file' => 'visa.svg', 'alt' => 'Visa'],
            ['file' => 'mastercard.svg', 'alt' => 'Mastercard'],
        ];
    }

    /**
     * List this gateway's Midtrans transactions, newest first, cursor-paginated.
     *
     * Midtrans has no list-transactions API, so this is sourced from PM One's own
     * reservation records for this gateway (the local ledger of what was charged
     * via Midtrans). Powers the admin Transactions panel/export and feeds the
     * reconciliation walk.
     */
    public function listTransactions(TransactionQuery $query): TransactionPage
    {
        $this->requireGateway();

        $builder = Reservation::query()
            ->where('payment_gateway_id', $this->gateway->id)
            ->orderByDesc('id');

        if ($query->status === 'success') {
            $builder->whereIn('status', [ReservationStatus::Paid->value, ReservationStatus::VoucherSent->value]);
        } elseif ($query->status === 'failed') {
            $builder->whereIn('status', [ReservationStatus::Expired->value, ReservationStatus::Cancelled->value]);
        }

        if ($query->dateFrom) {
            $builder->whereDate('created_at', '>=', $query->dateFrom);
        }
        if ($query->dateTo) {
            $builder->whereDate('created_at', '<=', $query->dateTo);
        }

        if ($query->afterId !== null && $query->afterId !== '') {
            $builder->where('id', '<', (int) $query->afterId);
        }

        $limit = max(1, min(100, $query->limit));
        $rows = $builder->limit($limit + 1)->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $entries = $rows->map(fn (Reservation $r): TransactionEntry => $this->reservationToTransactionEntry($r))->all();
        $nextCursor = $hasMore && $rows->isNotEmpty() ? (string) $rows->last()->id : null;

        return new TransactionPage($entries, $hasMore, $nextCursor);
    }

    protected function reservationToTransactionEntry(Reservation $reservation): TransactionEntry
    {
        $paid = $reservation->status->isPaid();

        return new TransactionEntry(
            id: (string) ($reservation->xendit_payment_id ?: $reservation->reservation_number),
            type: 'payment',
            status: $paid ? 'success' : ($reservation->status->isFinal() ? 'failed' : 'pending'),
            channelCode: $reservation->payment_channel,
            channelCategory: null,
            amount: (float) $reservation->total_amount,
            netAmount: null,
            currency: 'IDR',
            reference: $reservation->reservation_number,
            createdAt: $reservation->paid_at ?? $reservation->created_at,
            settlementStatus: $paid ? 'SETTLED' : null,
            estimatedSettlementTime: null,
        );
    }

    /**
     * Settlement snapshot derived from local Midtrans reservations (Midtrans has
     * no settlement-summary API). Midtrans settles to the merchant bank roughly
     * T+1, so payments captured today are treated as pending settlement and
     * earlier ones as settled.
     */
    public function getSettlementSummary(string $dateFrom, string $dateTo): SettlementSummary
    {
        $this->requireGateway();

        $paid = Reservation::query()
            ->where('payment_gateway_id', $this->gateway->id)
            ->whereIn('status', [ReservationStatus::Paid->value, ReservationStatus::VoucherSent->value])
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo)
            ->get(['paid_at', 'total_amount']);

        $today = now()->toDateString();
        $pending = $paid->filter(fn (Reservation $r): bool => optional($r->paid_at)->toDateString() === $today);
        $settled = $paid->reject(fn (Reservation $r): bool => optional($r->paid_at)->toDateString() === $today);

        return new SettlementSummary(
            pendingAmount: (float) $pending->sum('total_amount'),
            pendingCount: $pending->count(),
            settledAmount: (float) $settled->sum('total_amount'),
            settledCount: $settled->count(),
            currency: 'IDR',
            upcoming: [],
            truncated: false,
        );
    }

    protected function baseSnapUrl(): string
    {
        return $this->gateway?->mode === 'live'
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
    }

    protected function baseApiUrl(): string
    {
        return $this->gateway?->mode === 'live'
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }

    /**
     * Map a non-2xx Midtrans API response to a PaymentProviderException with a
     * stable error code. Midtrans returns {status_code, status_message} or
     * {status_code, status_messages: [...]}.
     */
    protected function mapApiError(Response $response, string $context): PaymentProviderException
    {
        $body = $response->json();
        $providerMsg = is_array($body)
            ? (string) ($body['status_message'] ?? (is_array($body['status_messages'] ?? null) ? implode('; ', $body['status_messages']) : ''))
            : '';
        $status = $response->status();

        Log::warning('Midtrans API returned non-2xx', [
            'status' => $status,
            'context' => $context,
            'gateway_id' => $this->gateway?->id,
            'message' => $providerMsg,
        ]);

        if ($status === 401) {
            return new PaymentProviderException(
                'Midtrans rejected the Server Key for this gateway. Check the credentials in payment gateway settings.',
                'PAYMENT_GATEWAY_MISCONFIGURED',
                502,
            );
        }

        if ($status === 429) {
            return new PaymentProviderException(
                'Too many requests to Midtrans. Wait a minute and try again.',
                'PAYMENT_GATEWAY_RATE_LIMITED',
                429,
            );
        }

        if ($status === 403) {
            return new PaymentProviderException(
                'Midtrans refused the request. The account may be suspended or the key may lack permission.',
                'PAYMENT_GATEWAY_FORBIDDEN',
                502,
            );
        }

        if ($status >= 500) {
            return new PaymentProviderException(
                'Midtrans reported a server error. Try again in a few minutes.',
                'PAYMENT_GATEWAY_SERVER_ERROR',
                502,
            );
        }

        return new PaymentProviderException(
            'Midtrans could not complete the request (status '.$status.'). '.$providerMsg,
            'PAYMENT_GATEWAY_UNAVAILABLE',
            502,
        );
    }

    protected function touchGatewayUsage(): void
    {
        if ($this->gateway) {
            $this->gateway->forceFill(['last_used_at' => now()])->saveQuietly();
        }
    }
}
