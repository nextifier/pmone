<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $slug
 * @property string $destination_url
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image
 * @property string $og_type
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Click> $clicks
 * @property-read int $clicks_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\User|null $deleter
 * @property-read \App\Models\User|null $updater
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink excludeProfileLinks()
 * @method static \Database\Factories\ShortLinkFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereDestinationUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereOgDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereOgImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereOgTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereOgType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShortLink withoutTrashed()
 *
 * @mixin \Eloquent
 */
class ShortLink extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'user_id',
        'slug',
        'destination_url',
        'is_active',
        'og_title',
        'og_description',
        'og_image',
        'og_type',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected $appends = ['clicks_count'];

    protected static function boot()
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

        static::deleting(function ($shortLink) {
            if ($shortLink->isForceDeleting()) {
                return;
            }
            if (auth()->check()) {
                $shortLink->deleted_by = auth()->id();
                $shortLink->saveQuietly();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['slug', 'destination_url', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function clicks(): MorphMany
    {
        return $this->morphMany(Click::class, 'clickable');
    }

    public function getClicksCountAttribute(): int
    {
        return $this->clicks()->count();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to exclude profile short links (user and project profiles).
     */
    public function scopeExcludeProfileLinks($query)
    {
        return $query->where('destination_url', 'not like', '%/users/%')
            ->where('destination_url', 'not like', '%/projects/%');
    }
}
