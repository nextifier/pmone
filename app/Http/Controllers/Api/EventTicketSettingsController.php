<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventTicketSettings\UpdateEventTicketSettingsRequest;
use App\Models\Event;
use App\Services\Xendit\XenditService;
use App\Support\PaymentChannels;
use Illuminate\Http\JsonResponse;
use Spatie\ResponseCache\Facades\ResponseCache;

class EventTicketSettingsController extends Controller
{
    public function show(Event $event): JsonResponse
    {
        return response()->json(['data' => $this->present($event)]);
    }

    public function update(UpdateEventTicketSettingsRequest $request, Event $event): JsonResponse
    {
        $validated = $request->validated();

        $columnUpdates = array_intersect_key($validated, array_flip([
            'tickets_enabled', 'business_matching_enabled', 'allow_cross_day', 'timezone', 'waitlist_mode',
        ]));

        if (! empty($columnUpdates)) {
            $event->fill($columnUpdates);
        }

        $settingKeys = ['default_min_quantity', 'default_max_quantity', 'default_stock', 'default_print_on_redeem', 'login_button_enabled', 'terms', 'allowed_payment_channels'];
        $settingUpdates = array_intersect_key($validated, array_flip($settingKeys));

        if (! empty($settingUpdates)) {
            $settings = $event->settings ?? [];
            $settings['tickets'] = array_merge($settings['tickets'] ?? [], $settingUpdates);
            $event->settings = $settings;
        }

        $event->save();

        // The Event model busts ['events','faqs','brands','gallery'] but NOT
        // 'tickets', which fronts the public tickets + custom-fields endpoints
        // affected by these settings (BM toggle, purchase terms).
        ResponseCache::clear(['tickets']);

        return response()->json([
            'message' => 'Ticket settings updated successfully',
            'data' => $this->present($event->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Event $event): array
    {
        $ticketDefaults = $event->settings['tickets'] ?? [];

        return [
            'tickets_enabled' => (bool) $event->tickets_enabled,
            'business_matching_enabled' => (bool) $event->business_matching_enabled,
            'allow_cross_day' => (bool) $event->allow_cross_day,
            'timezone' => $event->timezone,
            'waitlist_mode' => $event->waitlist_mode?->value,
            'default_min_quantity' => $ticketDefaults['default_min_quantity'] ?? null,
            'default_max_quantity' => $ticketDefaults['default_max_quantity'] ?? null,
            'default_stock' => $ticketDefaults['default_stock'] ?? null,
            'default_print_on_redeem' => (bool) ($ticketDefaults['default_print_on_redeem'] ?? false),
            // Show the one-click "Go to dashboard" sign-in on the e-ticket page
            // and email (default on). Holders can open their account in one tap.
            'login_button_enabled' => (bool) ($ticketDefaults['login_button_enabled'] ?? true),
            // Staff-managed purchase terms as {locale: html} for the editor's
            // per-language tabs (empty object when never set).
            'terms' => is_array($ticketDefaults['terms'] ?? null) ? $ticketDefaults['terms'] : (object) [],
            // Canonical payment-channel allowlist; empty = accept all channels.
            'allowed_payment_channels' => array_values(
                is_array($ticketDefaults['allowed_payment_channels'] ?? null) ? $ticketDefaults['allowed_payment_channels'] : []
            ),
        ];
    }

    /**
     * Channels the admin can pick from, scoped to the project's active gateway:
     *  - Xendit  -> canonical catalog intersected with the account's live channels.
     *  - Midtrans -> the Midtrans-supported subset (no live channel-list API).
     *  - none     -> full catalog (validation still guards what gets saved).
     */
    public function paymentChannels(Event $event): JsonResponse
    {
        $gateway = $event->project?->activePaymentGateway();

        if ($gateway === null) {
            return response()->json([
                'data' => PaymentChannels::catalog(),
                'meta' => ['gateway_configured' => false],
            ]);
        }

        if ($gateway->provider === 'midtrans') {
            $channels = PaymentChannels::catalogForCodes(PaymentChannels::midtransSupportedCodes());
        } else {
            $enabled = XenditService::forGateway($gateway)->enabledChannelCodes();
            $channels = $enabled === [] ? PaymentChannels::catalog() : PaymentChannels::catalogForEnabled($enabled);
        }

        return response()->json([
            'data' => $channels,
            'meta' => ['gateway_configured' => true, 'provider' => $gateway->provider],
        ]);
    }
}
