<?php

use App\Models\EmailSuppression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;

uses(RefreshDatabase::class);

/**
 * The test suite runs on the "array" mailer, so the real Mailer pipeline (and
 * therefore the MessageSending event) still runs. Mail::fake() would bypass it.
 */
function sentMessages(): Collection
{
    return Mail::mailer()->getSymfonyTransport()->messages();
}

/**
 * @param  list<string>  $to
 * @param  list<string>  $cc
 */
function sendTo(array $to, array $cc = []): void
{
    Mail::raw('Your e-ticket is attached.', function ($message) use ($to, $cc) {
        $message->to($to)->subject('E-ticket');

        if ($cc !== []) {
            $message->cc($cc);
        }
    });
}

/**
 * @return list<string>
 */
function recipientsOfFirstMessage(): array
{
    $email = sentMessages()->first()->getOriginalMessage();

    return array_map(fn (Address $address) => $address->getAddress(), $email->getTo());
}

beforeEach(function () {
    Mail::mailer()->getSymfonyTransport()->flush();
});

it('sends normally when nobody is suppressed', function () {
    sendTo(['visitor@example.com']);

    expect(sentMessages())->toHaveCount(1);
});

it('cancels a message addressed only to suppressed recipients', function () {
    EmailSuppression::factory()->create(['email' => 'dead@example.com']);

    sendTo(['dead@example.com']);

    expect(sentMessages())->toHaveCount(0);
});

it('strips suppressed recipients but still delivers to the rest', function () {
    EmailSuppression::factory()->create(['email' => 'dead@example.com']);

    sendTo(['dead@example.com', 'alive@example.com']);

    expect(sentMessages())->toHaveCount(1)
        ->and(recipientsOfFirstMessage())->toBe(['alive@example.com']);
});

it('matches suppressed addresses regardless of case', function () {
    EmailSuppression::factory()->create(['email' => 'dead@example.com']);

    sendTo(['DEAD@Example.COM']);

    expect(sentMessages())->toHaveCount(0);
});

it('strips a suppressed cc while keeping the to intact', function () {
    EmailSuppression::factory()->create(['email' => 'spam@example.com']);

    sendTo(['visitor@example.com'], cc: ['spam@example.com']);

    $email = sentMessages()->first()->getOriginalMessage();

    expect(sentMessages())->toHaveCount(1)
        ->and(recipientsOfFirstMessage())->toBe(['visitor@example.com'])
        ->and($email->getCc())->toBe([]);
});

it('cancels when every to is suppressed even if a cc survives', function () {
    EmailSuppression::factory()->create(['email' => 'dead@example.com']);

    sendTo(['dead@example.com'], cc: ['watcher@example.com']);

    expect(sentMessages())->toHaveCount(0);
});
