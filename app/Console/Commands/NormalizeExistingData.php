<?php

namespace App\Console\Commands;

use App\Models\Attendee;
use App\Models\Brand;
use App\Models\Contact;
use App\Models\CustomFieldValue;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Project;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\TicketOrder;
use App\Models\TicketWaitlistEntry;
use App\Models\User;
use App\Support\CustomFieldValues;
use App\Support\InputNormalizer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Spatie\ResponseCache\Facades\ResponseCache;

class NormalizeExistingData extends Command
{
    protected $signature = 'data:normalize
        {--table=* : Limit to specific targets (users, contacts, reservations, reservation_items, attendees, ticket_orders, ticket_waitlist_entries, hotels, brands, projects, guests, forms, form_responses, custom_field_values)}
        {--dry-run : Report what would change without writing anything}
        {--force : Apply without the confirmation prompt}
        {--chunk=200 : Rows per chunk}';

    protected $description = 'Normalize existing user-input data in place (emails lowercase, person names title case, phones international). Idempotent: rows already normalized are skipped.';

    /**
     * Targets whose fields come straight from the model's NormalizesAttributes
     * map, so the backfill can never drift from the runtime normalization.
     *
     * @var array<string, class-string<Model>>
     */
    private const MODEL_TARGETS = [
        'users' => User::class,
        'contacts' => Contact::class,
        'reservations' => Reservation::class,
        'reservation_items' => ReservationItem::class,
        'attendees' => Attendee::class,
        'ticket_orders' => TicketOrder::class,
        'ticket_waitlist_entries' => TicketWaitlistEntry::class,
        'hotels' => Hotel::class,
        'brands' => Brand::class,
        'projects' => Project::class,
        'guests' => Guest::class,
    ];

    private const JSON_TARGETS = ['forms', 'form_responses', 'custom_field_values'];

    private const SAMPLE_LIMIT = 5;

    /** @var array<int, array{string, int, int, int}> */
    private array $summary = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = (int) $this->option('chunk');
        $targets = $this->resolveTargets();

        if ($targets === []) {
            $this->error('No valid targets. Available: '.implode(', ', array_merge(array_keys(self::MODEL_TARGETS), self::JSON_TARGETS)));

            return self::FAILURE;
        }

        if (! $dryRun && ! $this->option('force')) {
            if (! $this->confirm('This rewrites existing rows in place ('.implode(', ', $targets).'). Continue?')) {
                $this->warn('Aborted.');

                return self::SUCCESS;
            }
        }

        foreach ($targets as $target) {
            match (true) {
                isset(self::MODEL_TARGETS[$target]) => $this->processModelTarget($target, self::MODEL_TARGETS[$target], $dryRun, $chunk),
                $target === 'forms' => $this->processForms($dryRun, $chunk),
                $target === 'form_responses' => $this->processFormResponses($dryRun, $chunk),
                $target === 'custom_field_values' => $this->processCustomFieldValues($dryRun, $chunk),
            };
        }

        $this->newLine();
        $this->table(['Target', 'Scanned', $dryRun ? 'Would change' : 'Changed', 'Failed'], $this->summary);

        $totalChanged = array_sum(array_column($this->summary, 2));

