<?php

use App\Enums\EmailSuppressionReason;
use App\Models\EmailSuppression;
use Aws\Sns\Exception\InvalidSnsMessageException;
use Aws\Sns\MessageValidator as SnsMessageValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

const TOPIC_ARN = 'arn:aws:sns:ap-southeast-1:819109475615:pmone-ses-events';

/**
 * The vendor validator's own signature checking is exercised upstream. Here it
 * is stubbed so these tests cover our topic allowlist, event parsing and
 * suppression writes instead.
 */
function acceptSignature(): void
{
    test()->instance(
        SnsMessageValidator::class,
        Mockery::mock(SnsMessageValidator::class)->shouldReceive('validate')->andReturnNull()->getMock(),
    );
}

function rejectSignature(): void
{
    test()->instance(
        SnsMessageValidator::class,
        Mockery::mock(SnsMessageValidator::class)
            ->shouldReceive('validate')
            ->andThrow(new InvalidSnsMessageException('Bad signature'))
            ->getMock(),
    );
}

/**
 * @param  array<string, mixed>  $overrides
 */
function snsEnvelope(array $overrides = []): array
{
    return array_merge([
        'Type' => 'Notification',
        'MessageId' => 'a1b2c3',
        'TopicArn' => TOPIC_ARN,
        'Message' => '{}',
        'Timestamp' => '2026-07-09T10:00:00.000Z',
        'SignatureVersion' => '1',
        'Signature' => 'signature-placeholder',
        'SigningCertURL' => 'https://sns.ap-southeast-1.amazonaws.com/cert.pem',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $envelope
 */
function postSns(array $envelope): TestResponse
{
    return test()->call(
        'POST',
        route('webhooks.ses'),
        server: ['CONTENT_TYPE' => 'text/plain'],
        content: json_encode($envelope),
    );
}

beforeEach(function () {
    config(['services.ses_sns.topic_arn' => TOPIC_ARN]);
    Http::preventStrayRequests();
});

it('refuses the payload when no topic arn is configured', function () {
    config(['services.ses_sns.topic_arn' => null]);
    acceptSignature();

    postSns(snsEnvelope())->assertStatus(503);
});

it('rejects a message whose signature does not validate', function () {
    rejectSignature();

    postSns(snsEnvelope())->assertStatus(403);
});

it('rejects a body that is not valid json', function () {
    acceptSignature();

    test()->call('POST', route('webhooks.ses'), server: ['CONTENT_TYPE' => 'text/plain'], content: 'not json')
        ->assertStatus(403);
});

it('rejects a validly signed message from someone elses topic', function () {
    acceptSignature();

    postSns(snsEnvelope(['TopicArn' => 'arn:aws:sns:ap-southeast-1:999999999999:attacker']))
        ->assertStatus(403);
});

it('confirms a subscription by calling the signed SubscribeURL', function () {
    acceptSignature();
    Http::fake(['sns.ap-southeast-1.amazonaws.com/*' => Http::response('ok')]);

    postSns(snsEnvelope([
        'Type' => 'SubscriptionConfirmation',
        'Token' => 'token-value',
        'SubscribeURL' => 'https://sns.ap-southeast-1.amazonaws.com/?Action=ConfirmSubscription',
    ]))->assertOk();

    Http::assertSentCount(1);
});

it('refuses to call a SubscribeURL that points outside aws', function () {
    acceptSignature();

    postSns(snsEnvelope([
        'Type' => 'SubscriptionConfirmation',
        'Token' => 'token-value',
        'SubscribeURL' => 'https://evil.example.com/?Action=ConfirmSubscription',
    ]))->assertStatus(403);

    Http::assertNothingSent();
});

it('suppresses every recipient of a permanent bounce', function () {
    acceptSignature();

    postSns(snsEnvelope(['Message' => json_encode([
        'eventType' => 'Bounce',
        'bounce' => [
            'bounceType' => 'Permanent',
            'bounceSubType' => 'General',
            'timestamp' => '2026-07-09T09:00:00.000Z',
            'bouncedRecipients' => [
                ['emailAddress' => 'Dead@Example.com'],
                ['emailAddress' => 'gone@example.com'],
            ],
        ],
    ])]))->assertOk();

    expect(EmailSuppression::count())->toBe(2)
        ->and(EmailSuppression::isSuppressed('dead@example.com'))->toBeTrue()
        ->and(EmailSuppression::query()->where('email', 'dead@example.com')->value('reason'))
        ->toBe(EmailSuppressionReason::Bounce);
});

it('leaves transient bounces alone because the mailbox may just be full', function () {
    acceptSignature();

    postSns(snsEnvelope(['Message' => json_encode([
        'eventType' => 'Bounce',
        'bounce' => [
            'bounceType' => 'Transient',
            'bounceSubType' => 'MailboxFull',
            'bouncedRecipients' => [['emailAddress' => 'full@example.com']],
        ],
    ])]))->assertOk();

    expect(EmailSuppression::count())->toBe(0);
});

it('suppresses complainers', function () {
    acceptSignature();

    postSns(snsEnvelope(['Message' => json_encode([
        'eventType' => 'Complaint',
        'complaint' => [
            'complaintFeedbackType' => 'abuse',
            'timestamp' => '2026-07-09T09:00:00.000Z',
            'complainedRecipients' => [['emailAddress' => 'angry@example.com']],
        ],
    ])]))->assertOk();

    expect(EmailSuppression::query()->where('email', 'angry@example.com')->value('reason'))
        ->toBe(EmailSuppressionReason::Complaint);
});

it('understands the older identity-level notificationType key', function () {
    acceptSignature();

    postSns(snsEnvelope(['Message' => json_encode([
        'notificationType' => 'Complaint',
        'complaint' => ['complainedRecipients' => [['emailAddress' => 'legacy@example.com']]],
    ])]))->assertOk();

    expect(EmailSuppression::isSuppressed('legacy@example.com'))->toBeTrue();
});

it('acknowledges delivery events without suppressing anyone', function () {
    acceptSignature();

    postSns(snsEnvelope(['Message' => json_encode(['eventType' => 'Delivery'])]))->assertOk();

    expect(EmailSuppression::count())->toBe(0);
});

/**
 * Shape captured from a live SES event on 2026-07-09 (mailbox simulator,
 * ap-southeast-1, configuration set pmone-transactional). Guards against the
 * handler being written against a payload we invented rather than the real one.
 */
it('parses the payload shape SES actually sends', function () {
    acceptSignature();

    postSns(snsEnvelope(['Message' => json_encode([
        'eventType' => 'Bounce',
        'bounce' => [
            'bounceType' => 'Permanent',
            'bounceSubType' => 'General',
            'timestamp' => '2026-07-09T11:24:04.378Z',
            'feedbackId' => '010f0198...',
            'bouncedRecipients' => [[
                'emailAddress' => 'bounce@simulator.amazonses.com',
                'action' => 'failed',
                'status' => '5.1.1',
                'diagnosticCode' => 'smtp; 550 5.1.1 user unknown',
            ]],
        ],
        'mail' => [
            'timestamp' => '2026-07-09T11:24:03.000Z',
            'source' => 'noreply@pmone.id',
            'destination' => ['bounce@simulator.amazonses.com'],
        ],
    ])]))->assertOk();

    $row = EmailSuppression::query()->where('email', 'bounce@simulator.amazonses.com')->sole();

    expect($row->reason)->toBe(EmailSuppressionReason::Bounce)
        ->and($row->subtype)->toBe('General')
        ->and($row->suppressed_at->toIso8601String())->toContain('2026-07-09')
        ->and($row->payload['bouncedRecipients'][0]['status'])->toBe('5.1.1');
});

it('does not create a duplicate row when the same address bounces twice', function () {
    acceptSignature();

    $payload = snsEnvelope(['Message' => json_encode([
        'eventType' => 'Bounce',
        'bounce' => [
            'bounceType' => 'Permanent',
            'bouncedRecipients' => [['emailAddress' => 'repeat@example.com']],
        ],
    ])]);

    postSns($payload)->assertOk();
    postSns($payload)->assertOk();

    expect(EmailSuppression::count())->toBe(1);
});
