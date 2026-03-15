<?php

namespace App\Models;

use App\Enums\ContactStatus;
use App\Helpers\PhoneCountryHelper;
use App\Traits\ClearsResponseCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Tags\HasTags;

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
