<?php

use App\Enums\EmailEventType;
use App\Models\EmailMessage;
use App\Services\Resend\ResendEmailApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Builds a Resend list-email item as the API returns it.
 *
 * @return array<string, mixed>
 */
function resendItem(string $id, string $createdAt, string $lastEvent = 'delivered'): array
{
    return [
        'id' => $id,
        'from' => 'PM One <noreply@pmone.id>',
        'to' => ['visitor@example.com'],
        'subject' => "Subject {$id}",
        'created_at' => $createdAt,
        'last_event' => $lastEvent,
    ];
}

it('backfills messages from the Resend API', function () {
    $this->mock(ResendEmailApi::class, function ($mock) {
        $mock->shouldReceive('list')->once()->with(null, 100)->andReturn([
            'has_more' => false,
            'data' => [
                resendItem('em-1', now()->toIso8601String(), 'delivered'),
                resendItem('em-2', now()->subDay()->toIso8601String(), 'bounced'),
            ],
        ]);
    });

    Artisan::call('emails:sync-resend', ['--full' => true]);

    expect(EmailMessage::count())->toBe(2);

    $first = EmailMessage::query()->where('message_id', 'em-1')->sole();

    expect($first->mailer)->toBe('resend')
        ->and($first->from_address)->toBe('PM One <noreply@pmone.id>')
        ->and($first->recipients)->toBe(['visitor@example.com'])
        ->and($first->status)->toBe(EmailEventType::Delivery);

    expect(EmailMessage::query()->where('message_id', 'em-2')->sole()->status)
        ->toBe(EmailEventType::Bounce);
});

it('walks every page in full mode', function () {
    $this->mock(ResendEmailApi::class, function ($mock) {
        $mock->shouldReceive('list')->once()->with(null, 100)->andReturn([
            'has_more' => true,
            'data' => [resendItem('em-a', now()->toIso8601String())],
        ]);
        $mock->shouldReceive('list')->once()->with('em-a', 100)->andReturn([
            'has_more' => false,
            'data' => [resendItem('em-b', now()->subDays(3)->toIso8601String())],
        ]);
    });

    Artisan::call('emails:sync-resend', ['--full' => true]);

    expect(EmailMessage::count())->toBe(2);
});

it('stops at the overlap window in incremental mode', function () {
    // The freshest row we already hold sets the cutoff at two days before it.
    EmailMessage::factory()->create(['message_id' => 'known', 'sent_at' => now()]);

    $this->mock(ResendEmailApi::class, function ($mock) {
        // Page one already reaches past the overlap window (oldest is 3 days
        // old, cutoff is 2 days), so a second page must never be requested.
        $mock->shouldReceive('list')->once()->with(null, 100)->andReturn([
            'has_more' => true,
            'data' => [
                resendItem('em-new', now()->toIso8601String()),
                resendItem('em-old', now()->subDays(3)->toIso8601String()),
            ],
        ]);
    });

    Artisan::call('emails:sync-resend');

    expect(EmailMessage::query()->whereIn('message_id', ['em-new', 'em-old'])->count())->toBe(2);
});

it('never downgrades a status already recorded by a webhook', function () {
    // A webhook has already marked this address as bounced.
    EmailMessage::factory()->bounced()->create(['message_id' => 'em-bounced']);

    $this->mock(ResendEmailApi::class, function ($mock) {
        // Resend's list still reports its coarse last_event as "delivered".
        $mock->shouldReceive('list')->once()->with(null, 100)->andReturn([
            'has_more' => false,
            'data' => [resendItem('em-bounced', now()->toIso8601String(), 'delivered')],
        ]);
    });

    Artisan::call('emails:sync-resend', ['--full' => true]);

    expect(EmailMessage::query()->where('message_id', 'em-bounced')->sole()->status)
        ->toBe(EmailEventType::Bounce);
});
