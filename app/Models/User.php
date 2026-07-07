<?php

namespace App\Models;

use App\Traits\HasMediaManager;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * @property int $id
 * @property string|null $ulid
 * @property string $name
 * @property string $username
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $title
 * @property string|null $phone
 * @property Carbon|null $birth_date
 * @property string|null $gender
 * @property string|null $bio
 * @property array<array-key, mixed>|null $user_settings
 * @property array<array-key, mixed>|null $more_details
 * @property string $status
 * @property string $visibility
 * @property Carbon|null $last_seen
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $last_login_user_agent
 * @property Carbon|null $suspended_at
 * @property string|null $suspension_reason
 * @property int|null $suspended_by
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $company_name
 * @property string|null $encrypted_password
 * @property array<array-key, mixed>|null $custom_fields
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, Task> $assignedTasks
 * @property-read int|null $assigned_tasks_count
 * @property-read Collection<int, Brand> $brands
 * @property-read int|null $brands_count
 * @property-read Collection<int, Post> $createdPosts
 * @property-read int|null $created_posts_count
 * @property-read Collection<int, Task> $createdTasks
 * @property-read int|null $created_tasks_count
 * @property-read User|null $creator
 * @property-read User|null $deleter
 * @property-read Collection<int, Link> $links
 * @property-read int|null $links_count
 * @property-read Collection<int, MagicLink> $magicLinks
 * @property-read int|null $magic_links_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, OAuthProvider> $oauthProviders
 * @property-read int|null $oauth_providers_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Post> $posts
 * @property-read int|null $posts_count
 * @property-read Collection<int, Project> $projects
 * @property-read int|null $projects_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, ShortLink> $shortLinks
 * @property-read int|null $short_links_count
 * @property-read Collection<int, Task> $tasks
 * @property-read int|null $tasks_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read User|null $updater
 * @property-read Collection<int, Visit> $visits
 * @property-read int|null $visits_count
 * @property-read Collection<int, Visit> $visitsMade
 * @property-read int|null $visits_made_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User byStatus(string $status)
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User public()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User verified()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBirthDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCompanyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCustomFields($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEncryptedPassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastSeen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMoreDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUserSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereVisibility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;

    use HasFactory;
    use HasMediaManager;
    use HasRoles;
    use InteractsWithMedia;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'phone',
        'title',
        'birth_date',
        'gender',
        'bio',
        'user_settings',
        'more_details',
        'status',
        'visibility',
        'last_seen',
        'last_login_at',
        'last_login_ip',
        'last_login_user_agent',
        'suspended_at',
        'suspension_reason',
        'suspended_by',
        'email_verified_at',
        'company_name',
        'country',
        'city',
        'profession',
        'position',
        'business_matching_opt_in',
        'encrypted_password',
        'custom_fields',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'encrypted_password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'birth_date' => 'date',
        'user_settings' => 'array',
        'more_details' => 'array',
        'last_seen' => 'datetime',
        'last_login_at' => 'datetime',
        'suspended_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'password' => 'hashed',
        'custom_fields' => 'array',
        'business_matching_opt_in' => 'boolean',
    ];

    /**
     * Set the user's name (normalize to title case).
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = Str::title(strtolower(trim($value)));
    }

    /**
     * Set the user's email (normalize to lowercase).
     */
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower(trim($value));
    }

    /**
     * Set the user's username (normalize to lowercase if provided).
     */
    public function setUsernameAttribute(?string $value): void
    {
        if ($value) {
            $this->attributes['username'] = strtolower(trim($value));
        }
        // Don't auto-generate here - let boot() handle it with uniqueness check
    }

    public function getRouteKeyName()
    {
        return 'username';
    }

    // Boot method to generate ULID
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            // Generate username from name if not provided
            if (empty($model->username) && ! empty($model->name)) {
                // Extract first name only (first word before space)
                $firstName = explode(' ', trim($model->name))[0];

                // Lowercase and remove special characters
                $cleaned = strtolower($firstName);
                $baseUsername = preg_replace('/[^a-z0-9._]/', '', $cleaned);

                // If baseUsername is empty after cleaning, use email prefix
                if (empty($baseUsername) && ! empty($model->email)) {
                    $baseUsername = explode('@', $model->email)[0];
                    $baseUsername = preg_replace('/[^a-z0-9._]/', '', strtolower($baseUsername));
                }

                $username = $baseUsername;
                $counter = 1;
                $maxAttempts = 10;

                // Ensure username is unique (retry with numeric suffix if taken)
                // Include soft-deleted records to respect database unique constraint
                while (static::withTrashed()->where('username', $username)->exists()) {
                    if ($counter > $maxAttempts) {
                        // After max attempts, use a random suffix to guarantee uniqueness
                        $username = $baseUsername.'_'.strtolower(Str::random(6));
                        break;
                    }

                    $username = $baseUsername.$counter;
                    $counter++;
                }

                // Final check: if still exists (rare race condition), add timestamp
                if (static::withTrashed()->where('username', $username)->exists()) {
                    $username = $baseUsername.'_'.time();
                }

                $model->username = $username;
            }

            // Set created_by
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            // Set updated_by
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function ($model) {
            // Set deleted_by for soft deletes
            if ($model->isForceDeleting() === false && auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }

            // Auto-cleanup media when user is force deleted
            if ($model->isForceDeleting()) {
                $model->clearMediaCollection();
            }
        });

        static::saved(function (User $user) {
            if ($user->wasChanged(self::PUBLIC_PROFILE_FIELDS)) {
                self::clearPublicProfileResponseCache();
            }
        });

        static::deleted(fn () => self::clearPublicProfileResponseCache());
        static::restored(fn () => self::clearPublicProfileResponseCache());
    }

    /**
     * Fields embedded in publicly cached payloads: the /resolve/{slug} profile
     * (short-links), project member lists (projects) and blog author bylines
     * (blog-posts). Deliberately EXCLUDES high-frequency columns such as
     * last_seen (written by the UpdateLastSeen middleware up to once a minute
     * per user) so routine traffic never flushes the response cache.
     *
     * @var string[]
     */
    public const PUBLIC_PROFILE_FIELDS = [
        'name', 'username', 'email', 'phone', 'birth_date',
        'gender', 'title', 'bio', 'status', 'visibility',
    ];

    protected static function clearPublicProfileResponseCache(): void
    {
        DB::afterCommit(fn () => ResponseCache::clear(['short-links', 'projects', 'blog-posts']));
    }

    /**
     * Auto-verify user if they have privileged roles
     */
    public function autoVerifyIfPrivileged(): void
    {
        if ($this->hasVerifiedEmail()) {
            return;
        }

        if ($this->hasRole(['master', 'admin'])) {
            $this->markEmailAsVerified();

            logger()->info('User auto-verified due to privileged role', [
                'user_id' => $this->id,
                'username' => $this->username,
                'email' => $this->email,
                'roles' => $this->getRoleNames()->toArray(),
            ]);
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'username', 'email', 'phone', 'title', 'status', 'visibility', 'company_name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function oauthProviders()
    {
        return $this->hasMany(OAuthProvider::class);
    }

    public function magicLinks()
    {
        return $this->hasMany(MagicLink::class, 'email', 'email');
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
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
            'cover_image' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function hasOAuthProvider(string $provider): bool
    {
        return $this->oauthProviders()
            ->where('provider', $provider)
            ->exists();
    }

    public function markAsOnline(): void
    {
        $this->update(['last_seen' => now()]);
    }

    public function markAsLoggedIn(?string $ip = null, ?string $userAgent = null): void
    {
        $this->update([
            'last_seen' => now(),
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'last_login_user_agent' => $userAgent,
        ]);
    }

    public function isOnline(): bool
    {
        return $this->last_seen && $this->last_seen->gt(now()->subMinutes(5));
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    // User Settings Helpers
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->user_settings, $key, $default);
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->user_settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['user_settings' => $settings]);
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'brand_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withTimestamps();
    }

    public function links(): MorphMany
    {
        return $this->morphMany(Link::class, 'linkable')
            ->orderBy('order');
    }

    public function shortLinks(): HasMany
    {
        return $this->hasMany(ShortLink::class);
    }

    public function visits(): MorphMany
    {
        return $this->morphMany(Visit::class, 'visitable');
    }

    public function visitsMade(): HasMany
    {
        return $this->hasMany(Visit::class, 'visitor_id');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_authors')
            ->withPivot(['order'])
            ->withTimestamps()
            ->orderBy('post_authors.order');
    }

    public function createdPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'created_by');
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
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

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(UserNote::class)->latest();
    }

    public function claimedAttendees(): HasMany
    {
        return $this->hasMany(Attendee::class, 'claimed_by_user_id');
    }

    public function fieldResponses(): HasMany
    {
        return $this->hasMany(FieldResponse::class);
    }

    /**
     * Birth year derived from `birth_date` (no separate column).
     */
    public function getBirthYearAttribute(): ?int
    {
        return $this->birth_date?->year;
    }

    /**
     * Percentage (0-100) of optional profile fields that are filled. Drives the
     * dashboard progress meter; computed, not stored.
     */
    public function getProfileCompletenessAttribute(): int
    {
        $fields = ['gender', 'birth_date', 'country', 'city', 'company_name', 'profession', 'position'];

        $filled = collect($fields)->filter(fn (string $field) => filled($this->getAttribute($field)))->count();

        return (int) round($filled / count($fields) * 100);
    }
}
