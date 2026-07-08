<?php

namespace App\Models;

use App\Support\FormFieldTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\ResponseCache\Facades\ResponseCache;
use Spatie\Translatable\HasTranslations;

/**
 * The single field-definition model for every dynamic-field feature: Form
 * Builder fields, business-matching intake, ticket-registration questions,
 * project brand fields, and ops-document mini-forms. Owners attach via the
 * `fieldable` morph plus a `context` discriminator; `type` values come from
 * the shared FormFieldTypes catalog. `label`, `placeholder`, and `help_text`
 * are translatable (5 locales). `key` is the brand-context value-storage
 * handle (brands.custom_fields JSON maps); `system_key` identifies rows
 * instantiated from the PredefinedCustomFields library.
 *
 * @property int $id
 * @property string $ulid
 * @property string $fieldable_type
 * @property int $fieldable_id
 * @property string $context
 * @property string $type
 * @property string $label
 * @property string|null $placeholder
 * @property string|null $help_text
 * @property array<array-key, mixed>|null $options
 * @property array<array-key, mixed>|null $validation
 * @property array<array-key, mixed>|null $settings
 * @property string|null $key
 * @property string|null $system_key
 * @property int|null $legacy_id
 * @property bool $is_active
 * @property int|null $order_column
 *
 * @mixin \Eloquent
 */
class CustomField extends Model implements Sortable
{
    use HasFactory;
    use HasTranslations;
    use SoftDeletes;
    use SortableTrait;

    public const CONTEXT_FORM = 'form';

    public const CONTEXT_BUSINESS_MATCHING = 'business_matching';

    public const CONTEXT_TICKET_REGISTRATION = 'ticket_registration';

    public const CONTEXT_BRAND = 'brand';

    public const CONTEXT_DOCUMENT = 'document';

    public const TYPE_TEXT = 'text';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPE_EMAIL = 'email';

    public const TYPE_NUMBER = 'number';

    public const TYPE_PHONE = 'phone';

    public const TYPE_URL = 'url';

    public const TYPE_DATE = 'date';

    public const TYPE_TIME = 'time';

    public const TYPE_SELECT = 'select';

    public const TYPE_MULTI_SELECT = 'multi_select';

    public const TYPE_CHECKBOX = 'checkbox';

    public const TYPE_CHECKBOX_GROUP = 'checkbox_group';

    public const TYPE_RADIO = 'radio';

    public const TYPE_FILE = 'file';

    public const TYPE_RATING = 'rating';

    public const TYPE_LINEAR_SCALE = 'linear_scale';

    public const TYPE_RICH_TEXT = 'rich_text';

    public const TYPE_TAGS = 'tags';

    public const TYPE_DATETIME = 'datetime';

    public const TYPE_DATE_RANGE = 'date_range';

    public const TYPE_SWITCH = 'switch';

    public const TYPE_SLIDER = 'slider';

    public const TYPE_SECTION = 'section';

    public const TYPE_COLOR = 'color';

    public const TYPE_COUNTRY = 'country';

    public array $translatable = [
        'label',
        'placeholder',
        'help_text',
    ];

