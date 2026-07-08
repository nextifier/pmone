<?php

namespace App\Services;

use App\Models\CustomField;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventDocumentSubmission;
use App\Models\Form;
use App\Models\Project;
use App\Models\User;
use App\Support\FormFieldTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * One-way backfill of the legacy field-definition tables (form_fields,
 * event_custom_fields, project_custom_fields, event_documents) into the
 * unified custom_fields table, plus the field_responses -> custom_field_values
 * and document-submission answer remaps. Idempotent and re-runnable: the
 * migration calls it once, and `php artisan custom-fields:backfill` re-runs it
 * on production to catch rows written by old code during the deploy window.
 * Reads/writes go through DB::table so later model changes can never break it.
 */
class CustomFieldMigrator
{
    private const CHUNK = 500;

    public function run(): void
    {
        $this->backfillFormFields();
        $this->backfillBusinessMatchingFields();
        $this->backfillFieldResponses();
        $this->backfillBrandFields();
        $this->backfillDocumentFields();
        $this->backfillDocumentSubmissionValues();

        // DB::table writes fire no model events, so the response caches that
        // normally clear via CustomField's saved hook must be cleared manually.
        ResponseCache::clear(['forms-public', 'tickets', 'brands']);
    }

    private function backfillFormFields(): void
    {
        if (! Schema::hasTable('form_fields')) {
            return;
        }

        DB::table('form_fields')->orderBy('id')->chunkById(self::CHUNK, function ($rows) {
            $existing = DB::table('custom_fields')
                ->whereIn('ulid', $rows->pluck('ulid'))
                ->pluck('ulid')
                ->all();

            $inserts = [];

            foreach ($rows as $row) {
                if (in_array($row->ulid, $existing, true)) {
                    continue;
                }

                $inserts[] = [
                    'ulid' => $row->ulid,
                    'fieldable_type' => Form::class,
                    'fieldable_id' => $row->form_id,
                    'context' => CustomField::CONTEXT_FORM,
                    'type' => $row->type,
                    'label' => json_encode(['en' => $row->label]),
                    'placeholder' => $row->placeholder !== null ? json_encode(['en' => $row->placeholder]) : null,
                    'help_text' => $row->help_text !== null ? json_encode(['en' => $row->help_text]) : null,
                    'options' => $row->options,
                    'validation' => $row->validation,
                    'settings' => $row->settings,
                    'legacy_id' => $row->id,
                    'is_active' => true,
                    'order_column' => $row->order_column,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }

            $this->insertChunked($inserts);
        });
    }

    private function backfillBusinessMatchingFields(): void
    {
        if (! Schema::hasTable('event_custom_fields')) {
            return;
        }

        DB::table('event_custom_fields')->orderBy('id')->chunkById(self::CHUNK, function ($rows) {
            $existing = DB::table('custom_fields')
                ->where('context', CustomField::CONTEXT_BUSINESS_MATCHING)
                ->whereIn('legacy_id', $rows->pluck('id'))
                ->pluck('legacy_id')
                ->all();

            $inserts = [];

            foreach ($rows as $row) {
                if (in_array($row->id, $existing)) {
                    continue;
                }

                $validation = $this->decode($row->settings)['validation'] ?? [];
                $validation = is_array($validation) ? $validation : [];
                $validation['required'] = (bool) $row->required;

                $inserts[] = [
                    'ulid' => (string) Str::ulid(),
                    'fieldable_type' => Event::class,
                    'fieldable_id' => $row->event_id,
                    'context' => CustomField::CONTEXT_BUSINESS_MATCHING,
                    'type' => $row->type,
                    'label' => json_encode($this->translationsFrom($row->label)),
                    'options' => $this->encodeOptions($this->decode($row->options)),
                    'validation' => json_encode($validation),
                    'settings' => $row->settings,
                    'legacy_id' => $row->id,
                    'is_active' => (bool) $row->is_active,
                    'order_column' => $row->order_column,
                    'created_by' => $row->created_by,
                    'updated_by' => $row->updated_by,
                    'deleted_by' => $row->deleted_by,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'deleted_at' => $row->deleted_at,
                ];
            }

            $this->insertChunked($inserts);
        });
    }

    private function backfillFieldResponses(): void
    {
        if (! Schema::hasTable('field_responses')) {
            return;
        }

        $fieldMap = DB::table('custom_fields')
            ->where('context', CustomField::CONTEXT_BUSINESS_MATCHING)
            ->whereNotNull('legacy_id')
            ->pluck('id', 'legacy_id');

        DB::table('field_responses')->orderBy('id')->chunkById(self::CHUNK, function ($rows) use ($fieldMap) {
            $inserts = [];

            foreach ($rows as $row) {
                $customFieldId = $fieldMap->get($row->event_custom_field_id);

                if ($customFieldId === null) {
                    continue;
                }

                $inserts[] = [
                    'custom_field_id' => $customFieldId,
                    'subject_type' => User::class,
                    'subject_id' => $row->user_id,
                    'value' => $row->value,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }

            foreach (array_chunk($inserts, 100) as $chunk) {
                DB::table('custom_field_values')->insertOrIgnore($chunk);
            }
        });
    }

    private function backfillBrandFields(): void
    {
        if (! Schema::hasTable('project_custom_fields')) {
            return;
        }

        DB::table('project_custom_fields')->orderBy('id')->chunkById(self::CHUNK, function ($rows) {
            $existing = DB::table('custom_fields')
                ->where('context', CustomField::CONTEXT_BRAND)
                ->whereIn('legacy_id', $rows->pluck('id'))
                ->pluck('legacy_id')
                ->all();

            $inserts = [];

            foreach ($rows as $row) {
                if (in_array($row->id, $existing)) {
                    continue;
                }

                $isYearSelect = $row->type === 'year_select';

                $inserts[] = [
                    'ulid' => (string) Str::ulid(),
                    'fieldable_type' => Project::class,
                    'fieldable_id' => $row->project_id,
                    'context' => CustomField::CONTEXT_BRAND,
                    'type' => $isYearSelect ? CustomField::TYPE_SELECT : $row->type,
                    'label' => json_encode(['en' => $row->label]),
                    'options' => $isYearSelect ? null : $this->encodeOptions($this->decode($row->options)),
                    'validation' => json_encode(['required' => (bool) $row->is_required]),
                    'settings' => $isYearSelect ? json_encode(['options_preset' => 'years']) : null,
                    'key' => $row->key,
                    'legacy_id' => $row->id,
                    'is_active' => true,
                    'order_column' => $row->order_column,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }

            $this->insertChunked($inserts);
        });
    }

    private function backfillDocumentFields(): void
    {
        if (! Schema::hasTable('event_documents')) {
            return;
        }

        DB::table('event_documents')->orderBy('id')->chunkById(self::CHUNK, function ($rows) {
            $existing = DB::table('custom_fields')
                ->where('context', CustomField::CONTEXT_DOCUMENT)
                ->whereIn('legacy_id', $rows->pluck('id'))
                ->pluck('legacy_id')
                ->all();

            $inserts = [];

            foreach ($rows as $row) {
                if (in_array($row->id, $existing)) {
                    continue;
                }

                $synthesized = $this->synthesizedDocumentField($row->document_type, (bool) $row->is_required);

                if ($synthesized === null) {
                    continue;
                }

                $inserts[] = array_merge($synthesized, [
                    'ulid' => (string) Str::ulid(),
                    'fieldable_type' => EventDocument::class,
                    'fieldable_id' => $row->id,
                    'context' => CustomField::CONTEXT_DOCUMENT,
                    'legacy_id' => $row->id,
                    'is_active' => true,
                    'order_column' => 1,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }

            $this->insertChunked($inserts);
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function synthesizedDocumentField(string $documentType, bool $required): ?array
    {
        return match ($documentType) {
            'checkbox_agreement' => [
                'type' => CustomField::TYPE_CHECKBOX,
                'system_key' => 'agreement',
                'label' => json_encode(['en' => 'I have read and agree to this document']),
                'validation' => json_encode(['required' => $required]),
                'settings' => json_encode(['legacy' => true]),
            ],
            'text_input' => [
                'type' => CustomField::TYPE_TEXTAREA,
                'system_key' => 'legacy_text',
                'label' => json_encode(['en' => 'Response']),
                'validation' => json_encode(['required' => $required]),
                'settings' => json_encode(['legacy' => true]),
            ],
            'file_upload' => [
                'type' => CustomField::TYPE_FILE,
                'system_key' => 'legacy_file',
                'label' => json_encode(['en' => 'Upload file']),
                'validation' => json_encode([
                    'required' => $required,
                    'max_file_size' => 51200,
                    'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'],
                ]),
                'settings' => json_encode(['legacy' => true, 'multiple' => false]),
            ],
            default => null,
        };
    }

    private function backfillDocumentSubmissionValues(): void
    {
        if (! Schema::hasTable('event_document_submissions') || ! Schema::hasColumn('event_document_submissions', 'field_values')) {
            return;
        }

        $fields = DB::table('custom_fields')
            ->where('context', CustomField::CONTEXT_DOCUMENT)
            ->whereNotNull('legacy_id')
            ->get(['ulid', 'type', 'legacy_id'])
            ->keyBy('legacy_id');

        DB::table('event_document_submissions')
            ->whereNull('field_values')
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($rows) use ($fields) {
                foreach ($rows as $row) {
                    $field = $fields->get($row->event_document_id);

                    if ($field === null) {
                        continue;
                    }

                    $value = match ($field->type) {
                        CustomField::TYPE_CHECKBOX => $row->agreed_at !== null,
                        CustomField::TYPE_TEXTAREA => $row->text_value,
                        CustomField::TYPE_FILE => $this->submissionFileMediaIds($row->id),
                        default => null,
                    };

                    if ($value === null || $value === false || $value === [] || $value === '') {
                        continue;
                    }

                    DB::table('event_document_submissions')
                        ->where('id', $row->id)
                        ->update(['field_values' => json_encode([$field->ulid => $value])]);
                }
            });
    }

    /**
     * @return array<int, int>
     */
    private function submissionFileMediaIds(int $submissionId): array
    {
        return DB::table('media')
            ->where('model_type', EventDocumentSubmission::class)
            ->where('model_id', $submissionId)
            ->where('collection_name', 'submission_file')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $inserts
     */
    private function insertChunked(array $inserts): void
    {
        foreach (array_chunk($inserts, 100) as $chunk) {
            DB::transaction(fn () => DB::table('custom_fields')->insert($chunk));
        }
    }

    /**
     * Decode a JSON column value that may arrive as string, array, or null.
     *
     * @return array<array-key, mixed>|null
     */
    private function decode(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Legacy labels may be plain strings (pre-translatable rows) or JSON
     * translation maps; both normalize to a {locale: string} array without
     * double-encoding.
     *
     * @return array<string, string>
     */
    private function translationsFrom(mixed $label): array
    {
        $decoded = is_string($label) ? json_decode($label, true) : $label;

        if (is_array($decoded) && $decoded !== []) {
            return $decoded;
        }

        return ['en' => (string) $label];
    }

    private function encodeOptions(?array $options): ?string
    {
        if ($options === null || $options === []) {
            return null;
        }

        return json_encode(FormFieldTypes::normalizeOptions($options));
    }
}
