<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives Meta WhatsApp Cloud API webhooks.
 *
 *  - GET  /api/webhooks/whatsapp  Meta subscription verification handshake.
 *  - POST /api/webhooks/whatsapp  Event delivery (message statuses + inbound messages).
 *
 * POST authenticity is verified with the X-Hub-Signature-256 header: an
 * HMAC-SHA256 of the raw request body keyed with the Meta App Secret. Detailed
 * parsing of statuses/inbound messages is intentionally deferred - for now valid
 * payloads are logged so deliveries are observable while it is built out.
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * Subscription verification. Meta sends hub.mode / hub.verify_token /
     * hub.challenge as query params (PHP rewrites the dots to underscores) and
     * expects the challenge echoed back verbatim on success.
     */
    public function verify(Request $request): Response
    {
        $mode = (string) $request->query('hub_mode');
        $token = (string) $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge');

        $expected = (string) config('services.whatsapp.webhook_verify_token');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return response($challenge, 200);
        }

        return response('', 403);
    }

    public function handle(Request $request): JsonResponse
    {
        if (! $this->signatureIsValid($request)) {
            Log::warning('WhatsApp webhook signature mismatch', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        Log::info('WhatsApp webhook', $request->all());

        return response()->json(['message' => 'ok']);
    }

    /**
     * Verify the X-Hub-Signature-256 HMAC over the raw request body. When the app
     * secret is not configured yet (initial wiring), accept but warn so the
     * endpoint can be hooked up before the secret is provisioned.
     */
    private function signatureIsValid(Request $request): bool
    {
        $appSecret = (string) config('services.whatsapp.app_secret');

        if ($appSecret === '') {
            Log::warning('WhatsApp webhook: app secret not configured, skipping signature check');

            return true;
        }

        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return $signature !== '' && hash_equals($expected, $signature);
    }
}
