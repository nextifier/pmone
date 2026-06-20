<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\Translatable\HasTranslations;

/**
 * An admin-managed, per-event dynamic field for business-matching intake. The
 * `type` value comes from the shared FormFieldTypes catalog. Responses live in
 * FieldResponse, stored per User. The `label` is translatable (5 locales).
 *
 * @property int $id
 * @property int $event_id
 * @property string $label
 * @property string $type
 * @property array<array-key, mixed>|null $options
 * @property bool $required
 * @property bool $is_active
 * @property int|null $order_column
 * @property array<array-key, mixed>|null $settings
 * @property-read Event|null $event
 *
 * @mixin \Eloquent
 */
class EventCustomField extends Model implements Sortable
{
    use HasFactory;
    use HasTranslations;
    use SoftDeletes;
    use SortableTrait;

    public array $translatable = [
        'label',
    ];

    protected $fillable = [
        'event_id',
        'label',
        'type',
        'options',
        'required',
        'is_active',
        'settings',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'settings' => 'array',
            'required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function ($model) {
            if ($model->isForceDeleting() === false && auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });
    }

    public function buildSortQuery(): Builder
    {
        return static::query()->where('event_id', $this->event_id);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function fieldResponses(): HasMany
    {
        return $this->hasMany(FieldResponse::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
