<?php

namespace Database\Seeders;

use App\Enums\Ticketing\TicketKind;
use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\Attendee;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\EventDay;
use App\Models\ExhibitorLead;
use App\Models\FieldResponse;
use App\Models\ScanLog;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\User;
use App\Services\Ticket\TicketPurchaseService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds a rich, varied demo dataset for Global AI Expo 2026 so every section of
 * the attendee analytics dashboard has realistic data: buyers (Users) with
 * orders spread over a 75-day campaign, mixed statuses + payment channels,
 * check-ins spread across event-day hours, Business Matching intake answers
 * across many field types, and exhibitor leads.
 *
 * Resettable + production-safe: each run first purges its own sentinel data
 * (orders by batch_label, buyers by email domain, seeded custom fields by a
 * settings marker, brands by slug prefix) then recreates it fresh - real public
 * data is never touched. Run with:
 *   php artisan db:seed --class=GlobalAiExpoAttendeesSeeder
 */
class GlobalAiExpoAttendeesSeeder extends Seeder
{
    private const PROJECT_USERNAME = 'globalaiexpo';

    private const BATCH_LABEL = 'Seeder: Global AI Expo Demo';

    /** Older sentinel labels purged on run so re-seeding cleans legacy demo data. */
    private const LEGACY_BATCH_LABELS = ['Seeder: Global AI Expo Attendees'];

    private const EMAIL_DOMAIN = 'gae-demo.test';

    private const BRAND_SLUG_PREFIX = 'gae-demo-';

    /** @var list<string> */
    private const CHANNELS = ['BCA', 'QRIS', 'MANDIRI', 'BNI', 'PERMATA'];

    private const BUYER_COUNT = 110;

    private const CAMPAIGN_DAYS = 75;

    public function run(): void
    {
        $event = Event::query()
            ->whereHas('project', fn ($q) => $q->where('username', self::PROJECT_USERNAME))
            ->where('is_active', true)
            ->latest()
            ->first();

        if (! $event) {
            $this->command?->warn('Global AI Expo event not found; nothing to seed.');

            return;
        }

        if (! $event->tickets_enabled || ! $event->business_matching_enabled) {
            $event->forceFill(['tickets_enabled' => true, 'business_matching_enabled' => true])->save();
        }

        $entryTickets = $this->entryTickets($event);
        if ($entryTickets->isEmpty()) {
            $this->command?->warn('No active entry tickets for Global AI Expo; run the tickets seeder first.');

            return;
        }

        $this->purge($event);

        $fields = $this->seedCustomFields($event);
        $confirmedAttendees = collect();
        $stats = ['buyers' => 0, 'orders' => 0, 'attendees' => 0, 'checked_in' => 0, 'responses' => 0];

        DB::transaction(function () use ($event, $entryTickets, $fields, &$confirmedAttendees, &$stats): void {
            $confirmedAttendees = $this->seedBuyersAndOrders($event, $entryTickets, $fields, $stats);
        });

        $leadCount = DB::transaction(fn (): int => $this->seedExhibitorLeads($event, $confirmedAttendees));

        $this->command?->info(sprintf(
            'Seeded %d buyers, %d orders, %d attendees (%d checked-in), %d BM answers, %d exhibitor leads.',
            $stats['buyers'], $stats['orders'], $stats['attendees'], $stats['checked_in'], $stats['responses'], $leadCount,
        ));
    }

    /**
     * @return Collection<int, Ticket>
     */
    private function entryTickets(Event $event): Collection
    {
        return Ticket::query()
            ->where('event_id', $event->id)
            ->where('kind', TicketKind::Entry)
            ->where('is_active', true)
            ->get()
            ->each(fn (Ticket $t) => $t->loadMissing('pricePhases'));
    }

    // -- Purge -----------------------------------------------------------------

