<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class FormField extends Model implements Sortable
{
    use SortableTrait;

    protected $fillable = [
        'ulid',
        'form_id',
        'type',
        'label',
        'placeholder',
        'help_text',
        'options',
        'validation',
        'settings',
        'order_column',
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
        ];
    }

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

    public static function allowedTypes(): array
    {
        return [
            self::TYPE_TEXT,
            self::TYPE_TEXTAREA,
            self::TYPE_EMAIL,
            self::TYPE_NUMBER,
            self::TYPE_PHONE,
            self::TYPE_URL,
            self::TYPE_DATE,
            self::TYPE_TIME,
            self::TYPE_SELECT,
            self::TYPE_MULTI_SELECT,
            self::TYPE_CHECKBOX,
            self::TYPE_CHECKBOX_GROUP,
            self::TYPE_RADIO,
            self::TYPE_FILE,
            self::TYPE_RATING,
            self::TYPE_LINEAR_SCALE,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function buildSortQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()->where('form_id', $this->form_id);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
