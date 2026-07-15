<?php

namespace App\Console\Commands;

use App\Enums\EmailEventType;
use App\Models\EmailMessage;
use App\Services\Resend\ResendEmailApi;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncResendEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:sync-resend
                            {--full : Walk the entire history instead of stopping at the overlap window}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill the local email history from the Resend API and keep statuses current';

    /**
     * Resend lists newest-first and paginates by cursor. We page from newest to
     * oldest, upserting each email, and stop once we have walked two days past
     * the most recent row we already hold - webhooks keep the fresh tail
     * up to date, so re-walking the whole history every run is wasteful.
     */
    public function handle(ResendEmailApi $api): int
    {
        $full = (bool) $this->option('full');

        $overlapCutoff = null;

        if (! $full) {
            $latest = EmailMessage::query()->max('sent_at');
            $overlapCutoff = $latest ? Carbon::parse($latest)->subDays(2) : null;
        }

        $after = null;
        $created = 0;
        $updated = 0;
        $pages = 0;

        do {
            try {
                $page = $api->list($after, 100);
            } catch (\Throwable $e) {
                $this->error("Resend API request failed: {$e->getMessage()}");

                return self::FAILURE;
            }

            $items = $page['data'];

            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                $this->upsert($item) ? $created++ : $updated++;
            }

            $pages++;

            $oldest = Carbon::parse($items[array_key_last($items)]['created_at']);
            $after = $items[array_key_last($items)]['id'];
            $hasMore = $page['has_more'];

            if ($overlapCutoff !== null && $oldest->lessThan($overlapCutoff)) {
                break;
            }

            if ($hasMore) {
                // Resend allows 5 requests/second per team; stay well under it.
                usleep(250_000);
            }
        } while ($hasMore);

        $this->info("Synced {$pages} page(s): {$created} created, {$updated} updated.");

        return self::SUCCESS;
    }

    /**
     * Upserts one Resend email into email_messages, advancing its status only
     * forward so a webhook-recorded bounce is never downgraded by a later sync.
     *
     * @param  array<string, mixed>  $item
     * @return bool True when a new row was created.
     */
    private function upsert(array $item): bool
    {
        $createdAt = Carbon::parse($item['created_at']);

        $message = EmailMessage::query()->firstOrNew(['message_id' => $item['id']]);
        $isNew = ! $message->exists;

        $message->fill([
            'mailer' => 'resend',
            'from_address' => $item['from'] ?? '',
            'subject' => $item['subject'] ?? null,
            'recipients' => $item['to'] ?? [],
            'sent_at' => $createdAt,
        ]);

        if ($isNew) {
            $message->status = EmailEventType::Send;
            $message->status_rank = EmailEventType::Send->rank();
            $message->last_event_at = null;
        }

        $message->save();

        $type = EmailEventType::fromResendLastEvent($item['last_event'] ?? '');

        if ($type !== null) {
            $message->applyEvent($type, $createdAt);
        }

        return $isNew;
    }
}
