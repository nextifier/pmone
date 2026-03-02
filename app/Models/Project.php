<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $username
 * @property string|null $bio
 * @property array<array-key, mixed> $settings
 * @property array<array-key, mixed> $more_details
 * @property string $status
 * @property string $visibility
 * @property string|null $email
 * @property array<array-key, mixed>|null $phone
 * @property int|null $order_column
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ContactFormSubmission> $contactFormSubmissions
 * @property-read int|null $contact_form_submissions_count
 * @property-read \App\Models\User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProjectCustomField> $customFields
 * @property-read int|null $custom_fields_count
 * @property-read \App\Models\User|null $deleter
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Event> $events
 * @property-read int|null $events_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GaProperty> $gaProperties
 * @property-read int|null $ga_properties_count
 * @property-read array|null $profile_image
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Link> $links
 * @property-read int|null $links_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $members
 * @property-read int|null $members_count
 * @property-read \App\Models\User|null $updater
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Visit> $visits
 * @property-read int|null $visits_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project byStatus(string $status)
 * @method static \Database\Factories\ProjectFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project public()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereMoreDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereVisibility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Project extends Model implements HasMedia, Sortable
{
    use ClearsResponseCache;
    use HasFactory;
    use HasMediaManager;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;
    use SortableTrait;

    protected $fillable = [
        'name',
        'username',
        'bio',
        'settings',
        'more_details',
        'status',
        'visibility',
        'email',
        'phone',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'more_details' => 'array',
            'phone' => 'array',
        ];
    }

    protected static function responseCacheTags(): array
    {
        return ['projects'];
    }

    public function getRouteKeyName(): string
    {
        return 'username';
    }

    /**
     * Get profile image URLs for the project.
     */
    public function getProfileImageAttribute(): ?array
    {
        return $this->getMediaUrls('profile_image');
    }

    /**
     * Set the project's username (normalize to lowercase if provided).
     */
    public function setUsernameAttribute(?string $value): void
    {
        if ($value) {
            $this->attributes['username'] = strtolower(trim($value));
        }
        // Don't auto-generate here - let boot() handle it with uniqueness check
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            // Generate username from name if not provided
            if (empty($model->username) && ! empty($model->name)) {
                // First lowercase, then remove spaces and special characters
                $cleaned = strtolower(trim($model->name));
                $cleaned = str_replace(' ', '', $cleaned);
                $baseUsername = preg_replace('/[^a-z0-9._]/', '', $cleaned);

                // Prevent empty username
                if (empty($baseUsername)) {
                    $baseUsername = 'project';
                }

                $username = $baseUsername;
                $counter = 1;
                $maxAttempts = 100;

                // Ensure username is unique with transaction-safe approach
                while (true) {
                    // Check if username exists
                    $exists = static::where('username', $username)->exists();

                    if (! $exists) {
                        break;
                    }

                    // Try numeric suffix
                    if ($counter <= $maxAttempts) {
                        $username = $baseUsername.$counter;
                        $counter++;
                    } else {
                        // After max attempts, use timestamp + random for guaranteed uniqueness
                        $username = $baseUsername.'_'.time().'_'.strtolower(Str::random(4));
                        break;
                    }
                }

                $model->username = $username;
            }

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

            // Auto-cleanup media when project is force deleted
            if ($model->isForceDeleting()) {
                $model->clearMediaCollection();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'username', 'status', 'visibility'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function registerMediaConversions($media = null): void
    {
        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('profile_image')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(200)
            ->height(200)
            ->quality(85)
            ->performOnCollections('profile_image')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(400)
            ->height(400)
            ->quality(90)
            ->performOnCollections('profile_image');

        $this->addMediaConversion('lg')
            ->width(800)
            ->height(800)
            ->quality(90)
            ->performOnCollections('profile_image');

        $this->addMediaConversion('xl')
            ->width(1080)
            ->height(1080)
            ->quality(95)
            ->performOnCollections('profile_image');

        $this->addMediaConversion('lqip')
            ->fit(Fit::Crop, 60, 20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('cover_image')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->fit(Fit::Crop, 450, 150)
            ->quality(85)
            ->performOnCollections('cover_image')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->fit(Fit::Crop, 900, 300)
            ->quality(90)
            ->performOnCollections('cover_image');

        $this->addMediaConversion('lg')
            ->fit(Fit::Crop, 1200, 400)
            ->quality(90)
            ->performOnCollections('cover_image');

        $this->addMediaConversion('xl')
            ->fit(Fit::Crop, 1500, 500)
            ->quality(95)
            ->performOnCollections('cover_image');
    }

    public function getMediaCollections(): array
    {
        return [
            'profile_image' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
            ],
            'cover_image' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
            ],
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->ordered();
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(ProjectCustomField::class)->ordered();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps();
    }

    public function links(): MorphMany
    {
        return $this->morphMany(Link::class, 'linkable')
            ->orderBy('order');
    }

    public function visits(): MorphMany
    {
        return $this->morphMany(Visit::class, 'visitable');
    }

    public function gaProperties(): HasMany
    {
        return $this->hasMany(GaProperty::class);
    }

    public function contactFormSubmissions(): HasMany
    {
        return $this->hasMany(ContactFormSubmission::class);
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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get contact form email configuration.
     */
    public function getContactFormEmailConfig(): array
    {
        $config = data_get($this->settings, 'contact_form.email_config', []);

        // Fallback to project email if config is empty
        return [
            'to' => $config['to'] ?? ($this->email ? [$this->email] : []),
            'cc' => $config['cc'] ?? [],
            'bcc' => $config['bcc'] ?? [],
            'from_name' => $config['from_name'] ?? $this->name,
            'reply_to' => $config['reply_to'] ?? $this->email,
        ];
    }

    /**
     * Check if contact form is enabled.
     */
    public function isContactFormEnabled(): bool
    {
        return data_get($this->settings, 'contact_form.enabled', false);
    }

    /**
     * Get contact form auto-reply configuration.
     */
    public function getContactFormAutoReplyConfig(): array
    {
        return data_get($this->settings, 'contact_form.auto_reply', [
            'enabled' => false,
            'subject' => 'Thank you for contacting us',
            'body' => 'We have received your message and will get back to you soon.',
        ]);
    }
}
