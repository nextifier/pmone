<?php

use App\Enums\EmailEventType;
use App\Models\EmailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

uses(RefreshDatabase::class);

function sentMessageFor(Email $email, string $to): SentMessage
{
    return new SentMessage(new SymfonySentMessage(
        $email,
        new Envelope(new Address('noreply@pmone.id'), [new Address($to)]),
    ));
}

/**
 * SesV2Transport writes the SES message id back into this header after the API
 * accepts the message. Other mailers never set it.
 */
function dispatchSentMessage(?string $sesMessageId, string $to = 'visitor@example.com'): void
{
    $email = (new Email)
        ->from('noreply@pmone.id')
        ->to($to)
        ->subject('Your e-ticket')
        ->text('Attached.');

    if ($sesMessageId !== null) {
        $email->getHeaders()->addTextHeader('X-SES-Message-ID', $sesMessageId);
    }

    event(new MessageSent(sentMessageFor($email, $to)));
}

it('records a message that SES accepted', function () {
    dispatchSentMessage('ses-message-id-1');

    $message = EmailMessage::query()->sole();

    expect($message->message_id)->toBe('ses-message-id-1')
        ->and($message->subject)->toBe('Your e-ticket')
        ->and($message->from_address)->toBe('noreply@pmone.id')
        ->and($message->recipients)->toBe(['visitor@example.com'])
        ->and($message->status)->toBe(EmailEventType::Send);
});

it('records nothing when the mailer is not SES', function () {
    dispatchSentMessage(null);

    expect(EmailMessage::count())->toBe(0);
});

it('records nothing for a real send through the array mailer', function () {
    Mail::raw('Hello', fn ($m) => $m->to('visitor@example.com')->subject('Hi'));

    expect(EmailMessage::count())->toBe(0);
});

it('does not create a second row when the same message id is seen twice', function () {
    dispatchSentMessage('ses-message-id-2');
    dispatchSentMessage('ses-message-id-2');

    expect(EmailMessage::count())->toBe(1);
});

it('captures cc and bcc recipients alongside the to', function () {
    $email = (new Email)
        ->from('noreply@pmone.id')
        ->to('a@example.com')
        ->cc('b@example.com')
        ->bcc('c@example.com')
        ->subject('Everyone')
        ->text('.');

    $email->getHeaders()->addTextHeader('X-SES-Message-ID', 'ses-message-id-3');

    event(new MessageSent(sentMessageFor($email, 'a@example.com')));

    expect(EmailMessage::query()->sole()->recipients)
        ->toBe(['a@example.com', 'b@example.com', 'c@example.com']);
});
