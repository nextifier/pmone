<?php

namespace App\Models;

use App\Traits\HasMediaManager;
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
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use HasMediaManager;
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

        // Auto-cleanup media when user is deleted
        static::deleting(function ($model) {
            $model->clearMediaCollection();
        });
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
        $this->registerDynamicMediaCollections();
    }

    public function registerMediaConversions($media = null): void
    {
        // Profile Image Conversions (square)
        $this->addMediaConversion('sm')
            ->width(200)
            ->height(200)
            ->quality(85)
            ->performOnCollections('profile_image');

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

        // Cover Image Conversions (maintain aspect ratio)
        $this->addMediaConversion('sm')
            ->width(400)
            ->quality(85)
            ->performOnCollections('cover_image');

        $this->addMediaConversion('md')
            ->width(800)
            ->quality(90)
            ->performOnCollections('cover_image');

        $this->addMediaConversion('lg')
            ->width(1200)
            ->quality(90)
            ->performOnCollections('cover_image');

        $this->addMediaConversion('xl')
            ->width(1600)
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

    protected function getMediaConversionsConfig(): array
    {
        return [
            'profile_sm' => [
                'width' => 150,
                'height' => 150,
                'quality' => 80,
                'collections' => ['profile_image'],
            ],
            'profile_md' => [
                'width' => 300,
                'height' => 300,
                'quality' => 85,
                'collections' => ['profile_image'],
            ],
            'profile_lg' => [
                'width' => 600,
                'height' => 600,
                'quality' => 90,
                'collections' => ['profile_image'],
            ],
            'cover_sm' => [
                'width' => 400,
                'height' => 200,
                'quality' => 80,
                'collections' => ['cover_image'],
            ],
            'cover_md' => [
                'width' => 800,
                'height' => 400,
                'quality' => 85,
                'collections' => ['cover_image'],
            ],
            'cover_lg' => [
                'width' => 1200,
                'height' => 600,
                'quality' => 90,
                'collections' => ['cover_image'],
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

    public function markAsLoggedIn(): void
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
