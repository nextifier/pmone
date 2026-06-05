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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Click> $clicks
 * @property-read int|null $clicks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContactFormSubmission> $contactFormSubmissions
 * @property-read int|null $contact_form_submissions_count
 * @property-read User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProjectCustomField> $customFields
 * @property-read int|null $custom_fields_count
 * @property-read User|null $deleter
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Event> $events
 * @property-read int|null $events_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, GaProperty> $gaProperties
 * @property-read int|null $ga_properties_count
 * @property-read array|null $profile_image
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Link> $links
 * @property-read int|null $links_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $members
 * @property-read int|null $members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProjectPaymentGateway> $paymentGateways
 * @property-read int|null $payment_gateways_count
 * @property-read User|null $updater
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Visit> $visits
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

        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('bio_images')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(450)
            ->quality(85)
            ->performOnCollections('bio_images')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(900)
            ->quality(90)
            ->performOnCollections('bio_images');

        $this->addMediaConversion('lg')
            ->width(1200)
            ->quality(90)
            ->performOnCollections('bio_images');

        $this->addMediaConversion('xl')
            ->width(1500)
            ->quality(95)
            ->performOnCollections('bio_images');
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
            'bio_images' => [
                'single_file' => false,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
                'max_size' => 20480,
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

    public function banners(): HasMany
    {
        return $this->hasMany(ProjectBanner::class);
    }

    public function paymentGateways(): HasMany
    {
        return $this->hasMany(ProjectPaymentGateway::class);
    }

    /**
     * Resolve the best gateway for {provider,mode}. Filters out gateways that are
     * marked active but lack real credentials (isConfigured()=false) — those would
     * otherwise pass pre-flight checks then fail at API call time with "invalid key".
     */
    public function defaultPaymentGateway(string $provider = 'xendit', string $mode = 'live'): ?ProjectPaymentGateway
    {
        return $this->paymentGateways()
            ->active()
            ->forProvider($provider)
            ->forMode($mode)
            ->orderByDesc('id')
            ->get()
            ->first(fn (ProjectPaymentGateway $gw) => $gw->isConfigured());
    }

    /**
     * Resolve a usable provider gateway with mode fallback. Tries the preferred
     * mode first (typically derived from app environment), then falls back to
     * the opposite mode. This lets staff run a project in test mode on
     * production while validating the integration before flipping the live
     * credentials on — the previous strict "production=live or fail" rule
     * blocked that workflow.
     */
    public function resolvePaymentGateway(string $provider = 'xendit', string $preferredMode = 'live'): ?ProjectPaymentGateway
    {
        $gateway = $this->defaultPaymentGateway($provider, $preferredMode);
        if ($gateway) {
            return $gateway;
        }

        $fallback = $preferredMode === 'live' ? 'test' : 'live';

        return $this->defaultPaymentGateway($provider, $fallback);
    }

    /**
     * The single active + configured gateway for this project, regardless of
     * provider. ProjectPaymentGateway's boot::saved hook enforces at most one
     * active gateway per project, so "the active gateway" is unambiguous. Used
     * by the provider-agnostic reservation checkout path. Returns null when the
     * active row lacks real credentials (isConfigured()=false), matching
     * defaultPaymentGateway().
     */
    public function activePaymentGateway(): ?ProjectPaymentGateway
    {
        return $this->paymentGateways()
            ->active()
            ->orderByDesc('id')
            ->get()
            ->first(fn (ProjectPaymentGateway $gw) => $gw->isConfigured());
    }

    public function hasActivePaymentGateway(): bool
    {
        return $this->paymentGateways()
            ->active()
            ->get()
            ->contains(fn (ProjectPaymentGateway $gw) => $gw->isConfigured());
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps();
    }

    /**
     * Get users who should be notified for this project.
     *
     * Combines project members with master/admin users (global visibility).
     */
    public function getNotifiableUsers(?string $permission = null, ?int $excludeUserId = null): Collection
    {
        $memberIds = $this->members()->pluck('users.id');

        try {
            $globalQuery = User::role(['master', 'admin']);
            if ($permission) {
                $globalQuery->permission($permission);
            }
            $globalIds = $globalQuery->pluck('id');
            $allIds = $memberIds->merge($globalIds)->unique();
        } catch (RoleDoesNotExist|PermissionDoesNotExist $e) {
            $allIds = $memberIds->unique();
        }

        if ($excludeUserId) {
            $allIds = $allIds->reject(fn ($id) => $id === $excludeUserId);
        }

        return User::whereIn('id', $allIds)->get();
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

    public function clicks(): MorphMany
    {
        return $this->morphMany(Click::class, 'clickable');
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

    /**
     * Get the hotel reservation staff-notification email configuration.
     *
     * Recipients notified when a hotel booking is confirmed or cancelled.
     *
     * @return array{to: list<string>, cc: list<string>, bcc: list<string>}
     */
    public function getHotelNotificationEmailConfig(): array
    {
        $config = data_get($this->settings, 'website_settings.hotels.notification_email', []);

        return [
            'to' => array_values(array_filter($config['to'] ?? [])),
            'cc' => array_values(array_filter($config['cc'] ?? [])),
            'bcc' => array_values(array_filter($config['bcc'] ?? [])),
        ];
    }

    /**
     * Per-email-type subject templates that admins can override from
     * Website Settings. Resolution: if an entry exists at
     * `website_settings.email_subjects.{$key}` and is a non-empty string,
     * return that. Otherwise fall back to the default template for the key.
     *
     * Supported keys (also drives validation + frontend form):
     *  - guest_paid       — sent to guest after payment is received
     *  - guest_voucher    — sent to guest when the hotel voucher is ready
     *  - guest_cancelled  — sent to guest when their reservation is cancelled
     *  - staff_confirmed  — sent to project staff when a booking is confirmed
     *  - staff_cancelled  — sent to project staff when a booking is cancelled
     *
     * Defaults intentionally suffix the project name (admin can edit it out
     * via Website Settings) and use `:` after the action label per the
     * agreed format with the team.
     */
    public function getEmailSubjectTemplate(string $key): string
    {
        $templates = data_get($this->settings, 'website_settings.email_subjects', []);
        $custom = $templates[$key] ?? null;

        if (is_string($custom) && trim($custom) !== '') {
            return trim($custom);
        }

        return match ($key) {
            'guest_paid' => 'Hotel Booking Confirmed: {reservation_number} - {project}',
            'guest_voucher' => 'Hotel Voucher: {reservation_number} - {project}',
            'guest_cancelled' => 'Hotel Booking Cancelled: {reservation_number} - {project}',
            'staff_confirmed' => 'Hotel Booking Confirmed: {reservation_number} - {hotel} - {project}',
            'staff_cancelled' => 'Hotel Booking Cancelled: {reservation_number} - {hotel} - {project}',
            default => '',
        };
    }

    /**
     * Render the subject template for the given key against a reservation.
     * Placeholders: `{reservation_number}`, `{hotel}`, `{event}`, `{guest}`,
     * `{project}`, `{status}` (Confirmed / Cancelled — staff keys only).
     * Missing values render as `-` so the subject is never empty.
     */
    public function renderEmailSubject(string $key, Reservation $reservation): string
    {
        $template = $this->getEmailSubjectTemplate($key);

        $statusLabel = str_starts_with($key, 'staff_')
            ? (str_ends_with($key, 'cancelled') ? 'Cancelled' : 'Confirmed')
            : '';

        return strtr($template, [
            '{reservation_number}' => (string) $reservation->reservation_number,
            '{hotel}' => $reservation->hotel?->name ?? '-',
            '{event}' => $reservation->event?->title ?? '-',
            '{guest}' => $reservation->guest_name ?? '-',
            '{project}' => $this->name,
            '{status}' => $statusLabel,
        ]);
    }
}
