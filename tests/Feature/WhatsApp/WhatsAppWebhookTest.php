<?php

beforeEach(function () {
    config()->set('services.whatsapp.webhook_verify_token', 'VERIFY_ME');
    config()->set('services.whatsapp.app_secret', 'APP_SECRET');
});

it('echoes the challenge when the verify token matches', function () {
    $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=VERIFY_ME&hub_challenge=1234567890')
        ->assertSuccessful()
        ->assertSee('1234567890');
});

it('rejects verification when the token is wrong', function () {
    $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=WRONG&hub_challenge=1234567890')
        ->assertForbidden();
});

it('accepts a webhook with a valid signature', function () {
    $raw = json_encode(['object' => 'whatsapp_business_account', 'entry' => []]);
    $signature = 'sha256='.hash_hmac('sha256', $raw, 'APP_SECRET');

    $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
        'HTTP_X-Hub-Signature-256' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $raw)->assertSuccessful();
});

it('rejects a webhook with an invalid signature', function () {
    $raw = json_encode(['object' => 'whatsapp_business_account']);

    $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
        'HTTP_X-Hub-Signature-256' => 'sha256=deadbeef',
        'CONTENT_TYPE' => 'application/json',
    ], $raw)->assertUnauthorized();
});
