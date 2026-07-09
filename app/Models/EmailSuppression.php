<?php

namespace App\Models;

use App\Enums\EmailSuppressionReason;
use Database\Factories\EmailSuppressionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An address PM One refuses to send to again. SES keeps its own account-level
 * suppression list, but this one is checked before the message ever reaches a
 * provider, so it saves a send charge and applies to every mailer, not just SES.
 */
class EmailSuppression extends Model
{
    /** @use HasFactory<EmailSuppressionFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'reason',
        'subtype',
        'source',
        'suppressed_at',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => EmailSuppressionReason::class,
            'suppressed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function isSuppressed(string $email): bool
    {
        return static::query()->where('email', static::normalize($email))->exists();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function suppress(
        string $email,
        EmailSuppressionReason $reason,
        ?string $subtype = null,
        ?Carbon $suppressedAt = null,
        ?array $payload = null,
        string $source = 'ses',
    ): self {
        return static::query()->updateOrCreate(
            ['email' => static::normalize($email)],
            [
                'reason' => $reason,
                'subtype' => $subtype,
                'source' => $source,
                'suppressed_at' => $suppressedAt ?? now(),
                'payload' => $payload,
            ],
        );
    }
}
