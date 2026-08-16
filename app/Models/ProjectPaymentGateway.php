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

        if (self::looksLikePlaceholder($secret)) {
            return false;
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

    /**
     * Whether a secret is a stand-in someone typed rather than a real credential.
     *
     * This used to be `str_contains($lower, $marker)` over the whole string, which
     * quietly rejected genuine keys: a random 40-character Xendit secret contains
     * "xxx" about once in 858, and the merchant would just see payments stop with
     * no message pointing here.
     *
     * The two failure directions are not equal. Wrongly calling a real key a
     * placeholder stops that merchant's payments with nothing in the logs
     * pointing here; wrongly accepting a fake one just fails at the provider,
     * loudly. So every rule below is tuned to almost never fire on a generated
     * credential, even at the cost of letting an odd placeholder through.
     *
     * Placeholders long enough to clear the length checks give themselves away
     * in one of three ways, and only these are disqualifying:
     *
     *   1. A dictionary word standing on its own between separators -
     *      "xnd_dummy_key", "SB-Mid-server-CHANGEME", "xnd_test_key_for_dev".
     *      Inside a random run ("a9xxxQ2") it is just noise and is left alone.
     *   2. A long run of one repeated character - "SB-Mid-server-XXXXXXXX",
     *      "xnd_00000000". This is how masked and redacted keys are written.
     *      Eight in a row is ~1 in 70,000,000 for a random hex secret and
     *      rarer still for base62, so it never fires by accident.
     *   3. Barely any alphabet at all - twenty or more characters drawn from
     *      four or fewer distinct ones. Note the ceiling is deliberately this
     *      low: an earlier attempt used eight, which rejected genuine 20-char
     *      hex secrets once in 142 - six times worse than the substring bug
     *      this guard replaced.
     */
    protected static function looksLikePlaceholder(string $secret): bool
    {
        if ($secret === '') {
            return false;
        }

        $markers = ['dummy', 'placeholder', 'fake', 'changeme', 'xxx', 'sample', 'test key for', 'replace'];

        // Collapse every separator to a single space so a marker can be matched
        // as a whole word regardless of how the placeholder was punctuated.
        $words = trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower($secret)));

        foreach ($markers as $marker) {
            if (preg_match('/(?:^| )'.preg_quote($marker, '/').'(?: |$)/', $words) === 1) {
                return true;
            }
        }

        if (preg_match('/(.)\1{7,}/', $secret) === 1) {
            return true;
        }

        return strlen($secret) >= 20 && count(array_unique(str_split(strtolower($secret)))) <= 4;
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
