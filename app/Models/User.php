<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string|null $ulid
 * @property string $name
 * @property string $username
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon|null $birth_date
 * @property string|null $gender
 * @property string|null $bio
 * @property array<array-key, mixed>|null $links
 * @property array<array-key, mixed>|null $user_settings
 * @property array<array-key, mixed>|null $more_details
 * @property string $status
 * @property string $visibility
 * @property \Illuminate\Support\Carbon|null $last_seen
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property \Illuminate\Support\Carbon|null $two_factor_confirmed_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read string|null $cover_image_large_url
 * @property-read string|null $cover_image_medium_url
 * @property-read string|null $cover_image_thumb_url
 * @property-read string|null $cover_image_url
 * @property-read string|null $profile_image_large_url
 * @property-read string|null $profile_image_medium_url
 * @property-read string|null $profile_image_thumb_url
 * @property-read string|null $profile_image_url
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MagicLink> $magicLinks
 * @property-read int|null $magic_links_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OAuthProvider> $oauthProviders
 * @property-read int|null $oauth_providers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User byStatus(string $status)
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User public()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User verified()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBirthDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastSeen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLinks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMoreDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUserSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereVisibility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens;

    use HasFactory;
    use HasRoles;
    use InteractsWithMedia;
    use LogsActivity;
    use Notifiable;
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
        'birth_date',
        'gender',
        'bio',
        'links',
        'user_settings',
        'more_details',
        'status',
        'visibility',
        'last_seen',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
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
        'links' => 'array',
        'user_settings' => 'array',
        'more_details' => 'array',
        'last_seen' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'password' => 'hashed',
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
     * Set the user's username (generate from name if not provided).
     */
    public function setUsernameAttribute(?string $value): void
    {
        if ($value) {
            $this->attributes['username'] = strtolower(trim($value));
        } else {
            // Generate username from name by replacing spaces with dots
            $username = strtolower(str_replace(' ', '.', trim($this->name)));
            $this->attributes['username'] = $username;
        }
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
                $baseUsername = strtolower(str_replace(' ', '.', trim($model->name)));
                $username = $baseUsername;
                $counter = 1;

                // Ensure username is unique
                while (static::where('username', $username)->exists()) {
                    $username = $baseUsername.'.'.$counter;
                    $counter++;
                }

                $model->username = $username;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'status'])
            ->logOnlyDirty();
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
        $this->addMediaCollection('profile_image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('cover_image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('profile_thumb')
            ->width(150)
            ->height(150)
            ->quality(80)
            ->performOnCollections('profile_image');

        $this->addMediaConversion('profile_medium')
            ->width(300)
            ->height(300)
            ->quality(85)
            ->performOnCollections('profile_image');

        $this->addMediaConversion('profile_large')
            ->width(600)
            ->height(600)
            ->quality(90)
            ->performOnCollections('profile_image');

        // Cover Image Conversions
        $this->addMediaConversion('cover_thumb')
            ->width(400)
            ->height(200)
            ->quality(80)
            ->performOnCollections('cover_image');

        $this->addMediaConversion('cover_medium')
            ->width(800)
            ->height(400)
            ->quality(85)
            ->performOnCollections('cover_image');

        $this->addMediaConversion('cover_large')
            ->width(1200)
            ->height(600)
            ->quality(90)
            ->performOnCollections('cover_image');
    }

    public function getProfileImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('profile_image');
    }

    public function getProfileImageThumbUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('profile_image', 'profile_thumb');
    }

    public function getProfileImageMediumUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('profile_image', 'profile_medium');
    }

    public function getProfileImageLargeUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('profile_image', 'profile_large');
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('cover_image');
    }

    public function getCoverImageThumbUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('cover_image', 'cover_thumb');
    }

    public function getCoverImageMediumUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('cover_image', 'cover_medium');
    }

    public function getCoverImageLargeUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('cover_image', 'cover_large');
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

    public function markAsLoggedIn(?string $ipAddress = null): void
    {
        $this->update([
            'last_seen' => now(),
        ]);
    }

    public function isOnline(): bool
    {
        return $this->last_seen && $this->last_seen->gt(now()->subMinutes(5));
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

    public function getLink(string $type): ?string
    {
        return data_get($this->links, $type);
    }

    public function setLink(string $type, string $url): void
    {
        $links = $this->links ?? [];
        $links[$type] = $url;
        $this->update(['links' => $links]);
    }

    public function removeLink(string $type): void
    {
        $links = $this->links ?? [];
        unset($links[$type]);
        $this->update(['links' => $links]);
    }
}
