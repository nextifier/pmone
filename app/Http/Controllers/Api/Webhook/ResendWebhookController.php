<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Resend\ResendEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Resend\WebhookSignature;

/**
 * Receives Resend delivery event notifications (delivered, bounced, complained,
 * opened, clicked, ...).
 *
 * Authenticity rests on the Svix signature Resend attaches to every request,
 * verified against our webhook signing secret. A missing secret is treated as a
 * misconfiguration and refuses the payload outright rather than trusting it.
 *
 * Mirrors the SES and payment webhooks: anything that is not a genuine
 * authenticity failure answers 200 so Resend does not enter its retry cycle.
 */
class ResendWebhookController extends Controller
{
    public function __construct(private readonly ResendEventRecorder $recorder) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('resend.webhook.secret');

        if ($secret === '') {
            Log::warning('Resend webhook hit while RESEND_WEBHOOK_SECRET is unset; refusing to trust the payload.');

            return response()->json(['message' => 'Webhook not configured'], 503);
        }

        try {
            WebhookSignature::verify(
                $request->getContent(),
                [
                    'svix-id' => (string) $request->header('svix-id'),
                    'svix-timestamp' => (string) $request->header('svix-timestamp'),
                    'svix-signature' => (string) $request->header('svix-signature'),
                ],
                $secret,
                (int) config('resend.webhook.tolerance', 300),
            );
        } catch (\Throwable $e) {
            Log::warning('Rejected Resend webhook with an invalid signature.', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response()->json(['message' => 'Unparsable event (acknowledged)']);
        }

        $type = $this->recorder->record($payload);

        return response()->json([
            'message' => $type === null ? 'Ignored event type' : "Recorded {$type->value}",
        ]);
    }
}
