<?php

namespace App\Enums\Payment;

/**
 * Which checkout integration a payment gateway uses to collect a payment.
 *
 * The cases map to Xendit's two hosted-checkout integration styles. The
 * concept is intentionally provider-agnostic: a future provider (Midtrans)
 * either reuses these cases or adds its own, and reports which ones it
 * supports through the App\Contracts\Payment\SupportsCheckoutMethods interface.
 */
enum CheckoutMethod: string
{
    /**
     * Xendit Sessions API in PAYMENT_LINK mode — Xendit-hosted checkout page.
     * Fastest, least effort, the modern default.
     */
    case PaymentLinkSessions = 'payment_link_sessions';

    /**
     * Xendit Invoices API — the legacy hosted checkout. Kept for backwards
     * compatibility; not recommended for new gateways.
     */
    case PaymentLinkLegacy = 'payment_link_legacy';

    /**
     * Human-readable name shown in the admin UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::PaymentLinkSessions => 'Payment Link - Sessions',
            self::PaymentLinkLegacy => 'Payment Link - Legacy',
        };
    }

    /**
     * Short explanation shown beneath the option in the admin UI.
     */
    public function description(): string
    {
        return match ($this) {
            self::PaymentLinkSessions => 'Provider-hosted checkout via the Sessions API. Fastest and least effort to integrate.',
            self::PaymentLinkLegacy => 'The legacy Invoices API checkout page. Kept for backwards compatibility; not recommended for new gateways.',
        };
    }

    /**
     * Whether this method is fully implemented and can be selected/saved.
     * Visible-but-disabled options return false.
     */
    public function available(): bool
    {
        return match ($this) {
            self::PaymentLinkSessions, self::PaymentLinkLegacy => true,
        };
    }

    /**
     * Default method applied to newly created gateways.
     */
    public static function default(): self
    {
        return self::PaymentLinkSessions;
    }

    /**
     * Every case's backing value.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Backing values of methods that can actually be selected/saved — used by
     * validation so a "coming soon" method is rejected even though it renders
     * in the dropdown.
     *
     * @return array<int, string>
     */
    public static function availableValues(): array
    {
        return array_map(
            fn (self $case): string => $case->value,
            array_filter(self::cases(), fn (self $case): bool => $case->available()),
        );
    }
}
