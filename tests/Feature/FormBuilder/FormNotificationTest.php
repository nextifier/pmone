<?php

use App\Jobs\ProcessFormResponseNotification;
use App\Mail\FormResponseSubmitted;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormResponse;
use App\Models\User;
use App\Notifications\FormResponseReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches a notification job when notification emails are configured', function () {
    Queue::fake();

    $form = Form::factory()->published()->create([
        'settings' => ['notification_emails' => ['admin@example.com']],
    ]);

    $this->postJson("/api/public/forms/{$form->slug}/submit", ['responses' => []])
        ->assertCreated();

    Queue::assertPushed(ProcessFormResponseNotification::class);
});

it('does not dispatch the job when no notification emails are set', function () {
    Queue::fake();

    $form = Form::factory()->published()->create([
        'settings' => ['confirmation_message' => 'Thanks!'],
    ]);

    $this->postJson("/api/public/forms/{$form->slug}/submit", ['responses' => []])
        ->assertCreated();

    Queue::assertNotPushed(ProcessFormResponseNotification::class);
});

it('sends the mail to configured recipients with reply-to set', function () {
    Mail::fake();

    $form = Form::factory()->published()->create([
        'settings' => ['notification_emails' => ['admin@example.com', 'sales@example.com']],
    ]);
    $response = FormResponse::factory()->create([
        'form_id' => $form->id,
        'respondent_email' => 'visitor@example.com',
    ]);

    (new ProcessFormResponseNotification($response))->handle();

    Mail::assertSent(FormResponseSubmitted::class, function (FormResponseSubmitted $mail) {
        return $mail->hasTo('admin@example.com')
            && $mail->hasTo('sales@example.com')
            && $mail->hasReplyTo('visitor@example.com');
    });
});

it('sends to to, cc, and bcc recipients when configured as an object', function () {
    Mail::fake();

    $form = Form::factory()->published()->create();
    $form->forceFill([
        'settings' => ['notification_emails' => [
            'to' => ['to@example.com'],
            'cc' => ['cc@example.com'],
            'bcc' => ['bcc@example.com'],
        ]],
    ])->saveQuietly();

    $response = FormResponse::factory()->create(['form_id' => $form->id]);

    (new ProcessFormResponseNotification($response))->handle();

    Mail::assertSent(FormResponseSubmitted::class, function (FormResponseSubmitted $mail) {
        return $mail->hasTo('to@example.com')
            && $mail->hasCc('cc@example.com')
            && $mail->hasBcc('bcc@example.com');
    });
});

it('does not send when the to list is empty even if cc is set', function () {
    Mail::fake();

    $form = Form::factory()->published()->create();
    $form->forceFill([
        'settings' => ['notification_emails' => ['to' => [], 'cc' => ['cc@example.com']]],
    ])->saveQuietly();

    $response = FormResponse::factory()->create(['form_id' => $form->id]);

    (new ProcessFormResponseNotification($response))->handle();

    Mail::assertNothingSent();
});

it('filters out invalid recipient addresses', function () {
    Mail::fake();

    $form = Form::factory()->published()->create();
    $form->forceFill([
        'settings' => ['notification_emails' => ['not-an-email', 'valid@example.com']],
    ])->saveQuietly();

    $response = FormResponse::factory()->create(['form_id' => $form->id]);

    (new ProcessFormResponseNotification($response))->handle();

    Mail::assertSent(FormResponseSubmitted::class, function (FormResponseSubmitted $mail) {
        return $mail->hasTo('valid@example.com') && ! $mail->hasTo('not-an-email');
    });
});

it('sends nothing when recipients are empty', function () {
    Mail::fake();

    $form = Form::factory()->published()->create();
    $response = FormResponse::factory()->create(['form_id' => $form->id]);

    (new ProcessFormResponseNotification($response))->handle();

    Mail::assertNothingSent();
});

it('sends an in-app notification to the form owner on submit', function () {
    Notification::fake();

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $form = Form::factory()->published()->create([
        'user_id' => $owner->id,
        'created_by' => $owner->id,
    ]);

    $this->postJson("/api/public/forms/{$form->slug}/submit", ['responses' => []])
        ->assertCreated();

    Notification::assertSentTo($owner, FormResponseReceivedNotification::class, function ($notification) use ($form) {
        $payload = $notification->toArray($form->user);

        return $payload['title'] === 'New form response'
            && str_contains($payload['body'], $form->title)
            && $payload['url'] === "/forms/{$form->slug}/responses"
            && isset($payload['icon']);
    });
    Notification::assertCount(1);
});

it('notifies both the owner and a distinct creator', function () {
    Notification::fake();

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $creator = User::factory()->create(['email_verified_at' => now()]);
    $form = Form::factory()->published()->create([
        'user_id' => $owner->id,
        'created_by' => $creator->id,
    ]);

    $this->postJson("/api/public/forms/{$form->slug}/submit", ['responses' => []])
        ->assertCreated();

    Notification::assertSentTo($owner, FormResponseReceivedNotification::class);
    Notification::assertSentTo($creator, FormResponseReceivedNotification::class);
    Notification::assertCount(2);
});

it('renders formatted answers in the mail body', function () {
    $form = Form::factory()->published()->create([
        'settings' => ['notification_emails' => ['admin@example.com']],
    ]);

    $select = FormField::factory()->type('select')->create([
        'form_id' => $form->id,
        'label' => 'Ticket Type',
        'options' => [['value' => 'vip', 'label' => 'VIP Pass']],
    ]);
    $switch = FormField::factory()->type('switch')->create([
        'form_id' => $form->id,
        'label' => 'Newsletter',
    ]);
    FormField::factory()->type('section')->create([
        'form_id' => $form->id,
        'label' => 'Hidden Section Heading',
    ]);

    $response = FormResponse::factory()->create([
        'form_id' => $form->id,
        'response_data' => [
            $select->ulid => 'vip',
            $switch->ulid => true,
        ],
    ]);

    $rendered = (new FormResponseSubmitted($response))->render();

    expect($rendered)->toContain('Ticket Type')
        ->toContain('VIP Pass')
        ->toContain('Newsletter')
        ->toContain('Yes')
        ->not->toContain('Hidden Section Heading');
});