    private function purge(Event $event): void
    {
        DB::transaction(function () use ($event): void {
            $seededBrandIds = Brand::query()
                ->where('slug', 'like', self::BRAND_SLUG_PREFIX.'%')
                ->pluck('id');

            ExhibitorLead::query()
                ->where('event_id', $event->id)
                ->whereIn('brand_id', $seededBrandIds)
                ->forceDelete();

            $orderIds = TicketOrder::withTrashed()
                ->where('event_id', $event->id)
                ->whereIn('batch_label', array_merge([self::BATCH_LABEL], self::LEGACY_BATCH_LABELS))
                ->pluck('id');

            if ($orderIds->isNotEmpty()) {
                $itemIds = TicketOrderItem::withTrashed()->whereIn('ticket_order_id', $orderIds)->pluck('id');
                $attendeeIds = Attendee::withTrashed()->whereIn('ticket_order_item_id', $itemIds)->pluck('id');

                ScanLog::query()->whereIn('attendee_id', $attendeeIds)->delete();
                ExhibitorLead::query()->whereIn('attendee_id', $attendeeIds)->forceDelete();
                Attendee::withTrashed()->whereIn('id', $attendeeIds)->forceDelete();
                TicketOrderItem::withTrashed()->whereIn('id', $itemIds)->forceDelete();
                TicketOrder::withTrashed()->whereIn('id', $orderIds)->forceDelete();
            }

            // This seeder owns the demo event's Business Matching field set, so
            // it replaces every custom field (cascade their FieldResponses).
            EventCustomField::query()
                ->where('event_id', $event->id)
                ->get()
                ->each(fn (EventCustomField $f) => $f->forceDelete());

            // Seeded buyers (cascade any remaining FieldResponses).
            User::withTrashed()
                ->where('email', 'like', '%@'.self::EMAIL_DOMAIN)
                ->get()
                ->each(fn (User $u) => $u->forceDelete());

            BrandEvent::query()->whereIn('brand_id', $seededBrandIds)->forceDelete();
            Brand::query()->whereIn('id', $seededBrandIds)->forceDelete();
        });
    }

    // -- Custom fields (Business Matching) -------------------------------------

    /**
     * @return Collection<string, EventCustomField> keyed by field key
     */
    private function seedCustomFields(Event $event): Collection
    {
        $defs = $this->customFieldDefs();
        $fields = collect();

        foreach ($defs as $order => $def) {
            $fields[$def['key']] = EventCustomField::query()->create([
                'event_id' => $event->id,
                'label' => $def['label'],
                'type' => $def['type'],
                'options' => $def['options'] ?? null,
                'required' => false,
                'is_active' => true,
                'settings' => ['seeded' => true],
                'order_column' => $order + 1,
            ]);
        }

        return $fields;
    }

    /**
     * Diverse intake fields covering options + numeric + text/date kinds.
     *
     * @return list<array<string, mixed>>
     */
    private function customFieldDefs(): array
    {
        return [
            ['key' => 'job_title', 'type' => 'text', 'label' => ['en' => 'Job title', 'id' => 'Jabatan']],
            ['key' => 'company_name', 'type' => 'text', 'label' => ['en' => 'Company name', 'id' => 'Nama perusahaan']],
            ['key' => 'company_size', 'type' => 'select', 'label' => ['en' => 'Company size', 'id' => 'Ukuran perusahaan'],
                'options' => ['1-10', '11-50', '51-200', '201-1000', '1000+']],
            ['key' => 'industry', 'type' => 'select', 'label' => ['en' => 'Industry', 'id' => 'Industri'],
                'options' => ['Technology', 'Finance', 'Healthcare', 'Education', 'Manufacturing', 'Retail', 'Government', 'Telco', 'Other']],
            ['key' => 'interests', 'type' => 'multi_select', 'label' => ['en' => 'Areas of interest', 'id' => 'Bidang minat'],
                'options' => ['Generative AI', 'Computer Vision', 'NLP', 'MLOps', 'Data Engineering', 'Robotics', 'Edge AI', 'AI Ethics']],
            ['key' => 'role', 'type' => 'radio', 'label' => ['en' => 'Primary role', 'id' => 'Peran utama'],
                'options' => ['Decision maker', 'Influencer', 'Practitioner', 'Student/Academic']],
            ['key' => 'goals', 'type' => 'checkbox_group', 'label' => ['en' => 'Goals at the expo', 'id' => 'Tujuan di expo'],
                'options' => ['Networking', 'Hiring', 'Find vendors', 'Learn', 'Invest', 'Partnerships']],
            ['key' => 'partnerships', 'type' => 'switch', 'label' => ['en' => 'Open to partnerships', 'id' => 'Terbuka untuk kemitraan']],
            ['key' => 'country', 'type' => 'country', 'label' => ['en' => 'Country', 'id' => 'Negara']],
            ['key' => 'experience', 'type' => 'number', 'label' => ['en' => 'Years of experience', 'id' => 'Tahun pengalaman']],
            ['key' => 'maturity', 'type' => 'linear_scale', 'label' => ['en' => 'Org AI maturity', 'id' => 'Kematangan AI organisasi']],
            ['key' => 'session_value', 'type' => 'rating', 'label' => ['en' => 'Expected session value', 'id' => 'Ekspektasi nilai sesi']],
            ['key' => 'date_of_birth', 'type' => 'date', 'label' => ['en' => 'Date of birth', 'id' => 'Tanggal lahir']],
        ];
    }

