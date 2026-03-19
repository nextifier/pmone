<?php

namespace App\Models;

use App\Enums\ContactStatus;
use App\Helpers\PhoneCountryHelper;
use App\Traits\ClearsResponseCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Tags\HasTags;
use Spatie\Tags\Tag;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string|null $job_title
 * @property array<array-key, mixed>|null $emails
 * @property array<array-key, mixed>|null $phones
 * @property string|null $company_name
 * @property string|null $website
 * @property array<array-key, mixed>|null $address
 * @property string|null $notes
 * @property string|null $source
 * @property array<array-key, mixed>|null $more_details
 * @property ContactStatus $status
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read User|null $creator
 * @property-read User|null $deleter
 * @property-read array<string> $business_categories_list
 * @property-read array<string> $contact_types_list
 * @property-read array<string> $tags_list
 * @property-read Collection<int, Project> $projects
 * @property-read int|null $projects_count
 * @property Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 * @property-read User|null $updater
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact byStatus(string $status)
 * @method static \Database\Factories\ContactFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact forProject(int $projectId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereCompanyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereEmails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereJobTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereMoreDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact wherePhones($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereWebsite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact withAllTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact withAllTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact withAnyTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact withAnyTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact withAnyTagsOfType(array|string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact withoutTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Contact extends Model
{
    use ClearsResponseCache;
    use HasFactory;
    use HasTags;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'job_title',
        'emails',
        'phones',
        'company_name',
        'website',
        'address',
        'notes',
        'source',
        'more_details',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'emails' => 'array',
            'phones' => 'array',
            'address' => 'array',
            'more_details' => 'array',
            'status' => ContactStatus::class,
        ];
    }

    protected static function responseCacheTags(): array
    {
        return ['contacts'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            if (auth()->check()) {
                $model->created_by = auth()->id();
            }

            // Auto-detect country from phone number if address.country is empty
            $address = $model->address;
            $phones = $model->phones;

            if (empty($address['country']) && ! empty($phones) && is_array($phones)) {
                $country = PhoneCountryHelper::getCountryName($phones[0]);

                if ($country) {
                    $address = is_array($address) ? $address : [];
                    $address['country'] = $country;
                    $model->address = $address;
                }
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'company_name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Tag methods

    /**
     * Sync business categories using spatie/laravel-tags.
     *
     * @param  array<string>  $names
     */
    public function syncBusinessCategories(array $names): void
    {
        $this->syncTagsWithType($names, 'business_category');
    }

    /**
     * Sync contact types using spatie/laravel-tags.
     *
     * @param  array<string>  $names
     */
    public function syncContactTypes(array $names): void
    {
        $this->syncTagsWithType($names, 'contact_type');
    }

    /**
     * Sync contact tags (free-form) using spatie/laravel-tags.
     *
     * @param  array<string>  $names
     */
    public function syncContactTags(array $names): void
    {
        $this->syncTagsWithType($names, 'contact_tag');
    }

    /**
     * Get business categories list.
     *
     * @return array<string>
     */
    public function getBusinessCategoriesListAttribute(): array
    {
        return $this->tags
            ->filter(fn ($tag) => str_starts_with($tag->type, 'business_category'))
            ->pluck('name')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get contact types list.
     *
     * @return array<string>
     */
    public function getContactTypesListAttribute(): array
    {
        return $this->tagsWithType('contact_type')
            ->pluck('name')
            ->map(fn ($name) => strtolower($name))
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get free-form tags list.
     *
     * @return array<string>
     */
    public function getTagsListAttribute(): array
    {
        return $this->tagsWithType('contact_tag')->pluck('name')->toArray();
    }

    // Relationships

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'contact_project')
            ->withTimestamps();
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

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->whereHas('projects', fn ($q) => $q->where('projects.id', $projectId));
    }
}
