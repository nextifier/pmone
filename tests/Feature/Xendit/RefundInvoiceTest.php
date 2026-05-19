<?php

use App\Models\ProjectPaymentGateway;
use App\Services\Xendit\XenditService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Regression: the Xendit SDK's createRefund() signature is
 * ($idempotency_key, $for_user_id, $create_refund). Earlier code called it
 * with ($payload) in the for_user_id slot, which made the SDK emit a
 * `for-user-id` header containing the serialized payload — Xendit rejected
 * the request with "Header 'for-user-id' is not in a valid XenPlatform
 * sub-account ID format" and the refund silently failed.
 */
test('refundInvoice sends payload as JSON body without for-user-id header', function () {
    $gateway = ProjectPaymentGateway::factory()->default()->create([
        'provider' => 'xendit',
        'mode' => 'test',
        'secret_key' => 'xnd_development_test_key',
    ]);

    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'rfnd_test_123',
            'amount' => 100000,
            'reason' => 'CANCELLATION',
            'status' => 'PENDING',
        ])),
    ]));
    $stack->push(Middleware::history($history));
    $captureClient = new GuzzleClient(['handler' => $stack]);

    $service = Mockery::mock(XenditService::class, [$gateway])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('httpClient')->andReturn($captureClient);

    $refundId = $service->refundInvoice('inv_abc', 100000.0, 'CANCELLATION');

    expect($refundId)->toBe('rfnd_test_123')
        ->and($history)->toHaveCount(1);

    $request = $history[0]['request'];

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toContain('/refunds')
        ->and($request->getHeaderLine('for-user-id'))->toBe('');

    $body = json_decode((string) $request->getBody(), true);
    expect($body)->toMatchArray([
        'invoice_id' => 'inv_abc',
        'amount' => 100000,
        'reason' => 'CANCELLATION',
    ]);
});
