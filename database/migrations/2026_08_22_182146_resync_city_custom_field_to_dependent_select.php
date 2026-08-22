<?php

use App\Models\CustomField;
use App\Support\PredefinedCustomFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bring already-instantiated City fields in line with the library.
 *
 * `city` used to be a free-text box unrelated to the country beside it; it is now
 * a dependent select that narrows on `province`. As with the Gender resync, the
 * catalog change alone reaches nothing: `CustomFieldService::update()` strips
 * `type` for rows carrying a `system_key`, and `togglePredefined()` only flips
 * `is_active` without re-reading the catalog.
 *
 * Existing answers are left alone. They were free text, so a stored "Jakarta"
 * may not match a dataset label exactly - harmless while no event uses ticketing
 * in production, and the field simply reads as unset if it does not match.
 */
return new class extends Migration
{
    private const CONTEXTS = [
        CustomField::CONTEXT_TICKET_REGISTRATION,
        CustomField::CONTEXT_BUSINESS_MATCHING,
    ];

    public function up(): void
    {
        foreach (self::CONTEXTS as $context) {
            $definition = PredefinedCustomFields::attributesFor($context, 'city');

            if (empty($definition['type'])) {
                continue;
            }

            DB::table('custom_fields')
                ->where('context', $context)
                ->where('system_key', 'city')
                ->update([
                    'type' => $definition['type'],
                    'settings' => json_encode($definition['settings']),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        foreach (self::CONTEXTS as $context) {
            DB::table('custom_fields')
                ->where('context', $context)
                ->where('system_key', 'city')
                ->update([
                    'type' => CustomField::TYPE_TEXT,
                    'settings' => null,
                    'updated_at' => now(),
                ]);
        }
    }
};
