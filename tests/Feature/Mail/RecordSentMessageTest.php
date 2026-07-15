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
 * The Resend transport writes the Resend email id into this header after the API
 * accepts the message. Later webhook events carry that same id.
 */
function dispatchResendMessage(string $resendId, string $to = 'visitor@example.com'): void
{
    $email = (new Email)
        ->from('noreply@pmone.id')
        ->to($to)
        ->subject('Your e-ticket')
        ->text('Attached.');

    $email->getHeaders()->addTextHeader('X-Resend-Email-ID', $resendId);

    event(new MessageSent(sentMessageFor($email, $to)));
}

/**
 * A message dispatched by a mailer that sets no Resend tracking header (e.g.
 * cloudflare in development).
 */
function dispatchUntrackedMessage(string $to = 'visitor@example.com'): void
{
    $email = (new Email)
        ->from('noreply@pmone.id')
        ->to($to)
        ->subject('Your e-ticket')
        ->text('Attached.');

    event(new MessageSent(sentMessageFor($email, $to)));
}

it('records a message that Resend accepted', function () {
    dispatchResendMessage('re_listener_test');

    $message = EmailMessage::query()->sole();

    expect($message->message_id)->toBe('re_listener_test')
        ->and($message->subject)->toBe('Your e-ticket')
        ->and($message->from_address)->toBe('noreply@pmone.id')
        ->and($message->recipients)->toBe(['visitor@example.com'])
        ->and($message->status)->toBe(EmailEventType::Send)
        ->and($message->mailer)->toBe('resend')
        ->and($message->configuration_set)->toBeNull();
});

it('records nothing when the mailer sets no tracking header', function () {
    dispatchUntrackedMessage();

    expect(EmailMessage::count())->toBe(0);
});

it('records nothing for a real send through the array mailer', function () {
    Mail::raw('Hello', fn ($m) => $m->to('visitor@example.com')->subject('Hi'));

    expect(EmailMessage::count())->toBe(0);
});

it('does not create a second row when the same message id is seen twice', function () {
    dispatchResendMessage('re_seen_twice');
    dispatchResendMessage('re_seen_twice');

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

    $email->getHeaders()->addTextHeader('X-Resend-Email-ID', 're_cc_bcc');

    event(new MessageSent(sentMessageFor($email, 'a@example.com')));

    expect(EmailMessage::query()->sole()->recipients)
        ->toBe(['a@example.com', 'b@example.com', 'c@example.com']);
});
