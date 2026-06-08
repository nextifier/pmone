<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @property int $id
 * @property int $event_id
 * @property string $name
 * @property string $slug
 * @property bool $no_container
 * @property int|null $order_column
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Event|null $event
 * @property-read Collection<int, Partner> $partners
 * @property-read int|null $partners_count
 * @property-read User|null $updater
 *
 * @method static Builder<static>|PartnerCategory findSimilarSlugs(string $attribute, array $config, string $slug)
 * @method static Builder<static>|PartnerCategory newModelQuery()
 * @method static Builder<static>|PartnerCategory newQuery()
 * @method static Builder<static>|PartnerCategory ordered(string $direction = 'asc')
 * @method static Builder<static>|PartnerCategory query()
 * @method static Builder<static>|PartnerCategory whereCreatedAt($value)
 * @method static Builder<static>|PartnerCategory whereCreatedBy($value)
 * @method static Builder<static>|PartnerCategory whereEventId($value)
 * @method static Builder<static>|PartnerCategory whereId($value)
 * @method static Builder<static>|PartnerCategory whereName($value)
 * @method static Builder<static>|PartnerCategory whereNoContainer($value)
 * @method static Builder<static>|PartnerCategory whereOrderColumn($value)
 * @method static Builder<static>|PartnerCategory whereSlug($value)
 * @method static Builder<static>|PartnerCategory whereUpdatedAt($value)
 * @method static Builder<static>|PartnerCategory whereUpdatedBy($value)
 * @method static Builder<static>|PartnerCategory withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array $config, string $slug)
 *
 * @mixin \Eloquent
 */
class PartnerCategory extends Model implements Sortable
{
    use ClearsResponseCache;
    use HasFactory;
    use HasSlug;
    use LogsActivity;
    use SortableTrait;

    protected $fillable = [
        'event_id',
        'name',
        'slug',
        'no_container',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'no_container' => 'boolean',
        ];
    }

    protected static function responseCacheTags(): array
    {
        return ['partners'];
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    /**
     * Scope slug uniqueness to event_id.
     */
    public function scopeWithUniqueSlugConstraints(
        Builder $query,
        Model $model,
        string $attribute,
        array $config,
        string $slug
    ): Builder {
        return $query->where('event_id', $model->event_id);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function buildSortQuery(): Builder
    {
        return static::query()->where('event_id', $this->event_id);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'slug',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($projectId = $this->event?->project_id) {
            $activity->properties = $activity->properties->put('project_id', $projectId);
        }
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
    }

    // Relationships

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class, 'partner_category_partner')
            ->withPivot('id', 'order_column')
            ->withTimestamps()
            ->orderByPivot('order_column');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
