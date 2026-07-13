<?php

use App\Enums\EmailEventType;
use App\Models\EmailEvent;
use App\Models\EmailSuppression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

const RESEND_WEBHOOK_SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';

/**
 * Reproduces the Svix scheme resend-php verifies against: HMAC-SHA256 over
 * "{id}.{timestamp}.{body}" with the base64-decoded secret, base64-encoded.
 */
function resendSignature(string $id, int $timestamp, string $body): string
{
    $key = base64_decode(substr(RESEND_WEBHOOK_SECRET, strlen('whsec_')));
    $digest = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key, true));

    return "v1,{$digest}";
}

/**
 * A self-contained payload builder, so this file runs in isolation without the
 * recorder test's helper.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function resendPayload(string $type, array $data = []): array
{
    return [
        'type' => $type,
        'created_at' => '2026-07-13T10:00:00.000Z',
        'data' => array_merge([
            'email_id' => 're_webhook_test',
            'created_at' => '2026-07-13T10:00:00.000Z',
            'from' => 'noreply@pmone.id',
            'to' => ['visitor@example.com'],
            'subject' => 'Your ticket',
        ], $data),
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function postResendWebhook(array $payload, ?string $signature = null, ?int $timestamp = null): TestResponse
{
    $body = json_encode($payload);
    $timestamp ??= time();
    $id = 'msg_2XYZ';

    return test()->call(
        'POST',
        '/api/webhooks/resend',
        [], [], [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SVIX_ID' => $id,
            'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
            'HTTP_SVIX_SIGNATURE' => $signature ?? resendSignature($id, $timestamp, $body),
        ],
        $body,
    );
}

beforeEach(function () {
    config(['resend.webhook.secret' => RESEND_WEBHOOK_SECRET]);
});

it('refuses the webhook when no signing secret is configured', function () {
    config(['resend.webhook.secret' => null]);

    postResendWebhook(resendPayload('email.delivered'))->assertStatus(503);

    expect(EmailEvent::count())->toBe(0);
});

it('rejects a payload with an invalid signature', function () {
    postResendWebhook(resendPayload('email.delivered'), signature: 'v1,not-a-real-signature')
        ->assertStatus(403);

    expect(EmailEvent::count())->toBe(0);
});

it('rejects a payload whose timestamp is outside the tolerance', function () {
    postResendWebhook(resendPayload('email.delivered'), timestamp: time() - 10_000)
        ->assertStatus(403);

    expect(EmailEvent::count())->toBe(0);
});

it('records a delivery event from a correctly signed webhook', function () {
    postResendWebhook(resendPayload('email.delivered'))
        ->assertOk()
        ->assertJson(['message' => 'Recorded delivery']);

    expect(EmailEvent::query()->where('type', EmailEventType::Delivery->value)->exists())->toBeTrue();
});

it('suppresses a hard bounce delivered through the webhook', function () {
    postResendWebhook(resendPayload('email.bounced', [
        'to' => ['dead@example.com'],
        'bounce' => ['type' => 'Permanent', 'message' => '550 user unknown'],
    ]))->assertOk();

    expect(EmailSuppression::isSuppressed('dead@example.com'))->toBeTrue();
});

it('acknowledges an untracked event type without recording it', function () {
    postResendWebhook(resendPayload('email.scheduled'))
        ->assertOk()
        ->assertJson(['message' => 'Ignored event type']);

    expect(EmailEvent::count())->toBe(0);
});