    protected $fillable = [
        'ulid',
        'fieldable_type',
        'fieldable_id',
        'context',
        'type',
        'label',
        'placeholder',
        'help_text',
        'options',
        'validation',
        'settings',
        'key',
        'system_key',
        'legacy_id',
        'is_active',
        'order_column',
        // Legacy-compat virtual attributes (see the Attribute accessors below).
        'form_id',
        'event_id',
        'project_id',
        'required',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'validation' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function contexts(): array
    {
        return [
            self::CONTEXT_FORM,
            self::CONTEXT_BUSINESS_MATCHING,
            self::CONTEXT_TICKET_REGISTRATION,
            self::CONTEXT_BRAND,
            self::CONTEXT_DOCUMENT,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allowedTypes(): array
    {
        return FormFieldTypes::all();
    }

    /**
     * @return array<int, string>
     */
    public static function allowedTypesFor(string $context): array
    {
        $excluded = match ($context) {
            self::CONTEXT_BUSINESS_MATCHING => [self::TYPE_FILE, self::TYPE_SECTION],
            self::CONTEXT_TICKET_REGISTRATION,
            self::CONTEXT_BRAND => [self::TYPE_FILE, self::TYPE_SECTION, self::TYPE_RICH_TEXT],
            self::CONTEXT_DOCUMENT => [self::TYPE_SECTION],
            default => [],
        };

        return array_values(array_diff(FormFieldTypes::all(), $excluded));
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            if (empty($model->context)) {
                $model->context = match ($model->fieldable_type) {
                    Form::class => self::CONTEXT_FORM,
                    Event::class => self::CONTEXT_BUSINESS_MATCHING,
                    Project::class => self::CONTEXT_BRAND,
                    EventDocument::class => self::CONTEXT_DOCUMENT,
                    default => $model->context,
                };
            }

            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function (self $model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function (self $model) {
            if ($model->isForceDeleting() === false && auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });

        static::saved(fn (self $model) => $model->clearContextResponseCache());
        static::deleted(fn (self $model) => $model->clearContextResponseCache());
        static::restored(fn (self $model) => $model->clearContextResponseCache());
    }

    /**
     * The public surface a field feeds depends on its context, so the static
     * ClearsResponseCache trait cannot be used here. Mirrors the trait's
     * afterCommit rationale: clearing inside a transaction lets a concurrent
     * public request re-cache pre-commit data.
     *
     * @return array<int, string>
     */
    public function responseCacheTagsForContext(): array
    {
        return match ($this->context) {
            self::CONTEXT_FORM => ['forms-public'],
            self::CONTEXT_BUSINESS_MATCHING,
            self::CONTEXT_TICKET_REGISTRATION => ['tickets'],
            self::CONTEXT_BRAND => ['brands'],
            default => [],
        };
    }

    protected function clearContextResponseCache(): void
    {
        $tags = $this->responseCacheTagsForContext();

        if ($tags !== []) {
            DB::afterCommit(fn () => ResponseCache::clear($tags));
        }
    }

    /**
     * Legacy-compat alias for the pre-unification form_fields.form_id column:
     * reads/writes the fieldable morph so old payloads, resources, and tests
     * keep working unchanged.
     */
    protected function formId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->fieldable_type === Form::class ? $this->fieldable_id : null,
            set: fn ($value) => [
                'fieldable_type' => Form::class,
                'fieldable_id' => $value,
                'context' => $this->attributes['context'] ?? self::CONTEXT_FORM,
            ],
        );
    }

    /**
     * Legacy-compat alias for event_custom_fields.event_id. Defaults the
     * context to business_matching unless a more specific event context was
     * already set (e.g. ticket_registration).
     */
    protected function eventId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->fieldable_type === Event::class ? $this->fieldable_id : null,
            set: function ($value) {
                $context = $this->attributes['context'] ?? null;

                return [
                    'fieldable_type' => Event::class,
                    'fieldable_id' => $value,
                    'context' => in_array($context, [self::CONTEXT_BUSINESS_MATCHING, self::CONTEXT_TICKET_REGISTRATION], true)
                        ? $context
                        : self::CONTEXT_BUSINESS_MATCHING,
                ];
            },
        );
    }

    /**
     * Legacy-compat alias for the pre-unification `required` columns
     * (event_custom_fields.required / project_custom_fields.is_required):
     * reads/writes validation.required.
     */
    protected function required(): Attribute
    {
        return Attribute::make(
            get: fn () => (bool) ($this->validation['required'] ?? false),
            set: function ($value) {
                $validation = $this->validation ?? [];
                $validation['required'] = (bool) $value;

                return ['validation' => json_encode($validation)];
            },
        );
    }

    /**
     * Legacy-compat alias for project_custom_fields.project_id.
     */
    protected function projectId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->fieldable_type === Project::class ? $this->fieldable_id : null,
            set: fn ($value) => [
                'fieldable_type' => Project::class,
                'fieldable_id' => $value,
                'context' => self::CONTEXT_BRAND,
            ],
        );
    }

    public function buildSortQuery(): Builder
    {
        return static::query()
            ->where('fieldable_type', $this->fieldable_type)
            ->where('fieldable_id', $this->fieldable_id)
            ->where('context', $this->context);
    }

    public function fieldable(): MorphTo
    {
        return $this->morphTo();
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public function scopeContext(Builder $query, string $context): Builder
    {
        return $query->where('context', $context);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
