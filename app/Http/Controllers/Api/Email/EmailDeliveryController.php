<?php

namespace App\Http\Controllers\Api\Email;

use App\Enums\EmailEventType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Email\EmailMessageResource;
use App\Http\Resources\Email\EmailSuppressionResource;
use App\Models\EmailEvent;
use App\Models\EmailMessage;
use App\Models\EmailSuppression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class EmailDeliveryController extends Controller
{
    /**
     * Every figure is counted from our own tables, so the dashboard never
     * depends on a provider API being reachable. Delivery outcomes come from the
     * message status; opens and clicks come from the event log, because a
     * delivered-then-opened message keeps its higher-ranked "delivery" status.
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
        $delivered = (int) $counts->get(EmailEventType::Delivery->value, 0);
        $bounced = (int) $counts->get(EmailEventType::Bounce->value, 0);
        $complained = (int) $counts->get(EmailEventType::Complaint->value, 0);

        $engagement = EmailEvent::query()
            ->where('occurred_at', '>=', $since)
            ->whereIn('type', [EmailEventType::Open, EmailEventType::Click])
            ->selectRaw('type, count(distinct message_id) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $opened = (int) $engagement->get(EmailEventType::Open->value, 0);
        $clicked = (int) $engagement->get(EmailEventType::Click->value, 0);

        return response()->json([
            'data' => [
                'last_30_days' => [
                    'sent' => $sent,
                    'delivered' => $delivered,
                    'bounced' => $bounced,
                    'complained' => $complained,
                    'opened' => $opened,
                    'clicked' => $clicked,
                    'delivery_rate' => $this->rate($delivered, $sent),
                    // Deliverability is judged against everything sent; engagement
                    // against what actually reached a mailbox.
                    'bounce_rate' => $this->rate($bounced, $sent),
                    'complaint_rate' => $this->rate($complained, $sent),
                    'open_rate' => $this->rate($opened, $delivered),
                    'click_rate' => $this->rate($clicked, $delivered),
                ],
                'daily' => $this->dailyTrend($since),
                'suppressed_total' => EmailSuppression::query()->count(),
            ],
        ]);
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 2) : 0.0;
    }

    /**
     * A continuous 30-day series for the trend chart: sends keyed by the day the
     * message left, deliveries and opens keyed by the day the event landed. Days
     * with no activity are filled with zeros so the x-axis never has gaps.
     *
     * @return list<array{date: string, sent: int, delivered: int, opened: int}>
     */
    private function dailyTrend(Carbon $since): array
    {
        $sentByDay = EmailMessage::query()
            ->where('sent_at', '>=', $since)
            ->selectRaw('date(sent_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $delivered = [];
        $opened = [];

        EmailEvent::query()
            ->where('occurred_at', '>=', $since)
            ->whereIn('type', [EmailEventType::Delivery, EmailEventType::Open])
            ->selectRaw('date(occurred_at) as day, type, count(distinct message_id) as total')
            ->groupBy('day', 'type')
            ->get()
            ->each(function ($row) use (&$delivered, &$opened) {
                if ($row->type === EmailEventType::Delivery) {
                    $delivered[$row->day] = (int) $row->total;
                } elseif ($row->type === EmailEventType::Open) {
                    $opened[$row->day] = (int) $row->total;
                }
            });

        $series = [];

        for ($daysAgo = 29; $daysAgo >= 0; $daysAgo--) {
            $day = now()->subDays($daysAgo)->toDateString();

            $series[] = [
                'date' => $day,
                'sent' => (int) ($sentByDay[$day] ?? 0),
                'delivered' => $delivered[$day] ?? 0,
                'opened' => $opened[$day] ?? 0,
            ];
        }

        return $series;
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
