<?php

use App\Enums\EmailEventType;
use App\Models\EmailEvent;
use App\Models\EmailMessage;
use App\Models\EmailSuppression;
use App\Services\Ses\SesEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const MSG_ID = '0100019876543210-abcdef01-2345-6789-abcd-ef0123456789-000000';

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sesEvent(string $type, array $overrides = []): array
{
    return array_merge([
        'eventType' => $type,
        'mail' => [
            'messageId' => MSG_ID,
            'timestamp' => '2026-07-09T10:00:00.000Z',
            'source' => 'noreply@pmone.id',
            'destination' => ['visitor@example.com'],
        ],
    ], $overrides);
}

function recorder(): SesEventRecorder
{
    return app(SesEventRecorder::class);
}

it('ignores an event type SES sends but we do not track', function () {
    expect(recorder()->record(sesEvent('Subscription')))->toBeNull()
        ->and(EmailEvent::count())->toBe(0);
});

it('records a send event for every destination', function () {
    $type = recorder()->record(sesEvent('Send'));

    expect($type)->toBe(EmailEventType::Send)
        ->and(EmailEvent::query()->where('recipient', 'visitor@example.com')->exists())->toBeTrue();
});

it('records a delivery with the smtp response', function () {
    recorder()->record(sesEvent('Delivery', [
        'delivery' => [
            'timestamp' => '2026-07-09T10:00:05.000Z',
            'recipients' => ['visitor@example.com'],
            'smtpResponse' => '250 2.0.0 OK',
        ],
    ]));

    $event = EmailEvent::query()->sole();

    expect($event->type)->toBe(EmailEventType::Delivery)
        ->and($event->diagnostic)->toBe('250 2.0.0 OK');
});

it('records a permanent bounce and suppresses the recipient', function () {
    recorder()->record(sesEvent('Bounce', [
        'bounce' => [
            'bounceType' => 'Permanent',
            'bounceSubType' => 'General',
            'timestamp' => '2026-07-09T10:00:05.000Z',
            'bouncedRecipients' => [[
                'emailAddress' => 'Dead@Example.com',
                'diagnosticCode' => 'smtp; 550 5.1.1 user unknown',
            ]],
        ],
    ]));

    $event = EmailEvent::query()->sole();

    expect($event->recipient)->toBe('dead@example.com')
        ->and($event->subtype)->toBe('General')
        ->and($event->diagnostic)->toContain('550')
        ->and(EmailSuppression::isSuppressed('dead@example.com'))->toBeTrue();
});

it('records a transient bounce without suppressing anyone', function () {
    recorder()->record(sesEvent('Bounce', [
        'bounce' => [
            'bounceType' => 'Transient',
            'bounceSubType' => 'MailboxFull',
            'bouncedRecipients' => [['emailAddress' => 'full@example.com']],
        ],
    ]));

    expect(EmailEvent::count())->toBe(1)
        ->and(EmailSuppression::count())->toBe(0);
});

it('still suppresses a bounce whose payload carries no message id', function () {
    $event = sesEvent('Bounce', [
        'bounce' => [
            'bounceType' => 'Permanent',
            'bouncedRecipients' => [['emailAddress' => 'orphan@example.com']],
        ],
    ]);
    unset($event['mail']['messageId']);

    recorder()->record($event);

    expect(EmailSuppression::isSuppressed('orphan@example.com'))->toBeTrue()
        ->and(EmailEvent::count())->toBe(0);
});

it('does not double count a redelivered notification', function () {
    $event = sesEvent('Delivery', [
        'delivery' => [
            'timestamp' => '2026-07-09T10:00:05.000Z',
            'recipients' => ['visitor@example.com'],
        ],
    ]);

    recorder()->record($event);
    recorder()->record($event);

    expect(EmailEvent::count())->toBe(1);
});

it('moves a message status forward but never backwards', function () {
    $message = EmailMessage::factory()->create(['message_id' => MSG_ID]);

    recorder()->record(sesEvent('Bounce', [
        'bounce' => [
            'bounceType' => 'Permanent',
            'timestamp' => '2026-07-09T10:00:10.000Z',
            'bouncedRecipients' => [['emailAddress' => 'dead@example.com']],
        ],
    ]));

    // A delivery notification for the same message arrives late and out of order.
    recorder()->record(sesEvent('Delivery', [
        'delivery' => [
            'timestamp' => '2026-07-09T10:00:05.000Z',
            'recipients' => ['dead@example.com'],
        ],
    ]));

    expect($message->fresh()->status)->toBe(EmailEventType::Bounce);
});

it('promotes a message from sent to delivered', function () {
    $message = EmailMessage::factory()->create(['message_id' => MSG_ID]);

    recorder()->record(sesEvent('Delivery', [
        'delivery' => [
            'timestamp' => '2026-07-09T10:00:05.000Z',
            'recipients' => ['visitor@example.com'],
        ],
    ]));

    expect($message->fresh()->status)->toBe(EmailEventType::Delivery);
});

it('keeps events for messages this application never recorded', function () {
    recorder()->record(sesEvent('Delivery', [
        'delivery' => ['recipients' => ['visitor@example.com']],
    ]));

    expect(EmailMessage::count())->toBe(0)
        ->and(EmailEvent::count())->toBe(1);
});
