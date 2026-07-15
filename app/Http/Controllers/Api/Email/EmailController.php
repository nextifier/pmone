<?php

namespace App\Http\Controllers\Api\Email;

use App\Enums\EmailEventType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Email\EmailMessageResource;
use App\Http\Resources\Email\EmailSuppressionResource;
use App\Models\EmailEvent;
use App\Models\EmailMessage;
use App\Models\EmailSuppression;
use App\Services\Resend\ResendEmailApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class EmailController extends Controller
{
    public function __construct(private readonly ResendEmailApi $resendEmails) {}

    /**
     * Every figure is counted from our own tables, so the dashboard never
     * depends on a provider API being reachable. Delivery outcomes come from the
     * message status. Open/click tracking is deliberately not used (it rewrites
     * links and adds pixels that hurt transactional deliverability), so those
     * metrics are not reported.
     */
    public function overview(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        $counts = EmailMessage::query()
            ->whereBetween('sent_at', [$from, $to])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sent = (int) $counts->sum();
        $delivered = (int) $counts->get(EmailEventType::Delivery->value, 0);
        $bounced = (int) $counts->get(EmailEventType::Bounce->value, 0);
        $complained = (int) $counts->get(EmailEventType::Complaint->value, 0);

        // Bounce classification comes from the delivery webhook; historical rows
        // (synced from the list API, which omits it) count as unclassified.
        $bounceTypes = EmailMessage::query()
            ->whereBetween('sent_at', [$from, $to])
            ->where('status', EmailEventType::Bounce)
            ->selectRaw('bounce_type, count(*) as total')
            ->groupBy('bounce_type')
            ->pluck('total', 'bounce_type');

        $permanentBounces = (int) $bounceTypes->get('permanent', 0);
        $transientBounces = (int) $bounceTypes->get('transient', 0);

        return response()->json([
            'data' => [
                'range' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'totals' => [
                    'sent' => $sent,
                    'delivered' => $delivered,
                    'bounced' => $bounced,
                    'complained' => $complained,
                    'delivery_rate' => $this->rate($delivered, $sent),
                    // Deliverability is judged against everything sent.
                    'bounce_rate' => $this->rate($bounced, $sent),
                    'complaint_rate' => $this->rate($complained, $sent),
                ],
                'bounce_breakdown' => [
                    'permanent' => $permanentBounces,
                    'transient' => $transientBounces,
                    'unclassified' => max(0, $bounced - $permanentBounces - $transientBounces),
                ],
                'daily' => $this->dailyTrend($from, $to),
                'suppressed_total' => EmailSuppression::query()->count(),
                // Sending quota is always measured against the live day/month, not
                // the selected range, because that is what the provider caps.
                'usage' => $this->usage(),
            ],
        ]);
    }

    /**
     * Current sending usage against the configured plan limits. Resend does not
     * expose its quota over the API, so the limits come from config.
     *
     * @return array{daily: array{used: int, limit: int}, monthly: array{used: int, limit: int}}
     */
    private function usage(): array
    {
        return [
            'daily' => [
                'used' => EmailMessage::query()->where('sent_at', '>=', now()->startOfDay())->count(),
                'limit' => (int) config('services.resend.limits.daily'),
            ],
            'monthly' => [
                'used' => EmailMessage::query()->where('sent_at', '>=', now()->startOfMonth())->count(),
                'limit' => (int) config('services.resend.limits.monthly'),
            ],
        ];
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 2) : 0.0;
    }

    /**
     * A continuous day-by-day series for the trend chart across the selected
     * range: sends keyed by the day the message left, deliveries keyed by the day
     * the event landed. Days with no activity are filled with zeros so the x-axis
     * never has gaps.
     *
     * @return list<array{date: string, sent: int, delivered: int}>
     */
    private function dailyTrend(Carbon $from, Carbon $to): array
    {
        $sentByDay = EmailMessage::query()
            ->whereBetween('sent_at', [$from, $to])
            ->selectRaw('date(sent_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $delivered = EmailEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->where('type', EmailEventType::Delivery)
            ->selectRaw('date(occurred_at) as day, count(distinct message_id) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];
        $cursor = $from->copy()->startOfDay();
        $lastDay = $to->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($lastDay)) {
            $day = $cursor->toDateString();

            $series[] = [
                'date' => $day,
                'sent' => (int) ($sentByDay[$day] ?? 0),
                'delivered' => (int) ($delivered[$day] ?? 0),
            ];

            $cursor->addDay();
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
            ->when(
                $this->parseDate($request, 'date_from'),
                fn ($query, Carbon $from) => $query->where('sent_at', '>=', $from->startOfDay())
            )
            ->when(
                $this->parseDate($request, 'date_to'),
                fn ($query, Carbon $to) => $query->where('sent_at', '<=', $to->endOfDay())
            )
            ->when($request->string('search')->toString(), function ($query, $search) {
                // recipients is a json array; casting it to text lets a partial
                // match hit any address (e.g. "paulus" finds
                // "paulusfelix.92@gmail.com"). whereJsonContains only matched a
                // full, exact address, so a partial query returned nothing.
                // CAST(... AS TEXT) + LOWER is portable across Postgres/SQLite;
                // Postgres rejects LIKE on a json column directly.
                $recipientsLike = '%'.mb_strtolower($search).'%';

                $query->where(function ($query) use ($search, $recipientsLike) {
                    $query->whereLike('subject', "%{$search}%")
                        ->orWhereLike('from_address', "%{$search}%")
                        ->orWhereLike('message_id', "%{$search}%")
                        ->orWhereRaw('LOWER(CAST(recipients AS TEXT)) LIKE ?', [$recipientsLike]);
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

    /**
     * The message body lives only at Resend, not in our tables, so it is fetched
     * on demand and cached briefly. Resend prunes old emails per plan, so a
     * lookup that fails (expired, unreachable, or a non-Resend row) answers 200
     * with available:false rather than an error the page has to special-case.
     */
    public function content(EmailMessage $emailMessage): JsonResponse
    {
        $cacheKey = "resend:email-content:{$emailMessage->message_id}";

        if ($cached = Cache::get($cacheKey)) {
            return response()->json(['data' => $cached]);
        }

        if ($emailMessage->mailer !== 'resend') {
            return response()->json(['data' => ['available' => false]]);
        }

        try {
            $email = $this->resendEmails->get($emailMessage->message_id);
        } catch (\Throwable) {
            return response()->json(['data' => ['available' => false]]);
        }

        $payload = [
            'available' => true,
            'html' => $email['html'] ?? null,
            'text' => $email['text'] ?? null,
            'cc' => $email['cc'] ?? [],
            'bcc' => $email['bcc'] ?? [],
            'reply_to' => $email['reply_to'] ?? [],
            'tags' => $email['tags'] ?? [],
            'last_event' => $email['last_event'] ?? null,
            'scheduled_at' => $email['scheduled_at'] ?? null,
        ];

        // Only successful fetches are cached, so a transient Resend outage does
        // not lock the body away for the full window.
        Cache::put($cacheKey, $payload, now()->addMinutes(10));

        return response()->json(['data' => $payload]);
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
     * Removing a row here only lifts *our* block. Resend keeps its own
     * account-level suppression list, which has to be cleared in Resend.
     */
    public function destroySuppression(EmailSuppression $emailSuppression): JsonResponse
    {
        $emailSuppression->delete();

        return response()->json(['message' => 'Address removed from the suppression list.']);
    }

    /**
     * Resolves the requested date window, defaulting to the last 30 days. A
     * missing or malformed bound falls back to the default rather than erroring.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $to = ($this->parseDate($request, 'date_to') ?? now())->endOfDay();
        $from = ($this->parseDate($request, 'date_from') ?? $to->copy()->subDays(29))->startOfDay();

        // A backwards range would produce an empty, confusing chart.
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * Parses a "Y-m-d" query parameter into a Carbon date, or null when it is
     * absent or not a valid date.
     */
    private function parseDate(Request $request, string $key): ?Carbon
    {
        $raw = $request->string($key)->toString();

        if ($raw === '') {
            return null;
        }

        $date = Carbon::createFromFormat('Y-m-d', $raw);

        return $date === false ? null : $date;
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
