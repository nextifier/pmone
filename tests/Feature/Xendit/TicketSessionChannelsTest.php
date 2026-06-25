<?php

use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\TicketOrder;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeTicketSession(): void
{
    Http::fake([
        'api.xendit.co/sessions' => Http::response([
            'payment_session_id' => 'ps-tix',
            'payment_link_url' => 'https://checkout.xendit.co/web/ps-tix',
            'status' => 'ACTIVE',
        ], 201),
    ]);
}

/** A ticket order whose event restricts checkout to the given canonical channels. */
function ticketOrderRestrictedTo(?array $channels): TicketOrder
{
    $project = Project::factory()->create();
    $event = Event::factory()->withoutPaymentGateway()->create([
        'project_id' => $project->id,
        'tickets_enabled' => true,
        'settings' => $channels === null ? [] : ['tickets' => ['allowed_payment_channels' => $channels]],
    ]);

    return TicketOrder::factory()->create(['event_id' => $event->id, 'total' => 150000]);
}

test('createSession restricts allowed_payment_channels from the ticket event allowlist', function () {
    fakeTicketSession();
    $order = ticketOrderRestrictedTo(['CREDIT_CARD', 'BCA']);
    $gateway = ProjectPaymentGateway::factory()->sessionsPaymentLink()->create();

    XenditService::forGateway($gateway)->createSession($order, 'https://app.test/s', 'https://app.test/c');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://api.xendit.co/sessions'
            && ($body['allowed_payment_channels'] ?? null) === ['CARDS', 'BCA_VIRTUAL_ACCOUNT']
            && str_starts_with((string) $body['reference_id'], 'TIX-');
    });
});

test('createSession omits allowed_payment_channels when the event has no restriction', function () {
    fakeTicketSession();
    $order = ticketOrderRestrictedTo(null);
    $gateway = ProjectPaymentGateway::factory()->sessionsPaymentLink()->create();

    XenditService::forGateway($gateway)->createSession($order, 'https://app.test/s', 'https://app.test/c');

    Http::assertSent(fn ($request) => ! array_key_exists('allowed_payment_channels', $request->data()));
});

test('createCheckout on a sessions gateway carries the ticket allowlist', function () {
    fakeTicketSession();
    $order = ticketOrderRestrictedTo(['BCA']);
    $gateway = ProjectPaymentGateway::factory()->sessionsPaymentLink()->create();

    $result = XenditService::forGateway($gateway)->createCheckout($order, 'https://app.test/s', 'https://app.test/c');

    expect($result['reference'])->toBe('ps-tix');
    Http::assertSent(fn ($request) => ($request->data()['allowed_payment_channels'] ?? null) === ['BCA_VIRTUAL_ACCOUNT']);
});

test('enabledChannelCodes returns activated channel codes from the gateway', function () {
    Http::fake([
        'api.xendit.co/payment_channels' => Http::response([
            ['channel_code' => 'BCA', 'is_activated' => true],
            ['channel_code' => 'OVO', 'is_activated' => true],
            ['channel_code' => 'DANA', 'is_activated' => false],
        ], 200),
    ]);

    $gateway = ProjectPaymentGateway::factory()->create();
    $codes = XenditService::forGateway($gateway)->enabledChannelCodes();

    expect($codes)->toEqualCanonicalizing(['BCA', 'OVO']);
});
