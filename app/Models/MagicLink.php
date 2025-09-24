<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $email
 * @property string $token
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagicLink forEmail(string $email)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagicLink newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagicLink newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagicLink query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagicLink valid()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagicLink whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagicLink whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagicLink whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagicLink whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagicLink whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagicLink whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagicLink whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagicLink whereUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagicLink whereUserAgent($value)
 * @mixin \Eloquent
 */
class MagicLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'token',
        'expires_at',
        'used_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public static function generate(string $email, int $expirationMinutes = 15): self
    {
        return self::create([
            'email' => $email,
            'token' => Str::random(64),
            'expires_at' => now()->addMinutes($expirationMinutes),
        ]);
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token', $token)->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return ! is_null($this->used_at);
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isUsed();
    }

    public function markAsUsed(?string $ipAddress = null, ?string $userAgent = null): void
    {
        $this->update([
            'used_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now())
            ->whereNull('used_at');
    }

    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', $email);
    }
}