        if (! $dryRun && $totalChanged > 0) {
            ResponseCache::clear();
            $this->comment('Cleared response cache.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function resolveTargets(): array
    {
        $available = array_merge(array_keys(self::MODEL_TARGETS), self::JSON_TARGETS);
        $requested = (array) $this->option('table');

        if ($requested === []) {
            return $available;
        }

        foreach (array_diff($requested, $available) as $unknown) {
            $this->warn("Unknown target '{$unknown}' ignored.");
        }

        return array_values(array_intersect($available, $requested));
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function processModelTarget(string $target, string $modelClass, bool $dryRun, int $chunk): void
    {
        $map = (new $modelClass)->normalizes();
        $stats = ['scanned' => 0, 'changed' => 0, 'failed' => 0];
        $samples = 0;

        $modelClass::query()->withoutGlobalScopes()->chunkById($chunk, function ($rows) use ($target, $map, $dryRun, &$stats, &$samples) {
            foreach ($rows as $row) {
                $stats['scanned']++;
                $changes = [];

                foreach ($map as $attribute => $method) {
                    $original = $row->{$attribute};

                    if ($original === null) {
                        continue;
                    }

                    $normalized = InputNormalizer::{$method}($original);

                    if ($normalized !== $original) {
                        $changes[$attribute] = [$original, $normalized];
                        $row->{$attribute} = $normalized;
                    }
                }

                if ($target === 'projects') {
                    $settings = $this->normalizedProjectSettings($row->settings);

                    if ($settings !== null) {
                        $changes['settings.hotels.notification_email'] = ['(mixed case)', '(lowercased)'];
                        $row->settings = $settings;
                    }
                }

                if ($changes === []) {
                    continue;
                }

                if ($samples < self::SAMPLE_LIMIT) {
                    $samples++;
                    $this->printSample($target, $row->getKey(), $changes);
                }

                $this->persist($row, $dryRun, $stats);
            }
        });

        $this->summary[] = [$target, $stats['scanned'], $stats['changed'], $stats['failed']];
    }

    /**
     * Returns the rewritten settings array, or null when nothing changed.
     *
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>|null
     */
    private function normalizedProjectSettings(?array $settings): ?array
    {
        if ($settings === null) {
            return null;
        }

        $changed = false;

        foreach (['to', 'cc', 'bcc'] as $list) {
            $path = "website_settings.hotels.notification_email.{$list}";
            $emails = data_get($settings, $path);

            if (! is_array($emails)) {
                continue;
            }

            $normalized = InputNormalizer::emailList($emails);

            if ($normalized !== $emails) {
                data_set($settings, $path, $normalized);
                $changed = true;
            }
        }

        return $changed ? $settings : null;
    }

    private function processForms(bool $dryRun, int $chunk): void
    {
        $stats = ['scanned' => 0, 'changed' => 0, 'failed' => 0];

        Form::query()->withoutGlobalScopes()->chunkById($chunk, function ($forms) use ($dryRun, &$stats) {
            foreach ($forms as $form) {
                $stats['scanned']++;
                $settings = $form->settings;

                if (! is_array($settings)) {
                    continue;
                }

                $changed = false;

                foreach (['to', 'cc', 'bcc'] as $list) {
                    $emails = data_get($settings, "notification_emails.{$list}");

                    if (! is_array($emails)) {
                        continue;
                    }

                    $normalized = InputNormalizer::emailList($emails);

                    if ($normalized !== $emails) {
                        data_set($settings, "notification_emails.{$list}", $normalized);
                        $changed = true;
                    }
                }

                if (! $changed) {
                    continue;
                }

                $form->settings = $settings;
                $this->persist($form, $dryRun, $stats);
            }
        });

        $this->summary[] = ['forms', $stats['scanned'], $stats['changed'], $stats['failed']];
    }

    private function processFormResponses(bool $dryRun, int $chunk): void
    {
        $stats = ['scanned' => 0, 'changed' => 0, 'failed' => 0];

        FormResponse::query()->withoutGlobalScopes()->with('form.fields')->chunkById($chunk, function ($responses) use ($dryRun, &$stats) {
            foreach ($responses as $response) {
                $stats['scanned']++;

                $email = $response->respondent_email;
                $normalizedEmail = $email === null ? null : InputNormalizer::email($email);

                if ($normalizedEmail !== $email) {
                    $response->respondent_email = $normalizedEmail;
                }

                $data = $response->response_data;

                if (is_array($data) && $response->form) {
                    $changed = false;

                    foreach ($response->form->fields as $field) {
                        if (! array_key_exists($field->ulid, $data)) {
                            continue;
                        }

                        $normalized = CustomFieldValues::normalizeByType($field, $data[$field->ulid]);

                        if ($normalized !== $data[$field->ulid]) {
                            $data[$field->ulid] = $normalized;
                            $changed = true;
                        }
                    }

                    if ($changed) {
                        $response->response_data = $data;
                    }
                }

                if ($response->isDirty()) {
                    $this->persist($response, $dryRun, $stats);
                }
            }
        });

        $this->summary[] = ['form_responses', $stats['scanned'], $stats['changed'], $stats['failed']];
    }

    private function processCustomFieldValues(bool $dryRun, int $chunk): void
    {
        $stats = ['scanned' => 0, 'changed' => 0, 'failed' => 0];

        CustomFieldValue::query()
            ->whereHas('customField', fn ($query) => $query->whereIn('type', ['email', 'phone']))
            ->with('customField')
            ->chunkById($chunk, function ($values) use ($dryRun, &$stats) {
                foreach ($values as $value) {
                    $stats['scanned']++;

                    $normalized = CustomFieldValues::normalizeByType($value->customField, $value->value);

                    if ($normalized === $value->value) {
                        continue;
                    }

                    $value->value = $normalized;
                    $this->persist($value, $dryRun, $stats);
                }
            });

        $this->summary[] = ['custom_field_values', $stats['scanned'], $stats['changed'], $stats['failed']];
    }

    /**
     * @param  array{scanned: int, changed: int, failed: int}  $stats
     */
    private function persist(Model $row, bool $dryRun, array &$stats): void
    {
        if ($dryRun) {
            $stats['changed']++;

            return;
        }

        try {
            $row->saveQuietly();
            $stats['changed']++;
        } catch (\Throwable $e) {
            $stats['failed']++;
            $this->warn('  ! '.$row::class." #{$row->getKey()}: ".$e->getMessage());
        }
    }

    /**
     * @param  array<string, array{mixed, mixed}>  $changes
     */
    private function printSample(string $target, mixed $key, array $changes): void
    {
        foreach ($changes as $attribute => [$old, $new]) {
            $this->line("  {$target} #{$key} {$attribute}: ".$this->formatSampleValue($old).' -> '.$this->formatSampleValue($new));
        }
    }

    private function formatSampleValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_array($value) => (string) json_encode($value),
            default => '"'.$value.'"',
        };
    }
}
