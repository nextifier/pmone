<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @property int $id
 * @property int $project_id
 * @property string $label
 * @property string $key
 * @property string $type
 * @property array<array-key, mixed>|null $options
 * @property bool $is_required
 * @property int $order_column
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Project|null $project
 *
 * @method static Builder<static>|ProjectCustomField newModelQuery()
 * @method static Builder<static>|ProjectCustomField newQuery()
 * @method static Builder<static>|ProjectCustomField ordered(string $direction = 'asc')
 * @method static Builder<static>|ProjectCustomField query()
 * @method static Builder<static>|ProjectCustomField whereCreatedAt($value)
 * @method static Builder<static>|ProjectCustomField whereId($value)
 * @method static Builder<static>|ProjectCustomField whereIsRequired($value)
 * @method static Builder<static>|ProjectCustomField whereKey($value)
 * @method static Builder<static>|ProjectCustomField whereLabel($value)
 * @method static Builder<static>|ProjectCustomField whereOptions($value)
 * @method static Builder<static>|ProjectCustomField whereOrderColumn($value)
 * @method static Builder<static>|ProjectCustomField whereProjectId($value)
 * @method static Builder<static>|ProjectCustomField whereType($value)
 * @method static Builder<static>|ProjectCustomField whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ProjectCustomField extends Model implements Sortable
{
    use LogsActivity;
    use SortableTrait;

    protected $fillable = [
        'project_id',
        'label',
        'key',
        'type',
        'options',
        'is_required',
        'order_column',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['label', 'type', 'is_required'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->key) && ! empty($model->label)) {
                $model->key = Str::snake(Str::ascii($model->label));
            }
        });
    }

    public function buildSortQuery(): Builder
    {
        return static::query()->where('project_id', $this->project_id);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
