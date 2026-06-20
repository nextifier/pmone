<?php

namespace Database\Seeders;

use App\Enums\Ticketing\PurchaseType;
use App\Enums\Ticketing\TicketKind;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Services\Ticket\EventDayService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Migrates the hardcoded `tickets.js` data from each pmone-events event website
 * into real first-party Ticket records (+ a price phase + poster image) under
 * the matching PM One project's active event.
 *
 * Data source: database/seeders/data/tickets/{username}.json (extracted from the
 * apps' tickets.js). Posters: database/seeders/ticket-posters/{username}/ (both
 * committed to git so this runs on production).
 *
 * Decisions: tickets become `first_party` (purchased via PM One; the old
 * external link is kept in more_details for reference). The seeder does NOT flip
 * `events.tickets_enabled` — go-live stays a manual, per-event choice.
 *
 * Idempotent: Ticket upserted by (event_id, slug); the phase upserted by label;
 * the poster attached only when none exists. Safe to re-run.
 * Run: php artisan db:seed --class=TicketsSeeder
 */
class TicketsSeeder extends Seeder
{
    private const DATA_DIR = __DIR__.'/data/tickets';

    private const IMG_DIR = __DIR__.'/ticket-posters';

    public function run(): void
    {
        if (! File::isDirectory(self::DATA_DIR)) {
            $this->command?->warn('No ticket seed data directory found; nothing to seed.');

            return;
        }

        foreach (File::files(self::DATA_DIR) as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $username = $file->getFilenameWithoutExtension();
            $this->seedProject($username, (array) json_decode(File::get($file->getPathname()), true));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $tickets
     */
    private function seedProject(string $username, array $tickets): void
    {
        $project = Project::query()->where('username', $username)->first();
        if (! $project) {
            $this->command?->warn("Project '{$username}' not found - skipped.");

            return;
        }

        $event = $project->events()->where('is_active', true)->latest()->first()
            ?? $project->events()->latest()->first();

        if (! $event) {
            $this->command?->warn("Project '{$username}' has no event - skipped.");

            return;
        }

        // Events with day-scoped tickets need their days derived from the date
        // range first (id-stable). Build a day_number => EventDay id map for linking.
        $dayMap = collect();
        if (collect($tickets)->contains(fn ($t) => ! empty($t['valid_days']))) {
            $dayMap = app(EventDayService::class)
                ->syncFromEventDates($event)
                ->pluck('id', 'day_number');
        }

        $created = 0;
        foreach ($tickets as $index => $data) {
            if (empty($data['title'])) {
                continue;
            }
            $this->seedTicket($event, $username, $data, $index, $dayMap);
            $created++;
        }

        $this->command?->info("Seeded {$created} ticket(s) for '{$username}' (event #{$event->id}).");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seedTicket(Event $event, string $username, array $data, int $index, Collection $dayMap): void
    {
        $slug = $data['slug'] ?? str()->slug($data['title']);
        $onSale = (bool) ($data['on_sale'] ?? false);
        $status = (string) ($data['status'] ?? 'available');

        $ticket = Ticket::query()->updateOrCreate(
            ['event_id' => $event->id, 'slug' => $slug],
            [
                'kind' => ($data['kind'] ?? 'entry') === 'add_on' ? TicketKind::AddOn : TicketKind::Entry,
                'title' => ['en' => $data['title']],
                'tier' => $data['tier'] ?? null,
                'benefits' => $data['benefits'] ?? [],
                'currency' => 'IDR',
                'purchase_type' => PurchaseType::FirstParty,
                'external_url' => null,
                'more_details' => array_filter([
                    'legacy_external_url' => $data['legacy_external_url'] ?? null,
                    'price_label' => $data['price_label'] ?? null,
                    'day_pass' => $data['day_pass'] ?? null,
                    'entrance' => $data['entrance'] ?? null,
                    'category' => $data['category'] ?? null,
                ], fn ($v) => $v !== null),
                'stock' => $status === 'sold_out' ? 0 : null,
                'requires_day_selection' => (bool) ($data['requires_day_selection'] ?? false),
                'min_quantity' => 1,
                'max_quantity' => $data['max_quantity'] ?? null,
                'is_active' => true,
                'order_column' => $index + 1,
            ],
        );

        // Price phase: only when the ticket is actually on sale (a numeric price,
        // not "TBA"/"coming soon"). updateOrCreate by label keeps re-runs clean.
        if ($onSale && isset($data['price'])) {
            $ticket->pricePhases()->updateOrCreate(
                ['label' => 'Normal'],
                [
                    'price' => (float) $data['price'],
                    'starts_at' => $this->parseDate($data['starts_at'] ?? null),
                    'ends_at' => $this->parseDate($data['ends_at'] ?? null),
                    'is_active' => true,
                ],
            );
        } else {
            // Not on sale (coming soon / TBA): deactivate any leftover phases so a
            // previously-seeded price never lingers and the ticket reads as upcoming.
            $ticket->pricePhases()->update(['is_active' => false]);
        }

        // Day-scoped entry tickets (e.g. a per-day pass or a multi-day bundle):
        // link to the event days by their day_number.
        if (! empty($data['valid_days']) && $dayMap->isNotEmpty()) {
            $dayIds = collect($data['valid_days'])
                ->map(fn ($number) => $dayMap->get((int) $number))
                ->filter()
                ->values()
                ->all();
            $ticket->validDays()->sync($dayIds);
        }

        // Add-on sessions (e.g. Meet & Greet time slots). Upsert by label; the
        // SortableTrait assigns order_column in insertion order.
        foreach ($data['sessions'] ?? [] as $session) {
            if (empty($session['label'])) {
                continue;
            }
            $ticket->sessions()->updateOrCreate(
                ['label' => $session['label']],
                [
                    'starts_at' => $this->parseDate($session['starts_at'] ?? null),
                    'ends_at' => $this->parseDate($session['ends_at'] ?? null),
                    'location' => $session['location'] ?? null,
                    'host' => $session['host'] ?? null,
                    'capacity' => $session['capacity'] ?? null,
                    'is_active' => true,
                ],
            );
        }

        $this->attachPoster($ticket, $username, $data['poster'] ?? null);
    }

    private function attachPoster(Ticket $ticket, string $username, ?string $poster): void
    {
        if (! $poster || $ticket->hasMedia('poster')) {
            return;
        }

        $path = self::IMG_DIR.'/'.$username.'/'.$poster;
        if (! File::exists($path)) {
            return;
        }

        // preservingOriginal: keep the committed seed file in place (Spatie moves
        // by default), so the seeder stays re-runnable from git on production.
        $ticket->addMedia($path)->preservingOriginal()->toMediaCollection('poster');
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
