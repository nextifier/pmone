<?php

use App\Enums\EmailEventType;
use App\Models\EmailEvent;
use App\Models\EmailMessage;
use App\Models\EmailSuppression;
use App\Services\Resend\ResendEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const RESEND_ID = 're_abcdef0123456789';

/**
 * @param  array<string, mixed>  $data
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function resendEvent(string $type, array $data = [], array $overrides = []): array
{
    return array_merge([
        'type' => $type,
        'created_at' => '2026-07-13T10:00:00.000Z',
        'data' => array_merge([
            'email_id' => RESEND_ID,
            'created_at' => '2026-07-13T10:00:00.000Z',
            'from' => 'noreply@pmone.id',
            'to' => ['visitor@example.com'],
            'subject' => 'Your ticket',
        ], $data),
    ], $overrides);
}

function resendRecorder(): ResendEventRecorder
{
    return app(ResendEventRecorder::class);
}

it('ignores a Resend event type we do not track', function () {
    expect(resendRecorder()->record(resendEvent('email.scheduled')))->toBeNull()
        ->and(EmailEvent::count())->toBe(0);
});

it('records a delivery for the recipient', function () {
    $type = resendRecorder()->record(resendEvent('email.delivered'));

    expect($type)->toBe(EmailEventType::Delivery)
        ->and(EmailEvent::query()->where('recipient', 'visitor@example.com')->exists())->toBeTrue();
});

it('records an open and a click, keeping the clicked link', function () {
    resendRecorder()->record(resendEvent('email.opened'));
    resendRecorder()->record(resendEvent('email.clicked', [
        'click' => ['link' => 'https://pmone.id/ticket', 'timestamp' => '2026-07-13T10:05:00.000Z'],
    ]));

    expect(EmailEvent::query()->where('type', EmailEventType::Open->value)->exists())->toBeTrue();

    $click = EmailEvent::query()->where('type', EmailEventType::Click->value)->sole();

    expect($click->diagnostic)->toBe('https://pmone.id/ticket');
});

it('records a permanent bounce and suppresses the recipient', function () {
    resendRecorder()->record(resendEvent('email.bounced', [
        'to' => ['Dead@Example.com'],
        'bounce' => [
            'type' => 'Permanent',
            'subType' => 'General',
            'message' => 'smtp; 550 5.1.1 user unknown',
        ],
    ]));

    $event = EmailEvent::query()->sole();

    expect($event->recipient)->toBe('dead@example.com')
        ->and($event->subtype)->toBe('General')
        ->and($event->diagnostic)->toContain('550')
        ->and(EmailSuppression::isSuppressed('dead@example.com'))->toBeTrue();
});

it('records a transient bounce without suppressing anyone', function () {
    resendRecorder()->record(resendEvent('email.bounced', [
        'to' => ['full@example.com'],
        'bounce' => ['type' => 'Transient', 'subType' => 'MailboxFull'],
    ]));

    expect(EmailEvent::count())->toBe(1)
        ->and(EmailSuppression::count())->toBe(0);
});

it('suppresses a complaint', function () {
    resendRecorder()->record(resendEvent('email.complained', ['to' => ['angry@example.com']]));

    expect(EmailSuppression::isSuppressed('angry@example.com'))->toBeTrue();
});

it('still suppresses a bounce whose payload carries no email id', function () {
    $event = resendEvent('email.bounced', [
        'to' => ['orphan@example.com'],
        'bounce' => ['type' => 'Permanent'],
    ]);
    unset($event['data']['email_id']);

    resendRecorder()->record($event);

    expect(EmailSuppression::isSuppressed('orphan@example.com'))->toBeTrue()
        ->and(EmailEvent::count())->toBe(0);
});

it('does not double count a redelivered webhook', function () {
    resendRecorder()->record(resendEvent('email.delivered'));
    resendRecorder()->record(resendEvent('email.delivered'));

    expect(EmailEvent::count())->toBe(1);
});

it('moves a message status forward but never backwards', function () {
    $message = EmailMessage::factory()->create(['message_id' => RESEND_ID]);

    resendRecorder()->record(resendEvent('email.bounced', [
        'to' => ['dead@example.com'],
        'bounce' => ['type' => 'Permanent'],
    ]));

    // A delivery webhook for the same message arrives late and out of order.
    resendRecorder()->record(resendEvent('email.delivered', ['to' => ['dead@example.com']]));

    expect($message->fresh()->status)->toBe(EmailEventType::Bounce);
});

it('keeps events for messages this application never recorded', function () {
    resendRecorder()->record(resendEvent('email.delivered'));

    expect(EmailMessage::count())->toBe(0)
        ->and(EmailEvent::count())->toBe(1);
});
