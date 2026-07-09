<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Enums\EmailSuppressionReason;
use App\Http\Controllers\Controller;
use App\Models\EmailSuppression;
use Aws\Sns\Message as SnsMessage;
use Aws\Sns\MessageValidator as SnsMessageValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Receives Amazon SES event notifications relayed through an SNS topic.
 *
 * Two independent checks guard this endpoint. The SNS signature proves the
 * request really came from SNS, and the TopicArn allowlist proves it came from
 * *our* topic: a valid signature alone would let anyone with an AWS account
 * point their own topic here and forge bounces for arbitrary addresses.
 *
 * Mirrors the Xendit and Midtrans controllers: anything that is not a genuine
 * authenticity failure answers 200 so SNS does not enter its retry cycle.
 */
class SesNotificationController extends Controller
{
    public function __invoke(Request $request, SnsMessageValidator $validator): JsonResponse
    {
        $expectedTopic = (string) config('services.ses_sns.topic_arn');

        if ($expectedTopic === '') {
            Log::warning('SES webhook hit while SES_SNS_TOPIC_ARN is unset; refusing to trust the payload.');

            return response()->json(['message' => 'Webhook not configured'], 503);
        }

        try {
            // fromJsonString() throws RuntimeException on a non-JSON body and
            // InvalidArgumentException when a required SNS key is missing;
            // InvalidSnsMessageException (a RuntimeException) means bad signature.
            $message = SnsMessage::fromJsonString($request->getContent());
            $validator->validate($message);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Log::warning('Rejected SES webhook with an invalid SNS message.', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid SNS message'], 403);
        }

        if (! hash_equals($expectedTopic, (string) $message['TopicArn'])) {
            Log::warning('Rejected SES webhook from an unexpected SNS topic.', ['topic' => $message['TopicArn']]);

            return response()->json(['message' => 'Unexpected topic'], 403);
        }

        return match ((string) $message['Type']) {
            'SubscriptionConfirmation' => $this->confirmSubscription($message),
            'Notification' => $this->handleNotification($message),
            default => response()->json(['message' => 'Ignored']),
        };
    }

    private function confirmSubscription(SnsMessage $message): JsonResponse
    {
        $subscribeUrl = (string) $message['SubscribeURL'];

        if (! $this->isAmazonUrl($subscribeUrl)) {
            Log::warning('Refused to confirm an SNS subscription pointing outside AWS.', ['url' => $subscribeUrl]);

            return response()->json(['message' => 'Invalid SubscribeURL'], 403);
        }

        Http::timeout(10)->get($subscribeUrl);

        Log::info('Confirmed SNS subscription for SES events.', ['topic' => $message['TopicArn']]);

        return response()->json(['message' => 'Subscription confirmed']);
    }

    private function handleNotification(SnsMessage $message): JsonResponse
    {
        $event = json_decode((string) $message['Message'], true);

        if (! is_array($event)) {
            return response()->json(['message' => 'Unparsable event (acknowledged)']);
        }

        // Configuration-set destinations send "eventType"; the older
        // identity-level notifications send "notificationType".
        $type = (string) ($event['eventType'] ?? $event['notificationType'] ?? '');

        return match ($type) {
            'Bounce' => $this->handleBounce($event),
            'Complaint' => $this->handleComplaint($event),
            default => response()->json(['message' => 'Ignored event type']),
        };
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function handleBounce(array $event): JsonResponse
    {
        $bounce = $event['bounce'] ?? [];
        $bounceType = (string) ($bounce['bounceType'] ?? '');

        // Transient bounces are full mailboxes and throttling, not dead
        // addresses. Suppressing them would silently drop deliverable mail.
        if ($bounceType !== 'Permanent') {
            return response()->json(['message' => 'Non-permanent bounce (acknowledged)']);
        }

        foreach ($bounce['bouncedRecipients'] ?? [] as $recipient) {
            $email = $recipient['emailAddress'] ?? null;

            if (! is_string($email) || $email === '') {
                continue;
            }

            EmailSuppression::suppress(
                email: $email,
                reason: EmailSuppressionReason::Bounce,
                subtype: $bounce['bounceSubType'] ?? null,
                suppressedAt: $this->timestamp($bounce['timestamp'] ?? null),
                payload: $bounce,
            );
        }

        return response()->json(['message' => 'Bounce recorded']);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function handleComplaint(array $event): JsonResponse
    {
        $complaint = $event['complaint'] ?? [];

        foreach ($complaint['complainedRecipients'] ?? [] as $recipient) {
            $email = $recipient['emailAddress'] ?? null;

            if (! is_string($email) || $email === '') {
                continue;
            }

            EmailSuppression::suppress(
                email: $email,
                reason: EmailSuppressionReason::Complaint,
                subtype: $complaint['complaintFeedbackType'] ?? null,
                suppressedAt: $this->timestamp($complaint['timestamp'] ?? null),
                payload: $complaint,
            );
        }

        return response()->json(['message' => 'Complaint recorded']);
    }

    private function timestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function isAmazonUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host)
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && (str_ends_with($host, '.amazonaws.com') || str_ends_with($host, '.amazonaws.com.cn'));
    }
}