    // -- Buyers + orders + attendees + BM answers ------------------------------

    /**
     * @param  Collection<int, Ticket>  $entryTickets
     * @param  Collection<string, EventCustomField>  $fields
     * @param  array<string, int>  $stats
     * @return Collection<int, Attendee> confirmed attendees (for lead scanning)
     */
    private function seedBuyersAndOrders(Event $event, Collection $entryTickets, Collection $fields, array &$stats): Collection
    {
        $purchases = app(TicketPurchaseService::class);
        $gateway = $event->project?->activePaymentGateway();
        $staffId = $this->resolveStaffId();
        $weighted = $this->weightedTickets($entryTickets, $purchases);
        $eventDates = $this->eventDates($event);
        $confirmed = collect();

        for ($i = 1; $i <= self::BUYER_COUNT; $i++) {
            $name = $this->buyerName($i);
            $email = $this->emailFor($name, $i);

            $optIn = $this->chance(0.65);
            $buyer = User::factory()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(16)),
                'email_verified_at' => CarbonImmutable::now(),
                'business_matching_opt_in' => $optIn,
            ]);
            $stats['buyers']++;

            $status = $this->orderStatus();
            $createdAt = $this->campaignTimestamp();

            /** @var Ticket $ticket */
            $ticket = $weighted[random_int(0, count($weighted) - 1)];
            $phase = $purchases->resolveActivePhase($ticket);
            $unitPrice = $phase ? (float) $phase->price : 0.0;
            $quantity = $this->quantity();
            $total = $unitPrice * $quantity;
            $confirmedPaid = $status === TicketOrderStatus::Confirmed && $total > 0;

            $order = TicketOrder::query()->create([
                'event_id' => $event->id,
                'user_id' => $buyer->id,
                'status' => $status,
                'buyer_name' => $name,
                'buyer_email' => $email,
                'buyer_phone' => $this->phone(),
                'subtotal' => $total,
                'discount_amount' => 0,
                'total' => $total,
                'payment_channel' => $confirmedPaid ? self::CHANNELS[$i % count(self::CHANNELS)] : null,
                'payment_gateway_id' => $confirmedPaid ? $gateway?->id : null,
                'payment_ref' => $confirmedPaid ? 'SEED-'.Str::upper(Str::random(10)) : null,
                'paid_at' => $status === TicketOrderStatus::Confirmed ? $createdAt : null,
                'source' => 'seeder',
                'batch_label' => self::BATCH_LABEL,
            ]);
            $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
            $stats['orders']++;

            $item = $order->items()->create([
                'ticket_id' => $ticket->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'phase_label' => $phase?->label,
                'subtotal' => $total,
            ]);

            for ($n = 1; $n <= $quantity; $n++) {
                $attendeeName = $n === 1 ? $name : $this->buyerName($i * 100 + $n);
                $checkIn = $status === TicketOrderStatus::Confirmed && $this->chance(0.55);
                $checkedAt = $checkIn ? $this->checkInTimestamp($eventDates) : null;

                $attendee = $item->attendees()->create([
                    'ticket_id' => $ticket->id,
                    'name' => $attendeeName,
                    'email' => $n === 1 ? $email : $this->emailFor($attendeeName, $i * 100 + $n),
                    'personalized_at' => $createdAt,
                    'checked_in_at' => $checkedAt,
                    'checked_in_by' => $checkIn ? $staffId : null,
                    'checkin_event_id' => $checkIn ? $event->id : null,
                ]);
                $stats['attendees']++;
                $stats['checked_in'] += $checkIn ? 1 : 0;

                if ($status === TicketOrderStatus::Confirmed) {
                    $confirmed->push($attendee);
                }
            }

            if ($optIn && $status === TicketOrderStatus::Confirmed) {
                $stats['responses'] += $this->seedBusinessMatching($buyer, $fields);
            }
        }

        return $confirmed;
    }

    /**
     * @param  Collection<string, EventCustomField>  $fields
     */
    private function seedBusinessMatching(User $buyer, Collection $fields): int
    {
        $count = 0;

        foreach ($fields as $key => $field) {
            // Each field answered ~85% of the time so coverage varies per field.
            if (! $this->chance(0.85)) {
                continue;
            }

            $value = $this->bmValue($key, $field);
            if ($value === null) {
                continue;
            }

            FieldResponse::query()->create([
                'user_id' => $buyer->id,
                'event_custom_field_id' => $field->id,
                'value' => is_array($value) ? $value : [$value],
            ]);
            $count++;
        }

        return $count;
    }

    private function bmValue(string $key, EventCustomField $field): mixed
    {
        // Choice answers must come from THIS field's own options (two different
        // select fields exist), not a shared per-type list.
        $options = array_values((array) ($field->options ?? []));

        return match ($field->type) {
            'select', 'radio' => $options ? $this->pick($options) : null,
            'multi_select', 'checkbox_group' => $options ? $this->pickSubset($options, 1, 4) : null,
            'switch' => $this->chance(0.5),
            'country' => $this->weightedCountry(),
            'number' => random_int(0, 30),
            'linear_scale' => $this->weightedScale(),
            'rating' => $this->weightedRating(),
            'date' => CarbonImmutable::create(random_int(1972, 2003), random_int(1, 12), random_int(1, 28))->format('Y-m-d'),
            'text' => $key === 'company_name'
                ? $this->pick(['Acme AI', 'Nusantara Tech', 'Garuda Labs', 'Sinar Data', 'Merdeka Cloud', 'Quantum Nusantara', 'Andalan Systems', 'Bandung AI Labs', 'Sentul Robotics', 'Jaya Digital'])
                : $this->pick(['Software Engineer', 'Data Scientist', 'Product Manager', 'CTO', 'Founder', 'ML Engineer', 'Research Scientist', 'VP Engineering', 'Business Analyst', 'Consultant']),
            default => null,
        };
    }

    // -- Exhibitor leads -------------------------------------------------------

    /**
     * @param  Collection<int, Attendee>  $confirmedAttendees
     */
    private function seedExhibitorLeads(Event $event, Collection $confirmedAttendees): int
    {
        if ($confirmedAttendees->isEmpty()) {
            return 0;
        }

        $brandNames = ['Nvidia Nusantara', 'Telkom AI', 'GoTo Labs', 'Bukalapak Data', 'Tokopedia ML', 'Bank Mandiri AI', 'Astra Digital', 'Pertamina Tech', 'Sinarmas Cloud', 'Traveloka AI'];
        $staffId = $this->resolveStaffId();
        $eventDates = $this->eventDates($event);
        $created = 0;

        foreach ($brandNames as $idx => $brandName) {
            $brand = Brand::factory()->create([
                'name' => $brandName,
                'slug' => self::BRAND_SLUG_PREFIX.Str::slug($brandName),
            ]);
            BrandEvent::factory()->create([
                'brand_id' => $brand->id,
                'event_id' => $event->id,
                'booth_number' => 'B'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
            ]);

            // Each exhibitor captures a different-sized, distinct set of leads.
            $scanCount = random_int(5, min(30, $confirmedAttendees->count()));
            $scanned = $confirmedAttendees->shuffle()->take($scanCount);

            foreach ($scanned as $attendee) {
                ExhibitorLead::query()->create([
                    'brand_id' => $brand->id,
                    'attendee_id' => $attendee->id,
                    'event_id' => $event->id,
                    'scanned_by' => $staffId,
                    'scanned_at' => $this->checkInTimestamp($eventDates),
                    'snapshot' => [
                        'name' => $attendee->name,
                        'email' => $attendee->email,
                        'phone' => $attendee->phone,
                        'ticket_tier' => $attendee->ticket?->tier,
                    ],
                ]);
                $created++;
            }
        }

        return $created;
    }

    // -- Helpers ---------------------------------------------------------------

    /**
     * @param  Collection<int, Ticket>  $entryTickets
     * @return list<Ticket>
     */
    private function weightedTickets(Collection $entryTickets, TicketPurchaseService $purchases): array
    {
        // Cheaper tickets sell more: weight inversely to price rank.
        $sorted = $entryTickets
            ->sortBy(fn (Ticket $t): float => (float) ($purchases->resolveActivePhase($t)?->price ?? 0))
            ->values();

        $bag = [];
        foreach ($sorted as $rank => $ticket) {
            $weight = max(1, 6 - $rank);
            for ($w = 0; $w < $weight; $w++) {
                $bag[] = $ticket;
            }
        }

        return $bag;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function eventDates(Event $event): array
    {
        $dates = EventDay::query()
            ->where('event_id', $event->id)
            ->orderBy('day_number')
            ->pluck('date')
            ->map(fn ($d): CarbonImmutable => CarbonImmutable::parse($d))
            ->all();

        return $dates ?: [CarbonImmutable::parse($event->start_date ?? CarbonImmutable::now())];
    }

    private function orderStatus(): TicketOrderStatus
    {
        $roll = random_int(1, 100);

        return match (true) {
            $roll <= 85 => TicketOrderStatus::Confirmed,
            $roll <= 95 => TicketOrderStatus::PendingPayment,
            default => TicketOrderStatus::Expired,
        };
    }

    private function campaignTimestamp(): CarbonImmutable
    {
        // Squared bias toward recent days so the cumulative trend curves upward.
        $daysAgo = (int) round(self::CAMPAIGN_DAYS * ($this->frand() ** 2));

        return CarbonImmutable::now()
            ->subDays($daysAgo)
            ->setTime(random_int(8, 22), random_int(0, 59));
    }

    /**
     * @param  list<CarbonImmutable>  $eventDates
     */
    private function checkInTimestamp(array $eventDates): CarbonImmutable
    {
        $day = $eventDates[random_int(0, count($eventDates) - 1)];

        return $day->setTime(random_int(9, 17), random_int(0, 59));
    }

    private function quantity(): int
    {
        $roll = random_int(1, 100);

        return match (true) {
            $roll <= 70 => 1,
            $roll <= 92 => 2,
            default => 3,
        };
    }

    private function weightedCountry(): string
    {
        if ($this->chance(0.6)) {
            return 'Indonesia';
        }

        return $this->pick(['Singapore', 'Malaysia', 'India', 'United States', 'Australia', 'Japan', 'South Korea', 'United Kingdom', 'Germany', 'Philippines', 'Vietnam', 'Thailand']);
    }

    private function weightedScale(): int
    {
        return $this->pick([1, 2, 3, 3, 3, 4, 4, 4, 5]);
    }

    private function weightedRating(): int
    {
        return $this->pick([2, 3, 4, 4, 4, 5, 5, 5]);
    }

    private function buyerName(int $seed): string
    {
        $first = ['Budi', 'Siti', 'Andi', 'Dewi', 'Rizki', 'Putri', 'Eko', 'Rina', 'Agus', 'Maya', 'Fajar', 'Indah', 'Hendra', 'Lia', 'Doni', 'Sri', 'Bayu', 'Nadia', 'Reza', 'Anita', 'Bambang', 'Clara', 'Gilang', 'Vania', 'Yusuf', 'Tania', 'Arif', 'Mega', 'Dimas', 'Ayu', 'Teguh', 'Fitri', 'Rangga', 'Sasha', 'Iwan', 'Linda', 'Yoga', 'Cinta', 'Galih', 'Bella'];
        $last = ['Santoso', 'Nurhaliza', 'Wijaya', 'Lestari', 'Pratama', 'Maharani', 'Saputra', 'Kartika', 'Setiawan', 'Anggraini', 'Nugroho', 'Permata', 'Gunawan', 'Marlina', 'Kurniawan', 'Wahyuni', 'Pahlevi', 'Salim', 'Sutrisno', 'Ramadhan', 'Hidayat', 'Pertiwi', 'Rahman', 'Puspita', 'Prayoga', 'Larasati', 'Iskandar', 'Handayani', 'Mahendra', 'Octaviani'];

        return $first[$seed % count($first)].' '.$last[intdiv($seed, count($first)) % count($last)];
    }

    private function emailFor(string $name, int $index): string
    {
        $handle = Str::of($name)->lower()->ascii()->replaceMatches('/[^a-z]+/', '.')->trim('.');

        return "{$handle}.{$index}@".self::EMAIL_DOMAIN;
    }

    private function phone(): string
    {
        return '0811'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    }

    private function resolveStaffId(): ?int
    {
        $staff = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['master', 'admin', 'staff']))
            ->value('id');

        return $staff ?? User::query()->value('id');
    }

    private function frand(): float
    {
        return random_int(0, 1_000_000) / 1_000_000;
    }

    private function chance(float $p): bool
    {
        return $this->frand() < $p;
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[random_int(0, count($items) - 1)];
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function pickSubset(array $items, int $min, int $max): array
    {
        shuffle($items);

        return array_values(array_slice($items, 0, random_int($min, min($max, count($items)))));
    }
}
