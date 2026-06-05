<?php

namespace App\Models;

use App\Enums\Payment\CheckoutMethod;
use App\Observers\ProjectPaymentGatewayObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property string $provider
 * @property string|null $label
 * @property string $mode
 * @property bool $is_active
 * @property string|null $secret_key
 * @property string|null $public_key
 * @property string|null $webhook_token
 * @property array<array-key, mixed> $config
 * @property Carbon|null $last_used_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property CheckoutMethod $checkout_method
 * @property-read User|null $creator
 * @property-read Project|null $project
 * @property-read User|null $updater
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway active()
 * @method static \Database\Factories\ProjectPaymentGatewayFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway forMode(string $mode)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway forProvider(string $provider)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereCheckoutMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereLastUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway wherePublicKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereSecretKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectPaymentGateway whereWebhookToken($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy([ProjectPaymentGatewayObserver::class])]
class ProjectPaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'provider',
        'label',
        'mode',
        'checkout_method',
        'is_active',
        'secret_key',
        'public_key',
        'webhook_token',
        'config',
        'last_used_at',
    ];

    /**
     * Whether this gateway has the credentials required to actually call its API.
     *
     * For Xendit: secret_key must start with `xnd_` and be at least 30 chars.
     * For generic providers: just require a non-trivial secret_key (>= 20 chars).
     *
     * The `is_active` flag alone is not sufficient — staff can toggle a gateway
     * active before pasting in real credentials, leading to silent API failures.
     */
    public function isConfigured(): bool
    {
        $secret = (string) ($this->secret_key ?? '');
        $lower = strtolower($secret);

        // Reject obvious placeholders so dummy seed values do not pass.
        foreach (['dummy', 'placeholder', 'fake', 'changeme', 'xxx', 'sample', 'test_key_for', 'replace'] as $marker) {
            if (str_contains($lower, $marker)) {
                return false;
            }
        }

        if ($this->provider === 'xendit') {
            return strlen($secret) >= 30 && str_starts_with($secret, 'xnd_');
        }

        if ($this->provider === 'midtrans') {
            // Server keys look like "Mid-server-XXXX" (live) / "SB-Mid-server-XXXX"
            // (sandbox). The substring check rejects a Client Key pasted by mistake.
            return strlen($secret) >= 20 && str_contains($secret, 'Mid-server-');
        }

        return strlen($secret) >= 20;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'checkout_method' => CheckoutMethod::class,
            'secret_key' => 'encrypted',
            'public_key' => 'encrypted',
            'webhook_token' => 'encrypted',
            'config' => 'array',
            'last_used_at' => 'datetime',
        ];
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
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::saved(function ($model) {
            if (! $model->is_active) {
                return;
            }

            $query = static::query()
                ->where('project_id', $model->project_id)
                ->where('is_active', true);

            if ($model->exists) {
                $query->where('id', '!=', $model->id);
            }

            $query->update(['is_active' => false]);
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeForMode($query, string $mode)
    {
        return $query->where('mode', $mode);
    }

    public function maskedSecret(): ?string
    {
        return self::mask($this->secret_key);
    }

    public function maskedWebhookToken(): ?string
    {
        return self::mask($this->webhook_token);
    }

    public static function mask(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $tail = substr($value, -4);

        return str_repeat('•', 8).$tail;
    }
}
