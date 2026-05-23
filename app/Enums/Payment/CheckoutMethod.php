<?php

namespace App\Enums\Payment;

/**
 * Which checkout integration a payment gateway uses to collect a payment.
 *
 * The cases map to Xendit's three integration styles (see demo-store.xendit.co).
 * The concept is intentionally provider-agnostic: a future provider (Midtrans)
 * either reuses these cases or adds its own, and reports which ones it supports
 * through the App\Contracts\Payment\SupportsCheckoutMethods interface.
 */
enum CheckoutMethod: string
{
    /**
     * Xendit Sessions API in PAYMENT_LINK mode — Xendit-hosted checkout page.
     * Fastest, least effort, the modern default.
     */
    case SessionsPaymentLink = 'sessions_payment_link';

    /**
     * Xendit Sessions API in COMPONENTS mode — payment UI embedded in our own
     * checkout page. Not implemented yet; surfaced in the UI as "coming soon".
     */
    case SessionsComponents = 'sessions_components';

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
            self::SessionsPaymentLink => 'Sessions - Payment Link',
            self::SessionsComponents => 'Sessions - Components',
            self::PaymentLinkLegacy => 'Payment Link (Legacy)',
        };
    }

    /**
     * Short explanation shown beneath the option in the admin UI.
     */
    public function description(): string
    {
        return match ($this) {
            self::SessionsPaymentLink => 'Provider-hosted checkout. Fastest and least effort to integrate.',
            self::SessionsComponents => 'Embed the payment UI in your own checkout page.',
            self::PaymentLinkLegacy => 'The old checkout page. Not recommended for new gateways.',
        };
    }

    /**
     * Whether this method is fully implemented and can be selected/saved.
     * Visible-but-disabled options return false.
     */
    public function available(): bool
    {
        return match ($this) {
            self::SessionsPaymentLink, self::SessionsComponents, self::PaymentLinkLegacy => true,
        };
    }

    /**
     * Default method applied to newly created gateways.
     */
    public static function default(): self
    {
        return self::SessionsPaymentLink;
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
