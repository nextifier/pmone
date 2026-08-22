<?php

use App\Models\CustomField;
use App\Support\PredefinedCustomFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bring already-instantiated Gender registration fields in line with the library.
 *
 * Editing PredefinedCustomFields alone changes nothing for events that already
 * enabled the field: `CustomFieldService::update()` strips `type` for any row
 * carrying a `system_key`, predefined rows cannot be deleted, and
 * `togglePredefined()` only flips `is_active` without re-reading the catalog.
 * Without this migration the change is invisible on every existing event.
 *
 * Safe to run: no event uses ticketing in production yet, and `prefer_not_to_say`
 * has no stored answers to strand. `radio` shares `select`'s validation and
 * formatting branches, so nothing downstream changes shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        $definition = PredefinedCustomFields::attributesFor(
            CustomField::CONTEXT_TICKET_REGISTRATION,
            'gender'
        );

        // attributesFor() returns [] for an unknown key, not null.
        if (empty($definition['type'])) {
            return;
        }

        DB::table('custom_fields')
            ->where('context', CustomField::CONTEXT_TICKET_REGISTRATION)
            ->where('system_key', 'gender')
            ->update([
                'type' => $definition['type'],
                'options' => json_encode($definition['options']),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // The previous shape: a select carrying a third "prefer not to say" option.
        DB::table('custom_fields')
            ->where('context', CustomField::CONTEXT_TICKET_REGISTRATION)
            ->where('system_key', 'gender')
            ->update([
                'type' => CustomField::TYPE_SELECT,
                'options' => json_encode([
                    ['value' => 'male', 'label' => ['en' => 'Male', 'id' => 'Laki-laki', 'ja' => '男性', 'ko' => '남성', 'zh' => '男']],
                    ['value' => 'female', 'label' => ['en' => 'Female', 'id' => 'Perempuan', 'ja' => '女性', 'ko' => '여성', 'zh' => '女']],
                    ['value' => 'prefer_not_to_say', 'label' => ['en' => 'Prefer not to say', 'id' => 'Memilih tidak menjawab', 'ja' => '回答しない', 'ko' => '답변하지 않음', 'zh' => '不愿透露']],
                ]),
                'updated_at' => now(),
            ]);
    }
};
