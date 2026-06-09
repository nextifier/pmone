<?php

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.whatsapp.token', 'TEST_TOKEN');
    config()->set('services.whatsapp.phone_number_id', '123456789');
    config()->set('services.whatsapp.api_version', 'v21.0');
});

it('posts a template message to the graph api with mapped parameters', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.X']]], 200),
    ]);

    $result = app(WhatsAppService::class)->sendTemplate(
        '628123456789',
        'ticket_confirmation',
        ['Budi', 'Megabuild', 'HTL-1', 'https://x'],
        'id',
    );

    expect($result['messages'][0]['id'])->toBe('wamid.X');

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $params = $body['template']['components'][0]['parameters'];

        return $request->url() === 'https://graph.facebook.com/v21.0/123456789/messages'
            && $request->hasHeader('Authorization', 'Bearer TEST_TOKEN')
            && $body['messaging_product'] === 'whatsapp'
            && $body['to'] === '628123456789'
            && $body['type'] === 'template'
            && $body['template']['name'] === 'ticket_confirmation'
            && $body['template']['language']['code'] === 'id'
            && $body['template']['components'][0]['type'] === 'body'
            && $params[0] === ['type' => 'text', 'text' => 'Budi']
            && $params[3] === ['type' => 'text', 'text' => 'https://x'];
    });
});

it('omits the components block when there are no parameters', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.Y']]], 200)]);

    app(WhatsAppService::class)->sendTemplate('628123456789', 'hello_world', [], 'en');

    Http::assertSent(fn (Request $request) => ! isset($request->data()['template']['components']));
});

it('throws on a failed graph api response so the job can retry', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'bad']], 400)]);

    expect(fn () => app(WhatsAppService::class)->sendTemplate('628', 'ticket_confirmation', ['a']))
        ->toThrow(RequestException::class);
});
