<?php

namespace App\Http\Controllers\Api\Email;

use App\Enums\EmailEventType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Email\EmailMessageResource;
use App\Http\Resources\Email\EmailSuppressionResource;
use App\Models\EmailMessage;
use App\Models\EmailSuppression;
use App\Services\Ses\SesAccountService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmailDeliveryController extends Controller
{
    public function __construct(private readonly SesAccountService $ses) {}

    /**
     * Quota comes from AWS; everything else is counted from our own tables,
     * which stay accurate even when the SES API is unreachable.
     */
    public function overview(): JsonResponse
    {
        $since = now()->subDays(30);

        $counts = EmailMessage::query()
            ->where('sent_at', '>=', $since)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sent = (int) $counts->sum();
        $bounced = (int) $counts->get(EmailEventType::Bounce->value, 0);
        $complained = (int) $counts->get(EmailEventType::Complaint->value, 0);

        return response()->json([
            'data' => [
                'quota' => $this->ses->quota(),
                'daily_statistics' => $this->ses->dailyStatistics(),
                'last_30_days' => [
                    'sent' => $sent,
                    'delivered' => (int) $counts->get(EmailEventType::Delivery->value, 0),
                    'bounced' => $bounced,
                    'complained' => $complained,
                    // AWS suspends accounts above 5% bounces or 0.1% complaints,
                    // so these two ratios are the numbers that actually matter.
                    'bounce_rate' => $sent > 0 ? round($bounced / $sent * 100, 2) : 0.0,
                    'complaint_rate' => $sent > 0 ? round($complained / $sent * 100, 2) : 0.0,
                ],
                'suppressed_total' => EmailSuppression::query()->count(),
            ],
        ]);
    }

    public function messages(Request $request): AnonymousResourceCollection
    {
        $messages = EmailMessage::query()
            ->when(
                $this->commaSeparated($request, 'status'),
                fn ($query, array $statuses) => $query->whereIn('status', $statuses)
            )
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->whereLike('subject', "%{$search}%")
                        ->orWhereLike('from_address', "%{$search}%")
                        ->orWhereLike('message_id', "%{$search}%")
                        // recipients is a json column: LIKE against it is not
                        // portable, and Postgres rejects it outright. An exact
                        // address match is what a support lookup needs anyway.
                        ->orWhereJsonContains('recipients', $search);
                });
            })
            ->tap(fn ($query) => $this->applySort($query, $request, ['sent_at', 'status', 'subject'], 'sent_at'))
            ->paginate($request->integer('per_page', 25));

        return EmailMessageResource::collection($messages);
    }

    public function show(EmailMessage $emailMessage): EmailMessageResource
    {
        $emailMessage->load(['events' => fn ($query) => $query->orderBy('occurred_at')]);

        return new EmailMessageResource($emailMessage);
    }

    public function suppressions(Request $request): AnonymousResourceCollection
    {
        $suppressions = EmailSuppression::query()
            ->when(
                $this->commaSeparated($request, 'reason'),
                fn ($query, array $reasons) => $query->whereIn('reason', $reasons)
            )
            ->when($request->string('search')->toString(), fn ($query, $search) => $query->whereLike('email', "%{$search}%"))
            ->tap(fn ($query) => $this->applySort($query, $request, ['suppressed_at', 'email', 'reason'], 'suppressed_at'))
            ->paginate($request->integer('per_page', 25));

        return EmailSuppressionResource::collection($suppressions);
    }

    /**
     * Removing a row here only lifts *our* block. SES keeps its own
     * account-level suppression list, which has to be cleared in AWS.
     */
    public function destroySuppression(EmailSuppression $emailSuppression): JsonResponse
    {
        $emailSuppression->delete();

        return response()->json(['message' => 'Address removed from the suppression list.']);
    }

    /**
     * The table sends multi-select filters as one comma-separated value.
     *
     * @return list<string>
     */
    private function commaSeparated(Request $request, string $key): array
    {
        $raw = $request->string($key)->toString();

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * Accepts the "-column" convention used elsewhere in this API. Anything
     * outside the allowlist falls back to the default, so a crafted sort
     * parameter cannot reach an unindexed or private column.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $sortable
     */
    private function applySort(Builder $query, Request $request, array $sortable, string $default): void
    {
        $sort = $request->string('sort')->toString() ?: "-{$default}";
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        if (! in_array($column, $sortable, true)) {
            $column = $default;
            $descending = true;
        }

        $query->orderBy($column, $descending ? 'desc' : 'asc');
    }
}
